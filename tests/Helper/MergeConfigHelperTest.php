<?php

namespace Ehyiah\ApiDocBundle\Tests\Helper;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper
 */
final class MergeConfigHelperTest extends TestCase
{
    public function testMergeScalarOverwrites(): void
    {
        $base = ['openapi' => '3.0.0', 'title' => 'Old'];
        $override = ['openapi' => '3.1.0'];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertSame('3.1.0', $result['openapi']);
        $this->assertSame('Old', $result['title']);
    }

    public function testMergeNewKeysAreAdded(): void
    {
        $base = ['openapi' => '3.0.0'];
        $override = ['info' => ['title' => 'My API']];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertArrayHasKey('info', $result);
        $this->assertSame('My API', $result['info']['title']);
    }

    public function testMergeDeeplyNestedAssociative(): void
    {
        $base = [
            'components' => [
                'schemas' => [
                    'User' => ['type' => 'object'],
                ],
            ],
        ];
        $override = [
            'components' => [
                'schemas' => [
                    'Product' => ['type' => 'object'],
                ],
                'securitySchemes' => [
                    'Bearer' => ['type' => 'http'],
                ],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertArrayHasKey('User', $result['components']['schemas']);
        $this->assertArrayHasKey('Product', $result['components']['schemas']);
        $this->assertArrayHasKey('Bearer', $result['components']['securitySchemes']);
    }

    public function testMergePathsCombined(): void
    {
        $base = [
            'paths' => [
                '/api/users' => ['get' => ['operationId' => 'listUsers']],
            ],
        ];
        $override = [
            'paths' => [
                '/api/products' => ['get' => ['operationId' => 'listProducts']],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertCount(2, $result['paths']);
        $this->assertArrayHasKey('/api/users', $result['paths']);
        $this->assertArrayHasKey('/api/products', $result['paths']);
    }

    public function testMergeTagsDeduplicatesByName(): void
    {
        $base = [
            'tags' => [
                ['name' => 'Users', 'description' => 'User operations'],
                ['name' => 'Products', 'description' => 'Product operations'],
            ],
        ];
        $override = [
            'tags' => [
                ['name' => 'Users', 'description' => 'Updated user operations'],
                ['name' => 'Orders', 'description' => 'Order operations'],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertCount(3, $result['tags']);
        // Users was merged (deduplicated, description updated)
        $this->assertSame('Updated user operations', $result['tags'][0]['description']);
        // Products from base remains
        $this->assertSame('Products', $result['tags'][1]['name']);
        // Orders from override was added
        $this->assertSame('Orders', $result['tags'][2]['name']);
    }

    public function testMergeServersDeduplicatesByUrl(): void
    {
        $base = [
            'servers' => [
                ['url' => 'https://api.example.com', 'description' => 'Prod'],
            ],
        ];
        $override = [
            'servers' => [
                ['url' => 'https://api.example.com', 'description' => 'Production'],
                ['url' => 'https://staging.example.com', 'description' => 'Staging'],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertCount(2, $result['servers']);
        $this->assertSame('Production', $result['servers'][0]['description']);
        $this->assertSame('Staging', $result['servers'][1]['description']);
    }

    public function testMergeSecurityJustAppends(): void
    {
        $base = [
            'security' => [
                ['BearerAuth' => []],
            ],
        ];
        $override = [
            'security' => [
                ['ApiKeyAuth' => []],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertCount(2, $result['security']);
    }

    public function testMergeOverrideReplacesScalarWithConfig(): void
    {
        $base = ['openapi' => '3.0.0'];
        $override = ['openapi' => ['nested' => 'value']];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertSame(['nested' => 'value'], $result['openapi']);
    }

    public function testMergeEmptyBase(): void
    {
        $base = [];
        $override = ['openapi' => '3.0.0', 'info' => ['title' => 'Test']];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertSame('3.0.0', $result['openapi']);
        $this->assertSame('Test', $result['info']['title']);
    }

    public function testMergeEmptyOverride(): void
    {
        $base = ['openapi' => '3.0.0', 'info' => ['title' => 'Test']];
        $override = [];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertSame('3.0.0', $result['openapi']);
        $this->assertSame('Test', $result['info']['title']);
    }

    public function testMergeTagsWithoutNameNotDeduplicated(): void
    {
        $base = [
            'tags' => [
                ['description' => 'First'],
            ],
        ];
        $override = [
            'tags' => [
                ['description' => 'Second'],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertCount(2, $result['tags']);
    }

    public function testMergeComponentsNested(): void
    {
        $base = [
            'components' => [
                'schemas' => [
                    'Pagination' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ];
        $override = [
            'components' => [
                'schemas' => [
                    'Pagination' => [
                        'required' => ['page'],
                    ],
                ],
            ],
        ];

        $result = LoadApiDocConfigHelper::mergeConfigs($base, $override);

        $this->assertSame('object', $result['components']['schemas']['Pagination']['type']);
        $this->assertSame(['page'], $result['components']['schemas']['Pagination']['required']);
    }
}
