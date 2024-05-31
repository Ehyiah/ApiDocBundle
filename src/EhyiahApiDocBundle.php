<?php

namespace Ehyiah\ApiDocBundle;

use Exception;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class EhyiahApiDocBundle extends AbstractBundle
{
    /**
     * @throws Exception
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::prependExtension($container, $builder);

        $loader = new YamlFileLoader($builder, new FileLocator(__DIR__ . '/../config/packages'));
        $loader->load('monolog.yaml');
    }

    /**
     * @param array<string,array<string,mixed>> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $container->import('../config/services.yaml');

        $container->parameters()->set('ehyiah_api_doc.site_url', $config['site_url']);
        $container->parameters()->set('ehyiah_api_doc.source_path', $config['source_path']);
        $container->parameters()->set('ehyiah_api_doc.dump_path', $config['dump_path']);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        parent::configure($definition);

        $definition->rootNode()
            ->children()
                ->scalarNode('site_url')
                    ->isRequired()
                    ->defaultValue('')
                ->end()
                ->scalarNode('source_path')
                    ->isRequired()
                    ->defaultValue('src/Swagger')
                ->end()
                ->scalarNode('dump_path')
                    ->isRequired()
                    ->defaultValue('src/Swagger/dump')
                ->end()
            ->end()
        ;
    }
}
