<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Route\RouteTuiManager;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class RouteTuiManagerTest extends TestCase
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

    public function testLoadRouteConfigLoadsExistingFile(): void
    {
        $routeName = 'get_users';
        $routePath = '/api/users';

        $route = new Route($routePath);
        $routeCollection = new RouteCollection();
        $routeCollection->add($routeName, $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger/'],
            ['ehyiah_api_doc.dump_path', '/Swagger/dump/'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__ . '/../../../App');

        $helper = new LoadApiDocConfigHelper($kernel, $parameterBag);

        $manager = new RouteTuiManager($router, $parameterBag, $kernel, $helper);

        $config = $manager->loadRouteConfig($routeName, 'routes');

        $this->assertNotEmpty($config, 'The configuration should be loaded');
    }

    public function testFindRouteFileReturnsFilePath(): void
    {
        $routeName = 'api_doc';
        $routePath = '/api/doc';

        $route = new Route($routePath);
        $routeCollection = new RouteCollection();
        $routeCollection->add($routeName, $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $dir = $this->tmpDir . '/Swagger/custom/';
        mkdir($dir, 0755, true);
        file_put_contents($dir . 'api_doc.yaml', Yaml::dump([
            'paths' => [
                '/api/doc' => [
                    'get' => ['summary' => 'Get doc'],
                ],
            ],
        ]));

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        $helper = $this->createMock(LoadApiDocConfigHelper::class);

        $manager = new RouteTuiManager($router, $parameterBag, $kernel, $helper);
        $file = $manager->findRouteFile($routeName);

        $this->assertNotNull($file);
        $this->assertStringContainsString('api_doc.yaml', $file);
    }

    public function testFindRouteFileReturnsNullWhenNotFound(): void
    {
        $routeName = 'nonexistent';
        $routePath = '/nonexistent';

        $route = new Route($routePath);
        $routeCollection = new RouteCollection();
        $routeCollection->add($routeName, $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        $helper = $this->createMock(LoadApiDocConfigHelper::class);

        $manager = new RouteTuiManager($router, $parameterBag, $kernel, $helper);
        $file = $manager->findRouteFile($routeName);

        $this->assertNull($file);
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
