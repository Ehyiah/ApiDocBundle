<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class HeaderTuiManager
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
                if (isset($config['documentation']['components']['headers'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['headers'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addHeader\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
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
     * Load the configuration of an existing header from YAML files.
     *
     * @return array{name: string, description: string, required: bool, deprecated: bool, schemaType: string, format: string, example: string}|null
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
            if (isset($config['documentation']['components']['headers'][$name])) {
                $header = $config['documentation']['components']['headers'][$name];

                return [
                    'name' => $name,
                    'description' => (string)($header['description'] ?? ''),
                    'required' => (bool)($header['required'] ?? false),
                    'deprecated' => (bool)($header['deprecated'] ?? false),
                    'schemaType' => (string)($header['schema']['type'] ?? 'string'),
                    'format' => (string)($header['schema']['format'] ?? ''),
                    'example' => isset($header['example']) ? (string)$header['example'] : '',
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

            $required = str_contains($content, '->required()');
            $deprecated = str_contains($content, '->deprecated()');

            $schemaType = 'string';
            $format = '';
            if (preg_match('/->typeString\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $typeMatch)) {
                $schemaType = 'string';
                $format = $typeMatch[1];
            } elseif (preg_match('/->typeInteger\(\)/', $content)) {
                $schemaType = 'integer';
            } elseif (preg_match('/->schema\(\s*\[[^\]]*[\'"]type[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $typeMatch)) {
                $schemaType = $typeMatch[1];
            }

            $example = '';
            if (preg_match('/->addExample\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $exMatch)) {
                $example = $exMatch[1];
            }

            return [
                'name' => $name,
                'description' => $description,
                'required' => $required,
                'deprecated' => $deprecated,
                'schemaType' => $schemaType,
                'format' => $format,
                'example' => $example,
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
                if (isset($config['documentation']['components']['headers'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
