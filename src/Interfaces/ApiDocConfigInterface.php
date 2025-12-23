<?php

namespace Ehyiah\ApiDocBundle\Interfaces;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;

/**
 * Interface for API documentation configuration providers.
 *
 * Classes implementing this interface will be auto-discovered via service tags
 * and used to programmatically build API documentation.
 */
interface ApiDocConfigInterface
{
    /**
     * Configure the API documentation using the builder.
     *
     * @param ApiDocBuilder $builder The fluent API builder
     */
    public function configure(ApiDocBuilder $builder): void;
}
