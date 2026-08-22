<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tag\TagTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class TagTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'Users.yaml', Yaml::dump([
            'documentation' => [
                'tags' => [
                    ['name' => 'Users', 'description' => 'User management'],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('Users', $components);
    }

    public function testGetExistingComponentsFindsMultipleTags(): void
    {
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'tags.yaml', Yaml::dump([
            'documentation' => [
                'tags' => [
                    ['name' => 'Users', 'description' => 'User management'],
                    ['name' => 'Products', 'description' => 'Product management'],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('Users', $components);
        $this->assertContains('Products', $components);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'Users.yaml', Yaml::dump([
            'documentation' => [
                'tags' => [
                    ['name' => 'Users', 'description' => 'User management'],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('Users');

        $this->assertNotNull($file);
        $this->assertStringContainsString('Users.yaml', $file);
    }

    public function testFindComponentFileReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $file = $manager->findComponentFile('NonExistent');

        $this->assertNull($file);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'Users.yaml', Yaml::dump([
            'documentation' => [
                'tags' => [
                    [
                        'name' => 'Users',
                        'description' => 'User management',
                        'externalDocs' => [
                            'url' => 'https://example.com/docs/users',
                            'description' => 'Users documentation',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('Users');

        $this->assertNotNull($config);
        $this->assertSame('Users', $config['name']);
        $this->assertSame('User management', $config['description']);
        $this->assertSame('https://example.com/docs/users', $config['externalDocsUrl']);
        $this->assertSame('Users documentation', $config['externalDocsDescription']);
    }

    public function testLoadComponentConfigWithMinimalFields(): void
    {
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'Simple.yaml', Yaml::dump([
            'documentation' => [
                'tags' => [
                    ['name' => 'Simple'],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('Simple');

        $this->assertNotNull($config);
        $this->assertSame('Simple', $config['name']);
        $this->assertSame('', $config['description']);
        $this->assertSame('', $config['externalDocsUrl']);
        $this->assertSame('', $config['externalDocsDescription']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    public function testFindComponentFileFindsPhpFile(): void
    {
        $dir = $this->tmpDir . '/Swagger/tags/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'Admin.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addTag('Admin')
            ->description('Admin operations')
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('Admin');

        $this->assertNotNull($file);
        $this->assertStringContainsString('Admin.php', $file);
    }

    private function createManager(): TagTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new TagTuiManager($kernel, $parameterBag);
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
