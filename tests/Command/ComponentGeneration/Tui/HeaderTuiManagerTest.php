<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header\HeaderTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class HeaderTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/headers/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'X-Request-ID.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'headers' => [
                        'X-Request-ID' => ['description' => 'Request ID', 'schema' => ['type' => 'string']],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('X-Request-ID', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/headers/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_header.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'headers' => ['CustomHeader' => ['description' => 'Custom', 'schema' => ['type' => 'string']]],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomHeader', $components);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'etag.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'headers' => ['ETag' => ['description' => 'Cache tag', 'schema' => ['type' => 'string']]],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('ETag');

        $this->assertNotNull($file);
        $this->assertStringContainsString('etag.yaml', $file);
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
        file_put_contents($dir . 'ETag.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addHeader('ETag')
            ->typeString('uuid')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('ETag');

        $this->assertNotNull($file);
        $this->assertStringContainsString('ETag.php', $file);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/headers/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'X-Request-ID.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'headers' => [
                        'X-Request-ID' => [
                            'description' => 'Unique request identifier',
                            'required' => true,
                            'deprecated' => false,
                            'schema' => ['type' => 'string', 'format' => 'uuid'],
                            'example' => 'abc-123',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('X-Request-ID');

        $this->assertNotNull($config);
        $this->assertSame('X-Request-ID', $config['name']);
        $this->assertSame('Unique request identifier', $config['description']);
        $this->assertTrue($config['required']);
        $this->assertFalse($config['deprecated']);
        $this->assertSame('string', $config['schemaType']);
        $this->assertSame('uuid', $config['format']);
        $this->assertSame('abc-123', $config['example']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    private function createManager(): HeaderTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new HeaderTuiManager($kernel, $parameterBag);
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
