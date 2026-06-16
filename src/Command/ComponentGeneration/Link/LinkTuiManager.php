<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class LinkTuiManager
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
                if (isset($config['documentation']['components']['links'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['links'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addLink\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
                if (!empty($phpMatches[1])) {
                    $names = array_merge($names, $phpMatches[1]);
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');

        return (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/');
    }

    /**
     * Load the configuration of an existing link from YAML files.
     *
     * @return array{operationRef: string, operationId: string, parameters: string, requestBody: string, description: string, serverUrl: string, serverDescription: string}|null
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
            if (isset($config['documentation']['components']['links'][$name])) {
                $link = $config['documentation']['components']['links'][$name];

                $parametersJson = '';
                if (isset($link['parameters']) && is_array($link['parameters'])) {
                    $parametersJson = (string)json_encode($link['parameters'], JSON_THROW_ON_ERROR);
                }

                return [
                    'operationRef' => (string)($link['operationRef'] ?? ''),
                    'operationId' => (string)($link['operationId'] ?? ''),
                    'parameters' => $parametersJson,
                    'requestBody' => is_string($link['requestBody'] ?? null) ? $link['requestBody'] : '',
                    'description' => (string)($link['description'] ?? ''),
                    'serverUrl' => (string)($link['server']['url'] ?? ''),
                    'serverDescription' => (string)($link['server']['description'] ?? ''),
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

            $operationRef = '';
            if (preg_match('/->operationRef\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $operationRef = $match[1];
            }

            $operationId = '';
            if (preg_match('/->operationId\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $operationId = $match[1];
            }

            $description = '';
            if (preg_match('/->description\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $description = $match[1];
            }

            $requestBody = '';
            if (preg_match('/->requestBody\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $requestBody = $match[1];
            }

            $serverUrl = '';
            $serverDescription = '';
            if (preg_match('/->server\(\s*[\'"]([^\'"]*)[\'"]\s*(?:,\s*[\'"]([^\'"]*)[\'"]\s*)?\)/', $content, $match)) {
                $serverUrl = $match[1];
                $serverDescription = $match[2] ?? '';
            }

            $parametersJson = '';
            if (preg_match_all('/->parameter\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $paramMatches)) {
                $params = [];
                foreach ($paramMatches[1] as $i => $paramName) {
                    $params[$paramName] = $paramMatches[2][$i];
                }
                $parametersJson = (string)json_encode($params, JSON_THROW_ON_ERROR);
            }

            return [
                'operationRef' => $operationRef,
                'operationId' => $operationId,
                'parameters' => $parametersJson,
                'requestBody' => $requestBody,
                'description' => $description,
                'serverUrl' => $serverUrl,
                'serverDescription' => $serverDescription,
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
                if (isset($config['documentation']['components']['links'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
