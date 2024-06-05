<?php

namespace Ehyiah\ApiDocBundle\Helper;

use LogicException;
use SplFileInfo;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

final class LoadApiDocConfigHelper
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public static function loadApiDocConfig(string $location, string $kernelProjectDir, string $dumpLocation): array
    {
        $config = [];
        $finder = new Finder();

        $finder->files()
            ->in($kernelProjectDir . $location)
            ->exclude($dumpLocation)
            ->name(['*.yaml', '*.yml'])
        ;

        if ($finder->hasResults()) {
            foreach ($finder->getIterator() as $import) {
                foreach (Yaml::parseFile($import) as $item) {
                    $config = array_merge_recursive($config, $item);
                }
            }
        }

        return $config;
    }

    /**
     * @return array<mixed>
     */
    public static function loadServerUrls(string $envUrls): array
    {
        $urls = [];

        $baseUrls = explode(',', $envUrls);
        foreach ($baseUrls as $index => $url) {
            $urls['servers'][$index]['url'] = $url;
        }

        return $urls;
    }

    public function findComponentFile(string $componentName, string $componentType): ?SplFileInfo
    {
        $finder = new Finder();
        $sourcePath = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($sourcePath)) {
            throw new LogicException('Location must be a string');
        }
        $dumpPath = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('dumpLocation must be a string');
        }

        $finder->files()
            ->in($this->kernel->getProjectDir() . $sourcePath)
            ->exclude($dumpPath)
            ->name(['*.yaml', '*.yml'])
        ;

        if ($finder->hasResults()) {
            foreach ($finder->getIterator() as $import) {
                foreach (Yaml::parseFile($import) as $item) {
                    if (isset($item['components'][$componentType][$componentName])) {
                        return $import;
                    }
                }
            }
        }

        return null;
    }
}
