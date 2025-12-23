<?php

namespace Ehyiah\ApiDocBundle\Builder;

/**
 * Fluent builder for defining OpenAPI schemas.
 */
class SchemaBuilder
{
    /** @var ApiDocBuilder|ContentBuilder|ResponseBuilder|null */
    private $parentBuilder;

    private ?string $schemaName = null;

    /** @var array<string, mixed> */
    private array $definition = [];

    /** @var array<string, array<string, mixed>> */
    private array $properties = [];

    /** @var array<string> */
    private array $requiredFields = [];

    /**
     * @param string|null $schemaName Optional schema name (for component schemas)
     * @param mixed|null $parentBuilder
     */
    public function __construct($parentBuilder = null, ?string $schemaName = null)
    {
        $this->parentBuilder = $parentBuilder;
        $this->schemaName = $schemaName;
    }

    /**
     * Set the schema type.
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
     * Set a custom reference name for this schema.
     * This allows you to use a short alias to reference this schema elsewhere.
     *
     * Example:
     *   $builder->addSchema('ProductEntity')
     *       ->setRefName('Product')  // Create alias 'Product'
     *       ->type('object')
     *       ...
     *
     * Then elsewhere:
     *   ->jsonContent()->refByName('Product')->end()
     *
     * @param string $refName The custom reference name/alias
     */
    public function setRefName(string $refName): self
    {
        if ($this->parentBuilder instanceof ApiDocBuilder && null !== $this->schemaName) {
            $this->parentBuilder->registerSchemaRef($refName, $this->schemaName);
        }

        return $this;
    }

    /**
     * Set the schema description.
     *
     * @param string $description Schema description
     */
    public function description(string $description): self
    {
        $this->definition['description'] = $description;

        return $this;
    }

    /**
     * Add a property to the schema using an array definition.
     * Use this for complex cases or when you need full control over the schema.
     *
     * @param string $name Property name
     * @param array<string, mixed> $schema Property schema definition
     */
    public function property(string $name, array $schema): self
    {
        $this->properties[$name] = $schema;

        return $this;
    }

    /**
     * Add a property to the schema using a fluent builder.
     * Provides IDE autocompletion for property definition.
     *
     * Example:
     *   ->addProperty('age')
     *       ->type('integer')
     *       ->nullable()
     *       ->example(30)
     *   ->end()
     *
     * @param string $name Property name
     */
    public function addProperty(string $name): PropertyBuilder
    {
        return new PropertyBuilder($this, $name);
    }

    /**
     * Set required fields for this schema.
     *
     * @param array<string> $fields Array of required field names
     */
    public function required(array $fields): self
    {
        $this->requiredFields = array_merge($this->requiredFields, $fields);

        return $this;
    }

    /**
     * Set the format for this schema.
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
     * Set a reference to another schema.
     *
     * @param string $ref Reference path (e.g., '#/components/schemas/User')
     */
    public function ref(string $ref): self
    {
        $this->definition = ['$ref' => $ref];

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
     * Mark field as nullable.
     *
     * @param bool $nullable Whether the field is nullable
     */
    public function nullable(bool $nullable = true): self
    {
        $this->definition['nullable'] = $nullable;

        return $this;
    }

    /**
     * Mark field as read-only.
     *
     * @param bool $readOnly Whether the field is read-only
     */
    public function readOnly(bool $readOnly = true): self
    {
        $this->definition['readOnly'] = $readOnly;

        return $this;
    }

    /**
     * Mark field as write-only.
     *
     * @param bool $writeOnly Whether the field is write-only
     */
    public function writeOnly(bool $writeOnly = true): self
    {
        $this->definition['writeOnly'] = $writeOnly;

        return $this;
    }

    /**
     * Finish building this schema and return to the parent builder.
     */
    public function end(): ApiDocBuilder|ContentBuilder|ResponseBuilder|null
    {
        if ($this->parentBuilder instanceof ApiDocBuilder && null !== $this->schemaName) {
            $this->parentBuilder->registerSchema($this->schemaName, $this->buildSchemaArray());
        }

        return $this->parentBuilder;
    }

    /**
     * Build the schema definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildSchemaArray(): array
    {
        $schema = $this->definition;

        // Add properties if defined
        if (!empty($this->properties)) {
            $schema['properties'] = $this->properties;
        }

        // Add required fields if defined
        if (!empty($this->requiredFields)) {
            $schema['required'] = array_unique($this->requiredFields);
        }

        return $schema;
    }
}
