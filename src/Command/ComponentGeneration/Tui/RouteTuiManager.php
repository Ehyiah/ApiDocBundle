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

    public function loadRouteConfig(string $routeName, string $componentType): array
    {
        $route = $this->router->getRouteCollection()->get($routeName);
        if (!$route) {
            return [];
        }
        $path = $route->getPath();

        $dumpDirectory = $this->kernel->getProjectDir() . $this->getDefaultDumpLocation() . \Symfony\Component\String\u($componentType)->ensureEnd('/');

        // Debugging
        // $this->apiDocConfigHelper->findYamlComponentFile($routeName, $componentType); // Log this? No.

        // 1. Check for YAML
        $yamlFile = $dumpDirectory . $routeName . '.yaml';
        if (file_exists($yamlFile)) {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);

            // $this->currentOutput->writeln("DEBUG: Path looking for: $path");
            // $this->currentOutput->writeln("DEBUG: Config paths: " . json_encode(array_keys($config['paths'] ?? [])));
            return $this->extractRouteData($config, $path);
        }

        // 2. Check for PHP (omitted for now)
        return [];
    }

    private function extractRouteData(array $config, string $path): array
    {
        // $this->currentOutput->writeln("DEBUG: Looking for path: $path");
        // $this->currentOutput->writeln("DEBUG: Available paths: " . json_encode(array_keys($config['paths'] ?? [])));

        $data = [
            'summary' => '',
            'description' => '',
            'methods' => [],
            'security' => [],
        ];

        if (isset($config['paths'][$path])) {
            foreach ($config['paths'][$path] as $method => $definition) {
                if (is_array($definition)) {
                    // ... (rest of logic)
                    // Only set summary/description if not already set by a previous method
                    if (empty($data['summary'])) {
                        $data['summary'] = $definition['summary'] ?? '';
                    }
                    if (empty($data['description'])) {
                        $data['description'] = $definition['description'] ?? '';
                    }
                    $data['methods'][] = strtoupper($method);

                    if (isset($definition['security'])) {
                        foreach ($definition['security'] as $security) {
                            $data['security'] = array_merge($data['security'], array_keys($security));
                        }
                    }
                }
            }
        }
        $data['methods'] = array_unique($data['methods']);
        $data['security'] = array_unique($data['security']);

        return $data;
    }
}
