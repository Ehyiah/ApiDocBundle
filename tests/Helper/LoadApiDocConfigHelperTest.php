<?php

namespace Ehyiah\ApiDocBundle\Tests\Helper;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Ehyiah\ApiDocBundle\Tests\AppKernelTest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper
 */
final class LoadApiDocConfigHelperTest extends TestCase
{
    private ?AppKernelTest $kernel = null;
    private Filesystem $filesystem;
    private string $testDir;
    private LoadApiDocConfigHelper $helper;

    protected function setUp(): void
    {
        $this->kernel = new AppKernelTest('test', true);
        $this->kernel->boot();
        $this->filesystem = new Filesystem();
        $this->testDir = $this->kernel->getProjectDir() . '/var/Swagger/';

        $componentDirs = [
            'schemas', 'requestBodies', 'parameters', 'headers',
            'responses', 'examples', 'securitySchemes', 'links',
            'callbacks', 'pathItems',
        ];
        foreach ($componentDirs as $dir) {
            $this->filesystem->mkdir($this->testDir . $dir . '/');
        }

        $phpFilePaths = [
            'schemas' => ['TestComponent' => $this->testDir . 'schemas/TestComponent.php'],
            'requestBodies' => ['TestRequestBody' => $this->testDir . 'requestBodies/TestRequestBody.php'],
            'parameters' => ['TestParameter' => $this->testDir . 'parameters/TestParameter.php'],
            'headers' => ['TestHeader' => $this->testDir . 'headers/TestHeader.php'],
            'responses' => ['TestResponse' => $this->testDir . 'responses/TestResponse.php'],
            'examples' => ['TestExample' => $this->testDir . 'examples/TestExample.php'],
            'securitySchemes' => ['TestSecurityScheme' => $this->testDir . 'securitySchemes/TestSecurityScheme.php'],
            'links' => ['TestLink' => $this->testDir . 'links/TestLink.php'],
            'callbacks' => ['TestCallback' => $this->testDir . 'callbacks/TestCallback.php'],
            'pathItems' => ['TestPathItem' => $this->testDir . 'pathItems/TestPathItem.php'],
        ];

        // Create stub PHP files on disk so file_exists() checks pass
        foreach ($phpFilePaths as $type => $entries) {
            foreach ($entries as $path) {
                $this->filesystem->dumpFile($path, "<?php\n");
            }
        }

        $parameterBag = new ParameterBag([
            'ehyiah_api_doc.source_path' => '/var/Swagger',
            'ehyiah_api_doc.dump_path' => '/var/Dump',
            'ehyiah_api_doc.component_files' => $phpFilePaths,
        ]);

        $this->helper = new LoadApiDocConfigHelper($this->kernel, $parameterBag);
    }

    protected function tearDown(): void
    {
        $testFiles = [
            $this->testDir . 'schemas/TestComponent.yaml',
            $this->testDir . 'schemas/TestComponent.php',
            $this->testDir . 'requestBodies/TestRequestBody.yaml',
            $this->testDir . 'requestBodies/TestRequestBody.php',
            $this->testDir . 'parameters/TestParameter.yaml',
            $this->testDir . 'parameters/TestParameter.php',
            $this->testDir . 'headers/TestHeader.yaml',
            $this->testDir . 'headers/TestHeader.php',
            $this->testDir . 'responses/TestResponse.yaml',
            $this->testDir . 'responses/TestResponse.php',
            $this->testDir . 'examples/TestExample.yaml',
            $this->testDir . 'examples/TestExample.php',
            $this->testDir . 'securitySchemes/TestSecurityScheme.yaml',
            $this->testDir . 'securitySchemes/TestSecurityScheme.php',
            $this->testDir . 'links/TestLink.yaml',
            $this->testDir . 'links/TestLink.php',
            $this->testDir . 'callbacks/TestCallback.yaml',
            $this->testDir . 'callbacks/TestCallback.php',
            $this->testDir . 'pathItems/TestPathItem.yaml',
            $this->testDir . 'pathItems/TestPathItem.php',
        ];

        foreach ($testFiles as $file) {
            if ($this->filesystem->exists($file)) {
                $this->filesystem->remove($file);
            }
        }
    }

    public function testFindYamlComponentFileReturnsNullWhenNotFound(): void
    {
        $result = $this->helper->findYamlComponentFile('NonExistentComponent', 'schemas');

        $this->assertNull($result);
    }

