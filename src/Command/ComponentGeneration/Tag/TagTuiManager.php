<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tag;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

use function Symfony\Component\String\u;

class TagTuiManager
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getExistingComponents(): array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        $names = [];
        if (is_dir($directory)) {
            $finder = new Finder();
            $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
            foreach ($finder as $file) {
                $config = Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['tags'])) {
                    foreach ($config['documentation']['tags'] as $tag) {
                        if (isset($tag['name'])) {
                            $names[] = (string)$tag['name'];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Load the configuration of an existing tag from YAML files.
     *
     * @return array{name: string, description: string, externalDocsUrl: string, externalDocsDescription: string}|null
     */
    public function loadComponentConfig(string $name): ?array
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml']);
        foreach ($finder as $file) {
            $config = Yaml::parseFile($file->getRealPath());
            if (isset($config['documentation']['tags'])) {
                foreach ($config['documentation']['tags'] as $tag) {
                    if (isset($tag['name']) && (string)$tag['name'] === $name) {
                        $externalDocsUrl = '';
                        $externalDocsDescription = '';
                        if (isset($tag['externalDocs']) && is_array($tag['externalDocs'])) {
                            $externalDocsUrl = (string)($tag['externalDocs']['url'] ?? '');
                            $externalDocsDescription = (string)($tag['externalDocs']['description'] ?? '');
                        }

                        return [
                            'name' => $name,
                            'description' => (string)($tag['description'] ?? ''),
                            'externalDocsUrl' => $externalDocsUrl,
                            'externalDocsDescription' => $externalDocsDescription,
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find the YAML or PHP file path for a given tag name.
     */
    public function findComponentFile(string $name): ?string
    {
        $sourcePath = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');
        $directory = $this->kernel->getProjectDir() . $sourcePath;

        if (!is_dir($directory)) {
            return null;
        }

        $finder = new Finder();
        $finder->files()->in($directory)->name(['*.yaml', '*.yml', '*.php']);
        foreach ($finder as $file) {
            if ('php' === $file->getExtension()) {
                $content = file_get_contents($file->getRealPath());
                if (false !== $content && (str_contains($content, "'{$name}'") || str_contains($content, "\"{$name}\""))) {
                    return $file->getRealPath();
                }
            } else {
                $config = Yaml::parseFile($file->getRealPath());
                if (isset($config['documentation']['tags'])) {
                    foreach ($config['documentation']['tags'] as $tag) {
                        if (isset($tag['name']) && (string)$tag['name'] === $name) {
                            return $file->getRealPath();
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getDefaultDumpLocation(): string
    {
        $dumpLocation = (string)$this->parameterBag->get('ehyiah_api_doc.source_path');

        return (string)u($dumpLocation)->ensureStart('/')->ensureEnd('/');
    }
}
