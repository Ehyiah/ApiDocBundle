<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining response headers.
 *
 * OpenAPI 3.0 Header Object specification.
 */
class HeaderBuilder
{
    private ResponseBuilder $responseBuilder;

    private string $name;

    /** @var array<string, mixed> */
    private array $definition = [];

    public function __construct(ResponseBuilder $responseBuilder, string $name)
    {
        $this->responseBuilder = $responseBuilder;
        $this->name = $name;
    }

    /**
     * Set the header description.
     *
     * @param string $description Header description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Mark the header as required.
     *
     * @param bool $required Whether the header is required
     */
    public function required(bool $required = true): self
    {
        $this->definition['required'] = $required;

        return $this;
    }

    /**
     * Mark the header as deprecated.
     *
     * @param bool $deprecated Whether the header is deprecated
     */
    public function deprecated(bool $deprecated = true): self
    {
        $this->definition['deprecated'] = $deprecated;

        return $this;
    }

    /**
     * Allow empty value for the header.
     *
     * @param bool $allowEmptyValue Whether empty values are allowed
     */
    public function allowEmptyValue(bool $allowEmptyValue = true): self
    {
        $this->definition['allowEmptyValue'] = $allowEmptyValue;

        return $this;
    }

    /**
     * Set the header schema.
     *
     * @param array<string, mixed> $schema Schema definition
     */
    public function schema(array $schema): self
    {
        $this->definition['schema'] = $schema;

        return $this;
    }

    /**
     * Set schema type to string.
     *
     * @param string|null $format Optional format (e.g., 'date-time', 'uuid')
     */
    public function typeString(?string $format = null): self
    {
        $this->definition['schema'] = ['type' => 'string'];
        if (null !== $format) {
            $this->definition['schema']['format'] = $format;
        }

        return $this;
    }

    /**
     * Set schema type to integer.
     *
     * @param string|null $format Optional format ('int32' or 'int64')
     */
    public function typeInteger(?string $format = null): self
    {
        $this->definition['schema'] = ['type' => 'integer'];
        if (null !== $format) {
            $this->definition['schema']['format'] = $format;
        }

        return $this;
    }

    /**
     * Set schema type to number.
     *
     * @param string|null $format Optional format ('float' or 'double')
     */
    public function typeNumber(?string $format = null): self
    {
        $this->definition['schema'] = ['type' => 'number'];
        if (null !== $format) {
            $this->definition['schema']['format'] = $format;
        }

        return $this;
    }

    /**
     * Set schema type to boolean.
     */
    public function typeBoolean(): self
    {
        $this->definition['schema'] = ['type' => 'boolean'];

        return $this;
    }

    /**
     * Set schema type to array.
     *
     * @param array<string, mixed> $items Items schema
     */
    public function typeArray(array $items): self
    {
        $this->definition['schema'] = [
            'type' => 'array',
            'items' => $items,
        ];

        return $this;
    }

    /**
     * Set an example value for the header.
     *
     * @param mixed $example Example value
     */
    public function example($example): self
    {
        $this->definition['example'] = $example;

        return $this;
    }

    /**
     * Set enum values for the header.
     *
     * @param array<mixed> $values Allowed values
     */
    public function enum(array $values): self
    {
        if (!isset($this->definition['schema'])) {
            $this->definition['schema'] = [];
        }
        $this->definition['schema']['enum'] = $values;

        return $this;
    }

    /**
     * Set a default value for the header.
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
     * Set minimum value for numeric headers.
     *
     * @param int|float $min Minimum value
     */
    public function minimum($min): self
    {
        if (!isset($this->definition['schema'])) {
            $this->definition['schema'] = [];
        }
        $this->definition['schema']['minimum'] = $min;

        return $this;
    }

    /**
     * Set maximum value for numeric headers.
     *
     * @param int|float $max Maximum value
     */
    public function maximum($max): self
    {
        if (!isset($this->definition['schema'])) {
            $this->definition['schema'] = [];
        }
        $this->definition['schema']['maximum'] = $max;

        return $this;
    }

    /**
     * Set pattern (regex) for string headers.
     *
     * @param string $pattern Regular expression pattern
     */
    public function pattern(string $pattern): self
    {
        if (!isset($this->definition['schema'])) {
            $this->definition['schema'] = [];
        }
        $this->definition['schema']['pattern'] = $pattern;

        return $this;
    }

    /**
     * Finish building this header and return to the response builder.
     */
    public function end(): ResponseBuilder
    {
        return $this->responseBuilder;
    }

    /**
     * Get the header name.
     *
     * @internal
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Build the header definition as an array.
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
