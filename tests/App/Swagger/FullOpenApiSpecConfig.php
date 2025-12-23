<?php

/**
 * Complete OpenAPI 3.0 Specification Example
 *
 * This file demonstrates ALL features supported by the fluent API builder,
 * showcasing the full OpenAPI 3.0 specification capabilities.
 */

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger;

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

class FullOpenApiSpecConfig implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $this->configureInfo($builder);
        $this->configureSecuritySchemes($builder);
        $this->configureTags($builder);
        $this->configureSchemas($builder);
        $this->configureRoutes($builder);
    }

    private function configureInfo(ApiDocBuilder $builder): void
    {
        $builder
            ->info()
                ->openApiVersion('3.0.0')
                ->title('Complete OpenAPI 3.0 Demo API')
                ->description('This API demonstrates **all** OpenAPI 3.0 features supported by the fluent builder.

## Features
- Multiple authentication methods
- All HTTP methods
- All parameter types
- All schema types and constraints
- Multiple content types')
                ->version('1.0.0')
                ->termsOfService('https://example.com/terms')
                ->contact('API Team', 'api@example.com', 'https://example.com/contact')
                ->license('Apache 2.0', 'https://www.apache.org/licenses/LICENSE-2.0.html')
                ->server('https://api.example.com/v1', 'Production server')
                ->server('https://staging-api.example.com/v1', 'Staging server')
                ->server('https://{environment}.example.com:{port}/v1', 'Dynamic server', [
                    'environment' => [
                        'default' => 'api',
                        'enum' => ['api', 'api-staging', 'api-dev'],
                        'description' => 'Server environment',
                    ],
                    'port' => [
                        'default' => '443',
                        'enum' => ['443', '8443'],
                    ],
                ])
                ->addSecurityRequirement('BearerAuth')
            ->end()
        ;
    }

    private function configureSecuritySchemes(ApiDocBuilder $builder): void
    {
        $builder
            ->addSecurityScheme('BearerAuth')
                ->bearer('JWT')
                ->description('JWT Bearer token authentication. Obtain token from /auth/login endpoint.')
            ->end()
        ;

        $builder
            ->addSecurityScheme('BasicAuth')
                ->basic()
                ->description('HTTP Basic authentication')
            ->end()
        ;

        $builder
            ->addSecurityScheme('ApiKeyHeader')
                ->apiKey('X-API-Key', 'header')
                ->description('API key passed in X-API-Key header')
            ->end()
        ;

        $builder
            ->addSecurityScheme('ApiKeyQuery')
                ->apiKey('api_key', 'query')
                ->description('API key passed as query parameter')
            ->end()
        ;

        $builder
            ->addSecurityScheme('ApiKeyCookie')
                ->apiKey('session_id', 'cookie')
                ->description('Session ID in cookie')
            ->end()
        ;

        $builder
            ->addSecurityScheme('OAuth2AuthCode')
                ->oauth2AuthorizationCode(
                    'https://example.com/oauth/authorize',
                    'https://example.com/oauth/token',
                    [
                        'read:users' => 'Read user information',
                        'write:users' => 'Modify user information',
                        'admin' => 'Full administrative access',
                    ],
                    'https://example.com/oauth/refresh'
                )
                ->description('OAuth2 with Authorization Code flow')
            ->end()
        ;

        $builder
            ->addSecurityScheme('OAuth2ClientCreds')
                ->oauth2ClientCredentials(
                    'https://example.com/oauth/token',
                    [
                        'read:system' => 'Read system data',
                        'write:system' => 'Write system data',
                    ]
                )
                ->description('OAuth2 Client Credentials for server-to-server communication')
            ->end()
        ;

        $builder
            ->addSecurityScheme('OAuth2Implicit')
                ->oauth2Implicit(
                    'https://example.com/oauth/authorize',
                    [
                        'read:public' => 'Read public data',
                    ]
                )
                ->description('OAuth2 Implicit flow (for legacy SPA applications)')
            ->end()
        ;

        $builder
            ->addSecurityScheme('OAuth2Password')
                ->oauth2Password(
                    'https://example.com/oauth/token',
                    [
                        'read:own' => 'Read own data',
                        'write:own' => 'Write own data',
                    ]
                )
                ->description('OAuth2 Resource Owner Password Credentials')
            ->end()
        ;

        $builder
            ->addSecurityScheme('OpenIdConnect')
                ->openIdConnect('https://example.com/.well-known/openid-configuration')
                ->description('OpenID Connect Discovery')
            ->end()
        ;
    }

    private function configureTags(ApiDocBuilder $builder): void
    {
        $builder
            ->addTag('Authentication')
                ->description('Authentication and authorization endpoints')
            ->end()
            ->addTag('Users')
                ->description('User management operations')
                ->externalDocs('https://example.com/docs/users', 'User API documentation')
            ->end()
            ->addTag('Products')
                ->description('Product catalog management')
                ->externalDocs('https://example.com/docs/products', 'Product API guide')
            ->end()
            ->addTag('Orders')
                ->description('Order processing and management')
            ->end()
        ;
    }

    private function configureSchemas(ApiDocBuilder $builder): void
    {
        // Error schema - common error response
        $builder
            ->addSchema('Error')
                ->setRefName('Error')
                ->type('object')
                ->description('Standard error response')
                ->addProperty('code')
                    ->type('integer')
                    ->format('int32')
                    ->description('Error code')
                    ->example(400)
                ->end()
                ->addProperty('message')
                    ->type('string')
                    ->description('Human-readable error message')
                    ->example('Validation failed')
                ->end()
                ->addProperty('details')
                    ->type('array')
                    ->description('Detailed error information')
                    ->items(['type' => 'string'])
                    ->nullable()
                ->end()
                ->addProperty('timestamp')
                    ->type('string')
                    ->format('date-time')
                    ->description('When the error occurred')
                    ->readOnly()
                ->end()
                ->required(['code', 'message'])
            ->end()
        ;

        $builder
            ->addSchema('Address')
                ->setRefName('Address')
                ->type('object')
                ->description('Physical address')
                ->addProperty('street')
                    ->type('string')
                    ->minLength(1)
                    ->maxLength(200)
                    ->example('123 Main Street')
                ->end()
                ->addProperty('city')
                    ->type('string')
                    ->minLength(1)
                    ->maxLength(100)
                    ->example('New York')
                ->end()
                ->addProperty('state')
                    ->type('string')
                    ->minLength(2)
                    ->maxLength(50)
                    ->example('NY')
                ->end()
                ->addProperty('postalCode')
                    ->type('string')
                    ->pattern('^[0-9]{5}(-[0-9]{4})?$')
                    ->description('US ZIP code format')
                    ->example('10001')
                ->end()
                ->addProperty('country')
                    ->type('string')
                    ->minLength(2)
                    ->maxLength(2)
                    ->description('ISO 3166-1 alpha-2 country code')
                    ->example('US')
                    ->defaultValue('US')
                ->end()
                ->required(['street', 'city', 'postalCode', 'country'])
            ->end()
        ;

        // User schema - comprehensive user model
        $builder
            ->addSchema('User')
                ->setRefName('User')
                ->type('object')
                ->description('User account information')
                ->addProperty('id')
                    ->type('integer')
                    ->format('int64')
                    ->description('Unique user identifier')
                    ->readOnly()
                    ->example(12345)
                ->end()
                ->addProperty('uuid')
                    ->type('string')
                    ->format('uuid')
                    ->description('UUID v4 identifier')
                    ->readOnly()
                    ->example('550e8400-e29b-41d4-a716-446655440000')
                ->end()
                ->addProperty('username')
                    ->type('string')
                    ->minLength(3)
                    ->maxLength(50)
                    ->pattern('^[a-zA-Z0-9_]+$')
                    ->description('Unique username (alphanumeric and underscores only)')
                    ->example('john_doe')
                ->end()
                ->addProperty('email')
                    ->type('string')
                    ->format('email')
                    ->description('User email address')
                    ->example('john.doe@example.com')
                ->end()
                ->addProperty('password')
                    ->type('string')
                    ->format('password')
                    ->minLength(8)
                    ->maxLength(128)
                    ->description('User password (write-only, never returned)')
                    ->writeOnly()
                ->end()
                ->addProperty('firstName')
                    ->type('string')
                    ->minLength(1)
                    ->maxLength(100)
                    ->nullable()
                    ->example('John')
                ->end()
                ->addProperty('lastName')
                    ->type('string')
                    ->minLength(1)
                    ->maxLength(100)
                    ->nullable()
                    ->example('Doe')
                ->end()
                ->addProperty('age')
                    ->type('integer')
                    ->format('int32')
                    ->minimum(0)
                    ->maximum(150)
                    ->nullable()
                    ->example(30)
                ->end()
                ->addProperty('balance')
                    ->type('number')
                    ->format('double')
                    ->minimum(0)
                    ->description('Account balance in USD')
                    ->example(1234.56)
                ->end()
                ->addProperty('rating')
                    ->type('number')
                    ->format('float')
                    ->minimum(0)
                    ->maximum(5)
                    ->multipleOf(0.5)
                    ->description('User rating (0-5, increments of 0.5)')
                    ->example(4.5)
                ->end()
                ->addProperty('isActive')
                    ->type('boolean')
                    ->description('Whether the user account is active')
                    ->defaultValue(true)
                    ->example(true)
                ->end()
                ->addProperty('isVerified')
                    ->type('boolean')
                    ->description('Whether the email is verified')
                    ->readOnly()
                    ->example(false)
                ->end()
                ->addProperty('role')
                    ->typeStringEnum(['user', 'moderator', 'admin', 'superadmin'])
                    ->description('User role')
                    ->defaultValue('user')
                    ->example('user')
                ->end()
                ->addProperty('status')
                    ->typeIntegerEnum([0, 1, 2, 3])
                    ->description('User status: 0=pending, 1=active, 2=suspended, 3=deleted')
                    ->example(1)
                ->end()
                ->addProperty('permissions')
                    ->typeArrayOfStringEnum(['read', 'write', 'delete', 'admin'])
                    ->description('User permissions')
                    ->minItems(1)
                    ->uniqueItems()
                    ->example(['read', 'write'])
                ->end()
                ->addProperty('tags')
                    ->type('array')
                    ->items(['type' => 'string', 'minLength' => 1, 'maxLength' => 50])
                    ->minItems(0)
                    ->maxItems(10)
                    ->uniqueItems()
                    ->description('User tags for categorization')
                    ->example(['premium', 'early-adopter'])
                ->end()
                ->addProperty('metadata')
                    ->type('object')
                    ->description('Additional user metadata (free-form)')
                    ->nullable()
                    ->example(['preferences' => ['theme' => 'dark'], 'source' => 'mobile'])
                ->end()
                ->addProperty('address')
                    ->ref('#/components/schemas/Address')
                    ->description('User primary address')
                ->end()
                ->addProperty('profilePictureUrl')
                    ->type('string')
                    ->format('uri')
                    ->nullable()
                    ->description('URL to profile picture')
                    ->example('https://example.com/avatars/john_doe.jpg')
                ->end()
                ->addProperty('website')
                    ->type('string')
                    ->format('uri')
                    ->nullable()
                    ->example('https://johndoe.com')
                ->end()
                ->addProperty('ipAddress')
                    ->type('string')
                    ->format('ipv4')
                    ->readOnly()
                    ->description('Last known IP address')
                    ->example('192.168.1.1')
                ->end()
                ->addProperty('birthDate')
                    ->type('string')
                    ->format('date')
                    ->nullable()
                    ->description('Date of birth (YYYY-MM-DD)')
                    ->example('1990-05-15')
                ->end()
                ->addProperty('createdAt')
                    ->type('string')
                    ->format('date-time')
                    ->readOnly()
                    ->description('Account creation timestamp')
                    ->example('2024-01-15T10:30:00Z')
                ->end()
                ->addProperty('updatedAt')
                    ->type('string')
                    ->format('date-time')
                    ->readOnly()
                    ->description('Last update timestamp')
                    ->example('2024-06-20T14:45:00Z')
                ->end()
                ->addProperty('deletedAt')
                    ->type('string')
                    ->format('date-time')
                    ->nullable()
                    ->readOnly()
                    ->description('Soft delete timestamp')
                ->end()
                ->addProperty('legacyField')
                    ->type('string')
                    ->deprecated()
                    ->description('This field is deprecated and will be removed in v2.0')
                    ->nullable()
                ->end()
                ->required(['username', 'email'])
            ->end()
        ;

        // UserCreateRequest - for POST requests
        $builder
            ->addSchema('UserCreateRequest')
                ->setRefName('UserCreateRequest')
                ->type('object')
                ->description('Request body for creating a new user')
                ->addProperty('username')
                    ->type('string')
                    ->minLength(3)
                    ->maxLength(50)
                    ->pattern('^[a-zA-Z0-9_]+$')
                ->end()
                ->addProperty('email')
                    ->type('string')
                    ->format('email')
                ->end()
                ->addProperty('password')
                    ->type('string')
                    ->format('password')
                    ->minLength(8)
                ->end()
                ->addProperty('firstName')
                    ->type('string')
                    ->nullable()
                ->end()
                ->addProperty('lastName')
                    ->type('string')
                    ->nullable()
                ->end()
                ->required(['username', 'email', 'password'])
            ->end()
        ;

        // Product schema
        $builder
            ->addSchema('Product')
                ->setRefName('Product')
                ->type('object')
                ->description('Product in the catalog')
                ->addProperty('id')
                    ->type('integer')
                    ->format('int64')
                    ->readOnly()
                ->end()
                ->addProperty('sku')
                    ->type('string')
                    ->pattern('^[A-Z]{3}-[0-9]{6}$')
                    ->description('Stock Keeping Unit')
                    ->example('PRD-123456')
                ->end()
                ->addProperty('name')
                    ->type('string')
                    ->minLength(1)
                    ->maxLength(200)
                ->end()
                ->addProperty('description')
                    ->type('string')
                    ->maxLength(5000)
                    ->nullable()
                ->end()
                ->addProperty('price')
                    ->type('number')
                    ->format('double')
                    ->minimum(0)
                    ->exclusiveMinimum(0)
                    ->description('Price must be greater than 0')
                ->end()
                ->addProperty('currency')
                    ->typeStringEnum(['USD', 'EUR', 'GBP', 'JPY'])
                    ->defaultValue('USD')
                ->end()
                ->addProperty('quantity')
                    ->type('integer')
                    ->format('int32')
                    ->minimum(0)
                    ->description('Available stock quantity')
                ->end()
                ->addProperty('weight')
                    ->type('number')
                    ->format('float')
                    ->minimum(0)
                    ->description('Weight in kilograms')
                    ->nullable()
                ->end()
                ->addProperty('categories')
                    ->type('array')
                    ->items(['type' => 'string'])
                    ->minItems(1)
                    ->example(['Electronics', 'Computers'])
                ->end()
                ->addProperty('images')
                    ->type('array')
                    ->items([
                        'type' => 'object',
                        'properties' => [
                            'url' => ['type' => 'string', 'format' => 'uri'],
                            'alt' => ['type' => 'string'],
                            'isPrimary' => ['type' => 'boolean', 'default' => false],
                        ],
                        'required' => ['url'],
                    ])
                    ->description('Product images')
                ->end()
                ->addProperty('specifications')
                    ->type('object')
                    ->description('Technical specifications (key-value pairs)')
                    ->custom('additionalProperties', ['type' => 'string'])
                    ->example(['color' => 'black', 'size' => 'large'])
                ->end()
                ->required(['sku', 'name', 'price', 'quantity'])
            ->end()
        ;

        // Pagination schema
        $builder
            ->addSchema('PaginationMeta')
                ->setRefName('PaginationMeta')
                ->type('object')
                ->description('Pagination metadata')
                ->addProperty('currentPage')
                    ->type('integer')
                    ->minimum(1)
                ->end()
                ->addProperty('perPage')
                    ->type('integer')
                    ->minimum(1)
                    ->maximum(100)
                ->end()
                ->addProperty('totalItems')
                    ->type('integer')
                    ->minimum(0)
                ->end()
                ->addProperty('totalPages')
                    ->type('integer')
                    ->minimum(0)
                ->end()
                ->addProperty('hasNextPage')
                    ->type('boolean')
                ->end()
                ->addProperty('hasPreviousPage')
                    ->type('boolean')
                ->end()
                ->required(['currentPage', 'perPage', 'totalItems', 'totalPages'])
            ->end()
        ;

        // Paginated User List
        $builder
            ->addSchema('UserList')
                ->setRefName('UserList')
                ->type('object')
                ->description('Paginated list of users')
                ->addProperty('data')
                    ->type('array')
                    ->items(['$ref' => '#/components/schemas/User'])
                ->end()
                ->addProperty('meta')
                    ->ref('#/components/schemas/PaginationMeta')
                ->end()
                ->required(['data', 'meta'])
            ->end()
        ;

        // File Upload response
        $builder
            ->addSchema('FileUploadResponse')
                ->setRefName('FileUploadResponse')
                ->type('object')
                ->addProperty('id')
                    ->type('string')
                    ->format('uuid')
                ->end()
                ->addProperty('filename')
                    ->type('string')
                ->end()
                ->addProperty('mimeType')
                    ->type('string')
                ->end()
                ->addProperty('size')
                    ->type('integer')
                    ->format('int64')
                    ->description('File size in bytes')
                ->end()
                ->addProperty('url')
                    ->type('string')
                    ->format('uri')
                ->end()
                ->addProperty('checksum')
                    ->type('string')
                    ->description('MD5 checksum')
                ->end()
                ->required(['id', 'filename', 'mimeType', 'size', 'url'])
            ->end()
        ;

        // Schema with allOf (inheritance example)
        $builder
            ->addSchema('AdminUser')
                ->setRefName('AdminUser')
                ->property('allOf', [
                    ['$ref' => '#/components/schemas/User'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'adminLevel' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 5,
                            ],
                            'managedDepartments' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                        ],
                        'required' => ['adminLevel'],
                    ],
                ])
                ->description('Admin user extending base User schema')
            ->end()
        ;

        // Schema with oneOf (polymorphism)
        $builder
            ->addSchema('PaymentMethod')
                ->setRefName('PaymentMethod')
                ->property('oneOf', [
                    [
                        'type' => 'object',
                        'title' => 'CreditCard',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['credit_card']],
                            'cardNumber' => ['type' => 'string', 'pattern' => '^[0-9]{16}$'],
                            'expiryMonth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12],
                            'expiryYear' => ['type' => 'integer'],
                            'cvv' => ['type' => 'string', 'pattern' => '^[0-9]{3,4}$'],
                        ],
                        'required' => ['type', 'cardNumber', 'expiryMonth', 'expiryYear', 'cvv'],
                    ],
                    [
                        'type' => 'object',
                        'title' => 'BankTransfer',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['bank_transfer']],
                            'iban' => ['type' => 'string'],
                            'bic' => ['type' => 'string'],
                        ],
                        'required' => ['type', 'iban'],
                    ],
                    [
                        'type' => 'object',
                        'title' => 'PayPal',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['paypal']],
                            'email' => ['type' => 'string', 'format' => 'email'],
                        ],
                        'required' => ['type', 'email'],
                    ],
                ])
                ->property('discriminator', [
                    'propertyName' => 'type',
                    'mapping' => [
                        'credit_card' => '#/components/schemas/PaymentMethod/oneOf/0',
                        'bank_transfer' => '#/components/schemas/PaymentMethod/oneOf/1',
                        'paypal' => '#/components/schemas/PaymentMethod/oneOf/2',
                    ],
                ])
                ->description('Payment method (credit card, bank transfer, or PayPal)')
            ->end()
        ;

        // Schema with anyOf
        $builder
            ->addSchema('SearchFilter')
                ->setRefName('SearchFilter')
                ->property('anyOf', [
                    [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => ['eq', 'neq', 'gt', 'lt', 'gte', 'lte']],
                            'value' => ['type' => 'string'],
                        ],
                    ],
                    [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => ['in', 'nin']],
                            'values' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                    [
                        'type' => 'object',
                        'properties' => [
                            'field' => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => ['like', 'ilike']],
                            'pattern' => ['type' => 'string'],
                        ],
                    ],
                ])
                ->description('Search filter supporting different operators')
            ->end()
        ;
    }

    private function configureRoutes(ApiDocBuilder $builder): void
    {
        $this->configureAuthRoutes($builder);
        $this->configureUserRoutes($builder);
        $this->configureProductRoutes($builder);
        $this->configureFileUploadRoute($builder);
    }

    private function configureAuthRoutes(ApiDocBuilder $builder): void
    {
        // POST /auth/login - Public endpoint (no security)
        $builder
            ->addRoute()
                ->path('/auth/login')
                ->method('POST')
                ->operationId('login')
                ->summary('Authenticate user')
                ->description('Authenticate with username/password and receive JWT token')
                ->tag('Authentication')
                ->noSecurity()
                ->requestBody()
                    ->description('Login credentials')
                    ->required()
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->addProperty('username')
                                ->type('string')
                                ->example('john_doe')
                            ->end()
                            ->addProperty('password')
                                ->type('string')
                                ->format('password')
                            ->end()
                            ->addProperty('rememberMe')
                                ->type('boolean')
                                ->defaultValue(false)
                            ->end()
                            ->required(['username', 'password'])
                        ->end()
                    ->end()
                ->end()
                ->response(200)
                    ->description('Successfully authenticated')
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->addProperty('accessToken')
                                ->type('string')
                                ->description('JWT access token')
                            ->end()
                            ->addProperty('refreshToken')
                                ->type('string')
                                ->description('Refresh token for obtaining new access tokens')
                            ->end()
                            ->addProperty('expiresIn')
                                ->type('integer')
                                ->description('Token validity in seconds')
                                ->example(3600)
                            ->end()
                            ->addProperty('tokenType')
                                ->type('string')
                                ->enum(['Bearer'])
                                ->example('Bearer')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->response(401)
                    ->description('Invalid credentials')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(429)
                    ->description('Too many login attempts')
                    ->header('Retry-After')
                        ->description('Number of seconds to wait before retrying')
                        ->typeInteger('int32')
                        ->minimum(1)
                        ->example(60)
                    ->end()
                    ->header('X-Rate-Limit-Limit')
                        ->description('Maximum number of login attempts per hour')
                        ->typeInteger('int32')
                        ->example(5)
                    ->end()
                    ->header('X-Rate-Limit-Remaining')
                        ->description('Remaining login attempts')
                        ->typeInteger('int32')
                        ->example(0)
                    ->end()
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function configureUserRoutes(ApiDocBuilder $builder): void
    {
        // GET /users - List users with all parameter types
        $builder
            ->addRoute()
                ->path('/users')
                ->method('GET')
                ->operationId('listUsers')
                ->summary('List all users')
                ->description('Retrieve a paginated list of users with optional filtering and sorting')
                ->tag('Users')
                ->security('BearerAuth')
                ->security('OAuth2AuthCode', ['read:users'])
                // Query parameters - pagination
                ->parameter()
                    ->name('page')
                    ->in('query')
                    ->description('Page number (1-based)')
                    ->schema(['type' => 'integer', 'minimum' => 1, 'default' => 1])
                    ->example(1)
                ->end()
                ->parameter()
                    ->name('perPage')
                    ->in('query')
                    ->description('Items per page')
                    ->schema(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20])
                    ->example(20)
                ->end()
                // Query parameters - filtering
                ->parameter()
                    ->name('status')
                    ->in('query')
                    ->description('Filter by user status')
                    ->schema(['type' => 'string', 'enum' => ['active', 'inactive', 'pending', 'suspended']])
                ->end()
                ->parameter()
                    ->name('role')
                    ->in('query')
                    ->description('Filter by role (can specify multiple)')
                    ->schema([
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['user', 'moderator', 'admin']],
                    ])
                ->end()
                ->parameter()
                    ->name('search')
                    ->in('query')
                    ->description('Search in username, email, first name, last name')
                    ->schema(['type' => 'string', 'minLength' => 2])
                ->end()
                ->parameter()
                    ->name('createdAfter')
                    ->in('query')
                    ->description('Filter users created after this date')
                    ->schema(['type' => 'string', 'format' => 'date-time'])
                ->end()
                ->parameter()
                    ->name('createdBefore')
                    ->in('query')
                    ->description('Filter users created before this date')
                    ->schema(['type' => 'string', 'format' => 'date-time'])
                ->end()
                // Query parameters - sorting
                ->parameter()
                    ->name('sortBy')
                    ->in('query')
                    ->description('Field to sort by')
                    ->schema(['type' => 'string', 'enum' => ['createdAt', 'username', 'email', 'lastName'], 'default' => 'createdAt'])
                ->end()
                ->parameter()
                    ->name('sortOrder')
                    ->in('query')
                    ->description('Sort direction')
                    ->schema(['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'])
                ->end()
                // Header parameters
                ->parameter()
                    ->name('X-Request-ID')
                    ->in('header')
                    ->description('Unique request identifier for tracing')
                    ->schema(['type' => 'string', 'format' => 'uuid'])
                ->end()
                ->parameter()
                    ->name('Accept-Language')
                    ->in('header')
                    ->description('Preferred response language')
                    ->schema(['type' => 'string', 'default' => 'en'])
                    ->example('en-US')
                ->end()
                // Responses
                ->response(200)
                    ->description('List of users')
                    // Response headers for pagination and rate limiting
                    ->header('X-Total-Count')
                        ->description('Total number of items across all pages')
                        ->typeInteger('int64')
                        ->example(1234)
                    ->end()
                    ->header('X-Page-Count')
                        ->description('Total number of pages')
                        ->typeInteger('int32')
                        ->minimum(1)
                        ->example(62)
                    ->end()
                    ->header('X-Current-Page')
                        ->description('Current page number')
                        ->typeInteger('int32')
                        ->minimum(1)
                        ->example(1)
                    ->end()
                    ->header('X-Per-Page')
                        ->description('Number of items per page')
                        ->typeInteger('int32')
                        ->minimum(1)
                        ->maximum(100)
                        ->example(20)
                    ->end()
                    ->header('X-Rate-Limit-Limit')
                        ->description('The maximum number of requests allowed in the current period')
                        ->typeInteger('int32')
                        ->example(1000)
                    ->end()
                    ->header('X-Rate-Limit-Remaining')
                        ->description('The number of remaining requests in the current period')
                        ->typeInteger('int32')
                        ->minimum(0)
                        ->example(999)
                    ->end()
                    ->header('X-Rate-Limit-Reset')
                        ->description('Unix timestamp when the rate limit will reset')
                        ->typeInteger('int64')
                        ->example(1703347200)
                    ->end()
                    ->header('X-Request-ID')
                        ->description('Unique identifier for this request (for debugging/tracing)')
                        ->typeString('uuid')
                        ->example('550e8400-e29b-41d4-a716-446655440000')
                    ->end()
                    ->header('Cache-Control')
                        ->description('Caching directives')
                        ->typeString()
                        ->example('max-age=60, private')
                    ->end()
                    ->header('ETag')
                        ->description('Entity tag for cache validation')
                        ->typeString()
                        ->example('"33a64df551425fcc55e4d42a148795d9f25f89d4"')
                    ->end()
                    ->jsonContent()
                        ->refByName('UserList')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Invalid query parameters')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(401)
                    ->description('Authentication required')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(403)
                    ->description('Insufficient permissions')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // POST /users - Create user
        $builder
            ->addRoute()
                ->path('/users')
                ->method('POST')
                ->operationId('createUser')
                ->summary('Create a new user')
                ->description('Create a new user account. Requires admin privileges.')
                ->tag('Users')
                ->security('BearerAuth')
                ->security('OAuth2AuthCode', ['write:users'])
                ->requestBody()
                    ->description('User data')
                    ->required()
                    ->jsonContent()
                        ->refByName('UserCreateRequest')
                    ->end()
                ->end()
                ->response(201)
                    ->description('User created successfully')
                    ->header('Location')
                        ->description('URL of the newly created user resource')
                        ->typeString('uri')
                        ->required()
                        ->example('https://api.example.com/v1/users/12345')
                    ->end()
                    ->header('X-Request-ID')
                        ->description('Unique request identifier')
                        ->typeString('uuid')
                    ->end()
                    ->jsonContent()
                        ->refByName('User')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Validation error')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(409)
                    ->description('Username or email already exists')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // GET /users/{id} - Get user by ID (path parameter)
        $builder
            ->addRoute()
                ->path('/users/{id}')
                ->method('GET')
                ->operationId('getUserById')
                ->summary('Get user by ID')
                ->description('Retrieve a single user by their unique identifier')
                ->tag('Users')
                ->security('BearerAuth')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer', 'format' => 'int64', 'minimum' => 1])
                    ->example(12345)
                ->end()
                ->parameter()
                    ->name('include')
                    ->in('query')
                    ->description('Related resources to include')
                    ->schema([
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => ['address', 'orders', 'preferences']],
                    ])
                ->end()
                ->response(200)
                    ->description('User found')
                    ->jsonContent()
                        ->refByName('User')
                    ->end()
                ->end()
                ->response(404)
                    ->description('User not found')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // PUT /users/{id} - Full update
        $builder
            ->addRoute()
                ->path('/users/{id}')
                ->method('PUT')
                ->operationId('updateUser')
                ->summary('Update user (full replacement)')
                ->description('Replace all user data. All fields are required.')
                ->tag('Users')
                ->security('BearerAuth')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer', 'format' => 'int64'])
                ->end()
                ->requestBody()
                    ->required()
                    ->jsonContent()
                        ->refByName('UserCreateRequest')
                    ->end()
                ->end()
                ->response(200)
                    ->description('User updated')
                    ->jsonContent()
                        ->refByName('User')
                    ->end()
                ->end()
                ->response(404)
                    ->description('User not found')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // PATCH /users/{id} - Partial update
        $builder
            ->addRoute()
                ->path('/users/{id}')
                ->method('PATCH')
                ->operationId('patchUser')
                ->summary('Partially update user')
                ->description('Update specific fields of a user. Only provided fields will be updated.')
                ->tag('Users')
                ->security('BearerAuth')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer', 'format' => 'int64'])
                ->end()
                ->requestBody()
                    ->required()
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->addProperty('firstName')
                                ->type('string')
                                ->nullable()
                            ->end()
                            ->addProperty('lastName')
                                ->type('string')
                                ->nullable()
                            ->end()
                            ->addProperty('email')
                                ->type('string')
                                ->format('email')
                            ->end()
                            ->addProperty('isActive')
                                ->type('boolean')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->response(200)
                    ->description('User updated')
                    ->jsonContent()
                        ->refByName('User')
                    ->end()
                ->end()
                ->response(404)
                    ->description('User not found')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // DELETE /users/{id}
        $builder
            ->addRoute()
                ->path('/users/{id}')
                ->method('DELETE')
                ->operationId('deleteUser')
                ->summary('Delete user')
                ->description('Soft delete a user account. Can be restored within 30 days.')
                ->tag('Users')
                ->security('BearerAuth')
                ->security('OAuth2AuthCode', ['admin'])
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer', 'format' => 'int64'])
                ->end()
                ->parameter()
                    ->name('permanent')
                    ->in('query')
                    ->description('Permanently delete (cannot be undone)')
                    ->schema(['type' => 'boolean', 'default' => false])
                ->end()
                ->response(204)
                    ->description('User deleted successfully')
                ->end()
                ->response(404)
                    ->description('User not found')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // HEAD /users/{id} - Check if user exists
        $builder
            ->addRoute()
                ->path('/users/{id}')
                ->method('HEAD')
                ->operationId('checkUserExists')
                ->summary('Check if user exists')
                ->description('Returns 200 if user exists, 404 otherwise. No response body.')
                ->tag('Users')
                ->security('BearerAuth')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('User ID')
                    ->required()
                    ->schema(['type' => 'integer', 'format' => 'int64'])
                ->end()
                ->response(200)
                    ->description('User exists')
                ->end()
                ->response(404)
                    ->description('User not found')
                ->end()
            ->end()
        ;

        // OPTIONS /users - Get allowed methods
        $builder
            ->addRoute()
                ->path('/users')
                ->method('OPTIONS')
                ->operationId('getUsersOptions')
                ->summary('Get allowed methods for /users')
                ->description('Returns allowed HTTP methods and CORS headers')
                ->tag('Users')
                ->noSecurity()
                ->response(200)
                    ->description('Allowed methods')
                ->end()
            ->end()
        ;
    }

    private function configureProductRoutes(ApiDocBuilder $builder): void
    {
        // GET /products with cookie parameter
        $builder
            ->addRoute()
                ->path('/products')
                ->method('GET')
                ->operationId('listProducts')
                ->summary('List products')
                ->description('Get paginated product list with optional filters')
                ->tag('Products')
                ->security('ApiKeyHeader')
                ->parameter()
                    ->name('category')
                    ->in('query')
                    ->description('Filter by category')
                    ->schema(['type' => 'string'])
                ->end()
                ->parameter()
                    ->name('minPrice')
                    ->in('query')
                    ->description('Minimum price filter')
                    ->schema(['type' => 'number', 'format' => 'double', 'minimum' => 0])
                ->end()
                ->parameter()
                    ->name('maxPrice')
                    ->in('query')
                    ->description('Maximum price filter')
                    ->schema(['type' => 'number', 'format' => 'double', 'minimum' => 0])
                ->end()
                ->parameter()
                    ->name('inStock')
                    ->in('query')
                    ->description('Only show products in stock')
                    ->schema(['type' => 'boolean'])
                ->end()
                // Cookie parameter for user preferences
                ->parameter()
                    ->name('currency_preference')
                    ->in('cookie')
                    ->description('Preferred currency for price display')
                    ->schema(['type' => 'string', 'enum' => ['USD', 'EUR', 'GBP'], 'default' => 'USD'])
                ->end()
                ->response(200)
                    ->description('Product list')
                    ->jsonContent()
                        ->schema()
                            ->type('object')
                            ->addProperty('data')
                                ->type('array')
                                ->items(['$ref' => '#/components/schemas/Product'])
                            ->end()
                            ->addProperty('meta')
                                ->ref('#/components/schemas/PaginationMeta')
                            ->end()
                        ->end()
                    ->end()
                    // XML response as alternative
                    ->content('application/xml')
                        ->schema()
                            ->type('object')
                            ->property('products', [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Product'],
                                'xml' => ['name' => 'product', 'wrapped' => true],
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        // POST /products with multiple content types
        $builder
            ->addRoute()
                ->path('/products')
                ->method('POST')
                ->operationId('createProduct')
                ->summary('Create product')
                ->description('Create a new product in the catalog')
                ->tag('Products')
                ->security('BearerAuth')
                ->requestBody()
                    ->description('Product data')
                    ->required()
                    // JSON content
                    ->jsonContent()
                        ->refByName('Product')
                    ->end()
                    // XML content
                    ->content('application/xml')
                        ->refByName('Product')
                    ->end()
                    // Form URL encoded
                    ->content('application/x-www-form-urlencoded')
                        ->schema()
                            ->type('object')
                            ->addProperty('sku')
                                ->type('string')
                            ->end()
                            ->addProperty('name')
                                ->type('string')
                            ->end()
                            ->addProperty('price')
                                ->type('number')
                            ->end()
                            ->required(['sku', 'name', 'price'])
                        ->end()
                    ->end()
                ->end()
                ->response(201)
                    ->description('Product created')
                    ->jsonContent()
                        ->refByName('Product')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Validation error')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;
    }

    private function configureFileUploadRoute(ApiDocBuilder $builder): void
    {
        // POST /files - Multipart file upload
        $builder
            ->addRoute()
                ->path('/files')
                ->method('POST')
                ->operationId('uploadFile')
                ->summary('Upload a file')
                ->description('Upload a file using multipart/form-data. Supports images, documents, and archives.')
                ->tag('Products')
                ->security('BearerAuth')
                ->requestBody()
                    ->description('File to upload')
                    ->required()
                    ->content('multipart/form-data')
                        ->schema()
                            ->type('object')
                            ->addProperty('file')
                                ->type('string')
                                ->format('binary')
                                ->description('The file to upload')
                            ->end()
                            ->addProperty('description')
                                ->type('string')
                                ->maxLength(500)
                                ->description('Optional file description')
                            ->end()
                            ->addProperty('tags')
                                ->type('array')
                                ->items(['type' => 'string'])
                                ->description('Tags for the file')
                            ->end()
                            ->addProperty('isPublic')
                                ->type('boolean')
                                ->defaultValue(false)
                                ->description('Make file publicly accessible')
                            ->end()
                            ->required(['file'])
                        ->end()
                    ->end()
                ->end()
                ->response(201)
                    ->description('File uploaded successfully')
                    ->jsonContent()
                        ->refByName('FileUploadResponse')
                    ->end()
                ->end()
                ->response(400)
                    ->description('Invalid file')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(413)
                    ->description('File too large (max 10MB)')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
                ->response(415)
                    ->description('Unsupported file type')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;

        // GET /files/{id} - Download file (binary response)
        $builder
            ->addRoute()
                ->path('/files/{id}')
                ->method('GET')
                ->operationId('downloadFile')
                ->summary('Download a file')
                ->description('Download a previously uploaded file')
                ->tag('Products')
                ->security('BearerAuth')
                ->parameter()
                    ->name('id')
                    ->in('path')
                    ->description('File ID')
                    ->required()
                    ->schema(['type' => 'string', 'format' => 'uuid'])
                ->end()
                ->response(200)
                    ->description('File content')
                    ->content('application/octet-stream')
                        ->schema()
                            ->type('string')
                            ->format('binary')
                        ->end()
                    ->end()
                ->end()
                ->response(404)
                    ->description('File not found')
                    ->jsonContent()
                        ->refByName('Error')
                    ->end()
                ->end()
            ->end()
        ;
    }
}
