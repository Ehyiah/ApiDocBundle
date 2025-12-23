<?php

declare(strict_types=1);

namespace Ehyiah\ApiDocBundle\Tests\Builder;

use Ehyiah\ApiDocBundle\Builder\ContentBuilder;
use Ehyiah\ApiDocBundle\Builder\ExampleBuilder;
use Ehyiah\ApiDocBundle\Builder\HeaderBuilder;
use Ehyiah\ApiDocBundle\Builder\ParameterBuilder;
use Ehyiah\ApiDocBundle\Builder\ResponseBuilder;
use Ehyiah\ApiDocBundle\Builder\RouteBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Builder\ExampleBuilder
 */
final class ExampleBuilderTest extends TestCase
{
    public function testBasicExampleBuilding(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'success');

        $result = $exampleBuilder
            ->summary('Successful response')
            ->description('A complete user object with all fields')
            ->value(['id' => 1, 'name' => 'John Doe'])
            ->buildArray()
        ;

        $this->assertSame([
            'summary' => 'Successful response',
            'description' => 'A complete user object with all fields',
            'value' => ['id' => 1, 'name' => 'John Doe'],
        ], $result);
    }

    public function testExampleWithExternalValue(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'external');

        $result = $exampleBuilder
            ->summary('External example')
            ->externalValue('https://example.com/examples/user.json')
            ->buildArray()
        ;

        $this->assertSame([
            'summary' => 'External example',
            'externalValue' => 'https://example.com/examples/user.json',
        ], $result);
    }

    public function testValueAndExternalValueAreMutuallyExclusive(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'test');

        $exampleBuilder->value(['id' => 1]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set externalValue when value is already set');

        $exampleBuilder->externalValue('https://example.com/example.json');
    }

    public function testExternalValueAndValueAreMutuallyExclusive(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'test');

        $exampleBuilder->externalValue('https://example.com/example.json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set value when externalValue is already set');

        $exampleBuilder->value(['id' => 1]);
    }

    public function testGetName(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'myExample');

        $this->assertSame('myExample', $exampleBuilder->getName());
    }

    public function testEndReturnsParentBuilder(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'test');

        $result = $exampleBuilder->end();

        $this->assertSame($contentBuilder, $result);
    }

    public function testMultipleExamplesInContentBuilder(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');

        $contentBuilder
            ->addExample('success')
                ->summary('Successful response')
                ->description('A complete user object')
                ->value(['id' => 1, 'name' => 'John'])
            ->end()
            ->addExample('minimal')
                ->summary('Minimal response')
                ->value(['id' => 2])
            ->end()
            ->addExample('external')
                ->summary('External example')
                ->externalValue('https://example.com/user.json')
            ->end()
        ;

        $result = $contentBuilder->buildArray();

        $this->assertArrayHasKey('examples', $result);
        $this->assertCount(3, $result['examples']);

        $this->assertSame([
            'summary' => 'Successful response',
            'description' => 'A complete user object',
            'value' => ['id' => 1, 'name' => 'John'],
        ], $result['examples']['success']);

        $this->assertSame([
            'summary' => 'Minimal response',
            'value' => ['id' => 2],
        ], $result['examples']['minimal']);

        $this->assertSame([
            'summary' => 'External example',
            'externalValue' => 'https://example.com/user.json',
        ], $result['examples']['external']);
    }

    public function testMultipleExamplesInParameterBuilder(): void
    {
        $routeBuilder = $this->createMock(RouteBuilder::class);
        $parameterBuilder = new ParameterBuilder($routeBuilder);

        $parameterBuilder
            ->name('userId')
            ->in('path')
            ->addExample('default')
                ->summary('Default user')
                ->value(1)
            ->end()
            ->addExample('admin')
                ->summary('Admin user')
                ->description('The administrator account')
                ->value(999)
            ->end()
        ;

        $result = $parameterBuilder->buildArray();

        $this->assertArrayHasKey('examples', $result);
        $this->assertCount(2, $result['examples']);

        $this->assertSame([
            'summary' => 'Default user',
            'value' => 1,
        ], $result['examples']['default']);

        $this->assertSame([
            'summary' => 'Admin user',
            'description' => 'The administrator account',
            'value' => 999,
        ], $result['examples']['admin']);
    }

    public function testMultipleExamplesInHeaderBuilder(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $headerBuilder = new HeaderBuilder($responseBuilder, 'X-Rate-Limit');

        $headerBuilder
            ->typeInteger()
            ->addExample('standard')
                ->summary('Standard rate limit')
                ->value(1000)
            ->end()
            ->addExample('premium')
                ->summary('Premium rate limit')
                ->description('Higher limit for premium users')
                ->value(10000)
            ->end()
        ;

        $result = $headerBuilder->buildArray();

        $this->assertArrayHasKey('examples', $result);
        $this->assertCount(2, $result['examples']);

        $this->assertSame([
            'summary' => 'Standard rate limit',
            'value' => 1000,
        ], $result['examples']['standard']);

        $this->assertSame([
            'summary' => 'Premium rate limit',
            'description' => 'Higher limit for premium users',
            'value' => 10000,
        ], $result['examples']['premium']);
    }

    public function testSingleExampleStillWorks(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');

        $contentBuilder->example(['id' => 1, 'name' => 'John']);

        $result = $contentBuilder->buildArray();

        $this->assertArrayHasKey('example', $result);
        $this->assertArrayNotHasKey('examples', $result);
        $this->assertSame(['id' => 1, 'name' => 'John'], $result['example']);
    }

    public function testEmptyExample(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'empty');

        $result = $exampleBuilder->buildArray();

        $this->assertSame([], $result);
    }

    public function testExampleWithOnlySummary(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'summarized');

        $result = $exampleBuilder
            ->summary('Just a summary')
            ->buildArray()
        ;

        $this->assertSame([
            'summary' => 'Just a summary',
        ], $result);
    }

    public function testExampleWithComplexValue(): void
    {
        $responseBuilder = $this->createMock(ResponseBuilder::class);
        $contentBuilder = new ContentBuilder($responseBuilder, 'application/json');
        $exampleBuilder = new ExampleBuilder($contentBuilder, 'complex');

        $complexValue = [
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'roles' => ['admin', 'user'],
                'profile' => [
                    'avatar' => 'https://example.com/avatar.jpg',
                    'bio' => 'Software developer',
                ],
            ],
            'metadata' => [
                'createdAt' => '2024-01-01T00:00:00Z',
                'updatedAt' => '2024-01-15T12:30:00Z',
            ],
        ];

        $result = $exampleBuilder
            ->summary('Complex nested object')
            ->description('A user object with nested profile and metadata')
            ->value($complexValue)
            ->buildArray()
        ;

        $this->assertSame([
            'summary' => 'Complex nested object',
            'description' => 'A user object with nested profile and metadata',
            'value' => $complexValue,
        ], $result);
    }
}
