<?php

namespace Ehyiah\ApiDocBundle\Command;

use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractGenerateComponentCommand extends Command
{
    protected string $dumpLocation;

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

        $this->dumpLocation = $dumpLocation;
    }

    /**
     * @param array<mixed> $array
     */
    protected function generateYamlFile(array $array, string $fileName, InputInterface $input, OutputInterface $output): void
    {
        $yaml = Yaml::dump($array, 8, 4, 1024);
        $dumpPath = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('Yaml dump must be a string');
        }
        $dumpLocation = $this->kernel->getProjectDir() . $dumpPath . '/' . $fileName . '.yaml';

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
}
