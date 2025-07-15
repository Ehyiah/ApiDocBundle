<?php

declare(strict_types=1);

namespace Ehyiah\ApiDocBundle\Tests\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Tests\AppKernelTest;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * @coversDefaultClass \Ehyiah\ApiDocBundle\Command\ComponentGeneration\GenerateComponentRequestBodyCommand
 */
final class GenerateComponentRequestBodyCommandTest extends TestCase
{
    public function testRequestBodyGenerationFromObject(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyObject',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File generated', $output);

        $filePath = $kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyObject.yaml';
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
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\ApiDocBundle\Tests\Dummy\DummyType',
        ]);

        $commandTester->assertCommandIsSuccessful();

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File generated', $output);

        $filePath = $kernel->getProjectDir() . '/var/Swagger/requestBodies/DummyType.yaml';
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
}
