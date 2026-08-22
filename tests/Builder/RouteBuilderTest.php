<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\RouteBuilder
 */
final class RouteBuilderTest extends TestCase
{
    public function testOperationIdIsIncludedWhenProvided(): void
    {
        $builder = new ApiDocBuilder();

        $builder->addRoute()
            ->path('/api/users')
            ->method('GET')
            ->operationId('listUsers')
            ->end()
        ;

        $result = $builder->build();

        $this->assertSame('listUsers', $result['paths']['/api/users']['get']['operationId']);
    }

    public function testOperationIdIsIgnoredWhenEmpty(): void
    {
        $builder = new ApiDocBuilder();

        $builder->addRoute()
            ->path('/api/users')
            ->method('GET')
            ->operationId('')
            ->end()
        ;

        $result = $builder->build();

        $this->assertArrayNotHasKey('operationId', $result['paths']['/api/users']['get']);
    }

    public function testRouteWithoutOperationId(): void
    {
        $builder = new ApiDocBuilder();

        $builder->addRoute()
            ->path('/api/users')
            ->method('GET')
            ->summary('List users')
            ->end()
        ;

        $result = $builder->build();

        $this->assertArrayNotHasKey('operationId', $result['paths']['/api/users']['get']);
        $this->assertSame('List users', $result['paths']['/api/users']['get']['summary']);
    }

    public function testMultipleRoutesWithDifferentOperationIds(): void
    {
        $builder = new ApiDocBuilder();

        $builder->addRoute()
            ->path('/api/users')
            ->method('GET')
            ->operationId('listUsers')
            ->end()
        ;

        $builder->addRoute()
            ->path('/api/users')
            ->method('POST')
            ->operationId('createUser')
            ->end()
        ;

        $builder->addRoute()
            ->path('/api/users/{id}')
            ->method('GET')
            ->operationId('getUser')
            ->end()
        ;

        $result = $builder->build();

        $this->assertSame('listUsers', $result['paths']['/api/users']['get']['operationId']);
        $this->assertSame('createUser', $result['paths']['/api/users']['post']['operationId']);
        $this->assertSame('getUser', $result['paths']['/api/users/{id}']['get']['operationId']);
    }
}
