<?php

namespace Ehyiah\ApiDocBundle;

use Ehyiah\ApiDocBundle\DependencyInjection\Compiler\ApiDocConfigPass;
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

        $container->parameters()->set('ehyiah_api_doc.site_urls', $config['site_urls']);
        $container->parameters()->set('ehyiah_api_doc.source_path', $config['source_path']);
        $container->parameters()->set('ehyiah_api_doc.dump_path', $config['dump_path']);
        $container->parameters()->set('ehyiah_api_doc.enable_php_config', $config['enable_php_config'] ?? true);
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
                ->booleanNode('enable_php_config')
                    ->defaultTrue()
                    ->info('Enable PHP configuration classes for API documentation')
                ->end()
            ->end()
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register the compiler pass to collect config providers
        $container->addCompilerPass(new ApiDocConfigPass());
    }
}
