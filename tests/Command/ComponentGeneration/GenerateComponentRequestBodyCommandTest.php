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
    public function testRequestBodyGeneration(): void
    {
        $kernel = new AppKernelTest('test', true);
        $application = new Application($kernel);
        $kernel->boot();

        $command = $application->find('apidocbundle:component:body');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'class' => 'Ehyiah\\ApiDocBundle\\Tests\\Dummy\\DummyObject',
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
}
