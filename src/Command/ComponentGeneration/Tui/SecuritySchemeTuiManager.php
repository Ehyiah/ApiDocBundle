<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class SecuritySchemeTuiManager
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
        $directory = $this->kernel->getProjectDir() . $sourcePath . '/securitySchemes/';

        $names = [];
        if (is_dir($directory)) {
            $finder = new Finder();
            $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
            foreach ($finder as $file) {
                $config = Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['components']['securitySchemes'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['securitySchemes'])));
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
     * Load the configuration of an existing security scheme from YAML files.
     *
     * @return array{type: string, scheme: string, bearerFormat: string, name: string, in: string, openIdConnectUrl: string, description: string}|null
     */
    public function loadComponentConfig(string $name): ?array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath . '/securitySchemes/';

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
        foreach ($finder as $file) {
            $config = Yaml::parseFile($file->getRealPath());
            if (isset($config['documentation']['components']['securitySchemes'][$name])) {
                $scheme = $config['documentation']['components']['securitySchemes'][$name];

                return [
                    'type' => $scheme['type'] ?? 'http',
                    'scheme' => $scheme['scheme'] ?? 'bearer',
                    'bearerFormat' => $scheme['bearerFormat'] ?? 'JWT',
                    'name' => $scheme['name'] ?? '',
                    'in' => $scheme['in'] ?? 'header',
                    'openIdConnectUrl' => $scheme['openIdConnectUrl'] ?? '',
                    'description' => $scheme['description'] ?? '',
                ];
            }
        }

        return null;
    }
}
