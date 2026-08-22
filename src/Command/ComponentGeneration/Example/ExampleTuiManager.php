<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class ExampleTuiManager
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
                if (isset($config['documentation']['components']['examples'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['examples'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addExample\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
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
        $dumpPath = (string)$this->parameterBag->get('ehyiah_api_doc.dump_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        $names = [];
        if (is_dir($directory)) {
            $finder = new Finder();
            $finder->files()->in($directory)->name(['*.yaml', '*.yml', '*.php']);
            foreach ($finder as $file) {
                if (in_array($file->getExtension(), ['yaml', 'yml'], true)) {
                    $config = Yaml::parseFile($file->getRealPath());
                    if (isset($config['documentation']['components']['schemas'])) {
                        $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['schemas'])));
                    }
                } else {
                    $content = file_get_contents($file->getRealPath());
                    if (false !== $content && preg_match_all('/->addSchema\s*\(\s*[\'"]([\w]+)[\'"]\s*\)/', $content, $matches)) {
                        $names = array_merge($names, $matches[1]);
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Load a schema definition by name from YAML/PHP files.
     *
     * @return array<string, mixed>|null
     */
    public function loadSchemaConfig(string $name): ?array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $dumpPath = (string)$this->parameterBag->get('ehyiah_api_doc.dump_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
        foreach ($finder as $file) {
            $config = Yaml::parseFile($file->getRealPath());
            if (isset($config['documentation']['components']['schemas'][$name])) {
                return $config['documentation']['components']['schemas'][$name];
            }
        }

        return null;
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');

        return (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/');
    }

    /**
     * Load the configuration of an existing example from YAML files.
     *
     * @return array{name: string, summary: string, description: string, value: string, externalValue: string}|null
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
            if (isset($config['documentation']['components']['examples'][$name])) {
                $example = $config['documentation']['components']['examples'][$name];

                $valueStr = '';
                if (isset($example['value'])) {
                    $valueStr = is_array($example['value']) ? (string)json_encode($example['value'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : (string)$example['value'];
                }

                return [
                    'name' => $name,
                    'summary' => (string)($example['summary'] ?? ''),
                    'description' => (string)($example['description'] ?? ''),
                    'value' => $valueStr,
                    'externalValue' => (string)($example['externalValue'] ?? ''),
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

            $summary = '';
            if (preg_match('/->summary\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $summary = $match[1];
            }

            $valueStr = '';
            if (preg_match('/->value\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $valueStr = $match[1];
            } elseif (preg_match('/->value\(([^)]+)\)/', $content, $match)) {
                $valueStr = trim($match[1]);
            }

            $externalValue = '';
            if (preg_match('/->externalValue\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $match)) {
                $externalValue = $match[1];
            }

            return [
                'name' => $name,
                'summary' => $summary,
                'description' => '',
                'value' => $valueStr,
                'externalValue' => $externalValue,
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
                if (isset($config['documentation']['components']['examples'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
