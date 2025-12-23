<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining route parameters.
 */
class ParameterBuilder
{
    private RouteBuilder $routeBuilder;

    /** @var array<string, mixed> */
    private array $definition = [];

    /** @var array<ExampleBuilder> */
    private array $exampleBuilders = [];

    public function __construct(RouteBuilder $routeBuilder)
    {
        $this->routeBuilder = $routeBuilder;
    }

    /**
     * Set the parameter name.
     *
     * @param string $name Parameter name
     */
    public function name(string $name): self
    {
        $this->definition['name'] = $name;

        return $this;
    }

    /**
     * Set where the parameter is located.
     *
     * @param string $in Location: 'query', 'path', 'header', 'cookie'
     */
    public function in(string $in): self
    {
        $this->definition['in'] = $in;

        return $this;
    }

    /**
     * Set the parameter description.
     *
     * @param string $description Parameter description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Mark the parameter as required.
     *
     * @param bool $required Whether the parameter is required
     */
    public function required(bool $required = true): self
    {
        $this->definition['required'] = $required;

        return $this;
    }

    /**
     * Set the parameter schema.
     *
     * @param array<string, mixed> $schema Schema definition
     */
    public function schema(array $schema): self
    {
        $this->definition['schema'] = $schema;

        return $this;
    }

    /**
     * Set a default value for the parameter.
     *
     * @param mixed $default Default value
     */
    public function defaultValue($default): self
    {
        if (!isset($this->definition['schema'])) {
            $this->definition['schema'] = [];
        }
        $this->definition['schema']['default'] = $default;

        return $this;
    }

    /**
     * Set an example value for the parameter.
     *
     * @param mixed $example Example value
     */
    public function example($example): self
    {
        $this->definition['example'] = $example;

        return $this;
    }

    /**
     * Add a named example for this parameter.
     * Use this method to add multiple examples with summary and description.
     *
     * OpenAPI allows multiple named examples, each with optional summary,
     * description, and either a value or externalValue.
     *
     * Example usage:
     *   ->addExample('default')
     *       ->summary('Default ID')
     *       ->value(1)
     *   ->end()
     *   ->addExample('admin')
     *       ->summary('Admin user ID')
     *       ->value(999)
     *   ->end()
     *
     * @param string $name The name/key for this example
     *
     * @return ExampleBuilder<self>
     */
    public function addExample(string $name): ExampleBuilder
    {
        $exampleBuilder = new ExampleBuilder($this, $name);
        $this->exampleBuilders[] = $exampleBuilder;

        return $exampleBuilder;
    }

    /**
     * Finish building this parameter and return to the route builder.
     */
    public function end(): RouteBuilder
    {
        return $this->routeBuilder;
    }

    /**
     * Build the parameter definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        // Build examples if present
        if (!empty($this->exampleBuilders)) {
            $examples = [];
            foreach ($this->exampleBuilders as $exampleBuilder) {
                $examples[$exampleBuilder->getName()] = $exampleBuilder->buildArray();
            }
            $this->definition['examples'] = $examples;
        }

        return $this->definition;
    }
}
