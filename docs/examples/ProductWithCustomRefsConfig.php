<?php

/**
 * Example: Using Custom Reference Names with setRefName() and refByName()
 *
 * This example demonstrates how to create custom aliases for schemas
 * and reference them easily throughout your API documentation.
 */

namespace App\ApiDoc;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

class ProductWithCustomRefsConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        // ✨ Step 1: Define schemas with custom reference names

        // Product schema with alias 'Product  '
        $builder
            ->addSchema('ProductEntity')  // Real component name in OpenAPI
                ->setRefName('Product')   // 🎯 Custom alias for easy reference
                ->type('object')
                ->property('id', ['type' => 'integer', 'readOnly' => true])
                ->property('name', ['type' => 'string', 'minLength' => 3])
                ->property('price', ['type' => 'number', 'format' => 'float'])
                ->property('category', ['type' => 'string'])
                ->required(['name', 'price'])
            ->end();

        // CreateProductRequest DTO with alias 'ProductCreate'
        $builder
            ->addSchema('CreateProductRequestDTO')  // Real component name
                ->setRefName('ProductCreate')       // 🎯 Shorter alias
                ->type('object')
                ->property('name', ['type' => 'string', 'minLength' => 3])
                ->property('price', ['type' => 'number', 'minimum' => 0.01])
                ->property('category', ['type' => 'string'])
                ->required(['name', 'price'])
            ->end();

        // UpdateProductRequest DTO with alias 'ProductUpdate'
        $builder
            ->addSchema('UpdateProductRequestDTO')
                ->setRefName('ProductUpdate')  // 🎯 Another custom alias
                ->type('object')
                ->property('name', ['type' => 'string', 'minLength' => 3])
                ->property('price', ['type' => 'number', 'minimum' => 0.01])
                ->property('category', ['type' => 'string'])
            ->end();

        // ✨ Step 2: Reference schemas using custom names

        // GET /api/products/{id} - Returns a Product
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('GET')
                ->summary('Get product by ID')
                ->tag('Products')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(200)
                    ->description('Product found')
                    ->jsonContent()
                        // ✨ Using custom ref name instead of full path!
                       ->refByName('Product')  // Much cleaner than:
                        // ->ref('#/components/schemas/ProductEntity')
                    ->end()
                ->end()
            ->end();

        // POST /api/products - Create a product
        $builder
            ->addRoute()
                ->path('/api/products')
                ->method('POST')
                ->summary('Create a new product')
                ->tag('Products')
                ->requestBody()
                    ->required()
                    ->jsonContent()
                        // ✨ Reference the create DTO by alias
                        ->refByName('ProductCreate')
                    ->end()
                ->end()
                ->response(201)
                    ->description('Product created')
                    ->jsonContent()
                        // ✨ Return the full Product entity
                        ->refByName('Product')
                    ->end()
                ->end()
            ->end();

        // PUT /api/products/{id} - Update a product
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('PUT')
                ->summary('Update a product')
                ->tag('Products')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->requestBody()
                    ->required()
                    ->jsonContent()
                        // ✨ Reference the update DTO
                        ->refByName('ProductUpdate')
                    ->end()
                ->end()
                ->response(200)
                    ->description('Product updated')
                    ->jsonContent()
                        // ✨ Return updated Product
                        ->refByName('Product')
                    ->end()
                ->end()
            ->end();

        // GET /api/products - List products
        $builder
            ->addRoute()
                ->path('/api/products')
                ->method('GET')
                ->summary('List all products')
                ->tag('Products')
                ->response(200)
                    ->description('Products list')
                    ->jsonContent()
                        // Inline schema with array of Products
                        ->schema()
                            ->type('object')
                            ->property('products', [
                                'type' => 'array',
                                'items' => [
                                    // Note: In inline schemas, you still need to use full $ref
                                    // refByName() is for ContentBuilder level
                                    '$ref' => '#/components/schemas/ProductEntity'
                                ]
                            ])
                            ->property('total', ['type' => 'integer'])
                        ->end()
                    ->end()
                ->end()
            ->end();
    }
}
