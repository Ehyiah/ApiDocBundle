<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response\ResponseTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class ResponseTuiManagerTest extends TestCase
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

    public function testGetAvailableSchemasReturnsArray(): void
    {
        $manager = $this->createManager();
        $result = $manager->getAvailableSchemas();
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
        $dir = $this->tmpDir . '/Swagger/responses/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'NotFound.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'responses' => [
                        'NotFound' => ['description' => 'Resource not found'],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('NotFound', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/responses/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_response.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'responses' => ['CustomResponse' => ['description' => 'Custom response']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomResponse', $components);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'error.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'responses' => ['Error' => ['description' => 'Error response']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('Error');

        $this->assertNotNull($file);
        $this->assertStringContainsString('error.yaml', $file);
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
        file_put_contents($dir . 'Error.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addResponse('Error')
            ->statusCode(500)
            ->description('Server error')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('Error');

        $this->assertNotNull($file);
        $this->assertStringContainsString('Error.php', $file);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/responses/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'NotFound.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'responses' => [
                        'NotFound' => [
                            'description' => 'Resource not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/Error'],
                                ],
                            ],
                            'headers' => [
                                'X-Request-ID' => ['description' => 'Request ID', 'schema' => ['type' => 'string']],
                            ],
                            'links' => [
                                'GetUser' => ['operationId' => 'getUser', 'parameters' => ['userId' => '$response.body#/id']],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NotFound');

        $this->assertNotNull($config);
        $this->assertSame('NotFound', $config['name']);
        $this->assertSame('Resource not found', $config['description']);
        $this->assertSame('Error', $config['schemaRef']);
        $this->assertSame('application/json', $config['contentType']);
        $this->assertStringContainsString('X-Request-ID', $config['headers']);
        $this->assertStringContainsString('GetUser', $config['links']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    private function createManager(): ResponseTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new ResponseTuiManager($kernel, $parameterBag);
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
