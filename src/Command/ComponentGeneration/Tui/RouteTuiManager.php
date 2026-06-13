<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

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
            $routeList[$name] = [
                'path' => $route->getPath(),
                'methods' => $route->getMethods() ?: ['GET'],
            ];
        }

        return $routeList;
    }

    /** @return string[] */
    public function getSecuritySchemes(): array
    {
        $config = $this->apiDocConfigHelper->loadPhpConfigDoc();
        $yamlConfig = $this->apiDocConfigHelper->loadYamlConfigDoc(
            $this->parameterBag->get('ehyiah_api_doc.source_path'),
            $this->kernel->getProjectDir(),
            $this->parameterBag->get('ehyiah_api_doc.dump_path')
        );

        $merged = LoadApiDocConfigHelper::mergeConfigs($config, $yamlConfig);

        return array_keys($merged['components']['securitySchemes'] ?? []);
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.source_path');

        return is_string($dumpLocation) ? (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/') : '/src/Swagger/';
    }

    public function getAvailableSchemas(): array
    {
        $sourcePath = $this->parameterBag->get('ehyiah_api_doc.source_path');
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

        return array_unique($schemas);
    }

    public function registerSchema(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder, string $schemaName): void
    {
        $builder->addSchema($schemaName)->setRefName($schemaName)->end();
    }

    public function loadRouteConfig(string $routeName, string $componentType): array
    {
        $route = $this->router->getRouteCollection()->get($routeName);
        if (!$route) {
            return [];
        }
        $path = $route->getPath();

        $dumpDirectory = $this->kernel->getProjectDir() . $this->getDefaultDumpLocation() . u($componentType)->ensureEnd('/');

        // 1. Check for YAML
        $yamlFile = $dumpDirectory . $routeName . '.yaml';
        if (file_exists($yamlFile)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

            return $this->extractRouteData($config, $path);
        }

        // 2. Check for PHP (omitted for now)
        return [];
    }

    private function extractRouteData(array $config, string $path): array
    {
        $data = [
            'summary' => '',
            'description' => '',
            'methods' => [],
            'security' => [],
            'requestBodySchema' => null,
            'responseSchema' => null,
        ];

        if (isset($config['paths'][$path])) {
            foreach ($config['paths'][$path] as $method => $definition) {
                if (is_array($definition)) {
                    $data['summary'] = empty($data['summary']) ? ($definition['summary'] ?? '') : $data['summary'];
                    $data['description'] = empty($data['description']) ? ($definition['description'] ?? '') : $data['description'];
                    $data['methods'][] = strtoupper($method);

                    if (isset($definition['security'])) {
                        foreach ($definition['security'] as $security) {
                            $data['security'] = array_merge($data['security'], array_keys($security));
                        }
                    }

                    if (empty($data['requestBodySchema']) && isset($definition['requestBody']['content']['application/json']['schema']['$ref'])) {
                        $data['requestBodySchema'] = $this->extractSchemaName($definition['requestBody']['content']['application/json']['schema']['$ref']);
                    }
                    $response200 = $definition['responses'][200] ?? $definition['responses']['200'] ?? null;
                    if (empty($data['responseSchema']) && isset($response200['content']['application/json']['schema']['$ref'])) {
                        $data['responseSchema'] = $this->extractSchemaName($response200['content']['application/json']['schema']['$ref']);
                    }
                }
            }
        }
        $data['methods'] = array_unique($data['methods']);
        $data['security'] = array_unique($data['security']);

        return $data;
    }

    private function extractSchemaName(?string $ref): ?string
    {
        if (!$ref) {
            return null;
        }

        return str_replace('#/components/schemas/', '', $ref);
    }
}
