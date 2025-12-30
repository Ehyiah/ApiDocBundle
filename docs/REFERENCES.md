# Schema References Guide

This bundle provides **three ways** to reference schemas in your documentation.
Choose the one that best fits your workflow!

## 1. Class Reference (`refClass`) [Recommended]

This is the fastest and most robust method if your **Schema name matches your PHP Class name**.

### How it works
You pass the PHP class FQCN (Project\Entity\User::class), and the bundle automatically extracts the short name (`User`) to build the reference.

### Usage

**Step 1: Define the schema**
The schema name MUST match the class short name.

```php
// UserDTO.php
$builder->addSchema('UserDTO')  // ✅ Matches class name
    ->type('object')
    // ...
->end();
```

**Step 2: Reference it**

```php
use App\DTO\UserDTO;

// ...
->jsonContent()
    ->refClass(UserDTO::class) // Generates: #/components/schemas/UserDTO
->end()
```

### ✅ Pros
- Fastest to write
- Refactoring-friendly (if you rename the class, the reference updates)
- Typos impossible (detected by PHP)

### ❌ Cons
- Requires Schema Name == Class Short Name

---

## 2. Named Alias (`refByName`)

Use this when you want to decouple your code from the OpenAPI naming structure, or when you want short, descriptive aliases.

### How it works
You explicitly define an **alias** (nickname) for a schema, and use that alias to reference it.

### Usage

**Step 1: Define the schema with an alias**

```php
$builder->addSchema('ComplexUserEntity_V2_FINAL') // Internal OpenAPI name
    ->setRefName('User')                          // 🎯 The Alias
    ->type('object')
    // ...
->end();
```

**Step 2: Reference it by alias**

```php
->jsonContent()
    ->refByName('User') // Generates: #/components/schemas/ComplexUserEntity_V2_FINAL
->end()
```

### ✅ Pros
- Decouples internal logic from External API names
- Allows short, clean references
- Runtime validation (error if alias doesn't exist)

### ❌ Cons
- Slightly more verbose definition

---

## 3. Standard Reference (`ref`)

This is the raw OpenAPI method. Use this for referencing components that are **not** schemas (like SecuritySchemes), or when referencing external files.

### How it works
You write the full JSON Pointer path string manually.

### Usage

```php
->jsonContent()
    ->ref('#/components/schemas/UserDTO')
->end()
```

Or for external files:

```php
->jsonContent()
    ->ref('./external-definitions.yaml#/User')
->end()
```

### ✅ Pros
- Works for EVERYTHING (local, external, remote URLs)
- Standard OpenAPI syntax

### ❌ Cons
- Verbose
- No validation (until you run the doc)
- Easy to make typos (`scemas` vs `schemas`)

---

## Summary Table

| Method | Syntax | Best For... |
| :--- | :--- | :--- |
| **Class** | `->refClass(User::class)` | **Most PHP projects.** Fast, type-safe, simple. |
| **Alias** | `->refByName('User')` | **Clean code.** When you want semantic names or decoupling. |
| **Standard** | `->ref('#/.../User')` | **Edge cases.** External files, weird paths, or legacy configs. |
