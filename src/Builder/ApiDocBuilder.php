<?php

namespace Ehyiah\ApiDocBundle\Builder;

use InvalidArgumentException;
use LogicException;

/**
 * Main fluent API builder for creating OpenAPI documentation programmatically.
 *
 * This builder allows you to define routes, schemas, and other OpenAPI components
 * using a chainable, type-safe PHP API instead of writing YAML files.
 */
class ApiDocBuilder
{
    /** @var array<string, mixed> */
    private array $paths = [];

    /** @var array<string, array<string, mixed>> */
    private array $schemas = [];

    /** @var array<string, string> Schema reference registry: [refName => schemaName] */
    private array $schemaRefRegistry = [];

    /**
     * Start building a new route/path definition.
     */
    public function addRoute(): RouteBuilder
    {
        return new RouteBuilder($this);
    }

    /**
     * Start building a new schema component.
     *
     * @param string $name The schema name
     */
    public function addSchema(string $name): SchemaBuilder
    {
        return new SchemaBuilder($this, $name);
    }

    /**
     * Register a custom reference name for a schema.
     * This allows you to use short aliases instead of full schema names.
     *
     * @param string $refName The custom reference name (e.g., 'Product', 'UserDTO')
     * @param string $schemaName The actual schema name in components
     *
     * @internal
     */
    public function registerSchemaRef(string $refName, string $schemaName): void
    {
        $this->schemaRefRegistry[$refName] = $schemaName;
    }

    /**
     * Get the full schema reference path from a custom reference name.
     *
     * @param string $refName The custom reference name
     *
     * @return string The full OpenAPI reference path
     *
     * @throws InvalidArgumentException If the reference name is not registered
     */
    public function getSchemaRef(string $refName): string
    {
        if (!isset($this->schemaRefRegistry[$refName])) {
            throw new InvalidArgumentException(sprintf('Schema reference "%s" is not registered. Did you forget to call setRefName() on the schema?', $refName));
        }

        $schemaName = $this->schemaRefRegistry[$refName];

        return '#/components/schemas/' . $schemaName;
    }

    /**
     * Check if a custom reference name is registered.
     *
     * @param string $refName The custom reference name
     */
    public function hasSchemaRef(string $refName): bool
    {
        return isset($this->schemaRefRegistry[$refName]);
    }

    /**
     * Internal method to register a route definition.
     *
     * @param string $path The route path
     * @param string $method The HTTP method
     * @param array<string, mixed> $definition The route definition
     *
     * @internal
     */
    public function registerRoute(string $path, string $method, array $definition): void
    {
        if (!isset($this->paths[$path])) {
            $this->paths[$path] = [];
        }

        $this->paths[$path][strtolower($method)] = $definition;
    }

    /**
     * Internal method to register a schema definition.
     *
     * @param string $name The schema name
     * @param array<string, mixed> $definition The schema definition
     *
     * @internal
     */
    public function registerSchema(string $name, array $definition): void
    {
        $this->schemas[$name] = $definition;
    }

    /**
     * Get all paths (routes) as an array.
     *
     * @return array<string, mixed>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Get all schemas as an array.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getSchemas(): array
    {
        return $this->schemas;
    }

    /**
     * Build the complete OpenAPI specification array.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $spec = [];

        if (!empty($this->paths)) {
            $spec['paths'] = $this->paths;
        }

        if (!empty($this->schemas)) {
            $spec['components']['schemas'] = $this->schemas;
        }

        return $spec;
    }

    /**
     * This method exists to satisfy static analysis for fluent chains where
     * the builder type might be ambiguous (e.g. SchemaBuilder::end() returning parent).
     *
     * @return never
     *
     * @throws LogicException always, as the root builder has no parent
     */
    public function end(): void
    {
        throw new LogicException('You are trying to call end() on the root ApiDocBuilder. Check your builder chain.');
    }
}
