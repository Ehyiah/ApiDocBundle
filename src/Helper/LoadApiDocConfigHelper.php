<?php

namespace Ehyiah\ApiDocBundle\Helper;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class LoadApiDocConfigHelper
{
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
}
