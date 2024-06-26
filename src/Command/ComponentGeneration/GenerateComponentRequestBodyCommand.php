<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\PropertyInfo\Type;

#[AsCommand(
    name: 'apidocbundle:component:body',
    description: 'generate a RequestBody component from a Class'
)]
final class GenerateComponentRequestBodyCommand extends AbstractGenerateComponentCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument(
            name: 'class',
            mode: InputArgument::REQUIRED,
            description: 'name of the file do not add the extension (with the namespace exemple : App\\\Form\\\MyFormType)',
        );

        $this->addOption(
            name: 'reference',
            shortcut: 'r',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Use a $ref as schema of the body request',
        );
    }

    /**
     * @throws ReflectionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fullClassName = $this->checkIfClassExists($input, $output);
        if (!is_string($fullClassName)) {
            return Command::FAILURE;
        }
        $shortClassName = $this->getShortClassName();

        $array = self::createComponentArray();

        if (null !== $input->getOption('reference')) {
            self::addRequestBodyToSchema($array, $shortClassName);

            $array['documentation']['components']['requestBodies'][$shortClassName]['content']['application/json']['schema'] = ['$ref' => '#/component/requestBodies/' . $shortClassName];
        } else {
            if ($this->getReflectionClass()->newInstance() instanceof AbstractType) {
                $form = $this->getReflectionClass()->getName();
                /** @phpstan-ignore-next-line */
                $form = $this->formFactory->create($form);
                $dataClass = $form->getConfig()->getOption('data_class');

                if (null !== $dataClass) {
                    self::addRequestBodyToSchema($array, $shortClassName);
                    /* @var class-string $dataClass */
                    $dataClassReflectionClass = new ReflectionClass($dataClass);

                    $array['documentation']['components']['requestBodies'][$shortClassName]['content']['application/json']['schema'] = ['$ref' => '#/component/requestBodies/' . $dataClassReflectionClass->getShortName()];
                } else {
                    $propertiesArray = [];
                    $ignoredProperty = [];
                    self::addRequestBodyToSchema($array, $shortClassName);

                    foreach ($form->all() as $child) {
                        $name = $child->getName();
                        $typeInformations = $this->guessTypeFromFormPrefix($child);

                        if (!self::addPropertyFromFormType($propertiesArray, $name, $typeInformations)) {
                            $ignoredProperty[] = $name;
                        }
                        self::addPropertiesToRequestBody($array, $propertiesArray, $shortClassName);
                    }

                    $output->writeln('<error>Properties ignored because no type can be guessed : ' . implode(' ,', $ignoredProperty) . ' Please edit these fields manually</error>');
                }
            } else {
                $properties = $this->propertyInfoExtractor->getProperties($fullClassName);
                $propertiesArray = [];
                $requiredProperties = [];
                foreach ($properties as $property) {
                    $types = $this->propertyInfoExtractor->getTypes($fullClassName, $property);
                    /** @var Type $firstType */
                    $firstType = $types[0];
                    //             add a warning for this property at the end of the command if it has multiple types

                    self::addRequestBodyToSchema($array, $shortClassName);

                    self::addProperty($propertiesArray, $property, $firstType);
                    if (!$firstType->isNullable()) {
                        self::addRequirement($requiredProperties, $property);
                    }

                    self::addRequirementsToRequestBody($array, $requiredProperties, $shortClassName);
                    self::addPropertiesToRequestBody($array, $propertiesArray, $shortClassName);
                }
            }
        }

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_REQUEST_BODIES;
        }

        $this->generateYamlFile($array, $shortClassName, $input, $output, self::COMPONENT_REQUEST_BODIES, $destination ?? null);

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $array
     */
    public static function addRequestBodyToSchema(array &$array, string $shortClassName): void
    {
        $array['documentation']['components']['requestBodies'][$shortClassName]['description'] = 'Modify my description';
        $array['documentation']['components']['requestBodies'][$shortClassName]['required'] = 'true';
        $array['documentation']['components']['requestBodies'][$shortClassName]['content']['application/json']['schema'] = [];
    }

    /**
     * @param array<mixed> $array
     * @param array<mixed> $properties
     */
    public static function addRequirementsToRequestBody(array &$array, array $properties, string $shortClassName): void
    {
        $array['documentation']['components']['requestBodies'][$shortClassName]['content']['application/json']['schema']['required'] = $properties;
    }

    /**
     * @param array<mixed> $array
     * @param array<mixed> $properties
     */
    public static function addPropertiesToRequestBody(array &$array, array $properties, string $shortClassName): void
    {
        $array['documentation']['components']['requestBodies'][$shortClassName]['content']['application/json']['schema']['properties'] = $properties;
    }
}
