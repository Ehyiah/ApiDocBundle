# ApiDocBundle
Symfony Bundle to deal with API documentation using **Swagger UI** or **Redoc** with **YAML files** or **PHP classes**.

## What this bundle does
- Display API documentation with Swagger UI or Redoc
- Generate schemas, request bodies and more via commands (currently WIP, only supports YAML dump file)
- Support both YAML and PHP configuration (or mix both!)

If you want to write simple YAML files to create your API doc, this bundle is made for you.
Or, if you prefer a programmatic approach with PHP classes and IDE autocompletion, we've got you covered too!

Install, create your API doc with YAML files or PHP configuration classes, and you're done!
You can create as many files/classes as you want and organize them to your needs.

To write YAML files, check the OpenAPI specifications: [OpenApi](https://swagger.io/specification/v3/)

This bundle uses [Swagger UI](https://swagger.io/tools/swagger-ui/) or [Redoc](https://redocly.com/redoc) to render the final result.

You will find examples after the bundle is installed in the default directory /src/Swagger.

---
- [Installation](#installation)
- [Configuration](#configuration)
  - [UI Selection](#ui-selection)
- [Usage](#usage)
  - [YAML Configuration](#yaml-configuration)
  - [PHP Configuration Classes](#php-configuration-classes)
  - [IDE Navigation with ApiDoc Attribute](#ide-navigation-with-apidoc-attribute)
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

# Configuration

## UI Selection

This bundle supports two documentation UIs:

| UI | Description | Try it out |
|:---|:------------|:-----------|
| **Swagger UI** | Interactive API documentation | ✅ Yes |
| **Redoc** | Clean, elegant documentation | ❌ No |

### Default UI

Configure the default UI in your bundle configuration (`config/packages/ehyiah_api_doc.yaml`):

```yaml
ehyiah_api_doc:
    ui: swagger  # or 'redoc'
```

### Switching UI via Query Parameter

You can switch between UIs on the fly using the `ui` query parameter:

- `/api/doc` → Uses the default UI (from config)
- `/api/doc?ui=swagger` → Forces Swagger UI (with "Try it out" feature)
- `/api/doc?ui=redoc` → Forces Redoc (elegant documentation)

This is useful if you want to use Redoc for public documentation but need Swagger UI for testing API calls.

---

# Usage

## YAML Configuration

- In your .env file, you can update the site_urls variable to use it in your Swagger UI interface (or define yourself via PHP classes or directly inside YAML files)

- In the src/Swagger (default directory) directory, add the YAML files that you want to be parsed and displayed on the Swagger UI interface.
**the directory can be modified in the .env file with the source_path variable.**

- the default route is ehyiah/api/doc example: localhost/ehyiah/api/doc, **you can modify this route in the config/routes/ehyiah_api_doc.yaml file.**

## PHP Configuration Classes

You can define your API documentation using PHP classes instead of (or in addition to) YAML files!

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

### Learn More

📚 **See [docs/PHP_CONFIG_CLASSES.md](docs/PHP_CONFIG_CLASSES.md) for complete documentation with examples.**

### Benefits

- ✅ **Type safety** - IDE autocompletion and type hints
- ✅ **Flexible** - Generate documentation dynamically
- ✅ **Reusable** - Share common patterns across routes
- ✅ **Hybrid** - Works alongside YAML files

## IDE Navigation with ApiDoc Attribute

To improve navigation between your controllers and their API documentation, you can use the `#[ApiDoc]` attribute.
This attribute creates a direct link from your controller methods to the PHP configuration class where the documentation is defined.

**Ctrl+Click** on the class reference in your IDE to navigate directly to the documentation!

```php
<?php
namespace App\Controller;

use Ehyiah\ApiDocBundle\Attributes\ApiDoc;
use App\ApiDoc\UserApiDocConfig;

class UserController
{
    #[ApiDoc(UserApiDocConfig::class)]
    public function getUser(int $id): Response
    {
        // Ctrl+Click on UserApiDocConfig::class to navigate to the documentation
    }

    // You can also reference a specific method in the config class
    #[ApiDoc(UserApiDocConfig::class, 'configureCreateUser')]
    public function createUser(Request $request): Response
    {
        // ...
    }
}
```

This attribute is purely for IDE navigation - it has no runtime behavior but makes it easy to find and maintain your API documentation.

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


# Generating ApiDoc Components (WIP)
Some commands are included in the bundle to pre-generate components.
You will probably have to edit the generated files or at least check if everything is okay.

| Command                       | Arguments                                                                                   | Options                                                                                                                                                                                                                          | Generation type                                                                          |
|:------------------------------|:--------------------------------------------------------------------------------------------|:---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|:-----------------------------------------------------------------------------------------|
| apidocbundle:component:schema | pass the FQCN of the php class you wish (exemple: a DTO, an Entity, any POPO)               | **--output** (-o) specify a custom output directory to dump the generated file from the kernel_project_dir<br/> **--skip** (-s) list of properties to skip _(you can pass multiple times this option to skip many properties)_ **--format** (-f) output format: `yaml`, `php`, or `both` (default: `yaml`)  | Generate a [schema](https://swagger.io/specification/v3/#schema-object)                  |
| apidocbundle:component:body   | pass the FQCN of the php class you wish (exemple: a DTO, an Entity, any POPO or a FormType) | **--reference** (-r) specify if a reference must be used instead of regenerating a new schema in the requestBody **--format** (-f) output format: `yaml`, `php`, or `both` (default: `yaml`)                                                                                                               | Generate a [RequestBody](https://swagger.io/docs/specification/describing-request-body/) |
| apidocbundle:route:generate   | **route**: Chemin de la route (exemple: /api/users)<br/>**method**: Méthode HTTP (GET, POST, PUT, DELETE...) | **--tag** (-t) Tags à associer à la route<br/>**--description** (-d) Description de la route<br/>**--response-schema** (-rs) Nom du schéma à utiliser pour la réponse<br/>**--request-body** (-rb) Nom du requestBody à utiliser<br/>**--output** (-o) Répertoire de sortie<br/>**--filename** (-f) Nom du fichier à générer | Generate a [Path Item Object](https://swagger.io/specification/v3/#path-item-object)     |

### Output Format

You can choose the output format using the `--format` option:

```bash
# Generate PHP file (default)
bin/console apidocbundle:component:schema "App\DTO\UserDTO"

OR bin/console apidocbundle:component:schema "App\DTO\UserDTO" --format=php

# Generate YAML file
bin/console apidocbundle:component:schema "App\DTO\UserDTO" --format=yaml

# Generate both YAML and PHP files
bin/console apidocbundle:component:schema "App\DTO\UserDTO" --format=both
```

### Duplicate Component Detection

When generating a component, the command will automatically check if a component with the same name already exists in your codebase:

1. **Same format exists**: If you're generating a YAML file and a YAML file with the same component already exists (or PHP for PHP), you will be prompted to confirm if you want to overwrite it.

2. **Different format exists**: If you're generating a YAML file but a PHP file with the same component already exists (or vice versa), you will be warned about potential duplicate definitions and asked to confirm before continuing.

**Example output:**
```
Component already exists in YAML file: /path/to/schemas/UserDTO.yaml
Do you want to overwrite this file with new values ? (yes or no, default is YES)

Component also exists in PHP file: /path/to/schemas/UserDTO.php
Do you want to continue generating the YAML file? This may cause duplicate definitions. (yes or no, default is YES)
```

This helps prevent accidental overwrites and warns you about duplicate component definitions that could cause conflicts in your API documentation.


# ApiDoc Linting
If needed, there is a command to generate your api doc into a single file in YAML or JSON format.

``` bin/console apidocbundle:api-doc:generate ```

You can use this command, for example, to generate a YAML file and use [vacuum](https://quobix.com/vacuum/api/getting-started) to lint your file.
