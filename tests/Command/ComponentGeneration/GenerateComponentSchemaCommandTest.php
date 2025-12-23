<?php

declare(strict_types=1);

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Tests\AppKernelTest;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Command\ComponentGeneration\GenerateComponentSchemaCommand
 */
final class GenerateComponentSchemaCommandTest extends TestCase
{
    public function testSchemaGeneration(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File generated', $output);

        $filePath = $kernel->getProjectDir() . '/var/Swagger/schemas/DummyObject.yaml';
        $arrayFromYaml = Yaml::parseFile($filePath);
        $this->assertIsArray($arrayFromYaml);
        $this->assertArrayHasKey('documentation', $arrayFromYaml);
        $this->assertArrayHasKey('components', $arrayFromYaml['documentation']);
        $this->assertArrayHasKey('schemas', $arrayFromYaml['documentation']['components']);
        $this->assertArrayHasKey('DummyObject', $arrayFromYaml['documentation']['components']['schemas']);
        $this->assertArrayHasKey('type', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertEquals('object', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['type']);
        $this->assertArrayHasKey('required', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertIsArray($arrayFromYaml['documentation']['components']['schemas']['DummyObject']['required']);
        $this->assertArrayHasKey('properties', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertIsArray($arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']);

        $this->assertCount(16, $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']);
        $this->assertCount(9, $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['required']);

        $this->assertSame('array', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['arrayString']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['arrayString']['items']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['id']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['skipedValue']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['stringNotNullable']['type']);
        $this->assertSame('integer', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['intNotNullable']['type']);
        $this->assertSame('boolean', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['booleanNotNullable']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['datetimeNullable']['type']);
        $this->assertSame('date-time', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['datetimeNullable']['format']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['enumNotNullable']['type']);
        $this->assertArrayHasKey('enum', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['enumNotNullable']);
        $this->assertIsArray($arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['enumNotNullable']['enum']);
        $this->assertArrayHasKey('$ref', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['objectNotNullable']);

        $this->assertSame('array', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['collectionOfDummyObject2']['type']);
        $this->assertSame('#/components/schemas/Collection', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['collectionOfDummyObject2']['items']['$ref']);
    }

    public function testSchemaGenerationWithSkipOption(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
            '--skip' => ['id', 'skipedValue'],
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File generated', $output);

        $filePath = $kernel->getProjectDir() . '/var/Swagger/schemas/DummyObject.yaml';
        $arrayFromYaml = Yaml::parseFile($filePath);
        $this->assertIsArray($arrayFromYaml);
        $this->assertArrayHasKey('documentation', $arrayFromYaml);
        $this->assertArrayHasKey('components', $arrayFromYaml['documentation']);
        $this->assertArrayHasKey('schemas', $arrayFromYaml['documentation']['components']);
        $this->assertArrayHasKey('DummyObject', $arrayFromYaml['documentation']['components']['schemas']);
        $this->assertArrayHasKey('type', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertEquals('object', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['type']);
        $this->assertArrayHasKey('required', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertIsArray($arrayFromYaml['documentation']['components']['schemas']['DummyObject']['required']);
        $this->assertArrayHasKey('properties', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']);
        $this->assertIsArray($arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']);

        $this->assertCount(14, $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']);

        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['stringNotNullable']['type']);
        $this->assertSame('integer', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['intNotNullable']['type']);
        $this->assertSame('boolean', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['booleanNotNullable']['type']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['datetimeNullable']['type']);
        $this->assertSame('date-time', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['datetimeNullable']['format']);
        $this->assertSame('string', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['enumNotNullable']['type']);
        $this->assertArrayHasKey('enum', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['enumNotNullable']);
        $this->assertArrayHasKey('$ref', $arrayFromYaml['documentation']['components']['schemas']['DummyObject']['properties']['objectNotNullable']);
    }

    public function testSchemaGenerationWithPhpFormat(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
            '--format' => 'php',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP file generated', $output);

        $filePath = $kernel->getProjectDir() . '/var/Swagger/schemas/DummyObject.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;', $content);
        $this->assertStringContainsString('use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;', $content);
        $this->assertStringContainsString('class implements ApiDocConfigInterface', $content);
        $this->assertStringContainsString("->addSchema('DummyObject')", $content);
        $this->assertStringContainsString("->type('object')", $content);
        $this->assertStringContainsString("->addProperty('id')", $content);
        $this->assertStringContainsString("->type('string')", $content);
        $this->assertStringContainsString('->required()', $content);
        $this->assertStringContainsString("->format('date-time')", $content);
        $this->assertStringContainsString('->enum(', $content);
        $this->assertStringContainsString("->ref('#/components/schemas/DummyObject2')", $content);
    }

    public function testSchemaGenerationWithBothFormats(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
            '--format' => 'both',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File generated', $output);
        $this->assertStringContainsString('PHP file generated', $output);

        $yamlPath = $kernel->getProjectDir() . '/var/Swagger/schemas/DummyObject.yaml';
        $phpPath = $kernel->getProjectDir() . '/var/Swagger/schemas/DummyObject.php';

        $this->assertFileExists($yamlPath);
        $this->assertFileExists($phpPath);
    }
}
