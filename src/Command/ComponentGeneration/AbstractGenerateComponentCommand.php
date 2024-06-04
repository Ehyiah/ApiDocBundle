<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

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
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

abstract class AbstractGenerateComponentCommand extends Command
{
    protected ?string $dumpLocation = null;

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
     * @throws ReflectionException
     */
    protected function checkIfClassExists(InputInterface $input, OutputInterface $output): int|string
    {
        /** @var class-string $class */
        $class = $input->getArgument('class');
        $reflectionClass = new ReflectionClass($class);
        $fullClassName = $reflectionClass->getName();

        if (!class_exists($fullClassName)) {
            $output->writeln(sprintf('Class "%s" not found', $fullClassName));

            return Command::FAILURE;
        }

        return $fullClassName;
    }

    /**
     * @throws ReflectionException
     */
    protected function getShortClassName(string $fullClassName): string
    {
        /** @var class-string $fullClassName */
        $refectionClass = new ReflectionClass($fullClassName);

        return $refectionClass->getShortName();
    }
}
