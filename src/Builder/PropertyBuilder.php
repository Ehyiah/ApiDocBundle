<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining schema properties with IDE autocompletion support.
 */
class PropertyBuilder
{
    private SchemaBuilder $schemaBuilder;

    private string $propertyName;

    /** @var array<string, mixed> */
    private array $definition = [];

    public function __construct(SchemaBuilder $schemaBuilder, string $propertyName)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->propertyName = $propertyName;
    }

    /**
     * Set the property type.
     *
     * OpenAPI types:
     * - 'string': Text data
     * - 'integer': Whole numbers (use format 'int32' or 'int64' for precision)
     * - 'number': Floating-point numbers (use format 'float' or 'double' for precision)
     * - 'boolean': true/false values
     * - 'array': List of items (define items schema with items() method)
     * - 'object': Key-value structure (define properties with addProperty() or property())
     *
     * @param string $type Type: 'string', 'integer', 'number', 'boolean', 'array', 'object'
     */
    public function type(string $type): self
    {
        $this->definition['type'] = $type;

        return $this;
    }

    /**
     * Set the property as a string enum type.
     * Shortcut for ->type('string')->enum($values).
     *
     * @param array<string> $values Allowed enum values
     */
    public function typeStringEnum(array $values): self
    {
        $this->definition['type'] = 'string';
        $this->definition['enum'] = $values;

        return $this;
    }

    /**
     * Set the property as an integer enum type.
     * Shortcut for ->type('integer')->enum($values).
     *
     * @param array<int> $values Allowed enum values
     */
    public function typeIntegerEnum(array $values): self
    {
        $this->definition['type'] = 'integer';
        $this->definition['enum'] = $values;

        return $this;
    }

    /**
     * Set the property as a number enum type.
     * Shortcut for ->type('number')->enum($values).
     *
     * @param array<int|float> $values Allowed enum values
     */
    public function typeNumberEnum(array $values): self
    {
        $this->definition['type'] = 'number';
        $this->definition['enum'] = $values;

        return $this;
    }

    /**
     * Set the property as an array of string enum values.
     * Shortcut for ->type('array')->items(['type' => 'string', 'enum' => $values]).
     *
     * @param array<string> $values Allowed enum values for each item
     */
    public function typeArrayOfStringEnum(array $values): self
    {
        $this->definition['type'] = 'array';
        $this->definition['items'] = [
            'type' => 'string',
            'enum' => $values,
        ];

        return $this;
    }

    /**
     * Set the property as an array of integer enum values.
     * Shortcut for ->type('array')->items(['type' => 'integer', 'enum' => $values]).
     *
     * @param array<int> $values Allowed enum values for each item
     */
    public function typeArrayOfIntegerEnum(array $values): self
    {
        $this->definition['type'] = 'array';
        $this->definition['items'] = [
            'type' => 'integer',
            'enum' => $values,
        ];

        return $this;
    }

    /**
     * Set the property as an array of number enum values.
     * Shortcut for ->type('array')->items(['type' => 'number', 'enum' => $values]).
     *
     * @param array<int|float> $values Allowed enum values for each item
     */
    public function typeArrayOfNumberEnum(array $values): self
    {
        $this->definition['type'] = 'array';
        $this->definition['items'] = [
            'type' => 'number',
            'enum' => $values,
        ];

        return $this;
    }

    /**
     * Set the property description.
     *
     * @param string $description Property description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Set the format for this property.
     *
     * Common formats by type:
     * - string: 'date', 'date-time', 'password', 'byte', 'binary', 'email', 'uuid', 'uri', 'hostname', 'ipv4', 'ipv6'
     * - integer: 'int32', 'int64'
     * - number: 'float', 'double'
     *
     * @param string $format Format: 'date', 'date-time', 'password', 'byte', 'binary', 'email', 'uuid', 'uri', 'hostname', 'ipv4', 'ipv6', 'int32', 'int64', 'float', 'double'
     */
    public function format(string $format): self
    {
        $this->definition['format'] = $format;

        return $this;
    }

    /**
     * Mark property as nullable.
     *
     * @param bool $nullable Whether the property is nullable
     */
    public function nullable(bool $nullable = true): self
    {
        $this->definition['nullable'] = $nullable;

        return $this;
    }

    /**
     * Set an example value.
     *
     * @param mixed $example Example value
     */
    public function example($example): self
    {
        $this->definition['example'] = $example;

        return $this;
    }

    /**
     * Set a default value.
     *
     * @param mixed $default Default value
     */
    public function defaultValue($default): self
    {
        $this->definition['default'] = $default;

        return $this;
    }

    /**
     * Set enum values.
     *
     * @param array<mixed> $values Allowed values
     */
    public function enum(array $values): self
    {
        $this->definition['enum'] = $values;

        return $this;
    }

    /**
     * Set minimum value for numeric types.
     *
     * @param int|float $min Minimum value
     */
    public function minimum($min): self
    {
        $this->definition['minimum'] = $min;

        return $this;
    }

    /**
     * Set maximum value for numeric types.
     *
     * @param int|float $max Maximum value
     */
    public function maximum($max): self
    {
        $this->definition['maximum'] = $max;

        return $this;
    }

    /**
     * Set exclusive minimum value for numeric types.
     *
     * @param int|float $min Exclusive minimum value
     */
    public function exclusiveMinimum($min): self
    {
        $this->definition['exclusiveMinimum'] = $min;

        return $this;
    }

    /**
     * Set exclusive maximum value for numeric types.
     *
     * @param int|float $max Exclusive maximum value
     */
    public function exclusiveMaximum($max): self
    {
        $this->definition['exclusiveMaximum'] = $max;

        return $this;
    }

    /**
     * Set multiple of constraint for numeric types.
     *
     * @param int|float $multipleOf Value must be a multiple of this number
     */
    public function multipleOf($multipleOf): self
    {
        $this->definition['multipleOf'] = $multipleOf;

        return $this;
    }

    /**
     * Set minimum length for string types.
     *
     * @param int $minLength Minimum length
     */
    public function minLength(int $minLength): self
    {
        $this->definition['minLength'] = $minLength;

        return $this;
    }

    /**
     * Set maximum length for string types.
     *
     * @param int $maxLength Maximum length
     */
    public function maxLength(int $maxLength): self
    {
        $this->definition['maxLength'] = $maxLength;

        return $this;
    }

    /**
     * Set pattern (regex) for string types.
     *
     * @param string $pattern Regular expression pattern
     */
    public function pattern(string $pattern): self
    {
        $this->definition['pattern'] = $pattern;

        return $this;
    }

    /**
     * Set the items schema for array type.
     *
     * @param array<string, mixed> $items Items schema definition
     */
    public function items(array $items): self
    {
        $this->definition['items'] = $items;

        return $this;
    }

    /**
     * Set minimum items for array types.
     *
     * @param int $minItems Minimum number of items
     */
    public function minItems(int $minItems): self
    {
        $this->definition['minItems'] = $minItems;

        return $this;
    }

    /**
     * Set maximum items for array types.
     *
     * @param int $maxItems Maximum number of items
     */
    public function maxItems(int $maxItems): self
    {
        $this->definition['maxItems'] = $maxItems;

        return $this;
    }

    /**
     * Set unique items constraint for array types.
     *
     * @param bool $uniqueItems Whether items must be unique
     */
    public function uniqueItems(bool $uniqueItems = true): self
    {
        $this->definition['uniqueItems'] = $uniqueItems;

        return $this;
    }

    /**
     * Set a reference to another schema.
     *
     * @param string $ref Reference path (e.g., '#/components/schemas/User')
     */
    public function ref(string $ref): self
    {
        $this->definition['$ref'] = $ref;

        return $this;
    }

    /**
     * Mark property as read-only.
     *
     * @param bool $readOnly Whether the property is read-only
     */
    public function readOnly(bool $readOnly = true): self
    {
        $this->definition['readOnly'] = $readOnly;

        return $this;
    }

    /**
     * Mark property as write-only.
     *
     * @param bool $writeOnly Whether the property is write-only
     */
    public function writeOnly(bool $writeOnly = true): self
    {
        $this->definition['writeOnly'] = $writeOnly;

        return $this;
    }

    /**
     * Mark property as deprecated.
     *
     * @param bool $deprecated Whether the property is deprecated
     */
    public function deprecated(bool $deprecated = true): self
    {
        $this->definition['deprecated'] = $deprecated;

        return $this;
    }

    /**
     * Set title for this property.
     *
     * @param string $title Title
     */
    public function title(string $title): self
    {
        $this->definition['title'] = $title;

        return $this;
    }

    /**
     * Add a custom property to the definition.
     * Use this for any OpenAPI property not covered by the builder methods.
     *
     * @param string $key Property key
     * @param mixed $value Property value
     */
    public function custom(string $key, $value): self
    {
        $this->definition[$key] = $value;

        return $this;
    }

    /**
     * Finish building this property and return to the schema builder.
     */
    public function end(): SchemaBuilder
    {
        $this->schemaBuilder->property($this->propertyName, $this->definition);

        return $this->schemaBuilder;
    }

    /**
     * Get the property name.
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * Build the property definition as an array.
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
