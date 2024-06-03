<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use BackedEnum;
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
    description: 'generate a schema component from a Class'
)]
final class GenerateComponentSchemaCommand extends AbstractGenerateComponentCommand
{
    protected function configure(): void
    {
        parent::configure();

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

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = 'schemas/';
        }

        $this->generateYamlFile($array, $shortClassName, $input, $output, $destination ?? null);

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

        if ('array' === $type->getBuiltinType()) {
            $arrayType = $type->getCollectionValueTypes();
            if (isset($arrayType[0])) {
                /** @var class-string $itemClass */
                $itemClass = $type->getCollectionValueTypes()[0]->getClassName();
                $reflectionClass = (new ReflectionClass($itemClass));

                if (in_array(BackedEnum::class, $reflectionClass->getInterfaceNames())) {
                    $values = [];
                    $enumCases = $reflectionClass->getConstants();
                    /** @var BackedEnum $enumCase */
                    foreach ($enumCases as $enumCase) {
                        $values[] = $enumCase->value;
                    }
                    $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'array';
                    $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['enum'] = $values;

                    return;
                }

                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'array';

                return;
            }

            $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'array';

            return;
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

            if ($type->isCollection()) {
                $collectionClass = $type->getCollectionValueTypes()[0]->getClassName();
                /** @var class-string $collectionClass */
                $reflectionClass = (new ReflectionClass($collectionClass));
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'array';

                return;
            }

            if (in_array(BackedEnum::class, $interfaces)) {
                $values = [];
                $enumCases = $reflectionClass->getConstants();
                /** @var BackedEnum $enumCase */
                foreach ($enumCases as $enumCase) {
                    $values[] = $enumCase->value;
                }
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = 'string';
                $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['enum'] = $values;

                return;
            }

            $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['$ref'] = '#/components/schemas/' . $reflectionClass->getShortName();

            return;
        }

        $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['type'] = $type->getBuiltinType();
        $schema['documentation']['components']['schemas'][$shortClassName]['properties'][$property]['description'] = '';
    }
}
