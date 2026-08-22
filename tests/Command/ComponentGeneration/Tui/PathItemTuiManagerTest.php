<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem\PathItemTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class PathItemTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/pathItems/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'UserOps.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'pathItems' => [
                        'UserOps' => ['summary' => 'User operations', 'description' => 'All user ops'],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('UserOps', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/pathItems/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_pathitem.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'pathItems' => ['CustomPathItem' => ['summary' => 'Custom']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomPathItem', $components);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/pathItems/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'UserOps.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'pathItems' => [
                        'UserOps' => [
                            'summary' => 'User operations',
                            'description' => 'All user operations',
                            '$ref' => 'https://example.com/userOps.json',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('UserOps');

        $this->assertNotNull($config);
        $this->assertSame('User operations', $config['summary']);
        $this->assertSame('All user operations', $config['description']);
        $this->assertSame('https://example.com/userOps.json', $config['ref']);
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
        file_put_contents($dir . 'my_pathitem.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'pathItems' => ['MyPathItem' => ['summary' => 'Test']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyPathItem');

        $this->assertNotNull($file);
        $this->assertStringContainsString('my_pathitem.yaml', $file);
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
        file_put_contents($dir . 'MyPathItem.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addPathItem('MyPathItem')
            ->summary('Test path item')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyPathItem');

        $this->assertNotNull($file);
        $this->assertStringContainsString('MyPathItem.php', $file);
    }

    private function createManager(): PathItemTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new PathItemTuiManager($kernel, $parameterBag);
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
