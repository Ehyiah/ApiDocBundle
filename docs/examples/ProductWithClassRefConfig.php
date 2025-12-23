<?php

/**
 * Example: Using refClass() to reference PHP classes automatically
 *
 * This example shows how to use the refClass() method to reference
 * schemas by their PHP class name instead of manually writing the $ref path.
 */

namespace App\ApiDoc;

use App\Entity\Product;
use App\Entity\Category;
use App\DTO\CreateProductRequest;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

class ProductWithClassRefConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        // ✅ NOUVELLE FAÇON - Avec refClass()
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
                        // ✨ Au lieu de: ->ref('#/components/schemas/Product')
                        ->refClass(Product::class)  // 🎉 Référence automatique !
                    ->end()
                ->end()
            ->end();

        // ❌ ANCIENNE FAÇON (toujours supportée)
        /*
        $builder
            ->addRoute()
                ->path('/api/products/{id}')
                ->method('GET')
                ->response(200)
                    ->jsonContent()
                        ->ref('#/components/schemas/Product')  // ❌ Long et sujet aux erreurs
                    ->end()
                ->end()
            ->end();
        */

        // Exemple avec POST - Référence à un DTO
        $builder
            ->addRoute()
                ->path('/api/products')
                ->method('POST')
                ->summary('Create a product')
                ->tag('Products')
                ->requestBody()
                    ->required()
                    ->jsonContent()
                        // Référence au DTO de création
                        ->refClass(CreateProductRequest::class)
                    ->end()
                ->end()
                ->response(201)
                    ->description('Product created')
                    ->jsonContent()
                        // Référence à l'entité complète
                        ->refClass(Product::class)
                    ->end()
                ->end()
            ->end();

        // Exemple avec relation - Liste de produits par catégorie
        $builder
            ->addRoute()
                ->path('/api/categories/{id}/products')
                ->method('GET')
                ->summary('Get products in a category')
                ->tag('Categories')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(200)
                    ->description('Products list')
                    ->jsonContent()
                        // Schéma inline avec référence à Product
                        ->schema()
                            ->type('object')
                            ->property('category', [
                                // Vous pouvez aussi utiliser refClass dans un schema inline
                                // mais c'est plus complexe, utilisez plutôt ->schema() directement
                            ])
                            ->property('products', [
                                'type' => 'array',
                                'items' => [
                                    '$ref' => '#/components/schemas/Product'
                                ]
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end();

        // Vous devez toujours définir les schemas pour que les références fonctionnent
        // Ces schemas peuvent être en YAML ou en PHP
        $builder
            ->addSchema('Product')
                ->type('object')
                ->property('id', ['type' => 'integer'])
                ->property('name', ['type' => 'string'])
                ->property('price', ['type' => 'number', 'format' => 'float'])
            ->end();

        $builder
            ->addSchema('CreateProductRequest')
                ->type('object')
                ->property('name', ['type' => 'string', 'minLength' => 3])
                ->property('price', ['type' => 'number', 'minimum' => 0.01])
                ->required(['name', 'price'])
            ->end();
    }
}
