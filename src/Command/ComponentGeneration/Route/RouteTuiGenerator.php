<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Route;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractTuiComponentGenerator;
use Ehyiah\ApiDocBundle\Enum\ComponentType;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Event\SettingChangeEvent;
use Symfony\Component\Tui\Event\SubmitEvent;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SettingItem;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

use function Symfony\Component\String\u;

#[AsTuiGenerator]
class RouteTuiGenerator extends AbstractTuiComponentGenerator
{
    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly RouteTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
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
        $this->showRouteList($tui, $onBack);
    }

    private function showRouteList(Tui $tui, callable $onBack): void
    {
        $routes = $this->manager->getAllRoutes();
        $choices = [];
        foreach ($routes as $name => $data) {
            $choices[] = [
                'value' => $name,
                'label' => $this->currentOutput->getFormatter()->format(sprintf('  %-20s [%s] %s', $name, implode(',', $data['methods']), $data['path'])),
            ];
        }
        $choices[] = [
            'value' => '__back__',
            'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Retour]</comment>'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Sélection de route</info>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Naviguer  ↵ Sélectionner  Échap Retour</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                $value = $event->getValue();
                if ('__back__' === $value) {
                    $onBack();

                    return;
                }

                $state = new RouteTuiState();
                $state->routeName = $value;
                $state->outputDir = $this->manager->getDefaultDumpLocation();
                $this->loadExistingConfig($state);
                $state->loadedFrom = $this->manager->findRouteFile($state->routeName);
                $this->showMethodList($tui, $state, $onBack);
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

    private function loadExistingConfig(RouteTuiState $state): void
    {
        $existingConfig = $this->manager->loadRouteConfig($state->routeName, ComponentType::Routes->value);

        if (!empty($existingConfig['methodsConfig'])) {
            $state->methodsConfig = $existingConfig['methodsConfig'];
        } else {
            $allRoutes = $this->manager->getAllRoutes();
            if (isset($allRoutes[$state->routeName])) {
                foreach ($allRoutes[$state->routeName]['methods'] as $method) {
                    $upperMethod = strtoupper($method);
                    $state->methodsConfig[$upperMethod] = [
                        'operationId' => $state->routeName . '_' . strtolower($upperMethod),
                        'summary' => '',
                        'description' => '',
                        'security' => [],
                        'requestBodySchema' => null,
                        'responseSchema' => null,
                    ];
                }
            }
        }
    }

    private function showMethodList(Tui $tui, RouteTuiState $state, callable $onBack): void
    {
        $choices = [];
        foreach (self::HTTP_METHODS as $method) {
            $configured = isset($state->methodsConfig[$method]);
            $indicator = $configured ? '<fg=green>✓</fg=green>' : '<fg=gray>○</fg=gray>';
            $status = $configured ? '<fg=green>Configuré</fg=green>' : '<fg=gray>Non configuré</fg=gray>';
            $choices[] = [
                'value' => $method,
                'label' => $this->currentOutput->getFormatter()->format(sprintf('  %s %-10s %s', $indicator, $method, $status)),
            ];
        }

        $hasConfig = !empty($state->methodsConfig);
        if ($hasConfig) {
            $choices[] = [
                'value' => '__generate__',
                'label' => $this->currentOutput->getFormatter()->format('  <info>▸ Générer</info>'),
            ];
        }

        $choices[] = [
            'value' => '__back__',
            'label' => $this->currentOutput->getFormatter()->format('  <comment>← Retour</comment>'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $routePath = $this->manager->getAllRoutes()[$state->routeName]['path'] ?? '';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  Route: {$state->routeName}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>|  ' . $routePath . '</fg=gray>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Naviguer  ↵ Configurer  Échap Retour</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() !== $selectWidget) {
                return;
            }

            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

            $value = $event->getValue();

            if ('__back__' === $value) {
                $this->showRouteList($tui, $onBack);

                return;
            }

            if ('__generate__' === $value) {
                $this->generateRoute($tui, $state);

                return;
            }

            $this->showMethodConfig($tui, $value, $state, $onBack);
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showRouteList($tui, $onBack);
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showMethodConfig(Tui $tui, string $method, RouteTuiState $state, callable $onBack): void
    {
        $defaultOperationId = $state->routeName . '_' . strtolower($method);
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => $defaultOperationId,
            'summary' => '',
            'description' => '',
            'security' => [],
            'requestBodySchema' => null,
            'responseSchema' => null,
        ];

        $textInputCallback = static function (string $currentValue, callable $onDone) {
            $inputWidget = new InputWidget();
            $inputWidget->setValue($currentValue);
            $inputWidget->setPrompt('Saisie : ');
            $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone) {
                $onDone($event->getValue());
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $settingItems = [];
        $settingItems[] = new SettingItem('operationId', 'Operation ID', $config['operationId'], 'Identifiant unique de l\'opération', [], $textInputCallback);
        $settingItems[] = new SettingItem('summary', 'Résumé', $config['summary'], 'Résumé de l\'opération', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $config['description'], 'Description détaillée', [], $textInputCallback);

        foreach ($this->manager->getSecuritySchemes() as $scheme) {
            $value = in_array($scheme, $config['security'], true) ? 'inclure' : 'exclure';
            $settingItems[] = new SettingItem('sec_' . $scheme, 'Sécurité : ' . $scheme, $value, 'Inclure/Exclure schéma de sécurité', ['inclure', 'exclure']);
        }

        $schemas = $this->manager->getAvailableSchemas();
        $schemaChoices = array_merge(['aucun'], $schemas);
        $settingItems[] = new SettingItem('rb_schema', 'Schema RequestBody', $config['requestBodySchema'] ?? 'aucun', 'Choisir un schéma', $schemaChoices);
        $settingItems[] = new SettingItem('res_schema', 'Schema Réponse 200', $config['responseSchema'] ?? 'aucun', 'Choisir un schéma', $schemaChoices);

        $settingItems[] = new SettingItem('format', 'Format', $state->format, 'YAML ou PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('action_validate', 'Valider', '[Confirmer]', 'Sauvegarder et revenir à la liste.', ['[Confirmer]']);
        $settingItems[] = new SettingItem('action_delete', 'Supprimer', '[Supprimer]', 'Supprimer cette méthode de la route.', ['[Supprimer]']);
        $settingItems[] = new SettingItem('action_cancel', 'Annuler', '[Annuler]', 'Retourner sans sauvegarder.', ['[Annuler]']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$method} — {$state->routeName}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Valider    ⌫ Supprimer    Échap Annuler</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $method, $state, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() !== $settingsWidget) {
                return;
            }

            switch ($event->getId()) {
                case 'action_cancel':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodList($tui, $state, $onBack);
                    break;
                case 'action_delete':
                    unset($state->methodsConfig[$method]);
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodList($tui, $state, $onBack);
                    break;
                case 'rb_schema':
                case 'res_schema':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $field = ('rb_schema' === $event->getId()) ? 'requestBodySchema' : 'responseSchema';
                    $this->showMethodSchemaSelection($tui, $method, $field, $state, $onBack);
                    break;
                case 'action_validate':
                    $security = [];
                    foreach ($this->manager->getSecuritySchemes() as $scheme) {
                        if ('inclure' === $settingsWidget->getValue('sec_' . $scheme)) {
                            $security[] = $scheme;
                        }
                    }

                    $state->methodsConfig[$method] = [
                        'operationId' => $settingsWidget->getValue('operationId') ?? '',
                        'summary' => $settingsWidget->getValue('summary') ?? '',
                        'description' => $settingsWidget->getValue('desc') ?? '',
                        'security' => $security,
                        'requestBodySchema' => 'aucun' !== ($settingsWidget->getValue('rb_schema') ?? 'aucun') ? $settingsWidget->getValue('rb_schema') : null,
                        'responseSchema' => 'aucun' !== ($settingsWidget->getValue('res_schema') ?? 'aucun') ? $settingsWidget->getValue('res_schema') : null,
                    ];

                    $state->format = $settingsWidget->getValue('format') ?? 'yaml';

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodList($tui, $state, $onBack);
                    break;
            }
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $settingsWidget, $state, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() === $settingsWidget) {
                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showMethodList($tui, $state, $onBack);
            }
        };

        $tui->addListener($changeListener);
        $tui->addListener($cancelListener);
    }

    private function showMethodSchemaSelection(Tui $tui, string $method, string $field, RouteTuiState $state, callable $onBack): void
    {
        $schemas = $this->manager->getAvailableSchemas();
        $choices = [['value' => 'aucun', 'label' => 'Aucun']];
        foreach ($schemas as $schema) {
            $choices[] = ['value' => $schema, 'label' => $schema];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>Sélectionnez un schéma pour {$method} — {$field}</info>\n")));
        $container->add($selectWidget);
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $method, $field, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                $newValue = 'aucun' === $event->getValue() ? null : $event->getValue();

                // Preserve current values for other fields
                $defaultOperationId = $state->routeName . '_' . strtolower($method);
                $current = $state->methodsConfig[$method] ?? [
                    'operationId' => $defaultOperationId,
                    'summary' => '',
                    'description' => '',
                    'security' => [],
                    'requestBodySchema' => null,
                    'responseSchema' => null,
                ];
                $current[$field] = $newValue;
                $state->methodsConfig[$method] = $current; // @phpstan-ignore-line

                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($listener);
    }

    private function generateRoute(Tui $tui, RouteTuiState $state): void
    {
        $tui->stop();
        $this->currentOutput->writeln(sprintf('<info>Génération de la route "%s"...</info>', $state->routeName));

        $routeData = $this->manager->getAllRoutes()[$state->routeName];
        $builder = new \Ehyiah\ApiDocBundle\Builder\ApiDocBuilder();

        foreach ($state->methodsConfig as $method => $config) {
            if ($config['requestBodySchema']) {
                $this->manager->registerSchema($builder, $config['requestBodySchema']);
            }
            if ($config['responseSchema']) {
                $this->manager->registerSchema($builder, $config['responseSchema']);
            }
        }

        foreach ($state->methodsConfig as $method => $config) {
            $routeBuilder = $builder->addRoute()
                ->path($routeData['path'])
                ->method($method)
                ->operationId($config['operationId'])
                ->summary($config['summary'])
                ->description($config['description'])
            ;

            foreach ($config['security'] as $scheme) {
                $routeBuilder->security($scheme);
            }

            if ($config['requestBodySchema']) {
                $routeBuilder->requestBody()
                    ->content('application/json')
                    ->refByName($config['requestBodySchema'])
                    ->end()
                ;
            }

            if ($config['responseSchema']) {
                $routeBuilder->response(200)
                    ->content('application/json')
                    ->refByName($config['responseSchema'])
                    ->end()
                ;
            }

            $routeBuilder->end();
        }

        $array = $builder->build();
        unset($array['components']);

        $destination = ComponentType::Routes->value;

        $extension = 'yaml' === $state->format ? 'yaml' : 'php';

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->routeName . '.' . $extension;
        } else {
            $dumpDirectory = $this->kernel->getProjectDir() . $state->outputDir . u($destination)->ensureEnd('/');

            if (!is_dir($dumpDirectory)) {
                mkdir($dumpDirectory, 0755, true);
            }

            $dumpLocation = $dumpDirectory . $state->routeName . '.' . $extension;
        }

        if ('yaml' === $state->format) {
            $this->writeYamlFile($array, $dumpLocation, $this->currentOutput);
        } else {
            $phpCode = $this->generatePhpBuilderCode($array, $state->routeName, $destination);
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Route "%s" générée avec succès dans %s</info>', $state->routeName, $dumpLocation));
    }
}
