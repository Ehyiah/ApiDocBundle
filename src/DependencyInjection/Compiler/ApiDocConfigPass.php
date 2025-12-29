<?php

namespace Ehyiah\ApiDocBundle\DependencyInjection\Compiler;

use Ehyiah\ApiDocBundle\Loader\PhpConfigLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to collect all API doc config providers.
 *
 * This pass finds all services tagged with 'ehyiah_api_doc.config_provider'
 * and registers them with the PhpConfigLoader.
 */
class ApiDocConfigPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(PhpConfigLoader::class)) {
            return;
        }

        $loaderDefinition = $container->findDefinition(PhpConfigLoader::class);

        $taggedServices = $container->findTaggedServiceIds('ehyiah_api_doc.config_provider');

        foreach ($taggedServices as $id => $tags) {
            $loaderDefinition->addMethodCall('addConfigProvider', [new Reference($id)]);
        }
    }
}
