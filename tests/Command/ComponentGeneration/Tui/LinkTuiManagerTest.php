<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link\LinkTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class LinkTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/links/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'GetUserOrders.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'links' => [
                        'GetUserOrders' => ['operationId' => 'getUserOrders', 'description' => 'User orders'],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('GetUserOrders', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/links/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_link.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'links' => ['CustomLink' => ['operationRef' => '/users/{id}']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomLink', $components);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/links/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'GetOrders.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'links' => [
                        'GetOrders' => [
                            'operationId' => 'getOrders',
                            'operationRef' => '/users/{id}/orders',
                            'description' => 'Get user orders',
                            'parameters' => ['userId' => '$response.body#/id'],
                            'requestBody' => '$request.body',
                            'server' => ['url' => 'https://api.example.com', 'description' => 'API'],
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('GetOrders');

        $this->assertNotNull($config);
        $this->assertSame('getOrders', $config['operationId']);
        $this->assertSame('/users/{id}/orders', $config['operationRef']);
        $this->assertSame('Get user orders', $config['description']);
        $this->assertStringContainsString('userId', $config['parameters']);
        $this->assertSame('$request.body', $config['requestBody']);
        $this->assertSame('https://api.example.com', $config['serverUrl']);
        $this->assertSame('API', $config['serverDescription']);
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
        file_put_contents($dir . 'my_link.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'links' => ['MyLink' => ['operationId' => 'myLink']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyLink');

        $this->assertNotNull($file);
        $this->assertStringContainsString('my_link.yaml', $file);
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
        file_put_contents($dir . 'MyLink.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addLink('MyLink')
            ->operationId('myLink')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyLink');

        $this->assertNotNull($file);
        $this->assertStringContainsString('MyLink.php', $file);
    }

    private function createManager(): LinkTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new LinkTuiManager($kernel, $parameterBag);
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
