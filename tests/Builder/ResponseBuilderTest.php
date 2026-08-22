<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\ResponseBuilder;
use Ehyiah\ApiDocBundle\Builder\RouteBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\ResponseBuilder
 */
final class ResponseBuilderTest extends TestCase
{
    public function testBasicResponseBuilding(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder
            ->description('Successful response')
            ->buildArray()
        ;

        $this->assertSame([
            'description' => 'Successful response',
        ], $result);
    }

    public function testResponseWithJsonContentRef(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder
            ->description('User found')
            ->jsonContent()
                ->ref('#/components/schemas/User')
            ->end()
            ->buildArray()
        ;

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('application/json', $result['content']);
        $this->assertSame(
            ['$ref' => '#/components/schemas/User'],
            $result['content']['application/json']['schema'],
        );
    }

    public function testResponseWithCustomMediaType(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder
            ->description('XML response')
            ->content('application/xml')
                ->schema()
                    ->type('object')
                ->end()
            ->end()
            ->buildArray()
        ;

        $this->assertArrayHasKey('application/xml', $result['content']);
    }

    public function testResponseWithHeaders(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder
            ->description('Paginated response')
            ->header('X-Total-Count')
                ->description('Total items')
                ->typeInteger('int64')
                ->example(100)
            ->end()
            ->header('X-Page')
                ->typeInteger('int32')
            ->end()
            ->buildArray()
        ;

        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('X-Total-Count', $result['headers']);
        $this->assertArrayHasKey('X-Page', $result['headers']);
        $this->assertSame('Total items', $result['headers']['X-Total-Count']['description']);
    }

    public function testResponseWithLink(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 201);

        $result = $builder
            ->description('Created')
            ->link('self', [
                'operationId' => 'getResource',
                'parameters' => ['id' => '$response.body#/id'],
            ])
            ->buildArray()
        ;

        $this->assertArrayHasKey('links', $result);
        $this->assertArrayHasKey('self', $result['links']);
        $this->assertSame('getResource', $result['links']['self']['operationId']);
    }

    public function testStatusCode(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 404);

        $this->assertSame(404, $builder->getStatusCode());
    }

    public function testEndReturnsRouteBuilderWhenInline(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder->end();

        $this->assertSame($routeBuilder, $result);
    }

    public function testEmptyResponse(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 204);

        $result = $builder->buildArray();

        $this->assertSame([], $result);
    }

    public function testHeaderArray(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $builder = new ResponseBuilder($routeBuilder, 200);

        $result = $builder
            ->description('With custom header')
            ->headerArray('X-Custom', [
                'schema' => ['type' => 'string'],
                'description' => 'A custom header',
            ])
            ->buildArray()
        ;

        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('X-Custom', $result['headers']);
        $this->assertSame('A custom header', $result['headers']['X-Custom']['description']);
    }

    public function testRegistersAsComponentOnApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new ResponseBuilder($apiDocBuilder, null, 'NotFound');

        $builder
            ->description('Resource not found')
            ->jsonContent()
                ->ref('#/components/schemas/Error')
            ->end()
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('responses', $spec['components']);
        $this->assertArrayHasKey('NotFound', $spec['components']['responses']);
        $this->assertSame('Resource not found', $spec['components']['responses']['NotFound']['description']);
    }

    public function testComponentAppearsInBuildViaApiDocBuilder(): void
    {
        $apiDocBuilder = new ApiDocBuilder();

        $apiDocBuilder
            ->addResponse('Unauthorized')
                ->description('Authentication required')
                ->jsonContent()
                    ->ref('#/components/schemas/Error')
                ->end()
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayHasKey('responses', $spec['components']);
        $this->assertArrayHasKey('Unauthorized', $spec['components']['responses']);
        $this->assertArrayHasKey('content', $spec['components']['responses']['Unauthorized']);
    }

    public function testComponentWithoutNameDoesNotRegister(): void
    {
        $apiDocBuilder = new ApiDocBuilder();
        $builder = new ResponseBuilder($apiDocBuilder, 200);

        $builder
            ->description('Orphan response')
            ->end()
        ;

        $spec = $apiDocBuilder->build();

        $this->assertArrayNotHasKey('components', $spec);
    }
}
