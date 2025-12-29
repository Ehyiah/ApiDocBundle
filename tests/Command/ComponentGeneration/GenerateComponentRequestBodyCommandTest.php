<?php

declare(strict_types=1);

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Tests\AppKernelTest;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Command\ComponentGeneration\GenerateComponentRequestBodyCommand
 */
final class GenerateComponentRequestBodyCommandTest extends TestCase
{
    private ?AppKernelTest $kernel = null;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->kernel = new AppKernelTest('test', true);
        $this->kernel->boot();
        $this->filesystem = new Filesystem();

        $this->cleanTestFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanTestFiles();
        $this->kernel = null;
    }

    private function cleanTestFiles(): void
    {
        if (null === $this->kernel) {
            return;
        }
        $paths = [
            $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyObject.yaml',
            $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyObject.php',
            $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyType.yaml',
            $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyType.php',
        ];

        foreach ($paths as $path) {
            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    public function testRequestBodyGenerationFromObject(): void
    {
        $application = new Application($this->kernel);

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes', 'yes', 'yes']);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
            '--format' => 'yaml',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('YAML file generated', $output);

        $filePath = $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyObject.yaml';
        $arrayFromYaml = Yaml::parseFile($filePath);
        $this->assertIsArray($arrayFromYaml);
        $this->assertArrayHasKey('documentation', $arrayFromYaml);
        $this->assertArrayHasKey('components', $arrayFromYaml['documentation']);
        $this->assertArrayHasKey('requestBodies', $arrayFromYaml['documentation']['components']);
        $this->assertArrayHasKey('DummyObject', $arrayFromYaml['documentation']['components']['requestBodies']);
        $this->assertArrayHasKey('required', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']);
        $this->assertArrayHasKey('content', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']);
        $this->assertArrayHasKey('application/json', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']['content']);
        $this->assertArrayHasKey('schema', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']['content']['application/json']);
        $this->assertArrayHasKey('required', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']['content']['application/json']['schema']);
        $this->assertArrayHasKey('properties', $arrayFromYaml['documentation']['components']['requestBodies']['DummyObject']['content']['application/json']['schema']);
    }

    public function testRequestBodyGenerationFromType(): void
    {
        $application = new Application($this->kernel);

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes', 'yes', 'yes']);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyType',
            '--format' => 'yaml',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('YAML file generated', $output);

        $filePath = $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyType.yaml';
        $arrayFromYaml = Yaml::parseFile($filePath);

        $this->assertIsArray($arrayFromYaml);
        $this->assertArrayHasKey('documentation', $arrayFromYaml);
        $this->assertArrayHasKey('components', $arrayFromYaml['documentation']);
        $this->assertArrayHasKey('requestBodies', $arrayFromYaml['documentation']['components']);
        $this->assertArrayHasKey('DummyType', $arrayFromYaml['documentation']['components']['requestBodies']);
        $this->assertArrayHasKey('required', $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']);
        $this->assertArrayHasKey('content', $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']);
        $this->assertArrayHasKey('application/json', $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']['content']);
        $this->assertArrayHasKey('schema', $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']['content']['application/json']);
        $this->assertArrayHasKey('properties', $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']['content']['application/json']['schema']);

        $properties = $arrayFromYaml['documentation']['components']['requestBodies']['DummyType']['content']['application/json']['schema']['properties'];
        $this->assertArrayHasKey('textField', $properties);
        $this->assertEquals('string', $properties['textField']['type']);
        $this->assertArrayHasKey('NumberField', $properties);
        $this->assertEquals('number', $properties['NumberField']['type']);
        $this->assertArrayHasKey('integerField', $properties);
        $this->assertEquals('integer', $properties['integerField']['type']);
        $this->assertArrayHasKey('dateField', $properties);
        $this->assertEquals('string', $properties['dateField']['type']);
        $this->assertEquals('date', $properties['dateField']['format']);
        $this->assertArrayHasKey('datetimeField', $properties);
        $this->assertEquals('string', $properties['datetimeField']['type']);
        $this->assertEquals('date-time', $properties['datetimeField']['format']);
        $this->assertArrayHasKey('choiceMultipleField', $properties);
        $this->assertEquals('array', $properties['choiceMultipleField']['type']);
        $this->assertArrayHasKey('enum', $properties['choiceMultipleField']);
        $this->assertArrayHasKey('choiceNotMultipleField', $properties);
        $this->assertEquals('string', $properties['choiceNotMultipleField']['type']);
        $this->assertArrayHasKey('enum', $properties['choiceNotMultipleField']);
        $this->assertArrayHasKey('collectionField', $properties);
        $this->assertEquals('array', $properties['collectionField']['type']);
    }

    public function testRequestBodyGenerationWithPhpFormat(): void
    {
        $application = new Application($this->kernel);

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes', 'yes', 'yes']);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
            '--format' => 'php',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('PHP file generated', $output);

        $filePath = $this->kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyObject.php';
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);
        $this->assertStringContainsString('use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;', $content);
        $this->assertStringContainsString('use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;', $content);
        $this->assertStringContainsString('class implements ApiDocConfigInterface', $content);
        $this->assertStringContainsString("->addRequestBody('DummyObject')", $content);
        $this->assertStringContainsString('->jsonContent()', $content);
    }
}
