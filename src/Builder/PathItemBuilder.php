<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI Path Item objects.
 *
 * A Path Item Object describes the operations available on a single path.
 */
class PathItemBuilder
{
    private ApiDocBuilder $apiDocBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    /**
     * @param ApiDocBuilder $apiDocBuilder The root builder
     * @param string $name The path item name
     */
    public function __construct(ApiDocBuilder $apiDocBuilder, string $name)
    {
        $this->apiDocBuilder = $apiDocBuilder;
        $this->name = $name;
    }

    /**
     * Set a summary of the path item.
     *
     * @param string $summary An optional summary
     */
    public function summary(string $summary): self
    {
        $this->definition['summary'] = $summary;

        return $this;
    }

    /**
     * Set a description of the path item.
     *
     * @param string $description An optional description, CommonMark syntax MAY be used
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Set a $ref reference to an external definition.
     *
     * @param string $ref The reference string
     */
    public function ref(string $ref): self
    {
        $this->definition['$ref'] = $ref;

        return $this;
    }

    /**
     * Add a parameter to this path item.
     *
     * @param array<string, mixed> $parameter The parameter definition
     */
    public function parameter(array $parameter): self
    {
        $this->definition['parameters'][] = $parameter;

        return $this;
    }

    /**
     * Add a server to this path item.
     *
     * @param string $url Server URL
     * @param string|null $description Optional description
     */
    public function server(string $url, ?string $description = null): self
    {
        $server = ['url' => $url];
        if (null !== $description) {
            $server['description'] = $description;
        }

        if (!isset($this->definition['servers'])) {
            $this->definition['servers'] = [];
        }
        $this->definition['servers'][] = $server;

        return $this;
    }

    /**
     * Add a GET operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function get(array $operation = []): self
    {
        $this->definition['get'] = $operation;

        return $this;
    }

    /**
     * Add a PUT operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function put(array $operation = []): self
    {
        $this->definition['put'] = $operation;

        return $this;
    }

    /**
     * Add a POST operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function post(array $operation = []): self
    {
        $this->definition['post'] = $operation;

        return $this;
    }

    /**
     * Add a DELETE operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function delete(array $operation = []): self
    {
        $this->definition['delete'] = $operation;

        return $this;
    }

    /**
     * Add an OPTIONS operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function options(array $operation = []): self
    {
        $this->definition['options'] = $operation;

        return $this;
    }

    /**
     * Add a HEAD operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function head(array $operation = []): self
    {
        $this->definition['head'] = $operation;

        return $this;
    }

    /**
     * Add a PATCH operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function patch(array $operation = []): self
    {
        $this->definition['patch'] = $operation;

        return $this;
    }

    /**
     * Add a TRACE operation to this path item.
     *
     * @param array<string, mixed> $operation The operation definition
     */
    public function trace(array $operation = []): self
    {
        $this->definition['trace'] = $operation;

        return $this;
    }

    /**
     * Finish building this path item and return to the main builder.
     */
    public function end(): ApiDocBuilder
    {
        // Register the path item as a component
        $this->apiDocBuilder->registerPathItem($this->name, $this->definition);

        return $this->apiDocBuilder;
    }

    /**
     * Get the path item name.
     *
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the path item definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        return $this->definition;
    }
}
