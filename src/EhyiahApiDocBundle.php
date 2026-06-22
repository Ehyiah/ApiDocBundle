<?php

namespace Ehyiah\ApiDocBundle;

use Composer\Autoload\ClassLoader;
use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\DependencyInjection\Compiler\ApiDocConfigPass;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ChildDefinition;
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

        $this->registerSourcePathServices($config, $builder, $container);
    }

    /**
     * Register the source_path directory as a service resource for autoconfiguration.
     *
     * This allows PHP config classes in the source_path to be automatically
     * discovered as services tagged with 'ehyiah_api_doc.config_provider'.
     */
    private function registerSourcePathServices(array $config, ContainerBuilder $builder, ContainerConfigurator $container): void
    {
        $sourcePath = $config['source_path'];
        $projectDir = $builder->getParameter('kernel.project_dir');

        $absolutePath = realpath($projectDir . '/' . ltrim($sourcePath, '/'));

        if (false === $absolutePath || !is_dir($absolutePath)) {
            return;
        }

        $namespace = self::resolveNamespace($absolutePath);

        if (null === $namespace) {
            return;
        }

        $container->services()
            ->load($namespace . '\\', $absolutePath)
            ->autowire()
            ->autoconfigure()
        ;
    }

    /**
     * Resolve the fully-qualified namespace for a given absolute path
     * by matching it against Composer's PSR-4 autoloading prefixes.
     *
     * For example, if '/var/www/project/src/Swagger' is under the PSR-4 prefix
     * 'App\\' => 'src/', the resolved namespace is 'App\\Swagger'.
     */
    public static function resolveNamespace(string $absolutePath): ?string
    {
        foreach (spl_autoload_functions() as $autoloadFunction) {
            if (!is_array($autoloadFunction) || !$autoloadFunction[0] instanceof ClassLoader) {
                continue;
            }

            foreach ($autoloadFunction[0]->getPrefixesPsr4() as $prefix => $paths) {
                foreach ($paths as $path) {
                    $resolvedPath = realpath($path);

                    if (false === $resolvedPath) {
                        continue;
                    }

                    if (!str_starts_with($absolutePath, $resolvedPath)) {
                        continue;
                    }

                    $relativePath = substr($absolutePath, strlen($resolvedPath) + 1);

                    if ('' === $relativePath) {
                        return rtrim($prefix, '\\');
                    }

                    return rtrim($prefix, '\\') . '\\' . str_replace('/', '\\', $relativePath);
                }
            }
        }

        return null;
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

        $container->registerAttributeForAutoconfiguration(
            AsTuiGenerator::class,
            static function (ChildDefinition $definition, AsTuiGenerator $attribute, ReflectionClass $reflector) {
                $definition->addTag('ehyiah_api_doc.tui_generator');
            }
        );

        $container->addCompilerPass(new ApiDocConfigPass());
    }
}
