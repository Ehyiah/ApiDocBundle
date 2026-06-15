<?php

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback\CallbackTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback\CallbackTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback\CallbackTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example\ExampleTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example\ExampleTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example\ExampleTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header\HeaderTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header\HeaderTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header\HeaderTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link\LinkTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link\LinkTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link\LinkTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter\ParameterTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter\ParameterTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter\ParameterTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem\PathItemTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem\PathItemTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem\PathItemTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\RequestBody\RequestBodyTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\RequestBody\RequestBodyTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\RequestBody\RequestBodyTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response\ResponseTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response\ResponseTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response\ResponseTuiState;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security\SecuritySchemeTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security\SecuritySchemeTuiManager;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security\SecuritySchemeTuiState;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversNothing
 */
class TuiGenerationTest extends TestCase
{
    private string $tmpDir;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/apidoc_gen_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->output = new BufferedOutput();
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    public function testParameterYamlGeneration(): void
    {
        $state = new ParameterTuiState();
        $state->name = 'userId';
        $state->in = 'path';
        $state->description = 'User ID';
        $state->required = true;
        $state->schemaType = 'integer';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ParameterTuiGenerator::class, ParameterTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/parameters/userId.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $this->assertArrayHasKey('documentation', $yaml);
        $this->assertArrayHasKey('components', $yaml['documentation']);
        $this->assertArrayHasKey('parameters', $yaml['documentation']['components']);
        $this->assertArrayHasKey('userId', $yaml['documentation']['components']['parameters']);

        $param = $yaml['documentation']['components']['parameters']['userId'];
        $this->assertSame('userId', $param['name']);
        $this->assertSame('path', $param['in']);
        $this->assertSame('User ID', $param['description']);
        $this->assertTrue($param['required']);
        $this->assertSame('integer', $param['schema']['type']);
    }

