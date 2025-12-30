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
    name: 'apidocbundle:component:header',
    description: 'Generate a reusable header component'
)]
final class GenerateComponentHeaderCommand extends AbstractGenerateComponentCommand
{
    public const COMPONENT_HEADERS = 'headers';

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the header component (e.g., X-Request-ID)');
        $this->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A description for the header');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The schema type of the header (e.g., string, integer)', 'string');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $description = $input->getOption('description');
        $type = $input->getOption('type');

        if (empty($description)) {
            $description = $io->askQuestion(new Question('Please provide a description for the header'));
        }

        $headerArray = [
            'description' => $description,
            'schema' => [
                'type' => $type,
            ],
        ];

        $array = self::createComponentArray();
        $array['documentation']['components'][self::COMPONENT_HEADERS][$name] = $headerArray;

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_HEADERS;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $name, $input, $output, self::COMPONENT_HEADERS, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFile($array, $name, $input, $output, self::COMPONENT_HEADERS, $destination ?? null);
        }

        return Command::SUCCESS;
    }
}
