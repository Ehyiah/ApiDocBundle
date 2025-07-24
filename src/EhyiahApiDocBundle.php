<?php

namespace Ehyiah\ApiDocBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class EhyiahApiDocBundle extends AbstractBundle
{
    /**
     * @param array<string,array<string,mixed>> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $container->import('../config/services.yaml');

        $container->parameters()->set('ehyiah_api_doc.site_urls', $config['site_urls']);
        $container->parameters()->set('ehyiah_api_doc.source_path', $config['source_path']);
        $container->parameters()->set('ehyiah_api_doc.dump_path', $config['dump_path']);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        parent::configure($definition);

        $definition->rootNode()
            ->children()
                ->scalarNode('site_urls')
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
