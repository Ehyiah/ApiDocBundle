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
