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
        bool $interactive = true,
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

            if (!$interactive) {
                $output->writeln('<info>Overwriting file (non-interactive mode).</info>');

                return true;
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
        bool $interactive = true,
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

            if (!$interactive) {
                $output->writeln('<info>Overwriting file (non-interactive mode).</info>');

                return true;
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
        bool $interactive = true,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $otherFormat = 'yaml' === $currentFormat ? 'PHP' : 'YAML';
            $output->writeln('<warning>A ' . $otherFormat . ' file also exists: ' . $filePath . '</warning>');

            if (!$interactive) {
                $output->writeln('<info>Continuing anyway (non-interactive mode).</info>');

                return true;
            }

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
        } elseif ('links' === $componentType) {
            $link = $array['documentation']['components']['links'][$componentName] ?? [];
            $code .= $this->buildLinkCode($componentName, $link, 2);
        } elseif ('callbacks' === $componentType) {
            $callback = $array['documentation']['components']['callbacks'][$componentName] ?? [];
            $code .= $this->buildCallbackCode($componentName, $callback, 2);
        } elseif ('pathItems' === $componentType) {
            $pathItem = $array['documentation']['components']['pathItems'][$componentName] ?? [];
            $code .= $this->buildPathItemCode($componentName, $pathItem, 2);
        } elseif ('tags' === $componentType) {
            $tag = $this->findTagByName($array, $componentName);
            $code .= $this->buildTagCode($componentName, $tag, 2);
        } elseif ('routes' === $componentType) {
            $code .= $this->buildRouteCode($array, $componentName, 2);
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

        if (isset($parameter['deprecated']) && $parameter['deprecated']) {
            $code .= "{$pad}    ->deprecated()\n";
        }

        if (isset($parameter['allowEmptyValue']) && $parameter['allowEmptyValue']) {
            $code .= "{$pad}    ->allowEmptyValue()\n";
        }

        if (isset($parameter['style'])) {
            $code .= "{$pad}    ->style('{$parameter['style']}')\n";
        }

        if (isset($parameter['explode'])) {
            $code .= "{$pad}    ->explode(" . ($parameter['explode'] ? 'true' : 'false') . ")\n";
        }

        if (isset($parameter['allowReserved']) && $parameter['allowReserved']) {
            $code .= "{$pad}    ->allowReserved()\n";
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

        if (isset($header['required']) && $header['required']) {
            $code .= "{$pad}    ->required()\n";
        }

        if (isset($header['deprecated']) && $header['deprecated']) {
            $code .= "{$pad}    ->deprecated()\n";
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

        if (isset($response['links']) && is_array($response['links'])) {
            foreach ($response['links'] as $linkName => $linkDef) {
                $linkJson = addslashes(json_encode($linkDef, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $code .= "{$pad}    ->link('{$linkName}', json_decode('{$linkJson}', true))\n";
            }
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

        if (isset($example['externalValue'])) {
            $code .= "{$pad}    ->externalValue('{$example['externalValue']}')\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $link
     */
    protected function buildLinkCode(string $name, array $link, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addLink('{$name}')\n";

        if (isset($link['operationRef'])) {
            $code .= "{$pad}    ->operationRef('{$link['operationRef']}')\n";
        }

        if (isset($link['operationId'])) {
            $code .= "{$pad}    ->operationId('{$link['operationId']}')\n";
        }

        if (isset($link['parameters']) && is_array($link['parameters'])) {
            foreach ($link['parameters'] as $paramName => $paramValue) {
                $code .= "{$pad}    ->parameter('{$paramName}', '{$paramValue}')\n";
            }
        }

        if (isset($link['requestBody']) && is_string($link['requestBody'])) {
            $code .= "{$pad}    ->requestBody('{$link['requestBody']}')\n";
        }

        if (isset($link['description'])) {
            $description = addslashes($link['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($link['server']['url'])) {
            $serverDescription = $link['server']['description'] ?? null;
            if (null !== $serverDescription) {
                $serverDescription = addslashes($serverDescription);
                $code .= "{$pad}    ->server('{$link['server']['url']}', '{$serverDescription}')\n";
            } else {
                $code .= "{$pad}    ->server('{$link['server']['url']}')\n";
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $callback
     */
    protected function buildCallbackCode(string $name, array $callback, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addCallback('{$name}')\n";

        foreach ($callback as $expression => $pathItem) {
            if (is_array($pathItem)) {
                $pathItemJson = addslashes(json_encode($pathItem, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $code .= "{$pad}    ->pathItem('{$expression}', json_decode('{$pathItemJson}', true))\n";
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $pathItem
     */
    protected function buildPathItemCode(string $name, array $pathItem, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addPathItem('{$name}')\n";

        if (isset($pathItem['$ref'])) {
            $code .= "{$pad}    ->ref('{$pathItem['$ref']}')\n";
        }

        if (isset($pathItem['summary'])) {
            $summary = addslashes($pathItem['summary']);
            $code .= "{$pad}    ->summary('{$summary}')\n";
        }

        if (isset($pathItem['description'])) {
            $description = addslashes($pathItem['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * Find a tag by name in the documentation.tags array.
     *
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private function findTagByName(array $array, string $name): array
    {
        $tags = $array['documentation']['tags'] ?? [];
        foreach ($tags as $tag) {
            if (isset($tag['name']) && $tag['name'] === $name) {
                return $tag;
            }
        }

        return [];
    }

    /**
     * @param array<mixed> $tag
     */
    protected function buildTagCode(string $name, array $tag, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addTag('{$name}')\n";

        if (isset($tag['description'])) {
            $description = addslashes($tag['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($tag['externalDocs']) && is_array($tag['externalDocs'])) {
            $url = addslashes((string)($tag['externalDocs']['url'] ?? ''));
            $desc = isset($tag['externalDocs']['description']) ? addslashes((string)$tag['externalDocs']['description']) : null;
            if (null !== $desc) {
                $code .= "{$pad}    ->externalDocs('{$url}', '{$desc}')\n";
            } else {
                $code .= "{$pad}    ->externalDocs('{$url}')\n";
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $array
     */
    protected function buildRouteCode(array $array, string $routeName, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = '';

        $paths = $array['documentation']['paths'] ?? $array['paths'] ?? [];
        foreach ($paths as $path => $methods) {
            if (!is_array($methods)) {
                continue;
            }
            foreach ($methods as $method => $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $upperMethod = strtoupper($method);
                $code .= "{$pad}\$builder->addRoute()\n";
                $code .= "{$pad}    ->path('{$path}')\n";
                $code .= "{$pad}    ->method('{$upperMethod}')\n";

                if (isset($definition['operationId'])) {
                    $code .= "{$pad}    ->operationId('{$definition['operationId']}')\n";
                }
                if (isset($definition['summary'])) {
                    $code .= "{$pad}    ->summary('" . addslashes($definition['summary']) . "')\n";
                }
                if (isset($definition['description'])) {
                    $code .= "{$pad}    ->description('" . addslashes($definition['description']) . "')\n";
                }
                if (isset($definition['tags']) && is_array($definition['tags'])) {
                    foreach ($definition['tags'] as $tag) {
                        $code .= "{$pad}    ->tag('{$tag}')\n";
                    }
                }
                if (isset($definition['security']) && is_array($definition['security'])) {
                    foreach ($definition['security'] as $securityEntry) {
                        if (is_array($securityEntry)) {
                            foreach (array_keys($securityEntry) as $schemeName) {
                                $code .= "{$pad}    ->security('{$schemeName}')\n";
                            }
                        }
                    }
                }

                if (isset($definition['requestBody']['content']['application/json']['schema']['$ref'])) {
                    $schemaName = str_replace('#/components/schemas/', '', (string)$definition['requestBody']['content']['application/json']['schema']['$ref']);
                    $code .= "{$pad}    ->requestBody()\n";
                    $code .= "{$pad}        ->content('application/json')\n";
                    $code .= "{$pad}        ->refByName('{$schemaName}')\n";
                    $code .= "{$pad}    ->end()\n";
                }

                if (isset($definition['responses']) && is_array($definition['responses'])) {
                    foreach ($definition['responses'] as $statusCode => $responseDef) {
                        if (!is_array($responseDef)) {
                            continue;
                        }
                        $code .= "{$pad}    ->response({$statusCode})\n";
                        if (isset($responseDef['description'])) {
                            $code .= "{$pad}        ->description('" . addslashes($responseDef['description']) . "')\n";
                        }
                        if (isset($responseDef['content']['application/json']['schema']['$ref'])) {
                            $schemaName = str_replace('#/components/schemas/', '', (string)$responseDef['content']['application/json']['schema']['$ref']);
                            $code .= "{$pad}        ->content('application/json')\n";
                            $code .= "{$pad}            ->refByName('{$schemaName}')\n";
                            $code .= "{$pad}        ->end()\n";
                        }
                        $code .= "{$pad}        ->end()\n";
                    }
                }

                $code .= "{$pad}    ->end();\n";
            }
        }

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
