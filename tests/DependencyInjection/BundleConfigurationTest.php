<?php

namespace Ehyiah\ApiDocBundle\Tests\DependencyInjection;

use Ehyiah\ApiDocBundle\EhyiahApiDocBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @coversNothing
 */
class BundleConfigurationTest extends TestCase
{
    public function testDefaultUiIsSwagger(): void
    {
        $kernel = new ApiDocTestKernel();
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertTrue($container->hasParameter('ehyiah_api_doc.ui'));
        $this->assertEquals('swagger', $container->getParameter('ehyiah_api_doc.ui'));
    }

    public function testUiCanBeSetToRedoc(): void
    {
        $kernel = new ApiDocTestKernel(['ui' => 'redoc']);
        $kernel->boot();
        $container = $kernel->getContainer();

        $this->assertEquals('redoc', $container->getParameter('ehyiah_api_doc.ui'));
    }
}

class ApiDocTestKernel extends Kernel
{
    use MicroKernelTrait;

    private array $apiDocConfig;

    public function __construct(array $apiDocConfig = [])
    {
        parent::__construct('test', true);

        // Add required defaults
        $this->apiDocConfig = array_merge([
            'source_path' => 'src/Swagger',
            'dump_path' => 'var/Swagger',
        ], $apiDocConfig);
    }

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new EhyiahApiDocBundle(),
        ];
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
        ]);

        $container->extension('ehyiah_api_doc', $this->apiDocConfig);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/api_doc_test/cache/' . spl_object_hash($this);
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/api_doc_test/log/' . spl_object_hash($this);
    }
}
