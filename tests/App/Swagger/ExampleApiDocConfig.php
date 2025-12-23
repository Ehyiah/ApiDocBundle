<?php

/**
 * Example API Documentation Configuration using PHP Classes
 *
 * This file demonstrates how to use the programmatic API builder
 * to define API documentation without YAML files.
 *
 * To use this:
 * 1. Copy this file to your src/ApiDoc directory
 * 2. Register it in your services.yaml with the tag 'ehyiah_api_doc.config_provider'
 * 3. Or use _instanceof to auto-tag all ApiDocConfigInterface implementations
 */

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

class ExampleApiDocConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        // Configure OpenAPI base info
        $builder
            ->info()
                ->openApiVersion('3.0.0')
                ->title('My API Documentation')
                ->description('This is an example API documentation built with PHP classes')
                ->version('2.0.0')
                ->contact('API Support', 'support@example.com', 'https://example.com/support')
                ->license('MIT', 'https://opensource.org/licenses/MIT')
            ->end()
        ;

        // Define security schemes
        $builder
            ->addSecurityScheme('Bearer')
                ->bearer('JWT')
                ->description('JWT token authentication')
            ->end()
            ->addSecurityScheme('ApiKey')
                ->apiKey('X-API-KEY', 'header')
                ->description('API Key authentication')
            ->end()
        ;

        // Define tags
        $builder
            ->addTag('Users')
                ->description('User management endpoints')
            ->end()
            ->addTag('Products')
                ->description('Product management endpoints')
                ->externalDocs('https://example.com/docs/products', 'Product documentation')
            ->end()
        ;

        // Define a GET endpoint with security
        $builder
            ->addRoute()
                ->path('/api/users2/{id}')
                ->method('GET')
                ->operationId('getUser')
                ->summary('Get user by ID')
                ->description('Returns a single user with all details')
                ->tag('Users')
                ->security('Bearer')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(200)
                    ->description('Successful response')
                    ->jsonContent()
                        ->ref('#/components/schemas/User')
                    ->end()
                ->end()
                ->response(404)
                    ->description('User not found')
                ->end()
            ->end()
        ;

        // Define a POST endpoint with inline schema
        $builder
            ->addRoute()
                ->path('/api/users2')
                ->method('POST')
                ->operationId('createUser2')
                ->summary('Create a new user')
                ->tag('Users')
                ->requestBody()
                    ->description('User data to create')
                    ->required()
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->property('name', [
                                'type' => 'string',
                                'minLength' => 2,
                                'maxLength' => 100,
                            ])
                            ->property('email', [
                                'type' => 'string',
                                'format' => 'email',
                            ])
                            ->property('age', [
                                'type' => 'integer',
                                'minimum' => 18,
                                'maximum' => 120,
                            ])
                            ->required(['name', 'email'])
                        ->end()
                    ->end()
                ->end()
                ->response(201)
                    ->description('User created successfully')
                    ->jsonContent()
                        ->ref('#/components/schemas/User')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Invalid input')
                ->end()
            ->end()
        ;

        // Define a schema component
        $builder
            ->addSchema('User')
                ->type('object')
                ->description('User entity')
                ->property('id', [
                    'type' => 'integer',
                    'readOnly' => true,
                    'example' => 123,
                ])
                ->property('name', [
                    'type' => 'string',
                    'example' => 'John Doe',
                ])
                ->property('email', [
                    'type' => 'string',
                    'format' => 'email',
                    'example' => 'john.doe@example.com',
                ])
                ->property('age', [
                    'type' => 'integer',
                    'nullable' => true,
                    'example' => 30,
                ])
                ->property('createdAt', [
                    'type' => 'string',
                    'format' => 'date-time',
                    'readOnly' => true,
                ])
                ->required(['id', 'name', 'email'])
            ->end()
        ;
    }
}
