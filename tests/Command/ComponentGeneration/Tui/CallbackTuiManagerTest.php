<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback\CallbackTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class CallbackTuiManagerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apidoc_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testGetExistingComponentsReturnsArray(): void
    {
        $manager = $this->createManager();
        $result = $manager->getExistingComponents();
        $this->assertIsArray($result);
    }

    public function testGetDefaultDumpLocationReturnsPath(): void
    {
        $manager = $this->createManager();
        $location = $manager->getDefaultDumpLocation();
        $this->assertIsString($location);
        $this->assertStringStartsWith('/', $location);
    }

    public function testGetExistingComponentsFindsYamlFiles(): void
    {
        $dir = $this->tmpDir . '/Swagger/callbacks/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'OnOrder.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'callbacks' => [
                        'OnOrder' => [
                            '{$request.body#/callbackUrl}' => [
                                'post' => ['operationId' => 'handleOrder'],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('OnOrder', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/callbacks/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_callback.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'callbacks' => [
                        'CustomCallback' => [
                            '{$request.body#/url}' => ['post' => ['operationId' => 'handle']],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomCallback', $components);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/callbacks/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'OnOrder.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'callbacks' => [
                        'OnOrder' => [
                            '{$request.body#/callbackUrl}' => [
                                'post' => [
                                    'operationId' => 'handleOrderCallback',
                                    'description' => 'Handle order event',
                                    'requestBody' => [
                                        'content' => [
                                            'application/json' => [
                                                'schema' => ['$ref' => '#/components/schemas/Order'],
                                            ],
                                        ],
                                    ],
                                    'responses' => [
                                        '200' => ['description' => 'Success'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('OnOrder');

        $this->assertNotNull($config);
        $this->assertSame('{$request.body#/callbackUrl}', $config['expression']);
        $this->assertSame('post', $config['method']);
        $this->assertSame('handleOrderCallback', $config['operationId']);
        $this->assertSame('Handle order event', $config['description']);
        $this->assertSame('#/components/schemas/Order', $config['requestBodyRef']);
        $this->assertSame('Success', $config['responseDescription']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_callback.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'callbacks' => [
                        'MyCallback' => [
                            '{$request.body#/url}' => ['post' => ['operationId' => 'handle']],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyCallback');

        $this->assertNotNull($file);
        $this->assertStringContainsString('my_callback.yaml', $file);
    }

    public function testFindComponentFileReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $file = $manager->findComponentFile('NonExistent');

        $this->assertNull($file);
    }

    public function testFindComponentFileFindsPhpFile(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'MyCallback.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addCallback('MyCallback')
            ->pathItem('{$request.body#/url}', ['post' => ['operationId' => 'handle']])
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyCallback');

        $this->assertNotNull($file);
        $this->assertStringContainsString('MyCallback.php', $file);
    }

    private function createManager(): CallbackTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new CallbackTuiManager($kernel, $parameterBag);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
