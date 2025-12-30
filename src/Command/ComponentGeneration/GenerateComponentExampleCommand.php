<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'apidocbundle:component:example',
    description: 'Generate a reusable example component'
)]
final class GenerateComponentExampleCommand extends AbstractGenerateComponentCommand
{
    public const COMPONENT_EXAMPLES = 'examples';

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the example component (e.g., UserExample)');
        $this->addOption('summary', 's', InputOption::VALUE_OPTIONAL, 'A short summary of the example');
        $this->addOption('value', null, InputOption::VALUE_OPTIONAL, 'The example value, as a JSON string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $summary = $input->getOption('summary');
        $value = $input->getOption('value');

        if (empty($summary)) {
            $summary = $io->askQuestion(new Question('Please provide a short summary for the example'));
        }

        if (empty($value)) {
            $value = $io->askQuestion(new Question('Please provide the example value (JSON string)'));
        }

        $decodedValue = json_decode($value, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $io->error('Invalid JSON provided for the value.');

            return Command::FAILURE;
        }

        $exampleArray = [
            'summary' => $summary,
            'value' => $decodedValue,
        ];

        $array = self::createComponentArray();
        $array['documentation']['components'][self::COMPONENT_EXAMPLES][$name] = $exampleArray;

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_EXAMPLES;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $name, $input, $output, self::COMPONENT_EXAMPLES, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFile($array, $name, $input, $output, self::COMPONENT_EXAMPLES, $destination ?? null);
        }

        return Command::SUCCESS;
    }
}
