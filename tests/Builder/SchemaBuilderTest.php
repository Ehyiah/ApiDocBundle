<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\SchemaBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\SchemaBuilder
 */
final class SchemaBuilderTest extends TestCase
{
    public function testDeprecated(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('string')
            ->deprecated()
            ->buildSchemaArray()
        ;

        $this->assertTrue($result['deprecated']);
    }

    public function testDeprecatedFalse(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('string')
            ->deprecated(false)
            ->buildSchemaArray()
        ;

        $this->assertFalse($result['deprecated']);
    }

    public function testTitle(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->title('User')
            ->buildSchemaArray()
        ;

        $this->assertSame('User', $result['title']);
    }

    public function testExternalDocs(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->externalDocs('https://example.com/docs', 'External docs')
            ->buildSchemaArray()
        ;

        $this->assertSame([
            'url' => 'https://example.com/docs',
            'description' => 'External docs',
        ], $result['externalDocs']);
    }

    public function testExternalDocsWithoutDescription(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->externalDocs('https://example.com/docs')
            ->buildSchemaArray()
        ;

        $this->assertSame([
            'url' => 'https://example.com/docs',
        ], $result['externalDocs']);
    }

    public function testConstValue(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('string')
            ->constValue('FIXED')
            ->buildSchemaArray()
        ;

        $this->assertSame('FIXED', $result['const']);
    }

    public function testAllOf(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->allOf([
                ['$ref' => '#/components/schemas/Cat'],
                ['$ref' => '#/components/schemas/Dog'],
            ])
            ->buildSchemaArray()
        ;

        $this->assertCount(2, $result['allOf']);
    }

    public function testAnyOf(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->anyOf([
                ['type' => 'string'],
                ['type' => 'integer'],
            ])
            ->buildSchemaArray()
        ;

        $this->assertCount(2, $result['anyOf']);
    }

    public function testOneOf(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->oneOf([
                ['type' => 'string'],
                ['type' => 'boolean'],
            ])
            ->buildSchemaArray()
        ;

        $this->assertCount(2, $result['oneOf']);
    }

    public function testNot(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->not(['type' => 'string'])
            ->buildSchemaArray()
        ;

        $this->assertSame(['type' => 'string'], $result['not']);
    }

    public function testAdditionalPropertiesFalse(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->additionalProperties(false)
            ->buildSchemaArray()
        ;

        $this->assertFalse($result['additionalProperties']);
    }

    public function testAdditionalPropertiesSchema(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->additionalProperties(['type' => 'string'])
            ->buildSchemaArray()
        ;

        $this->assertSame(['type' => 'string'], $result['additionalProperties']);
    }

    public function testMinProperties(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->minProperties(1)
            ->buildSchemaArray()
        ;

        $this->assertSame(1, $result['minProperties']);
    }

    public function testMaxProperties(): void
    {
        $builder = new SchemaBuilder();
        $result = $builder
            ->type('object')
            ->maxProperties(10)
            ->buildSchemaArray()
        ;

        $this->assertSame(10, $result['maxProperties']);
    }
}
