<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class CallbackTuiManager
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
                if (isset($config['documentation']['components']['callbacks'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['callbacks'])));
                }
            }

            $finderPhp = new Finder();
            $finderPhp->files()->in($directory)->name(['*.php']);
            foreach ($finderPhp as $file) {
                $content = file_get_contents($file->getRealPath());
                if (false === $content) {
                    continue;
                }
                preg_match_all('/\$builder->addCallback\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $phpMatches);
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
     * Load the configuration of an existing callback from YAML files.
     *
     * @return array{expression: string, path: string, method: string, description: string, operationId: string, requestBodyRef: string, responseDescription: string}|null
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
            if (isset($config['documentation']['components']['callbacks'][$name])) {
                $callback = $config['documentation']['components']['callbacks'][$name];

                // Find the first expression key (it's the expression string)
                $expression = array_key_first($callback) ?? '';
                $pathItem = $callback[$expression] ?? [];

                $path = '';
                $method = 'post';
                $description = '';
                $operationId = '';
                $requestBodyRef = '';
                $responseDescription = '';

                // Find the first path entry
                foreach ($pathItem as $key => $value) {
                    if (in_array($key, ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'], true)) {
                        $path = $key;
                        $method = $key;
                        $operation = is_array($value) ? $value : [];
                        $description = $operation['description'] ?? '';
                        $operationId = $operation['operationId'] ?? '';
                        if (isset($operation['requestBody']['content']['application/json']['schema']['$ref'])) {
                            $requestBodyRef = $operation['requestBody']['content']['application/json']['schema']['$ref'];
                        }
                        if (isset($operation['responses']['200']['description'])) {
                            $responseDescription = $operation['responses']['200']['description'];
                        }
                        break;
                    }
                }

                return [
                    'expression' => $expression,
                    'path' => $path,
                    'method' => $method,
                    'description' => $description,
                    'operationId' => $operationId,
                    'requestBodyRef' => $requestBodyRef,
                    'responseDescription' => $responseDescription,
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

            $expression = '';
            if (preg_match('/->pathItem\(\s*[\'"]([^\'"]*)[\'"]\s*,/', $content, $match)) {
                $expression = $match[1];
            }

            return [
                'expression' => $expression,
                'path' => '',
                'method' => 'post',
                'description' => '',
                'operationId' => '',
                'requestBodyRef' => '',
                'responseDescription' => '',
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
                if (isset($config['documentation']['components']['callbacks'][$name])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }
}
