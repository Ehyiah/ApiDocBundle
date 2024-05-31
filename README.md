# ApiDocBundle
Symfony Bundle to deal with api doc using SwaggerUI and yaml/yml files without annotations/attributes.

If you just want to write simple yaml/yml files to create your api doc, this bundle is made for you.
Install, create your api doc with yaml/yml files in the source directory, it's done !
You can create as many files as you want and organize them in subdirectory as well to your needs.

to write files Check the openapi specifications [OpenApi](https://swagger.io/specification/v3/)

The bundle use the [Swagger UI](https://swagger.io/tools/swagger-ui/) to render the final result.

You will find some exemple after the bundle is installed in the default directory /src/Swagger.

# Installation
## Installation for usage purpose
This bundle does not have a flex recipe (at the moment), so if you want an "auto-installation" :

**On the project you want to use this bundle:**
If you want the project to install every files automatically add these lines
1. On *composer.json*, please add these lines :
   ``` json
   "scripts": {
        "post-package-install": [
            "Ehyiah\\ApiDocBundle\\Composer\\ComposerScript::postPackageInstall"
        ],
        "pre-package-uninstall": [
            "Ehyiah\\ApiDocBundle\\Composer\\ComposerScript::prePackageUninstall"
        ]
    }
   ```

2. Run ``composer require ehyiah/apidoc-bundle``

On a composer remove, the files you have created will not be deleted.


## Installation for development purpose on this bundle
1. clone this project wherever you want.
2. **On the project you want to use this bundle:**
    1. On *composer.json*, please add these lines :
   ``` json
   "repositories": [
           {
               "type": "path",
               "url": "LINK_TO_BUNDLE_PROJECT_DIRECTORY",
               "options": {
                   "symlink": true
               }
           }
       ],
   ```
    2. Run ``composer require ehyiah/apidoc-bundle:@dev``

# Usage
- In your .env file, update the site_url variable to use it in your Swagger UI interface.

- In the src/Swagger, just add the yaml files that you want to be parsed and displayed on the Swagger UI interface.
the directory can be modified in the .env file with the source_path variable.

- the default route is ehyiah/api/doc exemple : localhost/ehyiah/api/doc, you can modify this route in the config/routes/ehyiah_api_doc.yaml file.


# ApiDoc Linting
If you want, there is a command to generate an apidoc file in yaml or json.

``` bin/console apidocbundle:api-doc:generate ```

You can use this command for exemple to generate a yaml file and use [vacuum](https://quobix.com/vacuum/api/getting-started) to lint your file.
