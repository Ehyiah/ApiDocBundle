<?php

namespace Ehyiah\ApiDocBundle\Command\Traits;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

/**
 * Trait providing common file generation functionality for API documentation commands.
 */
trait GenerateFileTrait
{
    abstract protected function getKernel(): KernelInterface;

    abstract protected function getParameterBag(): ParameterBagInterface;

    abstract protected function getApiDocConfigHelper(): LoadApiDocConfigHelper;

    protected function addFormatOption(): void
    {
        $this->addOption(
            name: 'format',
            shortcut: 'f',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output format: yaml, php, or both',
            default: 'php',
        );
    }

    protected function getSourcePath(): string
    {
        $sourcePath = $this->getParameterBag()->get('ehyiah_api_doc.source_path');
        if (!is_string($sourcePath)) {
            throw new LogicException('source_path must be a string');
        }

        return $sourcePath;
    }

    protected function getDumpPath(): string
    {
        $dumpPath = $this->getParameterBag()->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('dump_path must be a string');
        }

        return $dumpPath;
    }

    /**
     * Check if a YAML file with the same name exists and ask for confirmation.
     *
     * @param array<mixed> $newContentArray The new content to be written
     *
     * @return bool True if should continue, false if aborted
     */
    protected function checkExistingYamlFile(
        string $filePath,
        InputInterface $input,
        OutputInterface $output,
        ?array $newContentArray = null,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $output->writeln('<info>File already exists: ' . $filePath . '</info>');

            if (null !== $newContentArray && class_exists(Differ::class)) {
                $existingContent = file_get_contents($filePath);
                $newContent = Yaml::dump($newContentArray, 12, 4, 1024);

                if ($output->isVerbose()) {
                    $output->writeln('<info>Comparing generated YAML with existing file...</info>');
                }

                if (false !== $existingContent && $existingContent !== $newContent) {
                    $output->writeln('<comment>Differences found:</comment>');
                    $this->showDiff($existingContent, $newContent, $output);
                } else {
                    $output->writeln('<comment>No differences found.</comment>');
                }
            }

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Do you want to overwrite this file? (yes or no, default is YES)</question>', true);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    /**
     * Check if a PHP file with the same name exists and ask for confirmation.
     *
     * @param string|null $newContent The new content to be written
     *
     * @return bool True if should continue, false if aborted
     */
    protected function checkExistingPhpFile(
        string $filePath,
        InputInterface $input,
        OutputInterface $output,
        ?string $newContent = null,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $output->writeln('<info>File already exists: ' . $filePath . '</info>');

            if (null !== $newContent && class_exists(Differ::class)) {
                $existingContent = file_get_contents($filePath);

                if ($output->isVerbose()) {
                    $output->writeln('<info>Comparing generated PHP with existing file...</info>');
                }

                if (false !== $existingContent && $existingContent !== $newContent) {
                    $output->writeln('<comment>Differences found:</comment>');
                    $this->showDiff($existingContent, $newContent, $output);
                } else {
                    $output->writeln('<comment>No differences found.</comment>');
                }
            }

            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Do you want to overwrite this file? (yes or no, default is YES)</question>', true);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    private function showDiff(string $oldContent, string $newContent, OutputInterface $output): void
    {
        $builder = new UnifiedDiffOutputBuilder("--- Original\n+++ New\n", false);
        $differ = new Differ($builder);
        $diff = $differ->diff($oldContent, $newContent);

        $lines = explode("\n", $diff);
        foreach ($lines as $line) {
            if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
                $output->writeln('<fg=green>' . $line . '</>');
            } elseif (str_starts_with($line, '-') && !str_starts_with($line, '---')) {
                $output->writeln('<fg=red>' . $line . '</>');
            } elseif (str_starts_with($line, '@')) {
                $output->writeln('<comment>' . $line . '</comment>');
            } else {
                $output->writeln($line);
            }
        }
    }

    /**
     * Warn user about existing file in another format.
     *
     * @return bool True if should continue, false if aborted
     */
    protected function warnAboutOtherFormat(
        string $filePath,
        string $currentFormat,
        InputInterface $input,
        OutputInterface $output,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $otherFormat = 'yaml' === $currentFormat ? 'PHP' : 'YAML';
            $output->writeln('<warning>A ' . $otherFormat . ' file also exists: ' . $filePath . '</warning>');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>Do you want to continue? This may cause duplicate definitions. (yes or no, default is YES)</question>',
                true
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    /**
     * Write content to a YAML file.
     *
     * @param array<mixed> $array
     */
    protected function writeYamlFile(array $array, string $filePath, OutputInterface $output): void
    {
        $fileSystem = new Filesystem();
        $directory = dirname($filePath);

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $yaml = Yaml::dump($array, 12, 4, 1024);
        $fileSystem->dumpFile($filePath, $yaml);

        $output->writeln('<comment>YAML file generated at</comment> <info>' . $filePath . '</info>');
    }

    /**
     * Write content to a PHP file.
     */
    protected function writePhpFile(string $phpCode, string $filePath, OutputInterface $output): void
    {
        $fileSystem = new Filesystem();
        $directory = dirname($filePath);

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $fileSystem->dumpFile($filePath, $phpCode);

        $output->writeln('<comment>PHP file generated at</comment> <info>' . $filePath . '</info>');
    }

    /**
     * @param array<mixed> $array
     */
    public function generatePhpBuilderCode(array $array, string $componentName, string $componentType): string
    {
        $code = "<?php\n\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Builder\\ApiDocBuilder;\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Interfaces\\ApiDocConfigInterface;\n\n";
        $code .= "return new class implements ApiDocConfigInterface {\n";
        $code .= "    public function configure(ApiDocBuilder \$builder): void\n";
        $code .= "    {\n";

        if ('schemas' === $componentType) {
            $schema = $array['documentation']['components']['schemas'][$componentName] ?? [];
            $code .= $this->buildSchemaCode($componentName, $schema, 2);
        } elseif ('requestBodies' === $componentType) {
            $requestBody = $array['documentation']['components']['requestBodies'][$componentName] ?? [];
            $code .= $this->buildRequestBodyCode($componentName, $requestBody, 2);
        } elseif ('parameters' === $componentType) {
            $parameter = $array['documentation']['components']['parameters'][$componentName] ?? [];
            $code .= $this->buildParameterCode($componentName, $parameter, 2);
        } elseif ('headers' === $componentType) {
            $header = $array['documentation']['components']['headers'][$componentName] ?? [];
            $code .= $this->buildHeaderCode($componentName, $header, 2);
        } elseif ('responses' === $componentType) {
            $response = $array['documentation']['components']['responses'][$componentName] ?? [];
            $code .= $this->buildResponseCode($componentName, $response, 2);
        } elseif ('securitySchemes' === $componentType) {
            $securityScheme = $array['documentation']['components']['securitySchemes'][$componentName] ?? [];
            $code .= $this->buildSecuritySchemeCode($componentName, $securityScheme, 2);
        } elseif ('examples' === $componentType) {
            $example = $array['documentation']['components']['examples'][$componentName] ?? [];
            $code .= $this->buildExampleCode($componentName, $example, 2);
        }

        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }

    /**
     * @param array<mixed> $schema
     */
    protected function buildSchemaCode(string $name, array $schema, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addSchema('{$name}')\n";

        if (isset($schema['type'])) {
            $code .= "{$pad}    ->type('{$schema['type']}')\n";
        }

        if (isset($schema['description'])) {
            $description = addslashes($schema['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propDef) {
                $code .= $this->buildPropertyCode($propName, $propDef, $schema['required'] ?? [], $indent + 1);
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $propDef
     * @param array<string> $requiredFields
     */
    protected function buildPropertyCode(string $name, array $propDef, array $requiredFields, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}->addProperty('{$name}')\n";

        if (isset($propDef['$ref'])) {
            $ref = $propDef['$ref'];
            $code .= "{$pad}    ->ref('{$ref}')\n";
        } else {
            if (isset($propDef['type'])) {
                $code .= "{$pad}    ->type('{$propDef['type']}')\n";
            }

            if (isset($propDef['format'])) {
                $code .= "{$pad}    ->format('{$propDef['format']}')\n";
            }

            if (isset($propDef['description']) && '' !== $propDef['description']) {
                $description = addslashes($propDef['description']);
                $code .= "{$pad}    ->description('{$description}')\n";
            }

            if (isset($propDef['enum'])) {
                $enumValues = array_map(static function ($v) {
                    return is_string($v) ? "'" . addslashes($v) . "'" : $v;
                }, $propDef['enum']);
                $code .= "{$pad}    ->enum([" . implode(', ', $enumValues) . "])\n";
            }

            if (isset($propDef['items'])) {
                if (isset($propDef['items']['$ref'])) {
                    $code .= "{$pad}    ->items(['\$ref' => '{$propDef['items']['$ref']}'])\n";
                } elseif (isset($propDef['items']['type'])) {
                    $code .= "{$pad}    ->items(['type' => '{$propDef['items']['type']}'])\n";
                }
            }

            if (isset($propDef['nullable']) && $propDef['nullable']) {
                $code .= "{$pad}    ->nullable()\n";
            }
        }

        if (in_array($name, $requiredFields, true)) {
            $code .= "{$pad}    ->required()\n";
        }

        $code .= "{$pad}->end()\n";

        return $code;
    }

    /**
     * @param array<mixed> $requestBody
     */
    protected function buildRequestBodyCode(string $name, array $requestBody, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addRequestBody('{$name}')\n";

        if (isset($requestBody['description'])) {
            $description = addslashes($requestBody['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($requestBody['required']) && $requestBody['required']) {
            $code .= "{$pad}    ->required()\n";
        }

        if (isset($requestBody['content'])) {
            foreach ($requestBody['content'] as $mediaType => $content) {
                if ('application/json' === $mediaType) {
                    $code .= "{$pad}    ->jsonContent()\n";
                } else {
                    $code .= "{$pad}    ->content('{$mediaType}')\n";
                }

                if (isset($content['schema'])) {
                    if (isset($content['schema']['$ref'])) {
                        $code .= "{$pad}        ->ref('{$content['schema']['$ref']}')\n";
                    } elseif (isset($content['schema']['properties'])) {
                        $code .= "{$pad}        ->schema()\n";
                        $code .= "{$pad}            ->type('object')\n";
                        foreach ($content['schema']['properties'] as $propName => $propDef) {
                            $code .= $this->buildPropertyCode($propName, $propDef, $content['schema']['required'] ?? [], $indent + 3);
                        }
                        $code .= "{$pad}        ->end()\n";
                    }
                }

                $code .= "{$pad}    ->end()\n";
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $parameter
     */
    protected function buildParameterCode(string $name, array $parameter, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addParameter('{$name}')\n";

        if (isset($parameter['in'])) {
            $code .= "{$pad}    ->in('{$parameter['in']}')\n";
        }

        if (isset($parameter['description'])) {
            $description = addslashes($parameter['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($parameter['required']) && $parameter['required']) {
            $code .= "{$pad}    ->required()\n";
        }

        if (isset($parameter['schema'])) {
            $code .= "{$pad}    ->schema(['type' => '{$parameter['schema']['type']}'])\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $header
     */
    protected function buildHeaderCode(string $name, array $header, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addHeader('{$name}')\n";

        if (isset($header['description'])) {
            $description = addslashes($header['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($header['schema'])) {
            $code .= "{$pad}    ->schema(['type' => '{$header['schema']['type']}'])\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $response
     */
    protected function buildResponseCode(string $name, array $response, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addResponse('{$name}')\n";

        if (isset($response['description'])) {
            $description = addslashes($response['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($response['statusCode'])) {
            $code .= "{$pad}    ->statusCode({$response['statusCode']})\n";
        }

        if (isset($response['content']['application/json']['schema']['$ref'])) {
            $ref = $response['content']['application/json']['schema']['$ref'];
            $code .= "{$pad}    ->jsonContent()\n";
            $code .= "{$pad}        ->ref('{$ref}')\n";
            $code .= "{$pad}    ->end()\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $securityScheme
     */
    protected function buildSecuritySchemeCode(string $name, array $securityScheme, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addSecurityScheme('{$name}')\n";

        if (isset($securityScheme['type'])) {
            $code .= "{$pad}    ->type('{$securityScheme['type']}')\n";
        }

        if (isset($securityScheme['in'])) {
            $code .= "{$pad}    ->in('{$securityScheme['in']}')\n";
        }

        if (isset($securityScheme['name'])) {
            $code .= "{$pad}    ->nameInHeader('{$securityScheme['name']}')\n";
        }

        if (isset($securityScheme['scheme'])) {
            $code .= "{$pad}    ->scheme('{$securityScheme['scheme']}')\n";
        }

        if (isset($securityScheme['bearerFormat'])) {
            $code .= "{$pad}    ->bearerFormat('{$securityScheme['bearerFormat']}')\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $example
     */
    protected function buildExampleCode(string $name, array $example, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addExample('{$name}')\n";

        if (isset($example['summary'])) {
            $description = addslashes($example['summary']);
            $code .= "{$pad}    ->summary('{$description}')\n";
        }

        if (isset($example['value'])) {
            $value = var_export($example['value'], true);
            $code .= "{$pad}    ->value({$value})\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * Build the full output path for a file.
     */
    protected function buildOutputPath(string $outputDir, string $filename, string $extension, ?string $subdirectory = null): string
    {
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');

        $path = $this->getKernel()->getProjectDir() . $outputDir;

        if (null !== $subdirectory) {
            $path .= u($subdirectory)->ensureEnd('/');
        }

        return $path . $filename . '.' . $extension;
    }
}
