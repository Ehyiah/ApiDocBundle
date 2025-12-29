# PHP Configuration Classes for API Documentation

This guide explains how to use PHP configuration classes to programmatically define your API documentation.

## Overview

The **Programmatic API Builder** allows you to define API documentation using PHP classes instead of (or in addition to) YAML files. This approach offers:

- **Type safety**: IDE autocompletion and type hints
- **Flexibility**: Generate documentation dynamically
- **Reusability**: Share common patterns across routes
- **Testability**: Unit test your documentation logic

## Quick Start

### 1. Create a Configuration Class

Create a class that implements `ApiDocConfigInterface`:

```php
<?php

namespace App\ApiDoc;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

class UserApiDocConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder
            ->addRoute()
                ->path('/api/users/{id}')
                ->method('GET')
                ->summary('Get user by ID')
                ->tag('Users')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->required()
                    ->schema(['type' => 'integer'])
                ->end()
                ->response(200)
                    ->description('User found')
                    ->jsonContent()
                        ->ref('#/components/schemas/User')
                    ->end()
                ->end()
            ->end();
    }
}
```

### 2. Register the Configuration Class

**Option A: Auto-registration (Recommended)**

Add to your `config/services.yaml`:

```yaml
services:
    _instanceof:
        Ehyiah\ApiDocBundle\Config\ApiDocConfigInterface:
            tags: ['ehyiah_api_doc.config_provider']
```

All classes implementing `ApiDocConfigInterface` will be automatically discovered.

**Option B: Manual registration**

```yaml
services:
    App\ApiDoc\UserApiDocConfig:
        tags: ['ehyiah_api_doc.config_provider']
```

### 3. Enable PHP Config (Optional)

In your `config/packages/ehyiah_api_doc.yaml`:

```yaml
ehyiah_api_doc:
    enable_php_config: true  # true by default
```

That's it! Your PHP-defined documentation will be merged with any existing YAML documentation.

## Builder API Reference

### Routes

```php
$builder->addRoute()
    ->path('/api/resource')        // Route path
    ->method('POST')               // HTTP method
    ->operationId('createResource') // Unique operation ID
    ->summary('Short description')  // Brief summary
    ->description('Long description') // Detailed description
    ->tag('Resources')             // Group routes by tag
    // ... add parameters, request body, responses
    ->end();                       // Finish and register route
```

### Parameters

```php
->parameter()
    ->name('id')                   // Parameter name
    ->in('path')                   // Location: path, query, header, cookie
    ->description('Resource ID')   // Description
    ->required(true)               // Is required
    ->schema(['type' => 'integer']) // JSON Schema
    ->example(123)                 // Example value
->end()
```

### Request Body

```php
->requestBody()
    ->description('Request payload')
    ->required(true)
    ->jsonContent()                // application/json
        ->ref('#/components/schemas/User')  // Reference to schema
        // OR - NEW! Use PHP class reference
        ->refClass(User::class)    // ✨ Automatic schema reference
        // OR
        ->schema()                 // Inline schema
            ->type('object')
            ->property('name', ['type' => 'string'])
            ->required(['name'])
        ->end()
    ->end()
->end()
```

### Responses

```php
->response(200)                    // HTTP status code
    ->description('Success')
    ->jsonContent()
        ->ref('#/components/schemas/User')  // Manual reference
        // OR - NEW! Use PHP class reference
        ->refClass(User::class)             // ✨ Auto-generates: '#/components/schemas/User'
    ->end()
->end()
```

### ✨ NEW: Class-Based References

Instead of writing `->ref('#/components/schemas/Product')` manually, you can use `->refClass()`:

```php
use App\Entity\Product;
use App\DTO\CreateProductRequest;

// ✅ NEW WAY - Type-safe with IDE support
->jsonContent()
    ->refClass(Product::class)
->end()

// ❌ OLD WAY - Still works but more verbose
->jsonContent()
    ->ref('#/components/schemas/Product')
->end()
```

**Benefits:**
- ✅ **Type-safe**: IDE autocompletes the class name
- ✅ **Refactoring-friendly**: Renaming the class updates references
- ✅ **Less error-prone**: No manual string typing
- ✅ **Cleaner code**: More readable

**How it works:**
```php
Product::class               → 'App\Entity\Product'
refClass() extracts          → 'Product'
Generates reference          → '#/components/schemas/Product'
```

**Examples:**
```php
// Request body with DTO
->requestBody()
    ->jsonContent()
        ->refClass(CreateProductRequest::class)
    ->end()
->end()

// Response with Entity
->response(201)
    ->jsonContent()
        ->refClass(Product::class)
    ->end()
->end()
```

See [docs/examples/ProductWithClassRefConfig.php](examples/ProductWithClassRefConfig.php) for a complete example.

### Schemas

```php
$builder->addSchema('User')
    ->type('object')
    ->description('User entity')
    ->property('id', [
        'type' => 'integer',
        'readOnly' => true
    ])
    ->property('name', ['type' => 'string'])
    ->property('email', [
        'type' => 'string',
        'format' => 'email'
    ])
    ->required(['name', 'email'])
    ->end();
```

## Advanced Usage

### Schema Properties with Full Options

```php
->property('age', [
    'type' => 'integer',
    'minimum' => 0,
    'maximum' => 150,
    'default' => 18,
    'example' => 25,
    'nullable' => true,
    'readOnly' => false,
    'writeOnly' => false
])
```

### Array Types

```php
->property('tags', [
    'type' => 'array',
    'items' => [
        'type' => 'string'
    ]
])
```

### Multiple Content Types

```php
->response(200)
    ->description('Success')
    ->jsonContent()
        ->ref('#/components/schemas/User')
    ->end()
    ->content('application/xml')
        ->ref('#/components/schemas/User')
    ->end()
->end()
```

### Inline Schema in Response

```php
->response(200)
    ->description('Success')
    ->jsonContent()
        ->schema()
            ->type('object')
            ->property('success', ['type' => 'boolean'])
            ->property('data', ['$ref' => '#/components/schemas/User'])
        ->end()
    ->end()
->end()
```

## Complete Example

See `src/Config/ExampleApiDocConfig.php` for a complete working example.

## Hybrid Mode

You can use both YAML and PHP configuration simultaneously:
- YAML files in `src/Swagger/` (default)
- PHP config classes tagged with `ehyiah_api_doc.config_provider`

They will be automatically merged into a single OpenAPI specification.

## Benefits

✅ **Type Safety**: Your IDE provides autocompletion
✅ **DRY**: Reuse common schemas and patterns  
✅ **Dynamic**: Generate documentation from code  
✅ **Testable**: Write unit tests for your API docs  
✅ **Compatible**: Works alongside existing YAML files

## Troubleshooting

**Q: My config class is not being loaded**
- Check that it implements `ApiDocConfigInterface`
- Verify it's tagged with `ehyiah_api_doc.config_provider`
- Ensure `enable_php_config` is `true` in bundle configuration
- Clear cache: `php bin/console cache:clear`

**Q: Documentation is duplicated**
- Make sure you're not defining the same path/method in both YAML and PHP
- If intentional, the configurations will be merged (PHP takes precedence)

**Q: Schema references not working**
- Ensure schema names match exactly (case-sensitive)
- Use `#/components/schemas/SchemaName` format
- Define schemas before referencing them (order matters in same class)
