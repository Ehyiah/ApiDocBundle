<?php

declare(strict_types=1);

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

        // Ensure test directories exist
        $this->filesystem->mkdir($this->testDir . 'schemas/');
        $this->filesystem->mkdir($this->testDir . 'requestBodies/');

        // Create the helper with the test kernel and parameter bag
        $parameterBag = new ParameterBag([
            'ehyiah_api_doc.source_path' => '/var/Swagger',
            'ehyiah_api_doc.dump_path' => '/var/Dump',
        ]);

        $this->helper = new LoadApiDocConfigHelper($this->kernel, $parameterBag);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        $testFiles = [
            $this->testDir . 'schemas/TestComponent.yaml',
            $this->testDir . 'schemas/TestComponent.php',
            $this->testDir . 'requestBodies/TestRequestBody.yaml',
            $this->testDir . 'requestBodies/TestRequestBody.php',
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
        // Create a test YAML file
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
        // Create a test PHP file
        $phpContent = <<<'PHP'
<?php

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

return new class implements ApiDocConfigInterface {
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addSchema('TestComponent')
            ->type('object')
            ->addProperty('id')
                ->type('string')
            ->end()
        ->end();
    }
};
PHP;

        $filePath = $this->testDir . 'schemas/TestComponent.php';
        $this->filesystem->dumpFile($filePath, $phpContent);

        $result = $this->helper->findPhpComponentFile('TestComponent', 'schemas');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestComponent.php', $result->getPathname());
    }

    public function testFindPhpComponentFileFindsExistingRequestBody(): void
    {
        // Create a test PHP file
        $phpContent = <<<'PHP'
<?php

use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

return new class implements ApiDocConfigInterface {
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addRequestBody('TestRequestBody')
            ->description('Test request body')
            ->required()
        ->end();
    }
};
PHP;

        $filePath = $this->testDir . 'requestBodies/TestRequestBody.php';
        $this->filesystem->dumpFile($filePath, $phpContent);

        $result = $this->helper->findPhpComponentFile('TestRequestBody', 'requestBodies');

        $this->assertNotNull($result);
        $this->assertStringContainsString('TestRequestBody.php', $result->getPathname());
    }

    public function testFindYamlComponentFileFindsExistingRequestBody(): void
    {
        // Create a test YAML file
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
}
