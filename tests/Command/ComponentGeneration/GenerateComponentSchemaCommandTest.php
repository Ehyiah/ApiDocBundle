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
class GenerateComponentSchemaCommandTest extends TestCase
{
    public function testSchemaGeneration(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:schema');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\\ApiDocBundle\\Tests\\Dummy\\DummyObject',
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
    }
}
