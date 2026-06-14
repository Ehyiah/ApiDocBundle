<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui\SecuritySchemeTuiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class SecuritySchemeTuiManagerTest extends TestCase
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
        $dir = $this->tmpDir . '/Swagger/securitySchemes/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'BearerAuth.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'securitySchemes' => [
                        'BearerAuth' => ['type' => 'http', 'scheme' => 'bearer'],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('BearerAuth', $components);
    }

    public function testLoadComponentConfigReturnsConfig(): void
    {
        $dir = $this->tmpDir . '/Swagger/securitySchemes/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'ApiKeyAuth.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'securitySchemes' => [
                        'ApiKeyAuth' => [
                            'type' => 'apiKey',
                            'name' => 'X-API-Key',
                            'in' => 'header',
                            'description' => 'API key authentication',
                        ],
                    ],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $config = $manager->loadComponentConfig('ApiKeyAuth');

        $this->assertNotNull($config);
        $this->assertSame('apiKey', $config['type']);
        $this->assertSame('X-API-Key', $config['name']);
        $this->assertSame('header', $config['in']);
        $this->assertSame('API key authentication', $config['description']);
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
        file_put_contents($dir . 'my_scheme.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'securitySchemes' => ['MyScheme' => ['type' => 'http']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $file = $manager->findComponentFile('MyScheme');

        $this->assertNotNull($file);
        $this->assertStringContainsString('my_scheme.yaml', $file);
    }

    public function testFindComponentFileReturnsNullWhenNotFound(): void
    {
        $manager = $this->createManager();
        $file = $manager->findComponentFile('NonExistent');

        $this->assertNull($file);
    }

    public function testGetExistingComponentsFindsYamlInAnySubdirectory(): void
    {
        $dir = $this->tmpDir . '/Swagger/custom/auth/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'my_component.yaml', Yaml::dump([
            'documentation' => [
                'components' => [
                    'securitySchemes' => ['CustomScheme' => ['type' => 'http', 'scheme' => 'bearer']],
                ],
            ],
        ]));

        $manager = $this->createManager();
        $components = $manager->getExistingComponents();

        $this->assertContains('CustomScheme', $components);
    }

    private function createManager(): SecuritySchemeTuiManager
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        return new SecuritySchemeTuiManager($kernel, $parameterBag);
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