    public function testHeaderYamlGeneration(): void
    {
        $state = new HeaderTuiState();
        $state->name = 'X-Request-ID';
        $state->description = 'Unique request identifier';
        $state->schemaType = 'string';
        $state->format = 'uuid';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(HeaderTuiGenerator::class, HeaderTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/headers/X-Request-ID.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $header = $yaml['documentation']['components']['headers']['X-Request-ID'];
        $this->assertSame('Unique request identifier', $header['description']);
        $this->assertSame('string', $header['schema']['type']);
        $this->assertSame('uuid', $header['schema']['format']);
    }

    public function testResponseYamlGeneration(): void
    {
        $state = new ResponseTuiState();
        $state->name = 'NotFound';
        $state->statusCode = '404';
        $state->description = 'Resource not found';
        $state->schemaRef = 'Error';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ResponseTuiGenerator::class, ResponseTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/responses/NotFound.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $response = $yaml['documentation']['components']['responses']['NotFound'];
        $this->assertSame('Resource not found', $response['description']);
        $this->assertSame('#/components/schemas/Error', $response['content']['application/json']['schema']['$ref']);
    }

    public function testResponseWithoutSchema(): void
    {
        $state = new ResponseTuiState();
        $state->name = 'NoContent';
        $state->statusCode = '204';
        $state->description = 'No content';
        $state->schemaRef = null;
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ResponseTuiGenerator::class, ResponseTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/responses/NoContent.yaml');
        $response = $yaml['documentation']['components']['responses']['NoContent'];
        $this->assertSame('No content', $response['description']);
        $this->assertArrayNotHasKey('content', $response);
    }

    public function testRequestBodyYamlGeneration(): void
    {
        $state = new RequestBodyTuiState();
        $state->name = 'CreateUserRequest';
        $state->description = 'User creation payload';
        $state->required = true;
        $state->schemaRef = 'CreateUser';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(RequestBodyTuiGenerator::class, RequestBodyTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/requestBodies/CreateUserRequest.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $body = $yaml['documentation']['components']['requestBodies']['CreateUserRequest'];
        $this->assertSame('User creation payload', $body['description']);
        $this->assertTrue($body['required']);
        $this->assertSame('#/components/schemas/CreateUser', $body['content']['application/json']['schema']['$ref']);
    }

    public function testSecuritySchemeHttpBearer(): void
    {
        $state = new SecuritySchemeTuiState();
        $state->name = 'BearerAuth';
        $state->type = 'http';
        $state->scheme = 'bearer';
        $state->bearerFormat = 'JWT';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(SecuritySchemeTuiGenerator::class, SecuritySchemeTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/securitySchemes/BearerAuth.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $scheme = $yaml['documentation']['components']['securitySchemes']['BearerAuth'];
        $this->assertSame('http', $scheme['type']);
        $this->assertSame('bearer', $scheme['scheme']);
        $this->assertSame('JWT', $scheme['bearerFormat']);
    }

    public function testSecuritySchemeApiKey(): void
    {
        $state = new SecuritySchemeTuiState();
        $state->name = 'ApiKeyAuth';
        $state->type = 'apiKey';
        $state->apiKeyName = 'X-API-Key';
        $state->apiKeyIn = 'header';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(SecuritySchemeTuiGenerator::class, SecuritySchemeTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/securitySchemes/ApiKeyAuth.yaml');
        $scheme = $yaml['documentation']['components']['securitySchemes']['ApiKeyAuth'];
        $this->assertSame('apiKey', $scheme['type']);
        $this->assertSame('X-API-Key', $scheme['name']);
        $this->assertSame('header', $scheme['in']);
    }

    public function testExampleYamlGeneration(): void
    {
        $state = new ExampleTuiState();
        $state->name = 'SuccessfulLogin';
        $state->summary = 'Successful login response';
        $state->description = 'Returns a valid JWT token';
        $state->value = '{"token": "eyJhbG"}';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ExampleTuiGenerator::class, ExampleTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/examples/SuccessfulLogin.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $example = $yaml['documentation']['components']['examples']['SuccessfulLogin'];
        $this->assertSame('Successful login response', $example['summary']);
        $this->assertSame('Returns a valid JWT token', $example['description']);
        $this->assertSame(['token' => 'eyJhbG'], $example['value']);
    }

    public function testParameterPhpGeneration(): void
    {
        $state = new ParameterTuiState();
        $state->name = 'page';
        $state->in = 'query';
        $state->description = 'Page number';
        $state->required = false;
        $state->schemaType = 'integer';
        $state->format_output = 'php';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ParameterTuiGenerator::class, ParameterTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/parameters/page.php';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('ApiDocBuilder', $content);
        $this->assertStringContainsString('addParameter', $content);
        $this->assertStringContainsString("'page'", $content);
    }

    private function createGenerator(string $generatorClass, string $managerClass): object
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['ehyiah_api_doc.source_path', '/Swagger'],
            ['ehyiah_api_doc.dump_path', '/Swagger/dump'],
            ['ehyiah_api_doc.scan_directories', ['src/Entity']],
        ]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tmpDir);

        $propertyInfo = $this->createMock(PropertyInfoExtractorInterface::class);
        $helper = $this->createMock(LoadApiDocConfigHelper::class);

        $manager = new $managerClass($kernel, $parameterBag);

        return new $generatorClass($manager, $kernel, $parameterBag, $propertyInfo, $helper);
    }

    private function invokeGenerate(object $generator, object $state): void
    {
        $outputReflection = new ReflectionProperty($generator, 'currentOutput');
        $outputReflection->setValue($generator, $this->output);

        $reflection = new ReflectionMethod($generator, 'generateComponent');
        $reflection->setAccessible(true);
        $reflection->invoke($generator, $state);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ── Integration tests: loadedFrom ──

    public function testSecuritySchemeWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/auth/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'BearerAuth.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['securitySchemes' => ['BearerAuth' => ['type' => 'http']]]],
        ]));

        $state = new SecuritySchemeTuiState();
        $state->name = 'BearerAuth';
        $state->type = 'http';
        $state->scheme = 'bearer';
        $state->bearerFormat = 'JWT';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(SecuritySchemeTuiGenerator::class, SecuritySchemeTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('BearerAuth', $content);
        $this->assertStringContainsString('bearer', $content);
        $this->assertFileDoesNotExist($this->tmpDir . '/Swagger/securitySchemes/BearerAuth.yaml');
    }

    public function testParameterWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/params/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'userId.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['parameters' => ['userId' => ['name' => 'userId', 'in' => 'path']]]],
        ]));

        $state = new ParameterTuiState();
        $state->name = 'userId';
        $state->in = 'path';
        $state->description = 'User ID';
        $state->required = true;
        $state->schemaType = 'integer';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(ParameterTuiGenerator::class, ParameterTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('userId', $content);
        $this->assertStringContainsString('integer', $content);
    }

    public function testHeaderWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/headers/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'X-Request-ID.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['headers' => ['X-Request-ID' => ['description' => 'ID']]]],
        ]));

        $state = new HeaderTuiState();
        $state->name = 'X-Request-ID';
        $state->description = 'Unique request identifier';
        $state->schemaType = 'string';
        $state->format = 'uuid';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(HeaderTuiGenerator::class, HeaderTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('X-Request-ID', $content);
        $this->assertStringContainsString('uuid', $content);
    }

    public function testResponseWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/responses/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'NotFound.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['responses' => ['NotFound' => ['description' => 'Not found']]]],
        ]));

        $state = new ResponseTuiState();
        $state->name = 'NotFound';
        $state->statusCode = '404';
        $state->description = 'Resource not found';
        $state->schemaRef = null;
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(ResponseTuiGenerator::class, ResponseTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('NotFound', $content);
        $this->assertStringContainsString('Resource not found', $content);
    }

    public function testRequestBodyWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/bodies/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'CreateUser.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['requestBodies' => ['CreateUser' => ['description' => 'Create']]]],
        ]));

        $state = new RequestBodyTuiState();
        $state->name = 'CreateUser';
        $state->description = 'User creation payload';
        $state->required = true;
        $state->schemaRef = 'User';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(RequestBodyTuiGenerator::class, RequestBodyTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('CreateUser', $content);
        $this->assertStringContainsString('User creation payload', $content);
    }

    public function testExampleWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/examples/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'SuccessfulLogin.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['examples' => ['SuccessfulLogin' => ['summary' => 'Login']]]],
        ]));

        $state = new ExampleTuiState();
        $state->name = 'SuccessfulLogin';
        $state->summary = 'Successful login response';
        $state->description = 'Returns a valid JWT token';
        $state->value = '{"token": "abc"}';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(ExampleTuiGenerator::class, ExampleTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('Successful login response', $content);
        $this->assertStringContainsString('token:', $content);
    }

    public function testSecuritySchemeWritesToDefaultWhenLoadedFromIsNull(): void
    {
        $state = new SecuritySchemeTuiState();
        $state->name = 'NewScheme';
        $state->type = 'http';
        $state->scheme = 'bearer';
        $state->bearerFormat = 'JWT';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = null;

        $generator = $this->createGenerator(SecuritySchemeTuiGenerator::class, SecuritySchemeTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $expectedFile = $this->tmpDir . '/Swagger/securitySchemes/NewScheme.yaml';
        $this->assertFileExists($expectedFile);
        $content = file_get_contents($expectedFile);
        $this->assertStringContainsString('NewScheme', $content);
    }

    // ── Link tests ──

    public function testLinkYamlGeneration(): void
    {
        $state = new LinkTuiState();
        $state->name = 'GetUserOrders';
        $state->operationRef = '/users/{userId}/orders';
        $state->operationId = 'getUserOrders';
        $state->description = 'Orders for this user';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(LinkTuiGenerator::class, LinkTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/links/GetUserOrders.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $link = $yaml['documentation']['components']['links']['GetUserOrders'];
        $this->assertSame('/users/{userId}/orders', $link['operationRef']);
        $this->assertSame('getUserOrders', $link['operationId']);
        $this->assertSame('Orders for this user', $link['description']);
    }

    public function testLinkPhpGeneration(): void
    {
        $state = new LinkTuiState();
        $state->name = 'GetUserOrders';
        $state->operationId = 'getUserOrders';
        $state->format_output = 'php';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(LinkTuiGenerator::class, LinkTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/links/GetUserOrders.php';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('addLink', $content);
        $this->assertStringContainsString('GetUserOrders', $content);
    }

    public function testLinkWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/links/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'GetUserOrders.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['links' => ['GetUserOrders' => ['operationId' => 'getUserOrders']]]],
        ]));

        $state = new LinkTuiState();
        $state->name = 'GetUserOrders';
        $state->operationId = 'getUserOrders';
        $state->description = 'Updated description';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(LinkTuiGenerator::class, LinkTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('getUserOrders', $content);
        $this->assertStringContainsString('Updated description', $content);
    }

    // ── Callback tests ──

    public function testCallbackYamlGeneration(): void
    {
        $state = new CallbackTuiState();
        $state->name = 'OnOrderCreated';
        $state->expression = '{$request.body#/callbackUrl}';
        $state->method = 'post';
        $state->operationId = 'handleOrderCallback';
        $state->description = 'Handle order event';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(CallbackTuiGenerator::class, CallbackTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/callbacks/OnOrderCreated.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $callback = $yaml['documentation']['components']['callbacks']['OnOrderCreated'];
        $this->assertArrayHasKey('{$request.body#/callbackUrl}', $callback);
        $this->assertSame('handleOrderCallback', $callback['{$request.body#/callbackUrl}']['post']['operationId']);
    }

    public function testCallbackPhpGeneration(): void
    {
        $state = new CallbackTuiState();
        $state->name = 'OnOrderCreated';
        $state->expression = '{$request.body#/callbackUrl}';
        $state->method = 'post';
        $state->format_output = 'php';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(CallbackTuiGenerator::class, CallbackTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/callbacks/OnOrderCreated.php';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('addCallback', $content);
        $this->assertStringContainsString('OnOrderCreated', $content);
    }

    public function testCallbackWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/callbacks/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'OnOrder.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['callbacks' => ['OnOrder' => ['{$request.body#/url}' => ['post' => ['operationId' => 'oldHandler']]]]]],
        ]));

        $state = new CallbackTuiState();
        $state->name = 'OnOrder';
        $state->expression = '{$request.body#/url}';
        $state->method = 'post';
        $state->operationId = 'newHandler';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(CallbackTuiGenerator::class, CallbackTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('newHandler', $content);
    }

    // ── PathItem tests ──

    public function testPathItemYamlGeneration(): void
    {
        $state = new PathItemTuiState();
        $state->name = 'UserOperations';
        $state->summary = 'User operations';
        $state->description = 'All operations for users';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(PathItemTuiGenerator::class, PathItemTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/pathItems/UserOperations.yaml';
        $this->assertFileExists($file);

        $yaml = Yaml::parseFile($file);
        $pathItem = $yaml['documentation']['components']['pathItems']['UserOperations'];
        $this->assertSame('User operations', $pathItem['summary']);
        $this->assertSame('All operations for users', $pathItem['description']);
    }

    public function testPathItemPhpGeneration(): void
    {
        $state = new PathItemTuiState();
        $state->name = 'UserOperations';
        $state->summary = 'User operations';
        $state->format_output = 'php';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(PathItemTuiGenerator::class, PathItemTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $file = $this->tmpDir . '/Swagger/pathItems/UserOperations.php';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('addPathItem', $content);
        $this->assertStringContainsString('UserOperations', $content);
    }

    public function testPathItemWritesToOriginalLocationWhenLoadedFrom(): void
    {
        $customDir = $this->tmpDir . '/Swagger/custom/pathItems/';
        mkdir($customDir, 0755, true);
        $originalFile = $customDir . 'UserOps.yaml';
        file_put_contents($originalFile, Yaml::dump([
            'documentation' => ['components' => ['pathItems' => ['UserOps' => ['summary' => 'Old summary']]]],
        ]));

        $state = new PathItemTuiState();
        $state->name = 'UserOps';
        $state->summary = 'Updated summary';
        $state->description = 'New description';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger';
        $state->loadedFrom = $originalFile;

        $generator = $this->createGenerator(PathItemTuiGenerator::class, PathItemTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $this->assertFileExists($originalFile);
        $content = file_get_contents($originalFile);
        $this->assertStringContainsString('Updated summary', $content);
        $this->assertStringContainsString('New description', $content);
    }

    // ── New fields generation tests ──

    public function testParameterWithDeprecatedFields(): void
    {
        $state = new ParameterTuiState();
        $state->name = 'oldId';
        $state->in = 'query';
        $state->description = 'Old parameter';
        $state->required = false;
        $state->deprecated = true;
        $state->allowEmptyValue = true;
        $state->style = 'form';
        $state->explode = 'true';
        $state->allowReserved = true;
        $state->schemaType = 'string';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ParameterTuiGenerator::class, ParameterTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/parameters/oldId.yaml');
        $param = $yaml['documentation']['components']['parameters']['oldId'];
        $this->assertTrue($param['deprecated']);
        $this->assertTrue($param['allowEmptyValue']);
        $this->assertSame('form', $param['style']);
        $this->assertTrue($param['explode']);
        $this->assertTrue($param['allowReserved']);
    }

    public function testHeaderWithRequiredAndDeprecated(): void
    {
        $state = new HeaderTuiState();
        $state->name = 'X-Old-Header';
        $state->description = 'Deprecated header';
        $state->required = true;
        $state->deprecated = true;
        $state->schemaType = 'string';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(HeaderTuiGenerator::class, HeaderTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/headers/X-Old-Header.yaml');
        $header = $yaml['documentation']['components']['headers']['X-Old-Header'];
        $this->assertTrue($header['required']);
        $this->assertTrue($header['deprecated']);
    }

    public function testResponseWithHeadersAndLinks(): void
    {
        $state = new ResponseTuiState();
        $state->name = 'Created';
        $state->statusCode = '201';
        $state->description = 'Resource created';
        $state->headers = '{"X-Request-ID": {"description": "Request ID", "schema": {"type": "string"}}}';
        $state->links = '{"GetResource": {"operationId": "getResource", "parameters": {"id": "$response.body#/id"}}}';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ResponseTuiGenerator::class, ResponseTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/responses/Created.yaml');
        $response = $yaml['documentation']['components']['responses']['Created'];
        $this->assertArrayHasKey('headers', $response);
        $this->assertArrayHasKey('X-Request-ID', $response['headers']);
        $this->assertArrayHasKey('links', $response);
        $this->assertArrayHasKey('GetResource', $response['links']);
    }

    public function testExampleWithExternalValue(): void
    {
        $state = new ExampleTuiState();
        $state->name = 'ExternalUser';
        $state->summary = 'External user example';
        $state->externalValue = 'https://example.com/user.json';
        $state->format_output = 'yaml';
        $state->outputDir = '/Swagger/';

        $generator = $this->createGenerator(ExampleTuiGenerator::class, ExampleTuiManager::class);
        $this->invokeGenerate($generator, $state);

        $yaml = Yaml::parseFile($this->tmpDir . '/Swagger/examples/ExternalUser.yaml');
        $example = $yaml['documentation']['components']['examples']['ExternalUser'];
        $this->assertSame('External user example', $example['summary']);
        $this->assertSame('https://example.com/user.json', $example['externalValue']);
        $this->assertArrayNotHasKey('value', $example);
    }
}
