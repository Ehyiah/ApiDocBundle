<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\ParameterBuilder;
use Ehyiah\ApiDocBundle\Builder\RouteBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\ParameterBuilder
 */
final class ParameterBuilderTest extends TestCase
{
    public function testBasicParameterBuilding(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder
            ->name('page')
            ->in('query')
            ->description('Page number')
            ->required()
            ->schema(['type' => 'integer', 'minimum' => 1])
            ->buildArray()
        ;

        $this->assertSame([
            'name' => 'page',
            'in' => 'query',
            'description' => 'Page number',
            'required' => true,
            'schema' => ['type' => 'integer', 'minimum' => 1],
        ], $result);
    }

    public function testParameterWithAllOptions(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder
            ->name('filter')
            ->in('query')
            ->description('Filter results')
            ->required()
            ->style('form')
            ->explode()
            ->deprecated()
            ->allowEmptyValue()
            ->allowReserved()
            ->schema(['type' => 'string'])
            ->buildArray()
        ;

        $this->assertSame('filter', $result['name']);
        $this->assertSame('query', $result['in']);
        $this->assertSame('form', $result['style']);
        $this->assertTrue($result['explode']);
        $this->assertTrue($result['deprecated']);
        $this->assertTrue($result['allowEmptyValue']);
        $this->assertTrue($result['allowReserved']);
    }

    public function testParameterWithExample(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder
            ->name('id')
            ->in('path')
            ->required()
            ->schema(['type' => 'integer'])
            ->example(42)
            ->buildArray()
        ;

        $this->assertSame(42, $result['example']);
    }

    public function testParameterWithNamedExamples(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $builder
            ->name('id')
            ->in('path')
            ->required()
            ->schema(['type' => 'integer'])
            ->addExample('default')
                ->summary('Default user ID')
                ->value(1)
            ->end()
            ->addExample('admin')
                ->summary('Admin user ID')
                ->value(999)
            ->end()
        ;

        $result = $builder->buildArray();

        $this->assertArrayHasKey('examples', $result);
        $this->assertCount(2, $result['examples']);
        $this->assertSame(1, $result['examples']['default']['value']);
        $this->assertSame(999, $result['examples']['admin']['value']);
    }

    public function testDefaultValue(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder
            ->name('sort')
            ->in('query')
            ->schema(['type' => 'string'])
            ->defaultValue('asc')
            ->buildArray()
        ;

        $this->assertSame('asc', $result['schema']['default']);
    }

    public function testEndReturnsRouteBuilderWhenInline(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder->end();

        $this->assertSame($routeBuilder, $result);
    }

    public function testEmptyParameter(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ParameterBuilder($routeBuilder);

        $result = $builder->buildArray();

        $this->assertSame([], $result);
    }

    public function testRegistersAsComponentOnApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new ParameterBuilder($apiDocBuilder, 'PaginationPage');

        $builder
            ->name('page')
            ->in('query')
            ->description('Page number')
            ->required()
            ->schema(['type' => 'integer', 'minimum' => 1])
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('parameters', $spec['components']);
        $this->assertArrayHasKey('PaginationPage', $spec['components']['parameters']);
        $this->assertSame('page', $spec['components']['parameters']['PaginationPage']['name']);
        $this->assertSame('query', $spec['components']['parameters']['PaginationPage']['in']);
    }

    public function testComponentAppearsInBuildViaApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();

        $apiDocBuilder
            ->addParameter('UserId')
                ->name('id')
                ->in('path')
                ->required()
                ->schema(['type' => 'integer'])
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('parameters', $spec['components']);
        $this->assertArrayHasKey('UserId', $spec['components']['parameters']);
    }

    public function testComponentWithoutNameDoesNotRegister(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new ParameterBuilder($apiDocBuilder);

        $builder
            ->name('orphan')
            ->in('query')
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayNotHasKey('components', $spec);
    }
}
