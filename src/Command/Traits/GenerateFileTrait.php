<?php

namespace Ehyiah\ApiDocBundle\Command\Traits;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

/**
 * Trait providing common file generation functionality for API documentation commands.
 */
trait GenerateFileTrait
{
    abstract protected function getKernel(): KernelInterface;

    abstract protected function getParameterBag(): ParameterBagInterface;

    abstract protected function getApiDocConfigHelper(): LoadApiDocConfigHelper;

    protected function addFormatOption(): void
    {
        $this->addOption(
            name: 'format',
            shortcut: 'f',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output format: yaml, php, or both',
            default: 'php',
        );
    }

    protected function getSourcePath(): string
    {
        $sourcePath = $this->getParameterBag()->get('ehyiah_api_doc.source_path');
        if (!is_string($sourcePath)) {
            throw new LogicException('source_path must be a string');
        }

        return $sourcePath;
    }

    protected function getDumpPath(): string
    {
        $dumpPath = $this->getParameterBag()->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('dump_path must be a string');
        }

        return $dumpPath;
    }

    /**
     * Check if a YAML file with the same name exists and ask for confirmation.
     *
     * @return bool True if should continue, false if aborted
     */
    protected function checkExistingYamlFile(
        string $filePath,
        InputInterface $input,
        OutputInterface $output,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $output->writeln('<info>File already exists: ' . $filePath . '</info>');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Do you want to overwrite this file? (yes or no, default is YES)</question>', true);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    /**
     * Check if a PHP file with the same name exists and ask for confirmation.
     *
     * @return bool True if should continue, false if aborted
     */
    protected function checkExistingPhpFile(
        string $filePath,
        InputInterface $input,
        OutputInterface $output,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $output->writeln('<info>File already exists: ' . $filePath . '</info>');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Do you want to overwrite this file? (yes or no, default is YES)</question>', true);

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    /**
     * Warn user about existing file in another format.
     *
     * @return bool True if should continue, false if aborted
     */
    protected function warnAboutOtherFormat(
        string $filePath,
        string $currentFormat,
        InputInterface $input,
        OutputInterface $output,
    ): bool {
        $fileSystem = new Filesystem();

        if ($fileSystem->exists($filePath)) {
            $otherFormat = 'yaml' === $currentFormat ? 'PHP' : 'YAML';
            $output->writeln('<warning>A ' . $otherFormat . ' file also exists: ' . $filePath . '</warning>');
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                '<question>Do you want to continue? This may cause duplicate definitions. (yes or no, default is YES)</question>',
                true
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('');
                $output->writeln('<error>Aborting generation</error>');

                return false;
            }
        }

        return true;
    }

    /**
     * Write content to a YAML file.
     *
     * @param array<mixed> $array
     */
    protected function writeYamlFile(array $array, string $filePath, OutputInterface $output): void
    {
        $fileSystem = new Filesystem();
        $directory = dirname($filePath);

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $yaml = Yaml::dump($array, 12, 4, 1024);
        $fileSystem->dumpFile($filePath, $yaml);

        $output->writeln('<comment>YAML file generated at</comment> <info>' . $filePath . '</info>');
    }

    /**
     * Write content to a PHP file.
     */
    protected function writePhpFile(string $phpCode, string $filePath, OutputInterface $output): void
    {
        $fileSystem = new Filesystem();
        $directory = dirname($filePath);

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $fileSystem->dumpFile($filePath, $phpCode);

        $output->writeln('<comment>PHP file generated at</comment> <info>' . $filePath . '</info>');
    }

    /**
     * Build the full output path for a file.
     */
    protected function buildOutputPath(string $outputDir, string $filename, string $extension, ?string $subdirectory = null): string
    {
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');

        $path = $this->getKernel()->getProjectDir() . $outputDir;

        if (null !== $subdirectory) {
            $path .= u($subdirectory)->ensureEnd('/');
        }

        return $path . $filename . '.' . $extension;
    }
}
