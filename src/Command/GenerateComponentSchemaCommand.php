<?php

namespace Ehyiah\ApiDocBundle\Command;

use DateTimeInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyInfo\Type;

#[AsCommand(
    name: 'apidocbundle:component:schema',
    description: 'generate a schema component from a Class (DTO or Entity only)'
)]
final class GenerateComponentSchemaCommand extends AbstractGenerateComponentCommand
{
    protected function configure(): void
    {
        $this->addArgument(
            name: 'class',
            mode: InputArgument::REQUIRED,
            description: 'name of the file do not add the extension (with the namespace exemple : App\\\DTO\\\PostDTO)',
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fullClassName = (new ReflectionClass($input->getArgument('class')))->getName();

        if (!class_exists($fullClassName)) {
            $output->writeln(sprintf('Class "%s" not found', $fullClassName));

            return Command::FAILURE;
        }

        $shortClassName = (new ReflectionClass($fullClassName))->getShortName();

        $array = self::createComponentArray();

        $properties = $this->propertyInfoExtractor->getProperties($fullClassName);
        foreach ($properties as $property) {
            $types = $this->propertyInfoExtractor->getTypes($fullClassName, $property);
            /** @var Type $firstType */
            $firstType = $types[0];
            //             add a warning for this property at the end of the command if it has multiple types

            self::addTypeToSchema($array, $shortClassName);
            self::addPropertyToSchema($array, $shortClassName, $property, $firstType);
        }

        // add verif if yaml file already exist for this class and ask if override or cancel command
        $this->generateYamlFile($array, '/schemas/' . $shortClassName);

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
     * @param array<mixed> $schema
     *
     * @throws ReflectionException
     */
    public static function addPropertyToSchema(array &$schema, string $shortClassName, string $property, Type $type): void
    {
        if (!$type->isNullable()) {
            $schema['documentation']['components']['schemas'][$shortClassName]['required'][] = $property;
        }

        if (null !== $type->getClassName()) {
            /** @var class-string $className */
            $className = $type->getClassName();
            $reflectionClass = new ReflectionClass($className);
            $interfaces = $reflectionClass->getInterfaceNames();

            if (in_array(DateTimeInterface::class, $interfaces)) {
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'string';
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['format'] = 'date-time';

                return;
            }

            $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['$ref'] = '#/components/schemas/' . $reflectionClass->getShortName();

            return;
        }

        $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = $type->getBuiltinType();
    }
}
