<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\LinkBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\LinkBuilder
 */
final class LinkBuilderTest extends TestCase
{
    public function testBasicLinkBuilding(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'GetUserOrders');

        $result = $linkBuilder
            ->operationRef('/users/{userId}/orders')
            ->description('Orders for this user')
            ->buildArray()
        ;

        $this->assertSame([
            'operationRef' => '/users/{userId}/orders',
            'description' => 'Orders for this user',
        ], $result);
    }

    public function testLinkWithOperationId(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'GetOrder');

        $result = $linkBuilder
            ->operationId('getOrderById')
            ->description('Get the order details')
            ->buildArray()
        ;

        $this->assertSame([
            'operationId' => 'getOrderById',
            'description' => 'Get the order details',
        ], $result);
    }

    public function testLinkWithParameters(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'GetUser');

        $result = $linkBuilder
            ->operationId('getUser')
            ->parameter('userId', '$request.body#/userId')
            ->parameter('limit', '10')
            ->buildArray()
        ;

        $this->assertSame([
            'operationId' => 'getUser',
            'parameters' => [
                'userId' => '$request.body#/userId',
                'limit' => '10',
            ],
        ], $result);
    }

    public function testLinkWithRequestBody(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'CreateOrder');

        $result = $linkBuilder
            ->operationId('createOrder')
            ->requestBody('$request.body')
            ->buildArray()
        ;

        $this->assertSame([
            'operationId' => 'createOrder',
            'requestBody' => '$request.body',
        ], $result);
    }

    public function testLinkWithServer(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'ExternalApi');

        $result = $linkBuilder
            ->operationId('externalOperation')
            ->server('https://api.external.com', 'External API')
            ->buildArray()
        ;

        $this->assertSame([
            'operationId' => 'externalOperation',
            'server' => [
                'url' => 'https://api.external.com',
                'description' => 'External API',
            ],
        ], $result);
    }

    public function testLinkWithServerWithoutDescription(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'ExternalApi');

        $result = $linkBuilder
            ->operationId('externalOperation')
            ->server('https://api.external.com')
            ->buildArray()
        ;

        $this->assertSame([
            'operationId' => 'externalOperation',
            'server' => [
                'url' => 'https://api.external.com',
            ],
        ], $result);
    }

    public function testGetName(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'MyLink');

        $this->assertSame('MyLink', $linkBuilder->getName());
    }

    public function testEndReturnsParentBuilder(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'test');

        $result = $linkBuilder->end();

        $this->assertSame($builder, $result);
    }

    public function testEmptyLink(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'empty');

        $result = $linkBuilder->buildArray();

        $this->assertSame([], $result);
    }

    public function testLinkRegistersOnEnd(): void
    {
        $builder = new ApiDocBuilder();
        $linkBuilder = new LinkBuilder($builder, 'GetUser');

        $linkBuilder
            ->operationId('getUser')
            ->description('Get user details')
            ->end()
        ;

        $spec = $builder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('links', $spec['components']);
        $this->assertArrayHasKey('GetUser', $spec['components']['links']);
        $this->assertSame('getUser', $spec['components']['links']['GetUser']['operationId']);
    }
}
