<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security;

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
        $directory = $this->kernel->getProjectDir() . $sourcePath;

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

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addSecurityScheme\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
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
     * Load the configuration of an existing security scheme from YAML files.
     *
     * @return array{type: string, scheme: string, bearerFormat: string, name: string, in: string, openIdConnectUrl: string, description: string}|null
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

            $type = 'http';
            if (preg_match('/->type\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $type = $match[1];
            }

            $scheme = 'bearer';
            if (preg_match('/->scheme\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $scheme = $match[1];
            }

            $bearerFormat = 'JWT';
            if (preg_match('/->bearerFormat\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $bearerFormat = $match[1];
            }

            $nameInHeader = '';
            if (preg_match('/->nameInHeader\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $nameInHeader = $match[1];
            }

            $in = 'header';
            if (preg_match('/->in\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $in = $match[1];
            }

            $description = '';
            if (preg_match('/->description\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $description = $match[1];
            }

            if (preg_match('/->bearer\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $scheme = 'bearer';
                $bearerFormat = $match[1];
            }

            if (str_contains($content, '->basic()')) {
                $scheme = 'basic';
            }

            return [
                'type' => $type,
                'scheme' => $scheme,
                'bearerFormat' => $bearerFormat,
                'name' => $nameInHeader,
                'in' => $in,
                'openIdConnectUrl' => '',
                'description' => $description,
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
                if (isset($config['documentation']['components']['securitySchemes'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
