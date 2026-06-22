<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\HeaderBuilder;
use Ehyiah\ApiDocBundle\Builder\ResponseBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\HeaderBuilder
 */
final class HeaderBuilderTest extends TestCase
{
    public function testBasicHeaderBuilding(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Rate-Limit');

        $result = $builder
            ->description('Rate limit')
            ->required()
            ->typeInteger('int32')
            ->buildArray()
        ;

        $this->assertSame([
            'description' => 'Rate limit',
            'required' => true,
            'schema' => ['type' => 'integer', 'format' => 'int32'],
        ], $result);
    }

    public function testHeaderWithAllSchemaTypes(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);

        $stringResult = (new HeaderBuilder($responseBuilder, 'X-ID'))
            ->typeString('uuid')
            ->buildArray()
        ;
        $this->assertSame(['type' => 'string', 'format' => 'uuid'], $stringResult['schema']);

        $intResult = (new HeaderBuilder($responseBuilder, 'X-Count'))
            ->typeInteger('int64')
            ->buildArray()
        ;
        $this->assertSame(['type' => 'integer', 'format' => 'int64'], $intResult['schema']);

        $numberResult = (new HeaderBuilder($responseBuilder, 'X-Amount'))
            ->typeNumber('float')
            ->buildArray()
        ;
        $this->assertSame(['type' => 'number', 'format' => 'float'], $numberResult['schema']);

        $boolResult = (new HeaderBuilder($responseBuilder, 'X-Flag'))
            ->typeBoolean()
            ->buildArray()
        ;
        $this->assertSame(['type' => 'boolean'], $boolResult['schema']);

        $arrayResult = (new HeaderBuilder($responseBuilder, 'X-List'))
            ->typeArray(['type' => 'string'])
            ->buildArray()
        ;
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'string']], $arrayResult['schema']);
    }

    public function testHeaderWithExamples(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Rate-Limit');

        $builder
            ->typeInteger()
            ->addExample('free')
                ->summary('Free tier')
                ->value(100)
            ->end()
            ->addExample('pro')
                ->summary('Pro tier')
                ->value(1000)
            ->end()
        ;

        $result = $builder->buildArray();

        $this->assertArrayHasKey('examples', $result);
        $this->assertCount(2, $result['examples']);
        $this->assertSame(100, $result['examples']['free']['value']);
        $this->assertSame(1000, $result['examples']['pro']['value']);
    }

    public function testHeaderWithEnumAndDefault(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Sort');

        $result = $builder
            ->typeString()
            ->enum(['asc', 'desc'])
            ->defaultValue('asc')
            ->buildArray()
        ;

        $this->assertSame(['asc', 'desc'], $result['schema']['enum']);
        $this->assertSame('asc', $result['schema']['default']);
    }

    public function testHeaderWithConstraints(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Max');

        $result = $builder
            ->typeInteger()
            ->minimum(0)
            ->maximum(100)
            ->buildArray()
        ;

        $this->assertSame(0, $result['schema']['minimum']);
        $this->assertSame(100, $result['schema']['maximum']);
    }

    public function testHeaderWithPattern(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Code');

        $result = $builder
            ->typeString()
            ->pattern('^[A-Z]{3}-[0-9]{4}$')
            ->buildArray()
        ;

        $this->assertSame('^[A-Z]{3}-[0-9]{4}$', $result['schema']['pattern']);
    }

    public function testHeaderWithStyleAndExplode(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Filter');

        $result = $builder
            ->typeArray(['type' => 'string'])
            ->style('simple')
            ->explode()
            ->buildArray()
        ;

        $this->assertSame('simple', $result['style']);
        $this->assertTrue($result['explode']);
    }

    public function testHeaderWithDeprecatedAndAllowEmpty(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Legacy');

        $result = $builder
            ->typeString()
            ->deprecated()
            ->allowEmptyValue()
            ->buildArray()
        ;

        $this->assertTrue($result['deprecated']);
        $this->assertTrue($result['allowEmptyValue']);
    }

    public function testEndReturnsResponseBuilderWhenInline(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Test');

        $result = $builder->end();

        $this->assertSame($responseBuilder, $result);
    }

    public function testGetName(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Custom');

        $this->assertSame('X-Custom', $builder->getName());
    }

    public function testEmptyHeader(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Empty');

        $result = $builder->buildArray();

        $this->assertSame([], $result);
    }

    public function testHeaderWithExampleDirectly(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $builder = new HeaderBuilder($responseBuilder, 'X-Debug');

        $result = $builder
            ->typeString()
            ->example('test-value')
            ->buildArray()
        ;

        $this->assertSame('test-value', $result['example']);
    }

    public function testRegistersAsComponentOnApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new HeaderBuilder($apiDocBuilder, 'X-Rate-Limit');

        $builder
            ->description('Rate limit per hour')
            ->typeInteger('int32')
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('headers', $spec['components']);
        $this->assertArrayHasKey('X-Rate-Limit', $spec['components']['headers']);
        $this->assertSame(
            'Rate limit per hour',
            $spec['components']['headers']['X-Rate-Limit']['description'],
        );
    }

    public function testComponentAppearsInBuildViaApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();

        $apiDocBuilder
            ->addHeader('X-Request-Id')
                ->description('Unique request identifier')
                ->typeString('uuid')
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('headers', $spec['components']);
        $this->assertArrayHasKey('X-Request-Id', $spec['components']['headers']);
        $this->assertSame('uuid', $spec['components']['headers']['X-Request-Id']['schema']['format']);
    }
}
