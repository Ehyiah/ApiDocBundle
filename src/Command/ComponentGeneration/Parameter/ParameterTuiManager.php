<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class ParameterTuiManager
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
                if (isset($config['documentation']['components']['parameters'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['parameters'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addParameter\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
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
     * Load the configuration of an existing parameter from YAML files.
     *
     * @return array{name: string, in: string, description: string, required: bool, deprecated: bool, allowEmptyValue: bool, style: string, explode: string, allowReserved: bool, schemaType: string, format: string, example: string}|null
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
            if (isset($config['documentation']['components']['parameters'][$name])) {
                $param = $config['documentation']['components']['parameters'][$name];

                return [
                    'name' => (string)($param['name'] ?? $name),
                    'in' => (string)($param['in'] ?? 'query'),
                    'description' => (string)($param['description'] ?? ''),
                    'required' => (bool)($param['required'] ?? false),
                    'deprecated' => (bool)($param['deprecated'] ?? false),
                    'allowEmptyValue' => (bool)($param['allowEmptyValue'] ?? false),
                    'style' => (string)($param['style'] ?? ''),
                    'explode' => is_bool($param['explode'] ?? null) ? ($param['explode'] ? 'true' : 'false') : '',
                    'allowReserved' => (bool)($param['allowReserved'] ?? false),
                    'schemaType' => (string)($param['schema']['type'] ?? 'string'),
                    'format' => (string)($param['schema']['format'] ?? ''),
                    'example' => isset($param['example']) ? (string)$param['example'] : '',
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

            $in = 'query';
            if (preg_match('/->in\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $inMatch)) {
                $in = $inMatch[1];
            }

            $description = '';
            if (preg_match('/->description\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $descMatch)) {
                $description = $descMatch[1];
            }

            $required = str_contains($content, '->required()');
            $deprecated = str_contains($content, '->deprecated()');
            $allowEmptyValue = str_contains($content, '->allowEmptyValue()');

            $style = '';
            if (preg_match('/->style\(\s*[\'"]([^\'"]*)[\'"]\s*\)/', $content, $styleMatch)) {
                $style = $styleMatch[1];
            }

            $explode = '';
            if (preg_match('/->explode\((true|false)\)/', $content, $explodeMatch)) {
                $explode = $explodeMatch[1];
            }

            $allowReserved = str_contains($content, '->allowReserved()');

            $schemaType = 'string';
            if (preg_match('/->schema\(\s*\[[^\]]*[\'"]type[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $content, $typeMatch)) {
                $schemaType = $typeMatch[1];
            }

            return [
                'name' => $name,
                'in' => $in,
                'description' => $description,
                'required' => $required,
                'deprecated' => $deprecated,
                'allowEmptyValue' => $allowEmptyValue,
                'style' => $style,
                'explode' => $explode,
                'allowReserved' => $allowReserved,
                'schemaType' => $schemaType,
                'format' => '',
                'example' => '',
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
                if (isset($config['documentation']['components']['parameters'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
