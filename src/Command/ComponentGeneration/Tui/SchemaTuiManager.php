<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

use function Symfony\Component\String\u;

class SchemaTuiManager
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAllClasses(): array
    {
        $classes = [];
        /** @var array<int, string> $scanDirectories */
        $scanDirectories = (array)($this->parameterBag->get('ehyiah_api_doc.scan_directories') ?? ['src']);

        $finder = new Finder();
        $projectDir = $this->kernel->getProjectDir();
        if (str_ends_with($projectDir, '/tests/App')) {
            $projectDir = dirname($projectDir, 2);
        }

        $directoriesToScan = [];
        foreach ($scanDirectories as $dir) {
            $path = $projectDir . '/' . u($dir)->trim('/');
            if (is_dir($path)) {
                $directoriesToScan[] = $path;
            }
        }

        if (empty($directoriesToScan)) {
            return [];
        }

        $finder->in($directoriesToScan)->files()->name('*.php');
        foreach ($finder as $file) {
            $content = file_get_contents($file->getRealPath());
            if (false === $content) {
                continue;
            }
            if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
                $namespace = $matches[1];
                if (preg_match('/class\s+(\w+)/', $content, $matches)) {
                    $potentialClass = $namespace . '\\' . $matches[1];
                    if (class_exists($potentialClass) || interface_exists($potentialClass)) {
                        $classes[] = $potentialClass;
                    }
                }
            }
        }
        sort($classes);

        return $classes;
    }

    /**
     * @return string[]
     */
    public function getClassProperties(string $className): array
    {
        $properties = $this->propertyInfoExtractor->getProperties($className) ?? [];
        $reflectionClass = new ReflectionClass($className);
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            if (!in_array($reflectionProperty->getName(), $properties, true)) {
                $properties[] = $reflectionProperty->getName();
            }
        }

        return $properties;
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.source_path');

        return is_string($dumpLocation) ? (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/') : '/src/Swagger/';
    }

    // ... move generateFiles logic here eventually
}
