<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'apidocbundle:component:securityScheme',
    description: 'Generate a reusable securityScheme component'
)]
final class GenerateComponentSecuritySchemeCommand extends AbstractGenerateComponentCommand
{
    public const COMPONENT_SECURITY_SCHEMES = 'securitySchemes';

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the security scheme component (e.g., ApiKeyAuth)');
        $this->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The type of the security scheme (apiKey, http, oauth2, openIdConnect)');
        $this->addOption('in', null, InputOption::VALUE_REQUIRED, 'For apiKey type: The location of the API key (query, header, cookie)');
        $this->addOption('scheme', 's', InputOption::VALUE_REQUIRED, 'For http type: The name of the HTTP Authorization scheme to be used (e.g., bearer)');
        $this->addOption('bearer-format', 'b', InputOption::VALUE_OPTIONAL, 'For http bearer type: A hint to the client to identify how the bearer token is formatted (e.g., JWT)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $type = $input->getOption('type');

        if (empty($type)) {
            $type = $io->askQuestion(new ChoiceQuestion(
                'Please specify the type of the security scheme',
                ['apiKey', 'http', 'oauth2', 'openIdConnect'],
                'http'
            ));
        }

        $securityScheme = ['type' => $type];

        switch ($type) {
            case 'apiKey':
                $in = $input->getOption('in');
                if (empty($in)) {
                    $in = $io->askQuestion(new ChoiceQuestion('Location of the API key (in)', ['query', 'header', 'cookie'], 'header'));
                }
                $apiKeyName = $io->askQuestion(new Question('API key name'));
                $securityScheme['in'] = $in;
                $securityScheme['name'] = $apiKeyName;
                break;
            case 'http':
                $scheme = $input->getOption('scheme');
                if (empty($scheme)) {
                    $scheme = $io->askQuestion(new Question('HTTP Authorization scheme', 'bearer'));
                }
                $securityScheme['scheme'] = $scheme;
                if ('bearer' === $scheme) {
                    $bearerFormat = $input->getOption('bearer-format') ?? $io->askQuestion(new Question('Bearer format (e.g., JWT)', 'JWT'));
                    $securityScheme['bearerFormat'] = $bearerFormat;
                }
                break;
        }

        $array = self::createComponentArray();
        $array['documentation']['components'][self::COMPONENT_SECURITY_SCHEMES][$name] = $securityScheme;

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_SECURITY_SCHEMES;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $name, $input, $output, self::COMPONENT_SECURITY_SCHEMES, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFile($array, $name, $input, $output, self::COMPONENT_SECURITY_SCHEMES, $destination ?? null);
        }

        return Command::SUCCESS;
    }
}
