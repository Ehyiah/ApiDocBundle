<?php

namespace Ehyiah\ApiDocBundle\Helper;

use Ehyiah\ApiDocBundle\Loader\PhpConfigLoader;
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
        private readonly ?PhpConfigLoader $phpConfigLoader = null,
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
     * Load API documentation from PHP config classes.
     *
     * @return array<mixed>
     */
    public function loadPhpConfigDoc(): array
    {
        if (null === $this->phpConfigLoader) {
            return [];
        }

        $enablePhpConfig = $this->parameterBag->get('ehyiah_api_doc.enable_php_config');
        if (!$enablePhpConfig) {
            return [];
        }

        return $this->phpConfigLoader->load();
    }

    /**
     * @return array<mixed>
     */
    public static function loadServerUrls(?string $envUrls): array
    {
        if (null === $envUrls || '' === $envUrls) {
            return [];
        }

        $urls = [];

        $baseUrls = explode(',', $envUrls);
        foreach ($baseUrls as $index => $url) {
            $url = trim($url);
            if ('' !== $url) {
                $urls['servers'][$index]['url'] = $url;
            }
        }

        return $urls;
    }

    /**
     * Merge two OpenAPI configurations.
     *
     * - Scalar values: second array overwrites first
     * - Sequential arrays (tags, servers): merged and deduplicated by 'name' or 'url' key
     * - Associative arrays (paths, components): recursively merged
     *
     * @param array<string, mixed> $base Base configuration
     * @param array<string, mixed> $override Override configuration
     *
     * @return array<string, mixed>
     */
    public static function mergeConfigs(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (!array_key_exists($key, $result)) {
                $result[$key] = $value;
                continue;
            }

            $baseValue = $result[$key];

            if (!is_array($value)) {
                $result[$key] = $value;
                continue;
            }

            if (!is_array($baseValue)) {
                $result[$key] = $value;
                continue;
            }

            if (self::isSequentialArray($value) && self::isSequentialArray($baseValue)) {
                $result[$key] = self::mergeSequentialArrays($baseValue, $value, $key);
            } else {
                $result[$key] = self::mergeConfigs($baseValue, $value);
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $array
     */
    private static function isSequentialArray(array $array): bool
    {
        if ([] === $array) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Merge sequential arrays, deduplicating by a key based on the context.
     *
     * @param array<int, mixed> $base
     * @param array<int, mixed> $override
     * @param string $contextKey The parent key to determine deduplication strategy
     *
     * @return array<int, mixed>
     */
    private static function mergeSequentialArrays(array $base, array $override, string $contextKey): array
    {
        $uniqueKey = match ($contextKey) {
            'tags' => 'name',
            'servers' => 'url',
            'security' => null,
            default => null,
        };

        if (null === $uniqueKey) {
            return array_merge($base, $override);
        }

        $existing = [];
        foreach ($base as $index => $item) {
            if (is_array($item) && isset($item[$uniqueKey])) {
                $existing[$item[$uniqueKey]] = $index;
            }
        }

        $result = $base;
        foreach ($override as $item) {
            if (is_array($item) && isset($item[$uniqueKey])) {
                $key = $item[$uniqueKey];
                if (isset($existing[$key])) {
                    $result[$existing[$key]] = array_merge($result[$existing[$key]], $item);
                } else {
                    $result[] = $item;
                }
            } else {
                $result[] = $item;
            }
        }

        return array_values($result);
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
