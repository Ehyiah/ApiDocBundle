<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SettingChangeEvent;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsTuiGenerator]
class RouteTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly RouteTuiManager $manager,
        \Symfony\Component\HttpKernel\KernelInterface $kernel,
        \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag,
        \Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface $propertyInfoExtractor,
        \Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Route';
    }

    public function getDescription(): string
    {
        return 'Générer un composant de route API';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function run(Tui $tui, InputInterface $input, OutputInterface $output, callable $onBack): void
    {
        $this->currentOutput = $output;
        $routes = $this->manager->getAllRoutes();
        $choices = [];
        foreach ($routes as $name => $data) {
            $choices[] = [
                'value' => $name,
                'label' => sprintf('%s [%s] %s', $name, implode(',', $data['methods']), $data['path']),
            ];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($output->getFormatter()->format("<info>Génération de Route : Sélection de route</info>\n")));
        $container->add($selectWidget);
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                $selectedClass = $event->getValue();
                $this->showConfigurationDashboard($tui, $selectedClass, $onBack);
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $onBack();
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showConfigurationDashboard(Tui $tui, string $selectedClass, callable $onBack): void
    {
        $state = new RouteTuiState();
        $state->routeName = $selectedClass;
        $state->outputDir = $this->manager->getDefaultDumpLocation();

        $existingConfig = $this->manager->loadRouteConfig($selectedClass, \Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractGenerateComponentCommand::COMPONENT_ROUTES);

        $this->currentOutput->writeln("<comment>DEBUG: Checking route file for: $selectedClass</comment>");
        $this->currentOutput->writeln('<comment>DEBUG: Dump location: ' . $this->manager->getDefaultDumpLocation() . '</comment>');

        if (!empty($existingConfig)) {
            $state->summary = $existingConfig['summary'] ?? '';
            $state->description = $existingConfig['description'] ?? '';
            $state->methods = $existingConfig['methods'] ?? [];
            $state->security = $existingConfig['security'] ?? [];
        }

        $settingItems = [];

        $textInputCallback = static function (string $currentValue, callable $onDone) {
            $inputWidget = new \Symfony\Component\Tui\Widget\InputWidget();
            $inputWidget->setValue($currentValue);
            $inputWidget->setPrompt('Saisie : ');
            $inputWidget->onSubmit(static function (\Symfony\Component\Tui\Event\SubmitEvent $event) use ($onDone) {
                $onDone($event->getValue());
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('summary', 'Résumé', $state->summary, 'Résumé de la route', [], $textInputCallback);
        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('desc', 'Description', $state->description, 'Description détaillée', [], $textInputCallback);

        // Methods
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $value = in_array($method, $state->methods, true) ? 'inclure' : 'exclure';
            $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('method_' . $method, 'Méthode : ' . $method, $value, 'Inclure/Exclure méthode', ['inclure', 'exclure']);
        }

        // Security
        foreach ($this->manager->getSecuritySchemes() as $scheme) {
            $value = in_array($scheme, $state->security, true) ? 'inclure' : 'exclure';
            $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('sec_' . $scheme, 'Sécurité : ' . $scheme, $value, 'Inclure/Exclure schéma de sécurité', ['inclure', 'exclure']);
        }

        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('format', 'Format', $state->format, 'YAML ou PHP', ['yaml', 'php']);
        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('output', 'Dossier de sortie', $state->outputDir, 'Répertoire cible', [], $textInputCallback);

        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('action_generate', 'Générer la route', '[Confirmer]', 'Lancer la génération.', ['[Confirmer]']);
        $settingItems[] = new \Symfony\Component\Tui\Widget\SettingItem('action_cancel', 'Retour', '[Annuler]', 'Retourner à la liste.', ['[Annuler]']);

        $settingsWidget = new \Symfony\Component\Tui\Widget\SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>Configuration de la route : $selectedClass</info>\n")));
        $container->add($settingsWidget);
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $state, $onBack, &$changeListener, &$cancelListener) {
            $this->handleSettingChange($event, $tui, $settingsWidget, $state, $onBack, $changeListener, $cancelListener);
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $settingsWidget, $onBack, &$changeListener, &$cancelListener) {
            $this->handleCancel($event, $tui, $settingsWidget, $onBack, $changeListener, $cancelListener);
        };

        $tui->addListener($changeListener);
        $tui->addListener($cancelListener);
    }

    private function handleSettingChange(
        SettingChangeEvent $event,
        Tui $tui,
        \Symfony\Component\Tui\Widget\SettingsListWidget $settingsWidget,
        RouteTuiState $state,
        callable $onBack,
        callable $changeListener,
        callable $cancelListener,
    ): void {
        if ($event->getTarget() !== $settingsWidget) {
            return;
        }

        switch ($event->getId()) {
            case 'action_cancel':
                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->run($tui, new \Symfony\Component\Console\Input\ArrayInput([]), $this->currentOutput, $onBack);
                break;
            case 'action_generate':
                $state->summary = $settingsWidget->getValue('summary') ?? '';
                $state->description = $settingsWidget->getValue('desc') ?? '';
                $state->format = $settingsWidget->getValue('format') ?? 'yaml';
                $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                $state->methods = [];
                foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
                    if ('inclure' === $settingsWidget->getValue('method_' . $method)) {
                        $state->methods[] = $method;
                    }
                }

                $state->security = [];
                foreach ($this->manager->getSecuritySchemes() as $scheme) {
                    if ('inclure' === $settingsWidget->getValue('sec_' . $scheme)) {
                        $state->security[] = $scheme;
                    }
                }

                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $tui->stop();

                // 1. Construire la route avec RouteBuilder
                $routeData = $this->manager->getAllRoutes()[$state->routeName];
                $builder = new \Ehyiah\ApiDocBundle\Builder\ApiDocBuilder();

                foreach ($state->methods as $method) {
                    $routeBuilder = $builder->addRoute()
                        ->path($routeData['path'])
                        ->method($method)
                        ->summary($state->summary)
                        ->description($state->description)
                    ;

                    foreach ($state->security as $scheme) {
                        $routeBuilder->security($scheme);
                    }
                    $routeBuilder->end();
                }

                $array = $builder->build();

                // 2. Déterminer le chemin de sortie et écrire
                $destination = \Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractGenerateComponentCommand::COMPONENT_ROUTES;
                $dumpDirectory = $this->kernel->getProjectDir() . $state->outputDir . \Symfony\Component\String\u($destination)->ensureEnd('/');

                if (!is_dir($dumpDirectory)) {
                    mkdir($dumpDirectory, 0755, true);
                }

                if ('yaml' === $state->format) {
                    $dumpLocation = $dumpDirectory . $state->routeName . '.yaml';
                    $this->writeYamlFile($array, $dumpLocation, $this->currentOutput);
                } else {
                    $dumpLocation = $dumpDirectory . $state->routeName . '.php';
                    $phpCode = $this->generatePhpBuilderCode($array, $state->routeName, $destination);
                    $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
                }

                $this->currentOutput->writeln(sprintf('<info>Route "%s" générée avec succès dans %s (Méthodes: %s)</info>', $state->routeName, $dumpLocation, implode(', ', $state->methods)));
                break;
        }
    }

    private function handleCancel(
        CancelEvent $event,
        Tui $tui,
        \Symfony\Component\Tui\Widget\SettingsListWidget $settingsWidget,
        callable $onBack,
        callable $changeListener,
        callable $cancelListener,
    ): void {
        if ($event->getTarget() === $settingsWidget) {
            $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
            $this->run($tui, new \Symfony\Component\Console\Input\ArrayInput([]), $this->currentOutput, $onBack);
        }
    }
}
