# PHP Builder Reference

> This file is auto-generated. Do not edit it manually. Run `bin/console apidocbundle:doc:generate-builders` to update it.

## Table of Contents

- [ApiDocBuilder](#apidocbuilder)
- [CallbackBuilder](#callbackbuilder)
- [ContentBuilder](#contentbuilder)
- [ExampleBuilder](#examplebuilder)
- [HeaderBuilder](#headerbuilder)
- [InfoBuilder](#infobuilder)
- [LinkBuilder](#linkbuilder)
- [ParameterBuilder](#parameterbuilder)
- [PathItemBuilder](#pathitembuilder)
- [PropertyBuilder](#propertybuilder)
- [RequestBodyBuilder](#requestbodybuilder)
- [ResponseBuilder](#responsebuilder)
- [RouteBuilder](#routebuilder)
- [SchemaBuilder](#schemabuilder)
- [SecuritySchemeBuilder](#securityschemebuilder)
- [TagBuilder](#tagbuilder)

---

## ApiDocBuilder

Main fluent API builder for creating OpenAPI documentation programmatically. This builder allows you to define routes, schemas, and other OpenAPI components using a chainable, type-safe PHP API instead of writing YAML files.

### `->addRoute()`

Start building a new route/path definition.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RouteBuilder`

---

### `->addSchema()`

Start building a new schema component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\SchemaBuilder`

---

### `->info()`

Start building the OpenAPI base configuration (openapi, info, servers, global security).

**Returns:** `Ehyiah\ApiDocBundle\Builder\InfoBuilder`

---

### `->addSecurityScheme()`

Start building a new security scheme definition.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\SecuritySchemeBuilder`

---

### `->addTag()`

Start building a new tag definition.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\TagBuilder`

---

### `->addLink()`

Start building a new link component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\LinkBuilder`

---

### `->addCallback()`

Start building a new callback component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\CallbackBuilder`

---

### `->addPathItem()`

Start building a new path item component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\PathItemBuilder`

---

### `->addRequestBody()`

Start building a reusable request body component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\RequestBodyBuilder`

---

### `->addParameter()`

Start building a reusable parameter component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ParameterBuilder`

---

### `->addHeader()`

Start building a reusable header component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\HeaderBuilder`

---

### `->addResponse()`

Start building a reusable response component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ResponseBuilder`

---

### `->addExample()`

Start building a reusable example component.
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ExampleBuilder`

---

### `->registerSchemaRef()`

Register a custom reference name for a schema. This allows you to use short aliases instead of full schema names.
**Parameters:**

- `$refName` (`string`)
- `$schemaName` (`string`)

**Returns:** `void`

---

### `->getSchemaRef()`

Get the full schema reference path from a custom reference name.
**Parameters:**

- `$refName` (`string`)

**Returns:** `string`

---

### `->hasSchemaRef()`

Check if a custom reference name is registered.
**Parameters:**

- `$refName` (`string`)

**Returns:** `bool`

---

### `->registerRoute()`

Internal method to register a route definition.
**Parameters:**

- `$path` (`string`)
- `$method` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerSchema()`

Internal method to register a schema definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerInfo()`

Internal method to register info configuration.
**Parameters:**

- `$info` (`array`)

**Returns:** `void`

---

### `->registerTag()`

Internal method to register a tag definition.
**Parameters:**

- `$tag` (`array`)

**Returns:** `void`

---

### `->registerSecurityScheme()`

Internal method to register a security scheme definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerLink()`

Internal method to register a link definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerCallback()`

Internal method to register a callback definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerPathItem()`

Internal method to register a path item definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerRequestBody()`

Internal method to register a request body definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerParameter()`

Internal method to register a parameter definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerHeader()`

Internal method to register a header definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerResponse()`

Internal method to register a response definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->registerExample()`

Internal method to register an example definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `void`

---

### `->getPaths()`

Get all paths (routes) as an array.

**Returns:** `array`

---

### `->getSchemas()`

Get all schemas as an array.

**Returns:** `array`

---

### `->clearSchemas()`



**Returns:** `void`

---

### `->build()`

Build the complete OpenAPI specification array.

**Returns:** `array`

---

### `->end()`

This method exists to satisfy static analysis for fluent chains where the builder type might be ambiguous (e.g. SchemaBuilder::end() returning parent).

**Returns:** `void`

---

## CallbackBuilder

Fluent builder for defining OpenAPI Callback objects. A map of out-of-band callbacks related to the parent operation. Each entry is an expression that evaluates to a Path Item Object.

### `->pathItem()`

Add a path item under an expression.
**Parameters:**

- `$expression` (`string`)
- `$pathItem` (`array`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this callback and return to the parent builder.

**Returns:** `mixed`

---

### `->getName()`

Get the callback name.

**Returns:** `string`

---

### `->buildArray()`

Build the callback definition as an array.

**Returns:** `array`

---

## ContentBuilder

Fluent builder for defining content/media types in requests and responses.

### `->ref()`

Set a reference to a schema component.
**Parameters:**

- `$ref` (`string`)

**Returns:** `Builder instance`

---

### `->refByName()`

Set a reference to a schema component using a custom reference name. The reference name must have been registered with setRefName() on the schema. Example: First, define schema with custom ref name: $builder->addSchema('ProductEntity') ->setRefName('Product') ->type('object') ... Then, reference it by the custom name: ->jsonContent()->refByName('Product')->end()
**Parameters:**

- `$refName` (`string`)

**Returns:** `Builder instance`

---

### `->refClass()`

Set a reference to a schema component using a PHP class name. Automatically converts the class name to a schema reference.
**Parameters:**

- `$className` (`string`)

**Returns:** `Builder instance`

---

### `->schema()`

Start building an inline schema.

**Returns:** `Ehyiah\ApiDocBundle\Builder\SchemaBuilder`

---

### `->example()`

Set an example for this content.
**Parameters:**

- `$example` (`mixed`)

**Returns:** `Builder instance`

---

### `->addExample()`

Add a named example for this content. Use this method to add multiple examples with summary and description. OpenAPI allows multiple named examples, each with optional summary, description, and either a value or externalValue. Example usage: ->addExample('success') ->summary('Successful response') ->description('A complete user object') ->value(['id' => 1, 'name' => 'John']) ->end() ->addExample('minimal') ->summary('Minimal response') ->value(['id' => 2]) ->end()
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ExampleBuilder`

---

### `->encoding()`

Set encoding for a property. Defines encoding for a specific property within a multipart request body.
**Parameters:**

- `$property` (`string`)
- `$encoding` (`array`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this content and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RequestBodyBuilder|Ehyiah\ApiDocBundle\Builder\ResponseBuilder`

---

### `->getMediaType()`

Get the media type for this content.

**Returns:** `string`

---

### `->buildArray()`

Build the content definition as an array.

**Returns:** `array`

---

## ExampleBuilder

Fluent builder for defining named examples. OpenAPI 3.0 Example Object specification. Can be used with MediaType (Content), Parameter, and Header objects.

### `->summary()`

Set a short summary of the example.
**Parameters:**

- `$summary` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set a long description of the example. CommonMark syntax MAY be used for rich text representation.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->value()`

Set the embedded literal example value. The value field and externalValue field are mutually exclusive.
**Parameters:**

- `$value` (`mixed`)

**Returns:** `Builder instance`

---

### `->externalValue()`

Set a URL that points to the literal example. The value field and externalValue field are mutually exclusive.
**Parameters:**

- `$url` (`string`)

**Returns:** `Builder instance`

---

### `->ref()`

Set a reference to an existing example component.
**Parameters:**

- `$ref` (`string`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this example and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ContentBuilder|Ehyiah\ApiDocBundle\Builder\ParameterBuilder|Ehyiah\ApiDocBundle\Builder\HeaderBuilder|Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->getName()`

Get the example name/key.

**Returns:** `string`

---

### `->buildArray()`

Build the example definition as an array.

**Returns:** `array`

---

## HeaderBuilder

Fluent builder for defining response headers. OpenAPI 3.0 Header Object specification.

### `->description()`

Set the header description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->required()`

Mark the header as required.
**Parameters:**

- `$required` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->deprecated()`

Mark the header as deprecated.
**Parameters:**

- `$deprecated` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->allowEmptyValue()`

Allow empty value for the header.
**Parameters:**

- `$allowEmptyValue` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->schema()`

Set the header schema.
**Parameters:**

- `$schema` (`array`)

**Returns:** `Builder instance`

---

### `->typeString()`

Set schema type to string.
**Parameters:**

- `$format` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->typeInteger()`

Set schema type to integer.
**Parameters:**

- `$format` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->typeNumber()`

Set schema type to number.
**Parameters:**

- `$format` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->typeBoolean()`

Set schema type to boolean.

**Returns:** `Builder instance`

---

### `->typeArray()`

Set schema type to array.
**Parameters:**

- `$items` (`array`)

**Returns:** `Builder instance`

---

### `->example()`

Set an example value for the header.
**Parameters:**

- `$example` (`mixed`)

**Returns:** `Builder instance`

---

### `->addExample()`

Add a named example for this header. Use this method to add multiple examples with summary and description. OpenAPI allows multiple named examples, each with optional summary, description, and either a value or externalValue. Example usage: ->addExample('standard') ->summary('Standard rate limit') ->value('1000') ->end() ->addExample('premium') ->summary('Premium rate limit') ->value('10000') ->end()
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ExampleBuilder`

---

### `->enum()`

Set enum values for the header.
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->defaultValue()`

Set a default value for the header.
**Parameters:**

- `$default` (`mixed`)

**Returns:** `Builder instance`

---

### `->minimum()`

Set minimum value for numeric headers.
**Parameters:**

- `$min` (`mixed`)

**Returns:** `Builder instance`

---

### `->maximum()`

Set maximum value for numeric headers.
**Parameters:**

- `$max` (`mixed`)

**Returns:** `Builder instance`

---

### `->pattern()`

Set pattern (regex) for string headers.
**Parameters:**

- `$pattern` (`string`)

**Returns:** `Builder instance`

---

### `->style()`

Set the serialization style.
**Parameters:**

- `$style` (`string`)

**Returns:** `Builder instance`

---

### `->explode()`

Set explode behavior.
**Parameters:**

- `$explode` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->allowReserved()`

Allow reserved characters in parameter value.
**Parameters:**

- `$allowReserved` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this header and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ResponseBuilder|Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->getName()`

Get the header name.

**Returns:** `string`

---

### `->buildArray()`

Build the header definition as an array.

**Returns:** `array`

---

## InfoBuilder

Fluent builder for defining OpenAPI base configuration. Handles: openapi version, info (title, description, version, contact, license), servers, and global security requirements.

### `->openApiVersion()`

Set the OpenAPI specification version.
**Parameters:**

- `$version` (`string`)

**Returns:** `Builder instance`

---

### `->title()`

Set the API title.
**Parameters:**

- `$title` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set the API description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->version()`

Set the API version.
**Parameters:**

- `$version` (`string`)

**Returns:** `Builder instance`

---

### `->termsOfService()`

Set the terms of service URL.
**Parameters:**

- `$url` (`string`)

**Returns:** `Builder instance`

---

### `->contact()`

Set contact information.
**Parameters:**

- `$name` (`string`) (optional, default: `NULL`)
- `$email` (`string`) (optional, default: `NULL`)
- `$url` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->license()`

Set license information.
**Parameters:**

- `$name` (`string`)
- `$url` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->server()`

Add a server.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)
- `$variables` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->addSecurityRequirement()`

Set global security requirements.
**Parameters:**

- `$schemeName` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->buildArray()`

Build the configuration as an array.

**Returns:** `array`

---

## LinkBuilder

Fluent builder for defining OpenAPI Link objects. Links allow outgoing requests to other operations for request/response pairs.

### `->operationRef()`

Set the reference to an existing Operation Object.
**Parameters:**

- `$operationRef` (`string`)

**Returns:** `Builder instance`

---

### `->operationId()`

Set the operationId of an existing operation.
**Parameters:**

- `$operationId` (`string`)

**Returns:** `Builder instance`

---

### `->parameter()`

Add a parameter to pass to the operation.
**Parameters:**

- `$name` (`string`)
- `$value` (`string`)

**Returns:** `Builder instance`

---

### `->requestBody()`

Set the request body to pass to the operation.
**Parameters:**

- `$value` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set a description of the link.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->server()`

Set a server object to be used by the target operation.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this link and return to the parent builder.

**Returns:** `mixed`

---

### `->getName()`

Get the link name.

**Returns:** `string`

---

### `->buildArray()`

Build the link definition as an array.

**Returns:** `array`

---

## ParameterBuilder

Fluent builder for defining route parameters.

### `->name()`

Set the parameter name.
**Parameters:**

- `$name` (`string`)

**Returns:** `Builder instance`

---

### `->in()`

Set where the parameter is located.
**Parameters:**

- `$in` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set the parameter description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->required()`

Mark the parameter as required.
**Parameters:**

- `$required` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->schema()`

Set the parameter schema.
**Parameters:**

- `$schema` (`array`)

**Returns:** `Builder instance`

---

### `->defaultValue()`

Set a default value for the parameter.
**Parameters:**

- `$default` (`mixed`)

**Returns:** `Builder instance`

---

### `->example()`

Set an example value for the parameter.
**Parameters:**

- `$example` (`mixed`)

**Returns:** `Builder instance`

---

### `->addExample()`

Add a named example for this parameter. Use this method to add multiple examples with summary and description. OpenAPI allows multiple named examples, each with optional summary, description, and either a value or externalValue. Example usage: ->addExample('default') ->summary('Default ID') ->value(1) ->end() ->addExample('admin') ->summary('Admin user ID') ->value(999) ->end()
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ExampleBuilder`

---

### `->deprecated()`

Mark the parameter as deprecated.
**Parameters:**

- `$deprecated` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->allowEmptyValue()`

Allow empty value for the parameter.
**Parameters:**

- `$allowEmptyValue` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->style()`

Set the serialization style. Common styles by location: - query, header, cookie: 'form', 'spaceDelimited', 'pipeDelimited', 'deepObject' - path: 'simple', 'label', 'matrix'
**Parameters:**

- `$style` (`string`)

**Returns:** `Builder instance`

---

### `->explode()`

Set explode behavior. When true, parameter values of type array or object generate separate parameters. When false, array and object values are serialized using the style serialization.
**Parameters:**

- `$explode` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->allowReserved()`

Allow reserved characters in parameter value. Applies to query parameters. When true, allows RFC3986 reserved characters in the parameter value.
**Parameters:**

- `$allowReserved` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this parameter and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RouteBuilder|Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->buildArray()`

Build the parameter definition as an array.

**Returns:** `array`

---

## PathItemBuilder

Fluent builder for defining OpenAPI Path Item objects. A Path Item Object describes the operations available on a single path.

### `->summary()`

Set a summary of the path item.
**Parameters:**

- `$summary` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set a description of the path item.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->ref()`

Set a $ref reference to an external definition.
**Parameters:**

- `$ref` (`string`)

**Returns:** `Builder instance`

---

### `->parameter()`

Add a parameter to this path item.
**Parameters:**

- `$parameter` (`array`)

**Returns:** `Builder instance`

---

### `->server()`

Add a server to this path item.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->get()`

Add a GET operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->put()`

Add a PUT operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->post()`

Add a POST operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->delete()`

Add a DELETE operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->options()`

Add an OPTIONS operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->head()`

Add a HEAD operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->patch()`

Add a PATCH operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->trace()`

Add a TRACE operation to this path item.
**Parameters:**

- `$operation` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this path item and return to the main builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->getName()`

Get the path item name.

**Returns:** `string`

---

### `->buildArray()`

Build the path item definition as an array.

**Returns:** `array`

---

## PropertyBuilder

Fluent builder for defining schema properties with IDE autocompletion support.

### `->type()`

Set the property type. OpenAPI types: - 'string': Text data - 'integer': Whole numbers (use format 'int32' or 'int64' for precision) - 'number': Floating-point numbers (use format 'float' or 'double' for precision) - 'boolean': true/false values - 'array': List of items (define items schema with items() method) - 'object': Key-value structure (define properties with addProperty() or property())
**Parameters:**

- `$type` (`string`)

**Returns:** `Builder instance`

---

### `->typeStringEnum()`

Set the property as a string enum type. Shortcut for ->type('string')->enum($values).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->typeIntegerEnum()`

Set the property as an integer enum type. Shortcut for ->type('integer')->enum($values).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->typeNumberEnum()`

Set the property as a number enum type. Shortcut for ->type('number')->enum($values).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->typeArrayOfStringEnum()`

Set the property as an array of string enum values. Shortcut for ->type('array')->items(['type' => 'string', 'enum' => $values]).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->typeArrayOfIntegerEnum()`

Set the property as an array of integer enum values. Shortcut for ->type('array')->items(['type' => 'integer', 'enum' => $values]).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->typeArrayOfNumberEnum()`

Set the property as an array of number enum values. Shortcut for ->type('array')->items(['type' => 'number', 'enum' => $values]).
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->description()`

Set the property description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->format()`

Set the format for this property. Common formats by type: - string: 'date', 'date-time', 'password', 'byte', 'binary', 'email', 'uuid', 'uri', 'hostname', 'ipv4', 'ipv6' - integer: 'int32', 'int64' - number: 'float', 'double'
**Parameters:**

- `$format` (`string`)

**Returns:** `Builder instance`

---

### `->required()`

Mark this property as required in the parent schema.

**Returns:** `Builder instance`

---

### `->nullable()`

Mark property as nullable.
**Parameters:**

- `$nullable` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->example()`

Set an example value.
**Parameters:**

- `$example` (`mixed`)

**Returns:** `Builder instance`

---

### `->defaultValue()`

Set a default value.
**Parameters:**

- `$default` (`mixed`)

**Returns:** `Builder instance`

---

### `->enum()`

Set enum values.
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->minimum()`

Set minimum value for numeric types.
**Parameters:**

- `$min` (`mixed`)

**Returns:** `Builder instance`

---

### `->maximum()`

Set maximum value for numeric types.
**Parameters:**

- `$max` (`mixed`)

**Returns:** `Builder instance`

---

### `->exclusiveMinimum()`

Set exclusive minimum value for numeric types.
**Parameters:**

- `$min` (`mixed`)

**Returns:** `Builder instance`

---

### `->exclusiveMaximum()`

Set exclusive maximum value for numeric types.
**Parameters:**

- `$max` (`mixed`)

**Returns:** `Builder instance`

---

### `->multipleOf()`

Set multiple of constraint for numeric types.
**Parameters:**

- `$multipleOf` (`mixed`)

**Returns:** `Builder instance`

---

### `->minLength()`

Set minimum length for string types.
**Parameters:**

- `$minLength` (`int`)

**Returns:** `Builder instance`

---

### `->maxLength()`

Set maximum length for string types.
**Parameters:**

- `$maxLength` (`int`)

**Returns:** `Builder instance`

---

### `->pattern()`

Set pattern (regex) for string types.
**Parameters:**

- `$pattern` (`string`)

**Returns:** `Builder instance`

---

### `->items()`

Set the items schema for array type.
**Parameters:**

- `$items` (`array`)

**Returns:** `Builder instance`

---

### `->minItems()`

Set minimum items for array types.
**Parameters:**

- `$minItems` (`int`)

**Returns:** `Builder instance`

---

### `->maxItems()`

Set maximum items for array types.
**Parameters:**

- `$maxItems` (`int`)

**Returns:** `Builder instance`

---

### `->uniqueItems()`

Set unique items constraint for array types.
**Parameters:**

- `$uniqueItems` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->ref()`

Set a reference to another schema.
**Parameters:**

- `$ref` (`string`)

**Returns:** `Builder instance`

---

### `->readOnly()`

Mark property as read-only.
**Parameters:**

- `$readOnly` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->writeOnly()`

Mark property as write-only.
**Parameters:**

- `$writeOnly` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->deprecated()`

Mark property as deprecated.
**Parameters:**

- `$deprecated` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->title()`

Set title for this property.
**Parameters:**

- `$title` (`string`)

**Returns:** `Builder instance`

---

### `->externalDocs()`

Set external documentation for this property.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->additionalProperties()`

Set additional properties constraint for object type.
**Parameters:**

- `$additionalProperties` (`mixed`) (optional, default: `false`)

**Returns:** `Builder instance`

---

### `->custom()`

Add a custom property to the definition. Use this for any OpenAPI property not covered by the builder methods.
**Parameters:**

- `$key` (`string`)
- `$value` (`mixed`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this property and return to the schema builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\SchemaBuilder`

---

### `->getPropertyName()`

Get the property name.

**Returns:** `string`

---

### `->buildArray()`

Build the property definition as an array.

**Returns:** `array`

---

## RequestBodyBuilder

Fluent builder for defining request bodies.

### `->description()`

Set the request body description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->required()`

Mark the request body as required.
**Parameters:**

- `$required` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->jsonContent()`

Start building JSON content for the request body.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ContentBuilder`

---

### `->content()`

Start building content for a specific media type.
**Parameters:**

- `$mediaType` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ContentBuilder`

---

### `->end()`

Finish building this request body and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RouteBuilder|Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->buildArray()`

Build the request body definition as an array.

**Returns:** `array`

---

## ResponseBuilder

Fluent builder for defining API responses.

### `->description()`

Set the response description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->jsonContent()`

Start building JSON content for the response.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ContentBuilder`

---

### `->content()`

Start building content for a specific media type.
**Parameters:**

- `$mediaType` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ContentBuilder`

---

### `->header()`

Start building a response header using the fluent builder. Common headers: X-Rate-Limit-Limit, X-Rate-Limit-Remaining, X-Rate-Limit-Reset, X-Request-ID, ETag, Last-Modified, Location, Retry-After, X-Total-Count
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\HeaderBuilder`

---

### `->headerArray()`

Add a response header using an array definition.
**Parameters:**

- `$name` (`string`)
- `$definition` (`array`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this response and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RouteBuilder|Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->link()`

Add a link to another operation. Links allow outgoing requests to other operations for request/response pairs.
**Parameters:**

- `$name` (`string`)
- `$link` (`array`)

**Returns:** `Builder instance`

---

### `->getStatusCode()`

Get the status code for this response.

**Returns:** `int`

---

### `->buildArray()`

Build the response definition as an array.

**Returns:** `array`

---

## RouteBuilder

Fluent builder for defining API route/path operations.

### `->path()`

Set the path for this route.
**Parameters:**

- `$path` (`string`)

**Returns:** `Builder instance`

---

### `->method()`

Set the HTTP method for this route.
**Parameters:**

- `$method` (`string`)

**Returns:** `Builder instance`

---

### `->operationId()`

Set the operation ID.
**Parameters:**

- `$operationId` (`string`)

**Returns:** `Builder instance`

---

### `->summary()`

Set the summary for this route.
**Parameters:**

- `$summary` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set the description for this route.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->tag()`

Add a tag to this route.
**Parameters:**

- `$tag` (`string`)

**Returns:** `Builder instance`

---

### `->security()`

Add a security requirement to this route.
**Parameters:**

- `$schemeName` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->noSecurity()`

Mark this route as not requiring any authentication. Useful to override global security for public endpoints.

**Returns:** `Builder instance`

---

### `->deprecated()`

Mark this operation as deprecated.
**Parameters:**

- `$deprecated` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->externalDocs()`

Set external documentation for this operation.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->callback()`

Add a callback for this operation.
**Parameters:**

- `$name` (`string`)
- `$callback` (`array`)

**Returns:** `Builder instance`

---

### `->server()`

Add an alternative server for this operation.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)
- `$variables` (`array`) (optional, default: `array (
)`)

**Returns:** `Builder instance`

---

### `->parameter()`

Start building a parameter for this route.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ParameterBuilder`

---

### `->requestBody()`

Start building a request body for this route.

**Returns:** `Ehyiah\ApiDocBundle\Builder\RequestBodyBuilder`

---

### `->response()`

Start building a response for this route.
**Parameters:**

- `$statusCode` (`int`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\ResponseBuilder`

---

### `->end()`

Finish building this route and return to the main builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->getApiDocBuilder()`

Get the root ApiDocBuilder instance.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

## SchemaBuilder

Fluent builder for defining OpenAPI schemas.

### `->type()`

Set the schema type. OpenAPI types: - 'string': Text data - 'integer': Whole numbers (use format 'int32' or 'int64' for precision) - 'number': Floating-point numbers (use format 'float' or 'double' for precision) - 'boolean': true/false values - 'array': List of items (define items schema with items() method) - 'object': Key-value structure (define properties with addProperty() or property())
**Parameters:**

- `$type` (`string`)

**Returns:** `Builder instance`

---

### `->setRefName()`

Set a custom reference name for this schema. This allows you to use a short alias to reference this schema elsewhere. Example: $builder->addSchema('ProductEntity') ->setRefName('Product')  // Create alias 'Product' ->type('object') ... Then elsewhere: ->jsonContent()->refByName('Product')->end()
**Parameters:**

- `$refName` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set the schema description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->property()`

Add a property to the schema using an array definition. Use this for complex cases or when you need full control over the schema.
**Parameters:**

- `$name` (`string`)
- `$schema` (`array`)

**Returns:** `Builder instance`

---

### `->addProperty()`

Add a property to the schema using a fluent builder. Provides IDE autocompletion for property definition. Example: ->addProperty('age') ->type('integer') ->nullable() ->example(30) ->end()
**Parameters:**

- `$name` (`string`)

**Returns:** `Ehyiah\ApiDocBundle\Builder\PropertyBuilder`

---

### `->required()`

Set required fields for this schema.
**Parameters:**

- `$fields` (`array`)

**Returns:** `Builder instance`

---

### `->format()`

Set the format for this schema. Common formats by type: - string: 'date', 'date-time', 'password', 'byte', 'binary', 'email', 'uuid', 'uri', 'hostname', 'ipv4', 'ipv6' - integer: 'int32', 'int64' - number: 'float', 'double'
**Parameters:**

- `$format` (`string`)

**Returns:** `Builder instance`

---

### `->ref()`

Set a reference to another schema.
**Parameters:**

- `$ref` (`string`)

**Returns:** `Builder instance`

---

### `->items()`

Set the items schema for array type.
**Parameters:**

- `$items` (`array`)

**Returns:** `Builder instance`

---

### `->minimum()`

Set minimum value for numeric types.
**Parameters:**

- `$min` (`mixed`)

**Returns:** `Builder instance`

---

### `->maximum()`

Set maximum value for numeric types.
**Parameters:**

- `$max` (`mixed`)

**Returns:** `Builder instance`

---

### `->minLength()`

Set minimum length for string types.
**Parameters:**

- `$minLength` (`int`)

**Returns:** `Builder instance`

---

### `->maxLength()`

Set maximum length for string types.
**Parameters:**

- `$maxLength` (`int`)

**Returns:** `Builder instance`

---

### `->pattern()`

Set pattern (regex) for string types.
**Parameters:**

- `$pattern` (`string`)

**Returns:** `Builder instance`

---

### `->enum()`

Set enum values.
**Parameters:**

- `$values` (`array`)

**Returns:** `Builder instance`

---

### `->defaultValue()`

Set a default value.
**Parameters:**

- `$default` (`mixed`)

**Returns:** `Builder instance`

---

### `->example()`

Set an example value.
**Parameters:**

- `$example` (`mixed`)

**Returns:** `Builder instance`

---

### `->nullable()`

Mark field as nullable.
**Parameters:**

- `$nullable` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->readOnly()`

Mark field as read-only.
**Parameters:**

- `$readOnly` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->writeOnly()`

Mark field as write-only.
**Parameters:**

- `$writeOnly` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->deprecated()`

Mark schema as deprecated.
**Parameters:**

- `$deprecated` (`bool`) (optional, default: `true`)

**Returns:** `Builder instance`

---

### `->title()`

Set the schema title.
**Parameters:**

- `$title` (`string`)

**Returns:** `Builder instance`

---

### `->externalDocs()`

Set external documentation for this schema.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->constValue()`

Set a constant value for this schema.
**Parameters:**

- `$value` (`mixed`)

**Returns:** `Builder instance`

---

### `->allOf()`

Set allOf composition (AND).
**Parameters:**

- `$schemas` (`array`)

**Returns:** `Builder instance`

---

### `->anyOf()`

Set anyOf composition (OR).
**Parameters:**

- `$schemas` (`array`)

**Returns:** `Builder instance`

---

### `->oneOf()`

Set oneOf composition (XOR).
**Parameters:**

- `$schemas` (`array`)

**Returns:** `Builder instance`

---

### `->not()`

Set not composition (NOT).
**Parameters:**

- `$schema` (`array`)

**Returns:** `Builder instance`

---

### `->additionalProperties()`

Set additional properties constraint.
**Parameters:**

- `$additionalProperties` (`mixed`) (optional, default: `false`)

**Returns:** `Builder instance`

---

### `->minProperties()`

Set minimum number of properties for object type.
**Parameters:**

- `$minProperties` (`int`)

**Returns:** `Builder instance`

---

### `->maxProperties()`

Set maximum number of properties for object type.
**Parameters:**

- `$maxProperties` (`int`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this schema and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder|Ehyiah\ApiDocBundle\Builder\ContentBuilder|Ehyiah\ApiDocBundle\Builder\ResponseBuilder|null`

---

### `->buildSchemaArray()`

Build the schema definition as an array.

**Returns:** `array`

---

## SecuritySchemeBuilder

Fluent builder for defining OpenAPI security schemes. Security schemes define the authentication methods available for the API.

### `->bearer()`

Configure as a Bearer token authentication (JWT).
**Parameters:**

- `$bearerFormat` (`string`) (optional, default: `'JWT'`)

**Returns:** `Builder instance`

---

### `->basic()`

Configure as Basic authentication.

**Returns:** `Builder instance`

---

### `->apiKey()`

Configure as an API Key authentication.
**Parameters:**

- `$keyName` (`string`)
- `$in` (`string`) (optional, default: `'header'`)

**Returns:** `Builder instance`

---

### `->oauth2()`

Configure as OAuth2 authentication.
**Parameters:**

- `$flows` (`array`)

**Returns:** `Builder instance`

---

### `->oauth2AuthorizationCode()`

Configure OAuth2 with Authorization Code flow.
**Parameters:**

- `$authorizationUrl` (`string`)
- `$tokenUrl` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)
- `$refreshUrl` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->oauth2ClientCredentials()`

Configure OAuth2 with Client Credentials flow.
**Parameters:**

- `$tokenUrl` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)
- `$refreshUrl` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->oauth2Implicit()`

Configure OAuth2 with Implicit flow.
**Parameters:**

- `$authorizationUrl` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)
- `$refreshUrl` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->oauth2Password()`

Configure OAuth2 with Password flow.
**Parameters:**

- `$tokenUrl` (`string`)
- `$scopes` (`array`) (optional, default: `array (
)`)
- `$refreshUrl` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->openIdConnect()`

Configure as OpenID Connect authentication.
**Parameters:**

- `$openIdConnectUrl` (`string`)

**Returns:** `Builder instance`

---

### `->description()`

Set a description for this security scheme.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this security scheme and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->buildArray()`

Build the security scheme definition as an array.

**Returns:** `array`

---

## TagBuilder

Fluent builder for defining OpenAPI tags. Tags are used to group operations in the Swagger UI.

### `->description()`

Set the tag description.
**Parameters:**

- `$description` (`string`)

**Returns:** `Builder instance`

---

### `->externalDocs()`

Set external documentation for this tag.
**Parameters:**

- `$url` (`string`)
- `$description` (`string`) (optional, default: `NULL`)

**Returns:** `Builder instance`

---

### `->end()`

Finish building this tag and return to the parent builder.

**Returns:** `Ehyiah\ApiDocBundle\Builder\ApiDocBuilder`

---

### `->buildArray()`

Build the tag definition as an array.

**Returns:** `array`

---
