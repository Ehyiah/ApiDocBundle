<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter\ParameterTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class ParameterTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/parameters/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'userId.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'parameters' => [
                        'userId' => ['name' => 'userId', 'in' => 'path'],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('userId', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/params/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_param.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'parameters' => ['CustomParam' => ['name' => 'CustomParam', 'in' => 'query']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomParam', $components);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'page.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'parameters' => ['page' => ['name' => 'page', 'in' => 'query']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('page');

        $this->assertNotNull($file);
        $this->assertStringContainsString('page.yaml', $file);
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
        file_put_contents($dir . 'page.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addParameter('page')
            ->in('query')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('page');

        $this->assertNotNull($file);
        $this->assertStringContainsString('page.php', $file);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/parameters/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'userId.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'parameters' => [
                        'userId' => [
                            'name' => 'userId',
                            'in' => 'path',
                            'description' => 'User ID',
                            'required' => true,
                            'deprecated' => true,
                            'allowEmptyValue' => false,
                            'style' => 'simple',
                            'explode' => false,
                            'allowReserved' => true,
                            'schema' => ['type' => 'integer', 'format' => 'int64'],
                            'example' => '123',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('userId');

        $this->assertNotNull($config);
        $this->assertSame('userId', $config['name']);
        $this->assertSame('path', $config['in']);
        $this->assertSame('User ID', $config['description']);
        $this->assertTrue($config['required']);
        $this->assertTrue($config['deprecated']);
        $this->assertFalse($config['allowEmptyValue']);
        $this->assertSame('simple', $config['style']);
        $this->assertSame('false', $config['explode']);
        $this->assertTrue($config['allowReserved']);
        $this->assertSame('integer', $config['schemaType']);
        $this->assertSame('int64', $config['format']);
        $this->assertSame('123', $config['example']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    private function createManager(): ParameterTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new ParameterTuiManager($kernel, $parameterBag);
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
