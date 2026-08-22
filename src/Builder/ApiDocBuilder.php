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

    /** @var array<string, array<string, mixed>> */
    private array $links = [];

    /** @var array<string, array<string, mixed>> */
    private array $callbacks = [];

    /** @var array<string, array<string, mixed>> */
    private array $pathItems = [];

    /** @var array<string, array<string, mixed>> */
    private array $requestBodies = [];

    /** @var array<string, array<string, mixed>> */
    private array $parameters = [];

    /** @var array<string, array<string, mixed>> */
    private array $headers = [];

    /** @var array<string, array<string, mixed>> */
    private array $responses = [];

    /** @var array<string, array<string, mixed>> */
    private array $examples = [];

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
     * Start building a new link component.
     *
     * @param string $name The link name
     */
    public function addLink(string $name): LinkBuilder
    {
        return new LinkBuilder($this, $name);
    }

    /**
     * Start building a new callback component.
     *
     * @param string $name The callback name
     */
    public function addCallback(string $name): CallbackBuilder
    {
        return new CallbackBuilder($this, $name);
    }

    /**
     * Start building a new path item component.
     *
     * @param string $name The path item name
     */
    public function addPathItem(string $name): PathItemBuilder
    {
        return new PathItemBuilder($this, $name);
    }

    /**
     * Start building a reusable request body component.
     *
     * @param string $name The request body component name
     */
    public function addRequestBody(string $name): RequestBodyBuilder
    {
        return new RequestBodyBuilder($this, $name);
    }

    /**
     * Start building a reusable parameter component.
     *
     * @param string $name The parameter component name
     */
    public function addParameter(string $name): ParameterBuilder
    {
        return new ParameterBuilder($this, $name);
    }

    /**
     * Start building a reusable header component.
     *
     * @param string $name The header component name
     */
    public function addHeader(string $name): HeaderBuilder
    {
        return new HeaderBuilder($this, $name);
    }

    /**
     * Start building a reusable response component.
     *
     * @param string $name The response component name
     */
    public function addResponse(string $name): ResponseBuilder
    {
        return new ResponseBuilder($this, null, $name);
    }

    /**
     * Start building a reusable example component.
     *
     * @param string $name The example component name
     */
    public function addExample(string $name): ExampleBuilder
    {
        return new ExampleBuilder($this, $name);
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
        $this->info = $this->mergeConfig($this->info, $info);
    }

    /**
     * Deep merge two arrays, with later values overwriting earlier ones for scalar values.
     * Unlike array_merge_recursive, this does not convert scalar values to arrays.
     *
     * @param array<string, mixed> $base Base array
     * @param array<string, mixed> $override Array to merge (values take precedence)
     *
     * @return array<string, mixed>
     */
    private function mergeConfig(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                // Both are arrays - merge recursively (but only for associative arrays)
                if ($this->isAssociativeArray($value) && $this->isAssociativeArray($base[$key])) {
                    $base[$key] = $this->mergeConfig($base[$key], $value);
                } else {
                    // Sequential arrays (like servers, tags) - append
                    $base[$key] = array_merge($base[$key], $value);
                }
            } else {
                // Scalar or new key - override
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Check if an array is associative (has string keys).
     *
     * @param array<mixed> $array
     */
    private function isAssociativeArray(array $array): bool
    {
        if ([] === $array) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
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
     * Internal method to register a link definition.
     *
     * @param string $name The link name
     * @param array<string, mixed> $definition The link definition
     *
     * @internal
     */
    public function registerLink(string $name, array $definition): void
    {
        $this->links[$name] = $definition;
    }

    /**
     * Internal method to register a callback definition.
     *
     * @param string $name The callback name
     * @param array<string, mixed> $definition The callback definition
     *
     * @internal
     */
    public function registerCallback(string $name, array $definition): void
    {
        $this->callbacks[$name] = $definition;
    }

    /**
     * Internal method to register a path item definition.
     *
     * @param string $name The path item name
     * @param array<string, mixed> $definition The path item definition
     *
     * @internal
     */
    public function registerPathItem(string $name, array $definition): void
    {
        $this->pathItems[$name] = $definition;
    }

    /**
     * Internal method to register a request body definition.
     *
     * @param string $name The request body name
     * @param array<string, mixed> $definition The request body definition
     *
     * @internal
     */
    public function registerRequestBody(string $name, array $definition): void
    {
        $this->requestBodies[$name] = $definition;
    }

    /**
     * Internal method to register a parameter definition.
     *
     * @param string $name The parameter name
     * @param array<string, mixed> $definition The parameter definition
     *
     * @internal
     */
    public function registerParameter(string $name, array $definition): void
    {
        $this->parameters[$name] = $definition;
    }

    /**
     * Internal method to register a header definition.
     *
     * @param string $name The header name
     * @param array<string, mixed> $definition The header definition
     *
     * @internal
     */
    public function registerHeader(string $name, array $definition): void
    {
        $this->headers[$name] = $definition;
    }

    /**
     * Internal method to register a response definition.
     *
     * @param string $name The response name
     * @param array<string, mixed> $definition The response definition
     *
     * @internal
     */
    public function registerResponse(string $name, array $definition): void
    {
        $this->responses[$name] = $definition;
    }

    /**
     * Internal method to register an example definition.
     *
     * @param string $name The example name
     * @param array<string, mixed> $definition The example definition
     *
     * @internal
     */
    public function registerExample(string $name, array $definition): void
    {
        $this->examples[$name] = $definition;
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

    public function clearSchemas(): void
    {
        $this->schemas = [];
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

        // Add components
        $hasComponents = !empty($this->schemas)
            || !empty($this->securitySchemes)
            || !empty($this->links)
            || !empty($this->callbacks)
            || !empty($this->pathItems)
            || !empty($this->requestBodies)
            || !empty($this->parameters)
            || !empty($this->headers)
            || !empty($this->responses)
            || !empty($this->examples);

        if ($hasComponents) {
            if (!empty($this->schemas)) {
                $spec['components']['schemas'] = $this->schemas;
            }
            if (!empty($this->securitySchemes)) {
                $spec['components']['securitySchemes'] = $this->securitySchemes;
            }
            if (!empty($this->links)) {
                $spec['components']['links'] = $this->links;
            }
            if (!empty($this->callbacks)) {
                $spec['components']['callbacks'] = $this->callbacks;
            }
            if (!empty($this->pathItems)) {
                $spec['components']['pathItems'] = $this->pathItems;
            }
            if (!empty($this->requestBodies)) {
                $spec['components']['requestBodies'] = $this->requestBodies;
            }
            if (!empty($this->parameters)) {
                $spec['components']['parameters'] = $this->parameters;
            }
            if (!empty($this->headers)) {
                $spec['components']['headers'] = $this->headers;
            }
            if (!empty($this->responses)) {
                $spec['components']['responses'] = $this->responses;
            }
            if (!empty($this->examples)) {
                $spec['components']['examples'] = $this->examples;
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
