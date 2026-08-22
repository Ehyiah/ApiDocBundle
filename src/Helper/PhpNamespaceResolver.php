<?php

namespace Ehyiah\ApiDocBundle\Helper;

use Composer\Autoload\ClassLoader;

/**
 * Resolves PHP namespaces from file paths using Composer's PSR-4 autoloading prefixes.
 */
final class PhpNamespaceResolver
{
    /**
     * Resolve the fully-qualified namespace for a given absolute path
     * by matching it against Composer's PSR-4 autoloading prefixes.
     *
     * For example, if '/var/www/project/src/Swagger' is under the PSR-4 prefix
     * 'App\\' => 'src/', the resolved namespace is 'App\\Swagger'.
     */
    public static function resolveNamespace(string $absolutePath): ?string
    {
        foreach (spl_autoload_functions() as $autoloadFunction) {
            if (!is_array($autoloadFunction) || !$autoloadFunction[0] instanceof ClassLoader) {
                continue;
            }

            foreach ($autoloadFunction[0]->getPrefixesPsr4() as $prefix => $paths) {
                foreach ($paths as $path) {
                    $resolvedPath = realpath($path);

                    if (false === $resolvedPath) {
                        continue;
                    }

                    if (!str_starts_with($absolutePath, $resolvedPath)) {
                        continue;
                    }

                    $relativePath = substr($absolutePath, strlen($resolvedPath) + 1);

                    if ('' === $relativePath) {
                        return rtrim($prefix, '\\');
                    }

                    return rtrim($prefix, '\\') . '\\' . str_replace('/', '\\', $relativePath);
                }
            }
        }

        return null;
    }
}
