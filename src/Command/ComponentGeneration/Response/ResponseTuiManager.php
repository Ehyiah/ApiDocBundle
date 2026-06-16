<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class ResponseTuiManager
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getExistingComponents(): array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        $names = [];
        if (is_dir($directory)) {
            $finder = new Finder();
            $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
            foreach ($finder as $file) {
                $config = Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['components']['responses'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['responses'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addResponse\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
                if (!empty($phpMatches[1])) {
                    $names = array_merge($names, $phpMatches[1]);
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableSchemas(): array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $dumpDirectory = $this->kernel->getProjectDir() . $sourcePath;

        $schemas = [];
        if (is_dir($dumpDirectory)) {
            $finder = new Finder();
            $finder->files()->in($dumpDirectory)->name(['*.yaml', '*.php']);
            foreach ($finder as $file) {
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR)) {
                    $schemas[] = $file->getBasename('.' . $file->getExtension());
                }
            }
        }

        return array_values(array_unique($schemas));
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');

        return (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/');
    }

    /**
     * Load the configuration of an existing response from YAML files.
     *
     * @return array{name: string, description: string, schemaRef: string|null, contentType: string, headers: string, links: string}|null
     */
    public function loadComponentConfig(string $name): ?array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
        foreach ($finder as $file) {
            $config = Yaml::parseFile($file->getRealPath());
            if (isset($config['documentation']['components']['responses'][$name])) {
                $response = $config['documentation']['components']['responses'][$name];

                $schemaRef = null;
                $contentType = 'application/json';
                if (isset($response['content'])) {
                    $contentType = (string)(array_key_first($response['content']) ?? 'application/json');
                    if (isset($response['content'][$contentType]['schema']['$ref'])) {
                        $ref = (string)$response['content'][$contentType]['schema']['$ref'];
                        $schemaRef = str_replace('#/components/schemas/', '', $ref);
                    }
                }

                $headersJson = '';
                if (isset($response['headers']) && is_array($response['headers'])) {
                    $headersJson = (string)json_encode($response['headers'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }

                $linksJson = '';
                if (isset($response['links']) && is_array($response['links'])) {
                    $linksJson = (string)json_encode($response['links'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }

                return [
                    'name' => $name,
                    'description' => (string)($response['description'] ?? ''),
                    'schemaRef' => $schemaRef,
                    'contentType' => $contentType,
                    'headers' => $headersJson,
                    'links' => $linksJson,
                ];
            }
        }

        $finderPhp = new Finder();
        $finderPhp->files()->in($directory)->name(['*.php']);
        foreach ($finderPhp as $file) {
            $content = file_get_contents($file->getRealPath());
            if (false === $content) {
                continue;
            }
            if (!str_contains($content, "'{$name}'") && !str_contains($content, "\"{$name}\"")) {
                continue;
            }

            $description = '';
            if (preg_match('/->description\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $descMatch)) {
                $description = $descMatch[1];
            }

            $schemaRef = null;
            if (preg_match('/->ref\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $refMatch)) {
                $schemaRef = str_replace('#/components/schemas/', '', $refMatch[1]);
            }

            return [
                'name' => $name,
                'description' => $description,
                'schemaRef' => $schemaRef,
                'contentType' => 'application/json',
                'headers' => '',
                'links' => '',
            ];
        }

        return null;
    }

    /**
     * Find the YAML or PHP file path for a given component name.
     */
    public function findComponentFile(string $name): ?string
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml', '*.php']);
        foreach ($finder as $file) {
            if ('php' === $file->getExtension()) {
                $content = file_get_contents($file->getRealPath());
                if (false !== $content && (str_contains($content, "'{$name}'") || str_contains($content, "\"{$name}\""))) {
                    return $file->getRealPath();
                }
            } else {
                $config = Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['components']['responses'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
