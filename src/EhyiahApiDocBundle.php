<?php

namespace Ehyiah\ApiDocBundle;

use Ehyiah\ApiDocBundle\DependencyInjection\Compiler\ApiDocConfigPass;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;
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

        $this->setParameterIfNotExists($builder, $container, 'ehyiah_api_doc.source_path', $config['source_path']);
        $this->setParameterIfNotExists($builder, $container, 'ehyiah_api_doc.dump_path', $config['dump_path']);
        $this->setParameterIfNotExists($builder, $container, 'ehyiah_api_doc.ui', $config['ui']);
        $this->setParameterIfNotExists($builder, $container, 'ehyiah_api_doc.scan_directories', $config['scan_directories']);
    }

    private function setParameterIfNotExists(ContainerBuilder $builder, ContainerConfigurator $container, string $name, mixed $value): void
    {
        if (!$builder->hasParameter($name)) {
            $container->parameters()->set($name, $value);
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        parent::configure($definition);

        $definition->rootNode()
            ->children()
                ->scalarNode('source_path')
                    ->defaultValue('src/Swagger')
                ->end()
                ->scalarNode('dump_path')
                    ->defaultValue('src/Swagger/dump')
                ->end()
                ->enumNode('ui')
                    ->values(['swagger', 'redoc', 'stoplight', 'rapidoc', 'scalar'])
                    ->defaultValue('swagger')
                    ->info('Choose the UI to render the documentation: swagger, redoc, stoplight, rapidoc or scalar')
                ->end()
                ->arrayNode('scan_directories')
                    ->defaultValue(['src/Entity'])
                    ->scalarPrototype()->end()
                    ->info('Directories to scan for classes when generating components')
                ->end()
            ->end()
        ;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerForAutoconfiguration(ApiDocConfigInterface::class)
            ->addTag('ehyiah_api_doc.config_provider')
        ;

        $container->addCompilerPass(new ApiDocConfigPass());
    }
}
