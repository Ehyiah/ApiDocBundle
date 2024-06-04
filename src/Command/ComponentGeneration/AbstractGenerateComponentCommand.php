<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use BackedEnum;
use DateTimeInterface;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

abstract class AbstractGenerateComponentCommand extends Command
{
    protected ?string $dumpLocation = null;
    /** @phpstan-ignore-next-line */
    protected ?ReflectionClass $reflectionClass = null;

    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly ParameterBagInterface $parameterBag,
        protected readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
    ) {
        parent::__construct();

        $this->initializeClass();
    }

    protected function initializeClass(): void
    {
        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('Location must be a string');
        }

        $this->dumpLocation = u($dumpLocation)->ensureStart('/');
        $this->dumpLocation = u($dumpLocation)->ensureEnd('/');
    }

    protected function configure(): void
    {
        if (null === $this->dumpLocation) {
            $this->initializeClass();
        }

        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output dir, pass a relative path to the kernel_project_dir',
            default: $this->dumpLocation,
        );
    }

    /**
     * @param array<mixed> $array
     */
    protected function generateYamlFile(array $array, string $fileName, InputInterface $input, OutputInterface $output, ?string $destination = null): void
    {
        $outputDir = $input->getOption('output');
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');

        $yaml = Yaml::dump($array, 12, 4, 1024);
        $dumpLocation = $this->kernel->getProjectDir() . $outputDir . $destination . $fileName . '.yaml';

        $fileSystem = new Filesystem();
        if ($fileSystem->exists($dumpLocation)) {
            $output->writeln('File <question>' . $dumpLocation . '</question> already exists');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to overwrite this file ? (yes or no, default is true)', true);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<error>Aborting</error>');

                return;
            }
        }

        $fileSystem->dumpFile($dumpLocation, $yaml);
        $output->writeln('File generated at <info>' . $dumpLocation . '<info>');
    }

    /**
     * @return array<mixed>
     */
    public static function createComponentArray(): array
    {
        return [
            'documentation' => [
                'components' => [
                ],
            ],
        ];
    }

    /**
     * @phpstan-ignore-next-line
     */
    protected function getReflectionClass(?InputInterface $input = null): ReflectionClass
    {
        if (null !== $input) {
            /* @var class-string $fqcn */
            $fqcn = $input->getArgument('class');
            if (null === $this->reflectionClass) {
                $this->reflectionClass = new ReflectionClass($fqcn);
            }
        }

        if (null === $this->reflectionClass) {
            throw new LogicException('Class not found');
        }

        return $this->reflectionClass;
    }

    protected function checkIfClassExists(InputInterface $input, OutputInterface $output): int|string
    {
        $fullClassName = $this->getReflectionClass($input)->getName();

        if (!class_exists($fullClassName)) {
            $output->writeln(sprintf('Class "%s" not found', $fullClassName));

            return Command::FAILURE;
        }

        return $fullClassName;
    }

    /**
     * @throws ReflectionException
     */
    protected function getShortClassName(): string
    {
        return $this->getReflectionClass()->getShortName();
    }

    /**
     * @param array<mixed> $schema
     */
    public static function addProperty(array &$schema, string $property, Type $type): void
    {
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
                    $schema[$property]['type'] = 'array';
                    $schema[$property]['enum'] = $values;

                    return;
                }

                $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                $schema[$property]['type'] = 'array';

                return;
            }

            $schema[$property]['type'] = 'array';

            return;
        }

        if (null !== $type->getClassName()) {
            /** @var class-string $className */
            $className = $type->getClassName();
            $reflectionClass = new ReflectionClass($className);
            $interfaces = $reflectionClass->getInterfaceNames();

            if (in_array(DateTimeInterface::class, $interfaces)) {
                $schema[$property]['type'] = 'string';
                $schema[$property]['format'] = 'date-time';

                return;
            }

            if ($type->isCollection()) {
                $collectionClass = $type->getCollectionValueTypes()[0]->getClassName();
                /** @var class-string $collectionClass */
                $reflectionClass = (new ReflectionClass($collectionClass));
                $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                $schema[$property]['type'] = 'array';

                return;
            }

            if (in_array(BackedEnum::class, $interfaces)) {
                $values = [];
                $enumCases = $reflectionClass->getConstants();
                /** @var BackedEnum $enumCase */
                foreach ($enumCases as $enumCase) {
                    $values[] = $enumCase->value;
                }
                $schema[$property]['type'] = 'string';
                $schema[$property]['enum'] = $values;

                return;
            }

            $schema[$property]['$ref'] = '#/components/schemas/' . $reflectionClass->getShortName();

            return;
        }

        $schema[$property]['type'] = $type->getBuiltinType();
        $schema[$property]['description'] = '';
    }

    /**
     * @param array<mixed> $array
     */
    public static function addRequirement(array &$array, string $property): void
    {
        $array[] = $property;
    }
}