    public function testFindYamlComponentFileFindsExistingSchema(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        schemas:
            TestComponent:
                type: object
                properties:
                    id:
                        type: string
YAML;

        $filePath = $this->testDir . 'schemas/TestComponent.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestComponent', 'schemas');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestComponent.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileReturnsNullWhenNotFound(): void
    {
        $result = $this->helper->findPhpComponentFile('NonExistentComponent', 'schemas');

        $this->assertNull($result);
    }

    public function testFindPhpComponentFileFindsExistingSchema(): void
    {
        $result = $this->helper->findPhpComponentFile('TestComponent', 'schemas');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestComponent.php', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingRequestBody(): void
    {
        $result = $this->helper->findPhpComponentFile('TestRequestBody', 'requestBodies');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestRequestBody.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingRequestBody(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        requestBodies:
            TestRequestBody:
                description: Test request body
                required: true
                content:
                    application/json:
                        schema:
                            type: object
YAML;

        $filePath = $this->testDir . 'requestBodies/TestRequestBody.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestRequestBody', 'requestBodies');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestRequestBody.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingParameter(): void
    {
        $result = $this->helper->findPhpComponentFile('TestParameter', 'parameters');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestParameter.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingParameter(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        parameters:
            TestParameter:
                name: page
                in: query
                schema:
                    type: integer
YAML;

        $filePath = $this->testDir . 'parameters/TestParameter.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestParameter', 'parameters');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestParameter.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingHeader(): void
    {
        $result = $this->helper->findPhpComponentFile('TestHeader', 'headers');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestHeader.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingHeader(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        headers:
            TestHeader:
                description: Test header
                schema:
                    type: string
YAML;

        $filePath = $this->testDir . 'headers/TestHeader.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestHeader', 'headers');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestHeader.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingResponse(): void
    {
        $result = $this->helper->findPhpComponentFile('TestResponse', 'responses');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestResponse.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingResponse(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        responses:
            TestResponse:
                description: Not found
                content:
                    application/json:
                        schema:
                            type: object
YAML;

        $filePath = $this->testDir . 'responses/TestResponse.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestResponse', 'responses');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestResponse.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingExample(): void
    {
        $result = $this->helper->findPhpComponentFile('TestExample', 'examples');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestExample.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingExample(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        examples:
            TestExample:
                summary: Test
                value:
                    key: value
YAML;

        $filePath = $this->testDir . 'examples/TestExample.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestExample', 'examples');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestExample.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingSecurityScheme(): void
    {
        $result = $this->helper->findPhpComponentFile('TestSecurityScheme', 'securitySchemes');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestSecurityScheme.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingSecurityScheme(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        securitySchemes:
            TestSecurityScheme:
                type: http
                scheme: bearer
YAML;

        $filePath = $this->testDir . 'securitySchemes/TestSecurityScheme.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestSecurityScheme', 'securitySchemes');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestSecurityScheme.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingLink(): void
    {
        $result = $this->helper->findPhpComponentFile('TestLink', 'links');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestLink.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingLink(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        links:
            TestLink:
                operationId: getUser
                description: Get user
YAML;

        $filePath = $this->testDir . 'links/TestLink.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestLink', 'links');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestLink.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingCallback(): void
    {
        $result = $this->helper->findPhpComponentFile('TestCallback', 'callbacks');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestCallback.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingCallback(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        callbacks:
            TestCallback:
                '{$request.body#/url}':
                    post:
                        operationId: handle
YAML;

        $filePath = $this->testDir . 'callbacks/TestCallback.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestCallback', 'callbacks');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestCallback.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingPathItem(): void
    {
        $result = $this->helper->findPhpComponentFile('TestPathItem', 'pathItems');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestPathItem.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingPathItem(): void
    {
        $yamlContent = <<<'YAML'
documentation:
    components:
        pathItems:
            TestPathItem:
                summary: Test
                get:
                    operationId: get
YAML;

        $filePath = $this->testDir . 'pathItems/TestPathItem.yaml';
        $this->filesystem->dumpFile($filePath, $yamlContent);

        $result = $this->helper->findYamlComponentFile('TestPathItem', 'pathItems');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestPathItem.yaml', $result->getPathname());
    }

    public function testFindPhpComponentFileReturnsNullForUnknownType(): void
    {
        $result = $this->helper->findPhpComponentFile('SomeComponent', 'unknownType');

        $this->assertNull($result);
    }
}
