<?php

namespace Ehyiah\ApiDocBundle\Loader;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

/**
 * Loader for PHP configuration classes.
 *
 * This loader executes all registered config provider classes and collects
 * their API documentation definitions.
 */
class PhpConfigLoader
{
    /** @var array<ApiDocConfigInterface> */
    private array $configProviders = [];

    /**
     * Add a configuration provider.
     */
    public function addConfigProvider(ApiDocConfigInterface $configProvider): void
    {
        $this->configProviders[] = $configProvider;
    }

    /**
     * Load API documentation from all registered PHP config providers.
     *
     * @return array<string, mixed> The combined OpenAPI specification array
     */
    public function load(): array
    {
        $builder = new ApiDocBuilder();

        // Execute each config provider
        foreach ($this->configProviders as $configProvider) {
            $configProvider->configure($builder);
        }

        return $builder->build();
    }
}
