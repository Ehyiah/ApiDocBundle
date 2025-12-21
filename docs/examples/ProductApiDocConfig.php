<?php

/**
 * Example: PHP Configuration Class that complements YAML documentation
 * 
 * This class adds additional routes to the API documentation that are NOT defined in YAML.
 * When merged, the final API doc will contain routes from BOTH sources.
 * 
 * YAML File: docs/examples/products.yaml
 * - Defines: GET /api/products (list)
 * - Defines: Product schema
 * 
 * This PHP Class adds:
 * - POST /api/products (create)
 * - GET /api/products/{id} (get one)
 * - PUT /api/products/{id} (update)
 * - DELETE /api/products/{id} (delete)
 * - Category schema (new component)
 */

namespace App\ApiDoc;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Config\ApiDocConfigInterface;

class ProductApiDocConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        // Add POST /api/products - Create a new product
        $builder
            ->addRoute()
                ->path('/api/products')
                ->method('POST')
                ->operationId('createProduct')
                ->summary('Create a new product')
                ->description('Add a new product to the catalog')
                ->tag('Products')
                ->requestBody()
                    ->description('Product data')
                    ->required()
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->property('name', [
                                'type' => 'string',
                                'minLength' => 3,
                                'maxLength' => 100
                            ])
                            ->property('price', [
                                'type' => 'number',
                                'format' => 'float',
                                'minimum' => 0.01
                            ])
                            ->property('category', ['type' => 'string'])
                            ->property('description', ['type' => 'string'])
                            ->required(['name', 'price'])
                        ->end()
                    ->end()
                ->end()
                ->response(201)
                    ->description('Product created successfully')
                    ->jsonContent()
                        ->ref('#/components/schemas/Product')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Invalid input')
                ->end()
            ->end();

        // Add GET /api/products/{id} - Get a single product
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('GET')
                ->operationId('getProduct')
                ->summary('Get product by ID')
                ->description('Returns a single product with all details')
                ->tag('Products')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('Product ID')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(200)
                    ->description('Product found')
                    ->jsonContent()
                        ->ref('#/components/schemas/Product')
                    ->end()
                ->end()
                ->response(404)
                    ->description('Product not found')
                ->end()
            ->end();

        // Add PUT /api/products/{id} - Update a product
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('PUT')
                ->operationId('updateProduct')
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
                        ->ref('#/components/schemas/Product')
                    ->end()
                ->end()
                ->response(200)
                    ->description('Product updated')
                    ->jsonContent()
                        ->ref('#/components/schemas/Product')
                    ->end()
                ->end()
                ->response(404)
                    ->description('Product not found')
                ->end()
            ->end();

        // Add DELETE /api/products/{id} - Delete a product
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('DELETE')
                ->operationId('deleteProduct')
                ->summary('Delete a product')
                ->tag('Products')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(204)
                    ->description('Product deleted successfully')
                ->end()
                ->response(404)
                    ->description('Product not found')
                ->end()
            ->end();

        // Add a new schema component: Category
        $builder
            ->addSchema('Category')
                ->type('object')
                ->description('Product category')
                ->property('id', [
                    'type' => 'integer',
                    'readOnly' => true
                ])
                ->property('name', [
                    'type' => 'string',
                    'minLength' => 2,
                    'maxLength' => 50
                ])
                ->property('slug', [
                    'type' => 'string',
                    'readOnly' => true
                ])
                ->required(['name'])
            ->end();
    }
}
