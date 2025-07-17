# ApiDocBundle
Symfony Bundle to deal with api doc using SwaggerUI and yaml/yml files without annotations/attributes.

what this bundle does: 
- display api doc with SwaggerUI
- generate some schemas, bodies and more to come with commands

If you just want to write simple yaml/yml files to create your api doc, this bundle is made for you.
Install, create your api doc with yaml/yml files in the source directory, it's done!
You can create as many files as you want and organize them in a subdirectory as well to your needs.

to write files Check the openapi specifications [OpenApi](https://swagger.io/specification/v3/)

This bundle uses the [Swagger UI](https://swagger.io/tools/swagger-ui/) to render the final result.

You will find some example after the bundle is installed in the default directory /src/Swagger.

---
- [Installation](#installation)
- [Usage](#usage)
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
- In your .env file, update the site_urls variable to use it in your Swagger UI interface.

- In the src/Swagger directory, add the YAML files that you want to be parsed and displayed on the Swagger UI interface.
**the directory can be modified in the .env file with the source_path variable.**

- the default route is ehyiah/api/doc example: localhost/ehyiah/api/doc, **you can modify this route in the config/routes/ehyiah_api_doc.yaml file.**

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
