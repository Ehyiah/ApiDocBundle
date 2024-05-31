<?php

namespace Ehyiah\ApiDocBundle\Composer;

use Composer\Installer\PackageEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

class ComposerScript
{
    public static function postPackageInstall(PackageEvent $event): void
    {
        $package = $event->getOperation();
        if (!str_contains($package, 'ehyiah/apidoc-bundle')) {
            return;
        }

        $rootDir = $event->getComposer()->getConfig()->get('vendor-dir') . '/../';
        $configFile = $rootDir . 'config/packages/ehyiah_api_doc.yaml';

        if (!file_exists($configFile)) {
            $event->getIO()->write('Copying config file in config/packages/ehyiah_api_doc.yaml');
            copy(__DIR__ . '/../Resources/ehyiah_api_doc.yaml', $configFile);
        } else {
            $event->getIO()->write('Config file already exists');
        }

        $defaultEnvFile = __DIR__ . '/../Resources/env.yaml';
        $envVariables = Yaml::parseFile($defaultEnvFile)['envs'];
        $envFile = $rootDir . '/.env';
        /** @var string $envContent */
        $envContent = file_get_contents($envFile);
        $addedValue = [];

        if (!str_contains($envContent, '###> ehyiah/api_doc_bundle ###')) {
            foreach ($envVariables as $key => $value) {
                $data = $key . '=' . $value;
                if (0 === count($addedValue)) {
                    file_put_contents($envFile, PHP_EOL . '###> ehyiah/api_doc_bundle ###' . PHP_EOL, FILE_APPEND);
                }
                $addedValue[] = $data;
                file_put_contents($envFile, $data . PHP_EOL, FILE_APPEND);
            }

            file_put_contents($envFile, '###< ehyiah/api_doc_bundle ###' . PHP_EOL, FILE_APPEND);
            $event->getIO()->write('Added env variables ' . implode(',', $addedValue));
        }

        $routesDir = $rootDir . 'config/routes/';
        $routeFile = $routesDir . 'ehyiah_api_doc.yaml';
        if (!file_exists($routeFile)) {
            $event->getIO()->write('Copying route file in config/routes/ehyiah_api_doc.yaml');
            copy(__DIR__ . '/../Resources/routes/ehyiah_api_doc.yaml', $routeFile);
        } else {
            $event->getIO()->write('Route file already exists');
        }

        $sourceDir = $rootDir . 'src/Swagger';
        if (!file_exists($sourceDir)) {
            $filesystem = new Filesystem();
            $filesystem->mirror(__DIR__ . '/../Swagger', $sourceDir);
        }
    }

    public static function prePackageUninstall(PackageEvent $event): void
    {
        $package = $event->getOperation();
        if (!str_contains($package, 'ehyiah/apidoc-bundle')) {
            return;
        }

        $event->getIO()->write('Removing file in config/packages/ehyiah_api_doc.yaml');

        $rootDir = $event->getComposer()->getConfig()->get('vendor-dir') . '/../';

        $configFile = $rootDir . 'config/packages/ehyiah_api_doc.yaml';
        if (file_exists($configFile)) {
            unlink($configFile);
        }

        $routeFile = $rootDir . 'config/routes/ehyiah_api_doc.yaml';
        if (file_exists($routeFile)) {
            unlink($routeFile);
        }
    }
}
