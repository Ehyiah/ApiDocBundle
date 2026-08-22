<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests simulating the full PHP config + YAML merge flow.
 *
 * @coversNothing
 */
final class ApiDocBuilderIntegrationTest extends TestCase
{
    public function testPhpRouteAppearsInFinalSpec(): void
    {
        $builder = new ApiDocBuilder();

        $config = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder
                    ->addRoute()
                        ->path('/api/products')
                        ->method('POST')
                        ->operationId('createProduct')
                        ->summary('Create a product')
                    ->end()
                ;
            }
        };

        $config->configure($builder);
        $spec = $builder->build();

        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/api/products', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/products']);
        $this->assertSame('createProduct', $spec['paths']['/api/products']['post']['operationId']);
    }

    public function testPhpRouteNotOverwrittenByEmptyYamlMerge(): void
    {
        $builder = new ApiDocBuilder();

        $config = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder
                    ->addRoute()
                        ->path('/api/custom')
                        ->method('GET')
                        ->operationId('customRoute')
                    ->end()
                ;
            }
        };

        $config->configure($builder);
        $phpSpec = $builder->build();

        // Simulate empty YAML config
        $yamlConfig = [];

        $merged = LoadApiDocConfigHelper::mergeConfigs($yamlConfig, $phpSpec);

        $this->assertArrayHasKey('paths', $merged);
        $this->assertArrayHasKey('/api/custom', $merged['paths']);
    }

    public function testPhpRouteMergedWithYamlRoute(): void
    {
        $builder = new ApiDocBuilder();

        $config = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder
                    ->addRoute()
                        ->path('/api/items')
                        ->method('POST')
                        ->operationId('createItem')
                    ->end()
                ;
            }
        };

        $config->configure($builder);
        $phpSpec = $builder->build();

        // Simulate YAML config with a GET on the same path
        $yamlConfig = [
            'paths' => [
                '/api/items' => [
                    'get' => ['operationId' => 'listItems'],
                ],
            ],
        ];

        $merged = LoadApiDocConfigHelper::mergeConfigs($yamlConfig, $phpSpec);

        $this->assertArrayHasKey('paths', $merged);
        $this->assertArrayHasKey('/api/items', $merged['paths']);
        // Both GET and POST should be present
        $this->assertArrayHasKey('get', $merged['paths']['/api/items']);
        $this->assertArrayHasKey('post', $merged['paths']['/api/items']);
        $this->assertSame('listItems', $merged['paths']['/api/items']['get']['operationId']);
        $this->assertSame('createItem', $merged['paths']['/api/items']['post']['operationId']);
    }

    public function testPhpConfigWithInfoAndSchemaAndRoute(): void
    {
        $builder = new ApiDocBuilder();

        $config = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder
                    ->info()
                        ->title('Full API')
                        ->version('2.0.0')
                    ->end()
                    ->addSchema('Item')
                        ->type('object')
                        ->addProperty('id')
                            ->type('integer')
                        ->end()
                    ->end()
                    ->addRoute()
                        ->path('/api/items/{id}')
                        ->method('GET')
                        ->operationId('getItem')
                        ->response(200)
                            ->description('Item found')
                            ->jsonContent()
                                ->ref('#/components/schemas/Item')
                            ->end()
                        ->end()
                    ->end()
                ;
            }
        };

        $config->configure($builder);
        $spec = $builder->build();

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertSame('Full API', $spec['info']['title']);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('Item', $spec['components']['schemas']);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/api/items/{id}', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/items/{id}']);
        $this->assertSame('getItem', $spec['paths']['/api/items/{id}']['get']['operationId']);
    }

    public function testFullControllerFlowWithYamlAndPhp(): void
    {
        // Simulate the exact flow in ApiDocController::loadConfigFiles()

        // 1. Simulate YAML loading (as done by LoadApiDocConfigHelper::loadYamlConfigDoc)
        $yamlConfig = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Base API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/api/exemple' => [
                    'get' => [
                        'operationId' => 'listExamples',
                    ],
                ],
            ],
        ];

        // 2. Build PHP config (as done by PhpConfigLoader::load())
        $builder = new ApiDocBuilder();
        $config = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder
                    ->addRoute()
                        ->path('/api/products')
                        ->method('POST')
                        ->operationId('createProduct')
                    ->end()
                ;
            }
        };
        $config->configure($builder);
        $phpConfig = $builder->build();

        // 3. Merge (as done by LoadApiDocConfigHelper::mergeConfigs())
        $merged = LoadApiDocConfigHelper::mergeConfigs($yamlConfig, $phpConfig);

        $this->assertSame('3.0.0', $merged['openapi']);
        $this->assertSame('Base API', $merged['info']['title']);
        $this->assertArrayHasKey('/api/exemple', $merged['paths']);
        $this->assertArrayHasKey('/api/products', $merged['paths']);
        $this->assertSame('listExamples', $merged['paths']['/api/exemple']['get']['operationId']);
        $this->assertSame('createProduct', $merged['paths']['/api/products']['post']['operationId']);
    }

    public function testPhpRouteWithoutPathIsRegisteredWithEmptyPath(): void
    {
        $builder = new ApiDocBuilder();

        $builder
            ->addRoute()
                ->method('GET')
                ->operationId('orphanRoute')
            ->end()
        ;

        $spec = $builder->build();

        // end() always calls registerRoute, even with the default empty path
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('', $spec['paths']);
    }

    public function testPhpRouteWithoutMethodDefaultsToGet(): void
    {
        $builder = new ApiDocBuilder();

        $builder
            ->addRoute()
                ->path('/api/orphan')
                ->operationId('orphanRoute')
            ->end()
        ;

        $spec = $builder->build();

        // Default method is 'GET' when not explicitly set
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('/api/orphan', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/orphan']);
        $this->assertSame('orphanRoute', $spec['paths']['/api/orphan']['get']['operationId']);
    }

    public function testMultiplePhpConfigProviders(): void
    {
        $builder = new ApiDocBuilder();

        $config1 = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder->addRoute()->path('/api/a')->method('GET')->operationId('getA')->end();
            }
        };

        $config2 = new class implements ApiDocConfigInterface {
            public function configure(ApiDocBuilder $builder): void
            {
                $builder->addRoute()->path('/api/b')->method('POST')->operationId('createB')->end();
            }
        };

        $config1->configure($builder);
        $config2->configure($builder);
        $spec = $builder->build();

        $this->assertArrayHasKey('/api/a', $spec['paths']);
        $this->assertArrayHasKey('/api/b', $spec['paths']);
        $this->assertSame('getA', $spec['paths']['/api/a']['get']['operationId']);
        $this->assertSame('createB', $spec['paths']['/api/b']['post']['operationId']);
    }

    public function testEmptyPhpConfigDoesNotOverrideYaml(): void
    {
        $yamlConfig = [
            'openapi' => '3.0.0',
            'info' => ['title' => 'Test'],
            'paths' => [
                '/api/test' => ['get' => ['operationId' => 'test']],
            ],
        ];

        $phpConfig = []; // Simulate no PHP config providers

        $merged = LoadApiDocConfigHelper::mergeConfigs($yamlConfig, $phpConfig);

        $this->assertSame('3.0.0', $merged['openapi']);
        $this->assertArrayHasKey('/api/test', $merged['paths']);
    }
}
