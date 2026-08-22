<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example\ExampleTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class ExampleTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/examples/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'SuccessfulLogin.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'examples' => [
                        'SuccessfulLogin' => ['summary' => 'Login response', 'value' => ['token' => 'abc']],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('SuccessfulLogin', $components);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/examples/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_example.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'examples' => ['CustomExample' => ['summary' => 'Custom', 'value' => ['key' => 'val']]],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomExample', $components);
    }

    public function testFindComponentFileReturnsFilePath(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'user.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'examples' => ['UserExample' => ['summary' => 'User', 'value' => ['id' => 1]]],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('UserExample');

        $this->assertNotNull($file);
        $this->assertStringContainsString('user.yaml', $file);
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
        file_put_contents($dir . 'UserExample.php', <<<'PHP'
<?php
return new class implements \Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface {
    public function configure(\Ehyiah\ApiDocBundle\Builder\ApiDocBuilder $builder): void
    {
        $builder->addExample('UserExample')
            ->summary('User example')
            ->value(['id' => 1])
        ->end();
    }
};
PHP
        );

        $manager = $this->createManager();
        $file = $manager->findComponentFile('UserExample');

        $this->assertNotNull($file);
        $this->assertStringContainsString('UserExample.php', $file);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/examples/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'SuccessfulLogin.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'examples' => [
                        'SuccessfulLogin' => [
                            'summary' => 'Successful login',
                            'description' => 'Returns a valid JWT',
                            'value' => ['token' => 'abc'],
                            'externalValue' => '',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('SuccessfulLogin');

        $this->assertNotNull($config);
        $this->assertSame('SuccessfulLogin', $config['name']);
        $this->assertSame('Successful login', $config['summary']);
        $this->assertSame('Returns a valid JWT', $config['description']);
        $this->assertStringContainsString('token', $config['value']);
    }

    public function testLoadComponentConfigWithExternalValue(): void
    {
        $dir = $this->tmpDir . '/Swagger/examples/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'ExternalExample.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'examples' => [
                        'ExternalExample' => [
                            'summary' => 'External',
                            'externalValue' => 'https://example.com/data.json',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('ExternalExample');

        $this->assertNotNull($config);
        $this->assertSame('https://example.com/data.json', $config['externalValue']);
    }

    public function testLoadComponentConfigReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('NonExistent');

        $this->assertNull($config);
    }

    private function createManager(): ExampleTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new ExampleTuiManager($kernel, $parameterBag);
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
