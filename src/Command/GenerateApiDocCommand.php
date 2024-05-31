<?php

namespace Ehyiah\ApiDocBundle\Command;

use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'apidocbundle:api-doc:generate',
    description: 'generate api doc in different format'
)]
final class GenerateApiDocCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            name: 'format',
            shortcut: 'f',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'output format, yaml (default) or json',
            default: 'yaml',
        );
        $this->addOption(
            name: 'name',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'name of the file do not add the extension (default is api-doc)',
            default: 'api-doc',
        );

        $location = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }
        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'output dir',
            default: $location,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadConfigFiles($input->getOption('format'), $input->getOption('output'), $input->getOption('name'));

        return Command::SUCCESS;
    }

    private function loadConfigFiles(string $format, string $output, string $name): void
    {
        $config = [];
        $finder = new Finder();

        $baseUrl = $this->parameterBag->get('ehyiah_api_doc.site_url');

        $config['servers'][]['url'] = $baseUrl;

        $location = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }

        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('dumpLocation must be a string');
        }

        $finder->files()->in($this->kernel->getProjectDir() . $location)
            ->name(['*.yaml', '*.yml'])
            ->exclude($dumpLocation)
        ;

        if ($finder->hasResults()) {
            foreach ($finder->getIterator() as $import) {
                foreach (Yaml::parseFile($import) as $item) {
                    $config = array_merge_recursive($config, $item);
                }
            }
        }

        $fileSystem = new Filesystem();
        $dumpLocation = $this->kernel->getProjectDir() . $location . '/' . $dumpLocation;

        if (!file_exists($dumpLocation)) {
            $fileSystem->mkdir($dumpLocation);
        }
        $dumpName = $dumpLocation . '/' . $name . '.' . $format;

        if ('yaml' === $format) {
            $yaml = Yaml::dump($config, 8, 4, 1024);
            $fileSystem->dumpFile($dumpName, $yaml);
        }

        if ('json' === $format) {
            $jsonContent = json_encode($config);
            if (!is_string($jsonContent)) {
                throw new LogicException('problem while formatting json');
            }

            $fileSystem->dumpFile($dumpName, $jsonContent);
        }
    }
}
