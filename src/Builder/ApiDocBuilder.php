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

    /** @var array<string, mixed> */
    private array $info = [];

    /** @var array<array<string, mixed>> */
    private array $tags = [];

    /** @var array<string, array<string, mixed>> */
    private array $securitySchemes = [];

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
     * Start building the OpenAPI base configuration (openapi, info, servers, global security).
     */
    public function info(): InfoBuilder
    {
        return new InfoBuilder($this);
    }

    /**
     * Start building a new security scheme definition.
     *
     * @param string $name The security scheme name (e.g., 'Bearer', 'ApiKey')
     */
    public function addSecurityScheme(string $name): SecuritySchemeBuilder
    {
        return new SecuritySchemeBuilder($this, $name);
    }

    /**
     * Start building a new tag definition.
     *
     * @param string $name The tag name
     */
    public function addTag(string $name): TagBuilder
    {
        return new TagBuilder($this, $name);
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
     * Internal method to register info configuration.
     *
     * @param array<string, mixed> $info The info configuration
     *
     * @internal
     */
    public function registerInfo(array $info): void
    {
        $this->info = array_merge_recursive($this->info, $info);
    }

    /**
     * Internal method to register a tag definition.
     *
     * @param array<string, mixed> $tag The tag definition
     *
     * @internal
     */
    public function registerTag(array $tag): void
    {
        $this->tags[] = $tag;
    }

    /**
     * Internal method to register a security scheme definition.
     *
     * @param string $name The security scheme name
     * @param array<string, mixed> $definition The security scheme definition
     *
     * @internal
     */
    public function registerSecurityScheme(string $name, array $definition): void
    {
        $this->securitySchemes[$name] = $definition;
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

        // Add openapi version and info
        if (!empty($this->info)) {
            if (isset($this->info['openapi'])) {
                $spec['openapi'] = $this->info['openapi'];
            }
            if (isset($this->info['info'])) {
                $spec['info'] = $this->info['info'];
            }
            if (isset($this->info['servers'])) {
                $spec['servers'] = $this->info['servers'];
            }
            if (isset($this->info['security'])) {
                $spec['security'] = $this->info['security'];
            }
        }

        // Add tags
        if (!empty($this->tags)) {
            $spec['tags'] = $this->tags;
        }

        // Add paths
        if (!empty($this->paths)) {
            $spec['paths'] = $this->paths;
        }

        // Add components (schemas and securitySchemes)
        if (!empty($this->schemas) || !empty($this->securitySchemes)) {
            if (!empty($this->schemas)) {
                $spec['components']['schemas'] = $this->schemas;
            }
            if (!empty($this->securitySchemes)) {
                $spec['components']['securitySchemes'] = $this->securitySchemes;
            }
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
