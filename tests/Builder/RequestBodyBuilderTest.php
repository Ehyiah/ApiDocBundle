<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\RequestBodyBuilder;
use Ehyiah\ApiDocBundle\Builder\RouteBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\RequestBodyBuilder
 */
final class RequestBodyBuilderTest extends TestCase
{
    public function testBasicBodyBuilding(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder
            ->description('User data')
            ->required()
            ->buildArray()
        ;

        $this->assertSame([
            'description' => 'User data',
            'required' => true,
        ], $result);
    }

    public function testBodyNotRequiredByDefault(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder
            ->description('Optional data')
            ->buildArray()
        ;

        $this->assertSame([
            'description' => 'Optional data',
        ], $result);
    }

    public function testJsonContent(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder
            ->description('JSON payload')
            ->jsonContent()
                ->schema()
                    ->type('object')
                    ->addProperty('name')
                        ->type('string')
                    ->end()
                ->end()
            ->end()
            ->buildArray()
        ;

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('application/json', $result['content']);
        $this->assertArrayHasKey('schema', $result['content']['application/json']);
        $this->assertSame('object', $result['content']['application/json']['schema']['type']);
    }

    public function testCustomMediaType(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder
            ->description('XML payload')
            ->content('application/xml')
                ->schema()
                    ->type('object')
                ->end()
            ->end()
            ->buildArray()
        ;

        $this->assertArrayHasKey('application/xml', $result['content']);
    }

    public function testEndReturnsRouteBuilderWhenInline(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder->end();

        $this->assertSame($routeBuilder, $result);
    }

    public function testEmptyBody(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new RequestBodyBuilder($routeBuilder);

        $result = $builder->buildArray();

        $this->assertSame([], $result);
    }

    public function testRegistersAsComponentOnApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new RequestBodyBuilder($apiDocBuilder, 'CreateUser');

        $builder
            ->description('User creation payload')
            ->required()
            ->jsonContent()
                ->schema()
                    ->type('object')
                ->end()
            ->end()
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('requestBodies', $spec['components']);
        $this->assertArrayHasKey('CreateUser', $spec['components']['requestBodies']);
        $this->assertSame('User creation payload', $spec['components']['requestBodies']['CreateUser']['description']);
    }

    public function testComponentAppearsInBuildViaApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();

        $apiDocBuilder
            ->addRequestBody('CreateUser')
                ->description('User creation')
                ->required()
                ->jsonContent()
                    ->ref('#/components/schemas/User')
                ->end()
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('requestBodies', $spec['components']);
        $this->assertArrayHasKey('CreateUser', $spec['components']['requestBodies']);
        $this->assertTrue($spec['components']['requestBodies']['CreateUser']['required']);
    }

    public function testComponentWithoutNameDoesNotRegister(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new RequestBodyBuilder($apiDocBuilder);

        $builder
            ->description('Anonymous body')
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayNotHasKey('components', $spec);
    }
}
