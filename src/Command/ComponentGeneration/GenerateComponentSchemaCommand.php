<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PropertyInfo\Type;

use function Symfony\Component\String\u;

#[AsCommand(
    name: 'apidocbundle:component:schema',
    description: 'generate a schema component from a Class'
)]
final class GenerateComponentSchemaCommand extends AbstractGenerateComponentCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            name: 'class',
            mode: InputArgument::OPTIONAL,
            description: 'name of the file do not add the extension (with the namespace exemple : "App\DTO\PostDTO")',
        );
        $this->addOption(
            name: 'skip',
            shortcut: 's',
            mode: InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            description: 'list of properties to skip when generating',
            default: [],
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$input->getArgument('class')) {
            $io = new SymfonyStyle($input, $output);
            $classes = $this->getAllClasses();

            if (empty($classes)) {
                $io->error('No classes found in configured directories.');

                return Command::FAILURE;
            }

            $choices = [];
            $classMap = [];

            foreach ($classes as $className) {
                $parts = explode('\\', $className);
                $shortName = end($parts);

                $formats = [];
                if ($this->getApiDocConfigHelper()->findYamlComponentFile($shortName, self::COMPONENT_SCHEMAS)) {
                    $formats[] = 'YAML';
                }
                if ($this->getApiDocConfigHelper()->findPhpComponentFile($shortName, self::COMPONENT_SCHEMAS)) {
                    $formats[] = 'PHP';
                }

                $label = $className;
                if (!empty($formats)) {
                    $label .= sprintf(' <fg=yellow>(Already exists in: %s)</>', implode(', ', $formats));
                }

                $choices[] = $label;
                $classMap[$label] = $className;
            }

            $selectedLabel = $io->choice('Select a class to generate schema for', $choices);
            $fullClassName = $classMap[$selectedLabel];

            if (!class_exists($fullClassName)) {
                $io->error(sprintf('Class "%s" not found.', $fullClassName));

                return Command::FAILURE;
            }

            $this->reflectionClass = new ReflectionClass($fullClassName);
        }

        $fullClassName = $this->checkIfClassExists($input, $output);
        if (!is_string($fullClassName)) {
            return Command::FAILURE;
        }
        $shortClassName = $this->getShortClassName();

        $array = self::createComponentArray();

        $properties = $this->propertyInfoExtractor->getProperties($fullClassName);
        if (null === $properties) {
            $properties = [];
        }

        $reflectionClass = $this->getReflectionClass();
        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $reflectionProperty) {
            if (!in_array($reflectionProperty->getName(), $properties, true)) {
                $properties[] = $reflectionProperty->getName();
            }
        }

        if (empty($properties)) {
            $output->writeln(sprintf('<error>No properties found for class %s</error>', $fullClassName));

            return Command::FAILURE;
        }

        if ($output->isVerbose()) {
            $output->writeln(sprintf('<info>Detected properties for class %s: %s</info>', $fullClassName, implode(', ', $properties)));
        }

        $propertiesToSkip = $input->getOption('skip');
        $propertiesArray = [];
        $requiredProperties = [];
        foreach ($properties as $property) {
            if (in_array($property, $propertiesToSkip, true)) {
                continue;
            }
            $types = $this->propertyInfoExtractor->getTypes($fullClassName, $property);

            if (empty($types)) {
                // Fallback using Reflection
                try {
                    $reflectionProperty = new ReflectionProperty($fullClassName, $property);
                    $reflectionType = $reflectionProperty->getType();

                    if ($reflectionType instanceof ReflectionNamedType) {
                        $typeName = $reflectionType->getName();
                        $nullable = $reflectionType->allowsNull();

                        if (class_exists($typeName) || interface_exists($typeName)) {
                            $types = [new Type(Type::BUILTIN_TYPE_OBJECT, $nullable, $typeName)];
                        } else {
                            $types = [new Type($typeName, $nullable)];
                        }
                    }
                } catch (ReflectionException $e) {
                    // ignore
                }
            }

            if (empty($types)) {
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('<comment>No types found for property %s. Defaulting to string.</comment>', $property));
                }
                $types = [new Type(Type::BUILTIN_TYPE_STRING, true)];
            }

            /** @var Type $firstType */
            $firstType = $types[0];

            if ($output->isVerbose()) {
                $output->writeln(sprintf('<info>Property %s: type %s</info>', $property, $firstType->getBuiltinType()));
            }

            //             add a warning for this property at the end of the command if it has multiple types

            self::addTypeToSchema($array, $shortClassName);

            self::addProperty($propertiesArray, $property, $firstType);
            if (!$firstType->isNullable()) {
                self::addRequirement($requiredProperties, $property);
            }

            self::addRequirementsToSchema($array, $requiredProperties, $shortClassName);
            self::addPropertiesToSchema($array, $propertiesArray, $shortClassName);
        }

        if ($output->isVerbose()) {
            $output->writeln('<info>Generated Schema Array:</info>');
            $json = json_encode($array, JSON_PRETTY_PRINT);
            if (false === $json) {
                $output->writeln('<error>Could not encode schema array to JSON</error>');
            } else {
                $output->writeln($json);
            }
        }

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_SCHEMAS;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $shortClassName, $input, $output, self::COMPONENT_SCHEMAS, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFile($array, $shortClassName, $input, $output, self::COMPONENT_SCHEMAS, $destination ?? null);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $schema
     */
    public static function addTypeToSchema(array &$schema, string $shortClassName): void
    {
        $schema['documentation']['components']['schemas'][$shortClassName]['type'] = 'object';
    }

    /**
     * @param array<mixed> $array
     * @param array<mixed> $requiredProperties
     */
    private static function addRequirementsToSchema(array &$array, array $requiredProperties, string $shortClassName): void
    {
        $array['documentation']['components']['schemas'][$shortClassName]['required'] = $requiredProperties;
    }

    /**
     * @param array<mixed> $array
     * @param array<mixed> $propertiesArray
     */
    private static function addPropertiesToSchema(array &$array, array $propertiesArray, string $shortClassName): void
    {
        $array['documentation']['components']['schemas'][$shortClassName]['properties'] = $propertiesArray;
    }

    /**
     * @return string[]
     */
    private function getAllClasses(): array
    {
        $classes = [];
        $scanDirectories = $this->parameterBag->get('ehyiah_api_doc.scan_directories');

        if (!is_array($scanDirectories)) {
            $scanDirectories = ['src'];
        }

        $finder = new Finder();
        $projectDir = $this->getKernel()->getProjectDir();

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
                    $className = $matches[1];
                    $classes[] = $namespace . '\\' . $className;
                }
            }
        }
        sort($classes);

        return $classes;
    }
}
