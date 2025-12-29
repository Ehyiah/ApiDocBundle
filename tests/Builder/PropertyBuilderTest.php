<?php

declare(strict_types=1);

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\PropertyBuilder;
use Ehyiah\ApiDocBundle\Builder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\PropertyBuilder
 */
final class PropertyBuilderTest extends TestCase
{
    public function testBasicPropertyBuilding(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'age');

        $result = $propertyBuilder
            ->type('integer')
            ->nullable()
            ->example(30)
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'integer',
            'nullable' => true,
            'example' => 30,
        ], $result);
    }

    public function testStringPropertyWithConstraints(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'email');

        $result = $propertyBuilder
            ->type('string')
            ->format('email')
            ->description('User email address')
            ->minLength(5)
            ->maxLength(255)
            ->example('user@example.com')
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'format' => 'email',
            'description' => 'User email address',
            'minLength' => 5,
            'maxLength' => 255,
            'example' => 'user@example.com',
        ], $result);
    }

    public function testNumericPropertyWithConstraints(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'price');

        $result = $propertyBuilder
            ->type('number')
            ->format('float')
            ->minimum(0)
            ->maximum(9999.99)
            ->exclusiveMinimum(0)
            ->multipleOf(0.01)
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'number',
            'format' => 'float',
            'minimum' => 0,
            'maximum' => 9999.99,
            'exclusiveMinimum' => 0,
            'multipleOf' => 0.01,
        ], $result);
    }

    public function testEnumProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'status');

        $result = $propertyBuilder
            ->type('string')
            ->enum(['pending', 'active', 'inactive'])
            ->defaultValue('pending')
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'enum' => ['pending', 'active', 'inactive'],
            'default' => 'pending',
        ], $result);
    }

    public function testTypeEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'status');

        $result = $propertyBuilder
            ->typeStringEnum(['pending', 'active', 'inactive'])
            ->defaultValue('pending')
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'enum' => ['pending', 'active', 'inactive'],
            'default' => 'pending',
        ], $result);
    }

    public function testTypeIntegerEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'priority');

        $result = $propertyBuilder
            ->typeIntegerEnum([1, 2, 3, 4, 5])
            ->defaultValue(3)
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'integer',
            'enum' => [1, 2, 3, 4, 5],
            'default' => 3,
        ], $result);
    }

    public function testTypeNumberEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'discount');

        $result = $propertyBuilder
            ->typeNumberEnum([0.1, 0.25, 0.5, 0.75])
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'number',
            'enum' => [0.1, 0.25, 0.5, 0.75],
        ], $result);
    }

    public function testTypeArrayOfStringEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'greetings');

        $result = $propertyBuilder
            ->typeArrayOfStringEnum(['coucou', 'hello', 'bonjour'])
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'array',
            'items' => [
                'type' => 'string',
                'enum' => ['coucou', 'hello', 'bonjour'],
            ],
        ], $result);
    }

    public function testTypeArrayOfIntegerEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'priorities');

        $result = $propertyBuilder
            ->typeArrayOfIntegerEnum([1, 2, 3])
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'array',
            'items' => [
                'type' => 'integer',
                'enum' => [1, 2, 3],
            ],
        ], $result);
    }

    public function testTypeArrayOfNumberEnumShortcut(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'discounts');

        $result = $propertyBuilder
            ->typeArrayOfNumberEnum([0.1, 0.25, 0.5])
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'array',
            'items' => [
                'type' => 'number',
                'enum' => [0.1, 0.25, 0.5],
            ],
        ], $result);
    }

    public function testArrayProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'tags');

        $result = $propertyBuilder
            ->type('array')
            ->items(['type' => 'string'])
            ->minItems(1)
            ->maxItems(10)
            ->uniqueItems()
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'array',
            'items' => ['type' => 'string'],
            'minItems' => 1,
            'maxItems' => 10,
            'uniqueItems' => true,
        ], $result);
    }

    public function testReferenceProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'user');

        $result = $propertyBuilder
            ->ref('#/components/schemas/User')
            ->buildArray()
        ;

        $this->assertSame([
            '$ref' => '#/components/schemas/User',
        ], $result);
    }

    public function testPropertyWithAccessModifiers(): void
    {
        $schemaBuilder = new SchemaBuilder();

        $readOnlyProperty = (new PropertyBuilder($schemaBuilder, 'id'))
            ->type('integer')
            ->readOnly()
            ->buildArray()
        ;

        $writeOnlyProperty = (new PropertyBuilder($schemaBuilder, 'password'))
            ->type('string')
            ->writeOnly()
            ->buildArray()
        ;

        $this->assertSame(['type' => 'integer', 'readOnly' => true], $readOnlyProperty);
        $this->assertSame(['type' => 'string', 'writeOnly' => true], $writeOnlyProperty);
    }

    public function testDeprecatedProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'oldField');

        $result = $propertyBuilder
            ->type('string')
            ->deprecated()
            ->description('This field is deprecated, use newField instead')
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'deprecated' => true,
            'description' => 'This field is deprecated, use newField instead',
        ], $result);
    }

    public function testCustomProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'custom');

        $result = $propertyBuilder
            ->type('string')
            ->custom('x-custom-extension', 'custom-value')
            ->custom('additionalProperties', false)
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'x-custom-extension' => 'custom-value',
            'additionalProperties' => false,
        ], $result);
    }

    public function testPatternProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'phone');

        $result = $propertyBuilder
            ->type('string')
            ->pattern('^\+?[0-9]{10,14}$')
            ->title('Phone Number')
            ->buildArray()
        ;

        $this->assertSame([
            'type' => 'string',
            'pattern' => '^\+?[0-9]{10,14}$',
            'title' => 'Phone Number',
        ], $result);
    }

    public function testEndReturnsSchemaBuilder(): void
    {
        $schemaBuilder = new SchemaBuilder();

        $returnedBuilder = $schemaBuilder
            ->type('object')
            ->addProperty('name')
                ->type('string')
                ->example('John')
            ->end()
        ;

        $this->assertSame($schemaBuilder, $returnedBuilder);

        $schema = $schemaBuilder->buildSchemaArray();
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertSame([
            'type' => 'string',
            'example' => 'John',
        ], $schema['properties']['name']);
    }

    public function testMultiplePropertiesWithAddProperty(): void
    {
        $schemaBuilder = new SchemaBuilder();

        $schemaBuilder
            ->type('object')
            ->addProperty('id')
                ->type('integer')
                ->readOnly()
            ->end()
            ->addProperty('name')
                ->type('string')
                ->minLength(1)
                ->maxLength(100)
            ->end()
            ->addProperty('email')
                ->type('string')
                ->format('email')
            ->end()
        ;

        $schema = $schemaBuilder->buildSchemaArray();

        $this->assertCount(3, $schema['properties']);
        $this->assertArrayHasKey('id', $schema['properties']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('email', $schema['properties']);
    }

    public function testMixedPropertyAndAddPropertyUsage(): void
    {
        $schemaBuilder = new SchemaBuilder();

        $schemaBuilder
            ->type('object')
            ->addProperty('name')
                ->type('string')
            ->end()
            ->property('customField', [
                'type' => 'object',
                'additionalProperties' => ['type' => 'string'],
            ])
            ->addProperty('age')
                ->type('integer')
            ->end()
        ;

        $schema = $schemaBuilder->buildSchemaArray();

        $this->assertCount(3, $schema['properties']);
        $this->assertSame(['type' => 'string'], $schema['properties']['name']);
        $this->assertSame([
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ], $schema['properties']['customField']);
        $this->assertSame(['type' => 'integer'], $schema['properties']['age']);
    }

    public function testGetPropertyName(): void
    {
        $schemaBuilder = new SchemaBuilder();
        $propertyBuilder = new PropertyBuilder($schemaBuilder, 'testProperty');

        $this->assertSame('testProperty', $propertyBuilder->getPropertyName());
    }
}
