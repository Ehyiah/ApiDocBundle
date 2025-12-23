<?php

namespace Ehyiah\ApiDocBundle\Builder;

use InvalidArgumentException;
use LogicException;

/**
 * Fluent builder for defining content/media types in requests and responses.
 */
class ContentBuilder
{
    /** @var RequestBodyBuilder|ResponseBuilder */
    private $parentBuilder;

    private string $mediaType;

    /** @var array<string, mixed> */
    private array $definition = [];

    private ?SchemaBuilder $schemaBuilder = null;

    private ?ApiDocBuilder $apiDocBuilder = null;

    /**
     * @param RequestBodyBuilder|ResponseBuilder $parentBuilder
     */
    public function __construct($parentBuilder, string $mediaType, ?ApiDocBuilder $apiDocBuilder = null)
    {
        $this->parentBuilder = $parentBuilder;
        $this->mediaType = $mediaType;
        $this->apiDocBuilder = $apiDocBuilder;
    }

    /**
     * Set a reference to a schema component.
     *
     * @param string $ref Reference path (e.g., '#/components/schemas/User')
     */
    public function ref(string $ref): self
    {
        $this->definition['schema'] = ['$ref' => $ref];

        return $this;
    }

    /**
     * Set a reference to a schema component using a custom reference name.
     * The reference name must have been registered with setRefName() on the schema.
     *
     * Example:
     *   // First, define schema with custom ref name:
     *   $builder->addSchema('ProductEntity')
     *       ->setRefName('Product')
     *       ->type('object')
     *       ...
     *
     *   // Then, reference it by the custom name:
     *   ->jsonContent()->refByName('Product')->end()
     *
     * @param string $refName The custom reference name
     *
     * @throws InvalidArgumentException If the reference name is not registered
     */
    public function refByName(string $refName): self
    {
        if (null === $this->apiDocBuilder) {
            throw new LogicException('Cannot use refByName() without ApiDocBuilder reference');
        }

        $ref = $this->apiDocBuilder->getSchemaRef($refName);
        $this->definition['schema'] = ['$ref' => $ref];

        return $this;
    }

    /**
     * Set a reference to a schema component using a PHP class name.
     * Automatically converts the class name to a schema reference.
     *
     * @param string $className Full class name (e.g., App\Entity\User::class)
     */
    public function refClass(string $className): self
    {
        $schemaName = $this->getSchemaNameFromClass($className);
        $this->definition['schema'] = ['$ref' => '#/components/schemas/' . $schemaName];

        return $this;
    }

    /**
     * Extract schema name from class name.
     * App\Entity\User -> User
     * App\DTO\UserDTO -> UserDTO
     */
    private function getSchemaNameFromClass(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }

    /**
     * Start building an inline schema.
     */
    public function schema(): SchemaBuilder
    {
        $this->schemaBuilder = new SchemaBuilder($this);

        return $this->schemaBuilder;
    }

    /**
     * Set an example for this content.
     *
     * @param mixed $example Example value
     */
    public function example($example): self
    {
        $this->definition['example'] = $example;

        return $this;
    }

    /**
     * Finish building this content and return to the parent builder.
     */
    public function end(): RequestBodyBuilder|ResponseBuilder
    {
        return $this->parentBuilder;
    }

    /**
     * Get the media type for this content.
     *
     * @internal
     */
    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    /**
     * Build the content definition as an array.
     *
     * @return array<string, mixed>
     *
     * @internal
     */
    public function buildArray(): array
    {
        // Build inline schema if present
        if (null !== $this->schemaBuilder) {
            $this->definition['schema'] = $this->schemaBuilder->buildSchemaArray();
        }

        return $this->definition;
    }
}
