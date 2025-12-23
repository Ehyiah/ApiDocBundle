# Custom Schema Reference Names

## Overview

Instead of writing long schema references like `->ref('#/components/schemas/ProductEntity')`, you can now create **custom aliases** using `setRefName()` and reference them with `refByName()`.

## Quick Example

```php
// ✨ Step 1: Define schema with custom reference name
$builder
    ->addSchema('ProductEntity')    // Real OpenAPI component name
        ->setRefName('Product')    // 🎯 Custom alias
        ->type('object')
        // ... properties
    ->end();

// ✨ Step 2: Reference it anywhere by the custom name
$builder
    ->addRoute()
        ->path('/api/products/{id}')
        ->method('GET')
        ->response(200)
            ->jsonContent()
                ->refByName('Product')  // ✅ Clean and simple!
            ->end()
        ->end()
    ->end();
```

## Benefits

✅ **Shorter references** - `->refByName('Product')` vs `->ref('#/components/schemas/ProductEntity')`  
✅ **More readable** - Clearer intent in your code  
✅ **Flexible naming** - Decouple display names from internal schema names  
✅ **Type-safe** - IDE autocomplete still works  
✅ **No typos** - Runtime validation if reference doesn't exist  

## How It Works

### 1. Register Schema with Custom Name

When defining a schema, call `setRefName()` to create an alias:

```php
$builder
    ->addSchema('CreateProductRequestDTO')  // Internal OpenAPI name
        ->setRefName('ProductCreate')      // Custom alias for reference
        ->type('object')
        ->property('name', ['type' => 'string'])
        ->required(['name'])
    ->end();
```

**What happens:**
- OpenAPI component will be: `#/components/schemas/CreateProductRequestDTO`
- Alias `'ProductCreate'` is registered internally
- You can now use `'ProductCreate'` to reference this schema

### 2. Reference Schema by Custom Name

In routes, use `refByName()` instead of `ref()`:

```php
->requestBody()
    ->jsonContent()
        ->refByName('ProductCreate')  // ✨ Uses the alias
    ->end()
->end()
```

**This generates:**
```json
{
  "requestBody": {
    "content": {
      "application/json": {
        "schema": {
          "$ref": "#/components/schemas/CreateProductRequestDTO"
        }
      }
    }
  }
}
```

## Complete Example

```php
public function configure(ApiDocBuilder $builder): void
{
    // Define three schemas with custom names
    $builder->addSchema('ProductEntity')
        ->setRefName('Product')
        ->type('object')
        ->property('id', ['type' => 'integer'])
        ->property('name', ['type' => 'string'])
    ->end();

    $builder->addSchema('CreateProductRequestDTO')
        ->setRefName('ProductCreate')
        ->type('object')
        ->property('name', ['type' => 'string'])
    ->end();

    $builder->addSchema('UpdateProductRequestDTO')
        ->setRefName('ProductUpdate')
        ->type('object')
        ->property('name', ['type' => 'string'])
    ->end();

    // Use them in routes
    $builder->addRoute()
        ->path('/api/products')
        ->method('POST')
        ->requestBody()
            ->jsonContent()->refByName('ProductCreate')->end()
        ->end()
        ->response(201)
            ->jsonContent()->refByName('Product')->end()
        ->end()
    ->end();

    $builder->addRoute()
        ->path('/api/products/{id}')
        ->method('PUT')
        ->requestBody()
            ->jsonContent()->refByName('ProductUpdate')->end()
        ->end()
        ->response(200)
            ->jsonContent()->refByName('Product')->end()
        ->end()
    ->end();
}
```

## Comparison

### ❌ OLD WAY - Manual references

```php
->jsonContent()
    ->ref('#/components/schemas/CreateProductRequestDTO')
->end()
```

**Problems:**
- Long and verbose
- Easy to make typos
- Hard to refactor
- Couples code to OpenAPI paths

### ✅ NEW WAY - Custom aliases

```php
->jsonContent()
    ->refByName('ProductCreate')
->end()
```

**Advantages:**
- Short and clean
- Validated at runtime
- Easy to refactor
- Decouples aliases from schema names

## Error Handling

If you try to use a reference name that doesn't exist:

```php
->jsonContent()->refByName('NonExistent')->end()
```

You'll get a clear error:

```
InvalidArgumentException: Schema reference "NonExistent" is not registered. 
Did you forget to call setRefName() on the schema?
```

## Advanced Patterns

### Multiple Aliases for Same Schema

```php
$builder->addSchema('ProductEntity')
    ->setRefName('Product')
    ->setRefName('ProductResponse')  // Multiple aliases!
    ->type('object')
->end();

// Both work:
->refByName('Product')
->refByName('ProductResponse')
```

### Organizing by Domain

```php
// User domain
$builder->addSchema('UserEntity')->setRefName('User')->end();
$builder->addSchema('UserCreateDTO')->setRefName('UserCreate')->end();
$builder->addSchema('UserUpdateDTO')->setRefName('UserUpdate')->end();

// Product domain
$builder->addSchema('ProductEntity')->setRefName('Product')->end();
$builder->addSchema('ProductCreateDTO')->setRefName('ProductCreate')->end();
$builder->addSchema('ProductUpdateDTO')->setRefName('ProductUpdate')->end();

// Consistent naming pattern across domains!
```

### Versioned APIs

```php
// V1 schemas
$builder->addSchema('ProductEntityV1')->setRefName('ProductV1')->end();

// V2 schemas
$builder->addSchema('ProductEntityV2')->setRefName('ProductV2')->end();

// Routes can easily reference the right version
->refByName('ProductV1')  // For /api/v1/products
->refByName('ProductV2')  // For /api/v2/products
```

## When to Use

✅ **Do use `refByName()`  when:**
- You have descriptive, consistent naming
- You want cleaner, more maintainable code
- You're building an API with many schemas

✅ **Still use `ref()` when:**
- You need to reference external schemas
- You're integrating with third-party OpenAPI specs
- You prefer explicit full paths

## See Also

- [ProductWithCustomRefsConfig.php](ProductWithCustomRefsConfig.php) - Complete working example
- [PHP_CONFIG_CLASSES.md](../PHP_CONFIG_CLASSES.md) - Main documentation
