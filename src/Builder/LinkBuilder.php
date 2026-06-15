<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI Link objects.
 *
 * Links allow outgoing requests to other operations for request/response pairs.
 */
class LinkBuilder
{
    /** @var ResponseBuilder|ApiDocBuilder */
    private $parentBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    /**
     * @param ResponseBuilder|ApiDocBuilder $parentBuilder The parent builder
     * @param string $name The link name
     */
    public function __construct($parentBuilder, string $name)
    {
        $this->parentBuilder = $parentBuilder;
        $this->name = $name;
    }

    /**
     * Set the reference to an existing Operation Object.
     *
     * @param string $operationRef Relative or absolute URI reference to an OAS operation
     */
    public function operationRef(string $operationRef): self
    {
        $this->definition['operationRef'] = $operationRef;

        return $this;
    }

    /**
     * Set the operationId of an existing operation.
     *
     * @param string $operationId The name of an existing, resolvable OAS operation
     */
    public function operationId(string $operationId): self
    {
        $this->definition['operationId'] = $operationId;

        return $this;
    }

    /**
     * Add a parameter to pass to the operation.
     *
     * @param string $name Parameter name
     * @param string $value A literal value or expression
     */
    public function parameter(string $name, string $value): self
    {
        if (!isset($this->definition['parameters'])) {
            $this->definition['parameters'] = [];
        }
        $this->definition['parameters'][$name] = $value;

        return $this;
    }

    /**
     * Set the request body to pass to the operation.
     *
     * @param string $value A literal value or expression
     */
    public function requestBody(string $value): self
    {
        $this->definition['requestBody'] = $value;

        return $this;
    }

    /**
     * Set a description of the link.
     *
     * @param string $description CommonMark syntax MAY be used for rich text representation
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Set a server object to be used by the target operation.
     *
     * @param string $url URL of the target server
     * @param string|null $description An optional description
     */
    public function server(string $url, ?string $description = null): self
    {
        $server = ['url' => $url];
        if (null !== $description) {
            $server['description'] = $description;
        }
        $this->definition['server'] = $server;

        return $this;
    }

    /**
     * Finish building this link and return to the parent builder.
     *
     * @return ResponseBuilder|ApiDocBuilder
     */
    public function end()
    {
        if ($this->parentBuilder instanceof ApiDocBuilder) {
            $this->parentBuilder->registerLink($this->name, $this->definition);
        }

        return $this->parentBuilder;
    }

    /**
     * Get the link name.
     *
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the link definition as an array.
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
