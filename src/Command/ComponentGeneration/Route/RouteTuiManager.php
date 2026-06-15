<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Route;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\String\u;

class RouteTuiManager
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly ParameterBagInterface $parameterBag,
        private readonly KernelInterface $kernel,
        private readonly LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllRoutes(): array
    {
        $routes = $this->router->getRouteCollection();
        $routeList = [];
        foreach ($routes as $name => $route) {
            $methods = $route->getMethods() ?: ['GET'];
            $routeList[$name] = [
                'path' => $route->getPath(),
                'methods' => $methods,
                'operationId' => self::deriveOperationId($name, $methods, $route->getDefault('_controller')),
            ];
        }

        return $routeList;
    }

    /** @param string[] $methods */
    private static function deriveOperationId(string $routeName, array $methods, mixed $controller): string
    {
        $suffix = strtolower($methods[0]);

        if (is_string($controller) && str_contains($controller, '::')) {
            $parts = explode('::', $controller);
            $methodName = $parts[1];

            return $routeName . '_' . $methodName . '_' . $suffix;
        }

        return $routeName . '_' . $suffix;
    }

    /** @return array<int, string> */
    public function getSecuritySchemes(): array
    {
        $config = $this->apiDocConfigHelper->loadPhpConfigDoc();
        $yamlConfig = $this->apiDocConfigHelper->loadYamlConfigDoc(
            (string)$this->parameterBag->get('ehyiah_api_doc.source_path'),
            $this->kernel->getProjectDir(),
            (string)$this->parameterBag->get('ehyiah_api_doc.dump_path')
        );

        $merged = LoadApiDocConfigHelper::mergeConfigs($config, $yamlConfig);

        return array_values(array_map('strval', array_keys($merged['components']['securitySchemes'] ?? [])));
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');

        return (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/');
    }

    /** @return string[] */
    public function getAvailableSchemas(): array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $dumpDirectory = $this->kernel->getProjectDir() . $sourcePath;

        $schemas = [];
        if (is_dir($dumpDirectory)) {
            $finder = new \Symfony\Component\Finder\Finder();
            $finder->files()->in($dumpDirectory)->name(['*.yaml', '*.php']);
            foreach ($finder as $file) {
                // If it's a file in a 'schemas' subdirectory, use filename as schema name
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR)) {
                    $schemas[] = $file->getBasename('.' . $file->getExtension());
                }
            }
        }

        return array_values(array_unique($schemas));
    }

    /** @return array<int, string> */
    public function getAvailableExamples(): array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        $names = [];
        if (is_dir($directory)) {
            $finder = new \Symfony\Component\Finder\Finder();
            $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
            foreach ($finder as $file) {
                $config = \Symfony\Component\Yaml\Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['components']['examples'])) {
                    $names = array_merge($names, array_map('strval', array_keys($config['documentation']['components']['examples'])));
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function registerSchema(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder, string $schemaName): void
    {
        $builder->addSchema($schemaName)->setRefName($schemaName)->end();
    }

    /**
     * Find the YAML or PHP file path for a given route name.
     */
    public function findRouteFile(string $routeName): ?string
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in($directory)->name([$routeName . '.yaml', $routeName . '.php']);
        foreach ($finder as $file) {
            if ('php' === $file->getExtension()) {
                $content = file_get_contents($file->getRealPath());
                if (false !== $content && str_contains($content, "'{$routeName}'")) {
                    return $file->getRealPath();
                }
            } else {
                $config = \Symfony\Component\Yaml\Yaml::parseFile($file->getRealPath());
                if (isset($config['paths'])) {
                    return $file->getRealPath();
                }
            }
        }

        return null;
    }

    /**
     * Load existing route configuration from YAML file, returning per-method config.
     *
     * @return array{methodsConfig: array<string, array{summary: string, description: string, security: string[], requestBodySchema: ?string, requestBodyExample: ?string, responses: array<int, array{schema: ?string, description: string, example: ?string}>, operationId: string}>}
     */
    public function loadRouteConfig(string $routeName, string $componentType): array
    {
        $route = $this->router->getRouteCollection()->get($routeName);
        if (!$route) {
            return ['methodsConfig' => []];
        }
        $path = $route->getPath();

        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return ['methodsConfig' => []];
        }

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in($directory)->name($routeName . '.yaml');
        foreach ($finder as $file) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($file->getRealPath());
            if (isset($config['paths'])) {
                return ['methodsConfig' => $this->extractMethodsConfig($config, $path)];
            }
        }

        return ['methodsConfig' => []];
    }

    /**
     * Extract per-method configuration from an OpenAPI paths config.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, array{summary: string, description: string, security: string[], requestBodySchema: ?string, requestBodyExample: ?string, responses: array<int, array{schema: ?string, description: string, example: ?string}>, operationId: string}>
     */
    private function extractMethodsConfig(array $config, string $path): array
    {
        $methodsConfig = [];

        if (!isset($config['paths'][$path])) {
            return $methodsConfig;
        }

        foreach ($config['paths'][$path] as $method => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $security = [];
            if (isset($definition['security'])) {
                foreach ($definition['security'] as $securityEntry) {
                    $security = array_merge($security, array_map('strval', array_keys($securityEntry)));
                }
            }

            $requestBodySchema = null;
            if (isset($definition['requestBody']['content']['application/json']['schema']['$ref'])) {
                $requestBodySchema = $this->extractSchemaName($definition['requestBody']['content']['application/json']['schema']['$ref']);
            }

            $requestBodyExample = null;
            if (isset($definition['requestBody']['content']['application/json']['examples'])) {
                $examples = $definition['requestBody']['content']['application/json']['examples'];
                $firstExampleKey = array_key_first($examples);
                if (is_string($firstExampleKey)) {
                    $exampleDef = $examples[$firstExampleKey];
                    if (is_array($exampleDef) && isset($exampleDef['$ref'])) {
                        $requestBodyExample = str_replace('#/components/examples/', '', (string)$exampleDef['$ref']);
                    }
                }
            }

            $responses = [];
            if (isset($definition['responses']) && is_array($definition['responses'])) {
                foreach ($definition['responses'] as $statusCode => $responseDef) {
                    if (!is_array($responseDef)) {
                        continue;
                    }
                    $statusCodeInt = (int)$statusCode;
                    $schema = null;
                    if (isset($responseDef['content']['application/json']['schema']['$ref'])) {
                        $schema = $this->extractSchemaName($responseDef['content']['application/json']['schema']['$ref']);
                    }
                    $example = null;
                    if (isset($responseDef['content']['application/json']['examples'])) {
                        $examples = $responseDef['content']['application/json']['examples'];
                        $firstExampleKey = array_key_first($examples);
                        if (is_string($firstExampleKey)) {
                            $exampleDef = $examples[$firstExampleKey];
                            if (is_array($exampleDef) && isset($exampleDef['$ref'])) {
                                $example = str_replace('#/components/examples/', '', (string)$exampleDef['$ref']);
                            }
                        }
                    }
                    $responses[$statusCodeInt] = [
                        'schema' => $schema,
                        'description' => (string)($responseDef['description'] ?? ''),
                        'example' => $example,
                    ];
                }
            }

            $methodsConfig[strtoupper($method)] = [
                'summary' => (string)($definition['summary'] ?? ''),
                'description' => (string)($definition['description'] ?? ''),
                'security' => array_values(array_unique($security)),
                'requestBodySchema' => $requestBodySchema,
                'requestBodyExample' => $requestBodyExample,
                'responses' => $responses,
                'operationId' => (string)($definition['operationId'] ?? ''),
            ];
        }

        return $methodsConfig;
    }

    private function extractSchemaName(?string $ref): ?string
    {
        if (!$ref) {
            return null;
        }

        return str_replace('#/components/schemas/', '', $ref);
    }
}
