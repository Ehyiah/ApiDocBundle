<?php

namespace Ehyiah\ApiDocBundle\Tests;

use Ehyiah\ApiDocBundle\EhyiahApiDocBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @coversNothing
 */
class AppKernelTest extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
            new EhyiahApiDocBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $config = __DIR__ . '/ehyiah_api_doc_test.yaml';
        $loader->load($config);
    }
}
