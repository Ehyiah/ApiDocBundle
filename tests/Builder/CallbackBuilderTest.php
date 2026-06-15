<?php

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Builder\CallbackBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\CallbackBuilder
 */
final class CallbackBuilderTest extends TestCase
{
    public function testBasicCallbackBuilding(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'OnOrderCreated');

        $result = $callbackBuilder
            ->pathItem('{$request.body#/callbackUrl}', [
                'post' => [
                    'operationId' => 'handleOrderCallback',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/Order'],
                            ],
                        ],
                    ],
                ],
            ])
            ->buildArray()
        ;

        $this->assertArrayHasKey('{$request.body#/callbackUrl}', $result);
        $this->assertArrayHasKey('post', $result['{$request.body#/callbackUrl}']);
        $this->assertSame('handleOrderCallback', $result['{$request.body#/callbackUrl}']['post']['operationId']);
    }

    public function testCallbackWithMultiplePathItems(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'MultiCallback');

        $result = $callbackBuilder
            ->pathItem('{$request.body#/webhookUrl}', [
                'post' => ['operationId' => 'handleWebhook'],
            ])
            ->pathItem('{$request.body#/alternateUrl}', [
                'put' => ['operationId' => 'updateWebhook'],
            ])
            ->buildArray()
        ;

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('{$request.body#/webhookUrl}', $result);
        $this->assertArrayHasKey('{$request.body#/alternateUrl}', $result);
    }

    public function testGetName(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'MyCallback');

        $this->assertSame('MyCallback', $callbackBuilder->getName());
    }

    public function testEndReturnsParentBuilder(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'test');

        $result = $callbackBuilder->end();

        $this->assertSame($builder, $result);
    }

    public function testEmptyCallback(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'empty');

        $result = $callbackBuilder->buildArray();

        $this->assertSame([], $result);
    }

    public function testCallbackRegistersOnEnd(): void
    {
        $builder = new ApiDocBuilder();
        $callbackBuilder = new CallbackBuilder($builder, 'OnOrderCreated');

        $callbackBuilder
            ->pathItem('{$request.body#/callbackUrl}', [
                'post' => ['operationId' => 'handleCallback'],
            ])
            ->end()
        ;

        $spec = $builder->build();

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('callbacks', $spec['components']);
        $this->assertArrayHasKey('OnOrderCreated', $spec['components']['callbacks']);
    }
}
