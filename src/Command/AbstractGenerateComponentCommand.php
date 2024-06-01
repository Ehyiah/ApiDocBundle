<?php

namespace Ehyiah\ApiDocBundle\Command;

use LogicException;
use Symfony\Component\Console\Command\Command;
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
    protected function generateYamlFile(array $array, string $fileName): void
    {
        $yaml = Yaml::dump($array, 8, 4, 1024);
        $dumpPath = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('Yaml dump must be a string');
        }
        $dumpLocation = $this->kernel->getProjectDir() . $dumpPath . '/' . $fileName . '.yaml';

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($dumpLocation, $yaml);
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
