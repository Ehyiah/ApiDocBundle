<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui\RouteTuiManager;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * @coversNothing
 */
class RouteTuiManagerTest extends TestCase
{
    public function testLoadRouteConfigLoadsExistingFile(): void
    {
        $routeName = 'get_users';
        $routePath = '/api/users';

        $route = new Route($routePath);
        $routeCollection = new RouteCollection();
        $routeCollection->add($routeName, $route);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($routeCollection);

        // Mock ParameterBag to return a path that leads to tests/App/Swagger/
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger/'],
            ['ehyiah_api_doc.dump_path', '/Swagger/dump/'],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__ . '/../../../App');

        // The helper needs to find the file.
        $helper = new LoadApiDocConfigHelper($kernel, $parameterBag);

        $manager = new RouteTuiManager($router, $parameterBag, $kernel, $helper);

        $config = $manager->loadRouteConfig($routeName, 'routes');

        // Assert that the configuration was loaded
        $this->assertNotEmpty($config, 'The configuration should be loaded');
    }
}
