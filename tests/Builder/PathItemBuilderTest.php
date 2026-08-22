<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\PathItemBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\PathItemBuilder
 */
final class PathItemBuilderTest extends TestCase
{
    public function testBasicPathItemBuilding(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'UserOperations');

        $result = $pathItemBuilder
            ->summary('User operations')
            ->description('Operations related to users')
            ->buildArray()
        ;

        $this->assertSame([
            'summary' => 'User operations',
            'description' => 'Operations related to users',
        ], $result);
    }

    public function testPathItemWithRef(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'ExternalPath');

        $result = $pathItemBuilder
            ->ref('https://example.com/pathItem.json')
            ->buildArray()
        ;

        $this->assertSame([
            '$ref' => 'https://example.com/pathItem.json',
        ], $result);
    }

    public function testPathItemWithOperations(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'UserOps');

        $result = $pathItemBuilder
            ->get(['operationId' => 'getUser', 'summary' => 'Get user'])
            ->post(['operationId' => 'createUser', 'summary' => 'Create user'])
            ->delete(['operationId' => 'deleteUser', 'summary' => 'Delete user'])
            ->buildArray()
        ;

        $this->assertArrayHasKey('get', $result);
        $this->assertArrayHasKey('post', $result);
        $this->assertArrayHasKey('delete', $result);
        $this->assertSame('getUser', $result['get']['operationId']);
        $this->assertSame('createUser', $result['post']['operationId']);
        $this->assertSame('deleteUser', $result['delete']['operationId']);
    }

    public function testPathItemWithAllOperations(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'AllOps');

        $result = $pathItemBuilder
            ->get(['operationId' => 'get'])
            ->put(['operationId' => 'put'])
            ->post(['operationId' => 'post'])
            ->delete(['operationId' => 'delete'])
            ->options(['operationId' => 'options'])
            ->head(['operationId' => 'head'])
            ->patch(['operationId' => 'patch'])
            ->trace(['operationId' => 'trace'])
            ->buildArray()
        ;

        $this->assertArrayHasKey('get', $result);
        $this->assertArrayHasKey('put', $result);
        $this->assertArrayHasKey('post', $result);
        $this->assertArrayHasKey('delete', $result);
        $this->assertArrayHasKey('options', $result);
        $this->assertArrayHasKey('head', $result);
        $this->assertArrayHasKey('patch', $result);
        $this->assertArrayHasKey('trace', $result);
    }

    public function testPathItemWithParameters(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'WithParams');

        $result = $pathItemBuilder
            ->parameter(['name' => 'id', 'in' => 'path', 'required' => true])
            ->parameter(['name' => 'format', 'in' => 'query'])
            ->buildArray()
        ;

        $this->assertArrayHasKey('parameters', $result);
        $this->assertCount(2, $result['parameters']);
        $this->assertSame('id', $result['parameters'][0]['name']);
        $this->assertSame('format', $result['parameters'][1]['name']);
    }

    public function testPathItemWithServer(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'WithServer');

        $result = $pathItemBuilder
            ->server('https://api.example.com', 'Production')
            ->server('https://staging.example.com', 'Staging')
            ->buildArray()
        ;

        $this->assertArrayHasKey('servers', $result);
        $this->assertCount(2, $result['servers']);
        $this->assertSame('https://api.example.com', $result['servers'][0]['url']);
        $this->assertSame('Production', $result['servers'][0]['description']);
    }

    public function testGetName(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'MyPathItem');

        $this->assertSame('MyPathItem', $pathItemBuilder->getName());
    }

    public function testEndReturnsParentBuilder(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'test');

        $result = $pathItemBuilder->end();

        $this->assertSame($builder, $result);
    }

    public function testEmptyPathItem(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'empty');

        $result = $pathItemBuilder->buildArray();

        $this->assertSame([], $result);
    }

    public function testPathItemRegistersOnEnd(): void
    {
        $builder = new ApiDocBuilder();
        $pathItemBuilder = new PathItemBuilder($builder, 'UserOps');

        $pathItemBuilder
            ->summary('User operations')
            ->get(['operationId' => 'getUser'])
            ->end()
        ;

        $spec = $builder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('pathItems', $spec['components']);
        $this->assertArrayHasKey('UserOps', $spec['components']['pathItems']);
        $this->assertSame('User operations', $spec['components']['pathItems']['UserOps']['summary']);
    }
}
