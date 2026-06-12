<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractGenerateComponentCommand;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\Console\Input\ArrayInput;
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

                $state = new RouteTuiState();
                $state->routeName = $event->getValue();
                $this->showConfigurationDashboard($tui, $state, $onBack);
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

    private function showConfigurationDashboard(Tui $tui, RouteTuiState $state, callable $onBack): void
    {
        $state->outputDir = $this->manager->getDefaultDumpLocation();

        $existingConfig = $this->manager->loadRouteConfig($state->routeName, AbstractGenerateComponentCommand::COMPONENT_ROUTES);

        if (!empty($existingConfig)) {
            $state->summary = $existingConfig['summary'] ?? '';
            $state->description = $existingConfig['description'] ?? '';
            $state->methods = $existingConfig['methods'] ?? [];
            $state->security = $existingConfig['security'] ?? [];
        }

        $settingItems = [];

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

        $settingItems[] = new SettingItem('summary', 'Résumé', $state->summary, 'Résumé de la route', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Description détaillée', [], $textInputCallback);

        // Methods
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $value = in_array($method, $state->methods, true) ? 'inclure' : 'exclure';
            $settingItems[] = new SettingItem('method_' . $method, 'Méthode : ' . $method, $value, 'Inclure/Exclure méthode', ['inclure', 'exclure']);
        }

        // Security
        foreach ($this->manager->getSecuritySchemes() as $scheme) {
            $value = in_array($scheme, $state->security, true) ? 'inclure' : 'exclure';
            $settingItems[] = new SettingItem('sec_' . $scheme, 'Sécurité : ' . $scheme, $value, 'Inclure/Exclure schéma de sécurité', ['inclure', 'exclure']);
        }

        // Schemas
        $schemas = $this->manager->getAvailableSchemas();
        $settingItems[] = new SettingItem('rb_schema', 'Schema RequestBody', $state->requestBodySchema ?? 'aucun', 'Choisir un schéma', array_merge(['aucun'], $schemas));
        $settingItems[] = new SettingItem('res_schema', 'Schema Réponse 200', $state->responseSchema ?? 'aucun', 'Choisir un schéma', array_merge(['aucun'], $schemas));

        $settingItems[] = new SettingItem('format', 'Format', $state->format, 'YAML ou PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('output', 'Dossier de sortie', $state->outputDir, 'Répertoire cible', [], $textInputCallback);

        $settingItems[] = new SettingItem('action_generate', 'Générer la route', '[Confirmer]', 'Lancer la génération.', ['[Confirmer]']);
        $settingItems[] = new SettingItem('action_cancel', 'Retour', '[Annuler]', 'Retourner à la liste.', ['[Annuler]']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>Configuration de la route : {$state->routeName}</info>\n")));
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
        SettingsListWidget $settingsWidget,
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
                $this->run($tui, new ArrayInput([]), $this->currentOutput, $onBack);
                break;
            case 'rb_schema':
            case 'res_schema':
                $field = ('rb_schema' === $event->getId()) ? 'requestBodySchema' : 'responseSchema';
                $this->showSchemaSelection($tui, $field, $state, $onBack);
                break;
            case 'action_generate':
                $state->summary = $settingsWidget->getValue('summary') ?? '';
                $state->description = $settingsWidget->getValue('desc') ?? '';
                $state->format = $settingsWidget->getValue('format') ?? 'yaml';
                $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();
                $state->requestBodySchema = 'aucun' !== $settingsWidget->getValue('rb_schema') ? $settingsWidget->getValue('rb_schema') : null;
                $state->responseSchema = 'aucun' !== $settingsWidget->getValue('res_schema') ? $settingsWidget->getValue('res_schema') : null;

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

                $routeData = $this->manager->getAllRoutes()[$state->routeName];
                $builder = new \Ehyiah\ApiDocBundle\Builder\ApiDocBuilder();

                if ($state->requestBodySchema) {
                    $this->manager->registerSchema($builder, $state->requestBodySchema);
                }
                if ($state->responseSchema) {
                    $this->manager->registerSchema($builder, $state->responseSchema);
                }

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

                    if ($state->requestBodySchema) {
                        $routeBuilder->requestBody()
                            ->content('application/json')
                            ->refByName($state->requestBodySchema)
                            ->end()
                        ;
                    }

                    if ($state->responseSchema) {
                        $routeBuilder->response(200)
                            ->content('application/json')
                            ->refByName($state->responseSchema)
                            ->end()
                        ;
                    }
                    $routeBuilder->end();
                }

                $array = $builder->build();
                unset($array['components']);

                $destination = AbstractGenerateComponentCommand::COMPONENT_ROUTES;
                $dumpDirectory = $this->kernel->getProjectDir() . $state->outputDir . u($destination)->ensureEnd('/');

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

                $this->currentOutput->writeln(sprintf('<info>Route "%s" générée avec succès dans %s</info>', $state->routeName, $dumpLocation));
                break;
        }
    }

    private function showSchemaSelection(Tui $tui, string $field, RouteTuiState $state, callable $onBack): void
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
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>Sélectionnez un schéma pour : $field</info>\n")));
        $container->add($selectWidget);
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $field, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                $state->$field = 'aucun' === $event->getValue() ? null : $event->getValue();
                // Retour au dashboard
                $this->showConfigurationDashboard($tui, $state, $onBack);
            }
        };

        $tui->addListener($listener);
    }

    private function handleCancel(
        CancelEvent $event,
        Tui $tui,
        SettingsListWidget $settingsWidget,
        callable $onBack,
        callable $changeListener,
        callable $cancelListener,
    ): void {
        if ($event->getTarget() === $settingsWidget) {
            $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
            $this->run($tui, new ArrayInput([]), $this->currentOutput, $onBack);
        }
    }
}
