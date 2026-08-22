<?php

namespace Ehyiah\ApiDocBundle\DependencyInjection\Compiler;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Loader\PhpConfigLoader;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to collect all API doc config providers and build a component file registry.
 *
 * This pass finds all services tagged with 'ehyiah_api_doc.config_provider'
 * (those implementing ApiDocConfigInterface) and:
 * 1. Registers them with the PhpConfigLoader.
 * 2. Builds a mapping of [type][name] => filePath from the #[ApiDocConfig] attribute,
 *    stored as the 'ehyiah_api_doc.component_files' container parameter.
 *    This allows finding PHP component files by their attribute rather than directory scanning.
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

        $componentFiles = [];

        foreach ($taggedServices as $id => $tags) {
            $loaderDefinition->addMethodCall('addConfigProvider', [new Reference($id)]);

            $definition = $container->getDefinition($id);
            $class = $definition->getClass();

            if (null === $class || !class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(ApiDocConfig::class);

            if ([] === $attributes) {
                continue;
            }

            $attribute = $attributes[0]->newInstance();
            $filePath = $reflection->getFileName();

            if (null !== $attribute->type && null !== $attribute->component && false !== $filePath) {
                $componentFiles[$attribute->type][$attribute->component] = $filePath;
            }
        }

        $container->setParameter('ehyiah_api_doc.component_files', $componentFiles);
    }
}
