<?php

namespace Ehyiah\ApiDocBundle\Tests\Command;

use Ehyiah\ApiDocBundle\Command\GenerateRouteCommand;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @coversNothing
 */
class GenerateRouteCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private string $tempDir;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/apidoc_route_test_' . uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir($this->tempDir);
        $this->filesystem->mkdir($this->tempDir . '/src/Swagger');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->tempDir);

        $parameterBag = new ParameterBag([
            'ehyiah_api_doc.source_path' => '/src/Swagger',
            'ehyiah_api_doc.dump_path' => '/var/Dump',
        ]);

        $apiDocConfigHelper = new LoadApiDocConfigHelper($kernel, $parameterBag);

        $command = new GenerateRouteCommand($kernel, $parameterBag, $apiDocConfigHelper);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tempDir);
    }

    public function testExecuteGeneratesYamlFile(): void
    {
        $this->commandTester->execute([
            'route' => '/api/users',
            'method' => 'GET',
            '--tag' => ['users', 'api'],
            '--description' => 'Récupère la liste des utilisateurs',
            '--response-schema' => 'User',
            '--output' => '/src/Swagger',
            '--filename' => 'users_route',
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('YAML file generated', $output);

        $expectedFilePath = $this->tempDir . '/src/Swagger/users_route.yaml';
        $this->assertFileExists($expectedFilePath);

        $fileContent = file_get_contents($expectedFilePath);
        $this->assertStringContainsString('/api/users', $fileContent);
        $this->assertStringContainsString('get', $fileContent);
        $this->assertStringContainsString('users', $fileContent);
        $this->assertStringContainsString('api', $fileContent);
        $this->assertStringContainsString('Récupère la liste des utilisateurs', $fileContent);
        $this->assertStringContainsString('#/components/schemas/User', $fileContent);
    }

    public function testExecuteWithRequestBody(): void
    {
        $this->commandTester->execute([
            'route' => '/api/users',
            'method' => 'POST',
            '--request-body' => 'UserCreate',
            '--filename' => 'create_user',
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $expectedFilePath = $this->tempDir . '/src/Swagger/create_user.yaml';
        $this->assertFileExists($expectedFilePath);

        $fileContent = file_get_contents($expectedFilePath);
        $this->assertStringContainsString('post', $fileContent);
        $this->assertStringContainsString('#/components/requestBodies/UserCreate', $fileContent);
    }

    public function testExecuteWithMinimalOptions(): void
    {
        $this->commandTester->execute([
            'route' => '/api/minimal',
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $expectedFilePath = $this->tempDir . '/src/Swagger/route.yaml';
        $this->assertFileExists($expectedFilePath);

        $fileContent = file_get_contents($expectedFilePath);
        $this->assertStringContainsString('/api/minimal', $fileContent);
        $this->assertStringContainsString('get', $fileContent); // GET is the default method
        $this->assertStringContainsString('api', $fileContent); // default tag
    }

    public function testExecuteGeneratesPhpFile(): void
    {
        $this->commandTester->execute([
            'route' => '/api/products',
            'method' => 'GET',
            '--tag' => ['products'],
            '--description' => 'Get all products',
            '--response-schema' => 'Product',
            '--output' => '/src/Swagger',
            '--filename' => 'products_route',
            '--format' => 'php',
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('PHP file generated', $output);

        $expectedFilePath = $this->tempDir . '/src/Swagger/products_route.php';
        $this->assertFileExists($expectedFilePath);

        $fileContent = file_get_contents($expectedFilePath);
        $this->assertStringContainsString("->path('/api/products')", $fileContent);
        $this->assertStringContainsString("->method('GET')", $fileContent);
        $this->assertStringContainsString("->tag('products')", $fileContent);
        $this->assertStringContainsString("->description('Get all products')", $fileContent);
        $this->assertStringContainsString("->ref('#/components/schemas/Product')", $fileContent);
        $this->assertStringContainsString('ApiDocConfigInterface', $fileContent);
        $this->assertStringContainsString('ApiDocBuilder', $fileContent);
    }

    public function testExecuteGeneratesBothFormats(): void
    {
        $this->commandTester->execute([
            'route' => '/api/orders',
            'method' => 'POST',
            '--description' => 'Create an order',
            '--output' => '/src/Swagger',
            '--filename' => 'orders_route',
            '--format' => 'both',
        ]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('YAML file generated', $output);
        $this->assertStringContainsString('PHP file generated', $output);

        $yamlPath = $this->tempDir . '/src/Swagger/orders_route.yaml';
        $phpPath = $this->tempDir . '/src/Swagger/orders_route.php';

        $this->assertFileExists($yamlPath);
        $this->assertFileExists($phpPath);
    }
}
