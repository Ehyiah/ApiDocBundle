<?php

namespace Ehyiah\ApiDocBundle\Builder;

use InvalidArgumentException;

/**
 * Fluent builder for defining named examples.
 *
 * OpenAPI 3.0 Example Object specification.
 * Can be used with MediaType (Content), Parameter, and Header objects.
 *
 * @template T of ContentBuilder|ParameterBuilder|HeaderBuilder
 */
class ExampleBuilder
{
    /** @var T */
    private $parentBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    /**
     * @param T $parentBuilder The parent builder
     * @param string $name The name/key for this example
     */
    public function __construct($parentBuilder, string $name)
    {
        $this->parentBuilder = $parentBuilder;
        $this->name = $name;
    }

    /**
     * Set a short summary of the example.
     *
     * @param string $summary Short summary
     */
    public function summary(string $summary): self
    {
        $this->definition['summary'] = $summary;

        return $this;
    }

    /**
     * Set a long description of the example.
     * CommonMark syntax MAY be used for rich text representation.
     *
     * @param string $description Long description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Set the embedded literal example value.
     * The value field and externalValue field are mutually exclusive.
     *
     * @param mixed $value Example value
     */
    public function value($value): self
    {
        if (isset($this->definition['externalValue'])) {
            throw new InvalidArgumentException('Cannot set value when externalValue is already set. They are mutually exclusive.');
        }
        $this->definition['value'] = $value;

        return $this;
    }

    /**
     * Set a URL that points to the literal example.
     * The value field and externalValue field are mutually exclusive.
     *
     * @param string $url URL pointing to the example
     */
    public function externalValue(string $url): self
    {
        if (isset($this->definition['value'])) {
            throw new InvalidArgumentException('Cannot set externalValue when value is already set. They are mutually exclusive.');
        }
        $this->definition['externalValue'] = $url;

        return $this;
    }

    /**
     * Finish building this example and return to the parent builder.
     *
     * @return T
     */
    public function end()
    {
        return $this->parentBuilder;
    }

    /**
     * Get the example name/key.
     *
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the example definition as an array.
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
