# ApiDocBundle
Symfony Bundle to deal with API documentation using SwaggerUI with **YAML files** or **PHP configuration classes**.

## What this bundle does
- Display API documentation with SwaggerUI
- Generate schemas, request bodies and more via commands
- **NEW**: Define API documentation programmatically using PHP classes
- Support both YAML and PHP configuration (or mix both!)

If you want to write simple YAML files to create your API doc, this bundle is made for you.
Or, if you prefer a programmatic approach with PHP classes and IDE autocompletion, we've got you covered too!

Install, create your API doc with YAML files or PHP configuration classes, and you're done!
You can create as many files/classes as you want and organize them to your needs.

To write YAML files, check the OpenAPI specifications: [OpenApi](https://swagger.io/specification/v3/)

This bundle uses the [Swagger UI](https://swagger.io/tools/swagger-ui/) to render the final result.

You will find examples after the bundle is installed in the default directory /src/Swagger.

---
- [Installation](#installation)
- [Usage](#usage)
  - [YAML Configuration](#yaml-configuration)
  - [PHP Configuration Classes](#php-configuration-classes-new)
- [Components generation via commands](#generating-apidoc-components)
---


# Installation
Be sure that contrib recipes are allowed in your project 
```sh
    composer config extra.symfony.allow-contrib true
```

Then Run 
```sh
  composer require ehyiah/apidoc-bundle
```

# Usage

## YAML Configuration

- In your .env file, update the site_urls variable to use it in your Swagger UI interface.

- In the src/Swagger directory, add the YAML files that you want to be parsed and displayed on the Swagger UI interface.
**the directory can be modified in the .env file with the source_path variable.**

- the default route is ehyiah/api/doc example: localhost/ehyiah/api/doc, **you can modify this route in the config/routes/ehyiah_api_doc.yaml file.**

## PHP Configuration Classes (NEW)

You can now define your API documentation using PHP classes instead of (or in addition to) YAML files!

### Quick Example

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

### Register Your Config Class

In your `config/services.yaml`:

```yaml
services:
    _instanceof:
        Ehyiah\ApiDocBundle\Config\ApiDocConfigInterface:
            tags: ['ehyiah_api_doc.config_provider']
```

### Learn More

📚 **See [docs/PHP_CONFIG_CLASSES.md](docs/PHP_CONFIG_CLASSES.md) for complete documentation with examples.**

### Benefits

- ✅ **Type safety** - IDE autocompletion and type hints
- ✅ **Flexible** - Generate documentation dynamically
- ✅ **Reusable** - Share common patterns across routes
- ✅ **Hybrid** - Works alongside YAML files

## YAML Directory Structure

## Recommended directory structure
If you want to use generation commands (see below) but do not want to use Auto-generated components names, 
you will have to check and update all ``$ref`` used in the generated yaml/yml files by the commands.

**Exemple**: You got a DTO class called ``MyDto``, a schema named ``MyDto`` will be created and used everywhere a reference to this class is created. 
So if you want to call your component ``MyAwesomeDto`` instead of default name, you will have to update the reference (``$ref``) in every file.

```{SOURCE_PATH}``` => is the env variable used as source directory for your api doc default is ```src/Swagger```

| Type of Components |   Default directory   |
|:------------------:|:---------------------:|
|      Schemas       | {SOURCE_PATH}/schemas |
|                    |                       |


# Generating ApiDoc Components
Some commands are included in the bundle to pre-generate components.
You will probably have to edit the generated files or at least check if everything is okay.

| Command                       | Arguments                                                                                   | Options                                                                                                                                                                                                                          | Generation type                                                                          |
|:------------------------------|:--------------------------------------------------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|:-----------------------------------------------------------------------------------------|
| apidocbundle:component:schema | pass the FQCN of the php class you wish (exemple: a DTO, an Entity, any POPO)               | **--output** (-o) specify a custom output directory to dump the generated file from the kernel_project_dir<br/> **--skip** (-s) list of properties to skip _(you can pass multiple times this option to skip many properties)_   | Generate a [schema](https://swagger.io/specification/v3/#schema-object)                  |
| apidocbundle:component:body   | pass the FQCN of the php class you wish (exemple: a DTO, an Entity, any POPO or a FormType) | **--reference** (-r) specify if a reference must be used instead of regenerating a new schema in the requestBody                                                                                                                 | Generate a [RequestBody](https://swagger.io/docs/specification/describing-request-body/) |


# ApiDoc Linting
If needed, there is a command to generate your api doc into a single file in YAML or JSON format.

``` bin/console apidocbundle:api-doc:generate ```

You can use this command, for example, to generate a YAML file and use [vacuum](https://quobix.com/vacuum/api/getting-started) to lint your file.
