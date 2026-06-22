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
        return 'Generate an API route component';
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
            'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Back]</comment>'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Route Selection</info>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Select  Esc Back</fg=gray>')));
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
                if (null !== $state->loadedFrom) {
                    $state->format = str_ends_with($state->loadedFrom, '.php') ? 'php' : 'yaml';
                }
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
            foreach ($existingConfig['methodsConfig'] as $method => $config) {
                $state->methodsConfig[$method] = [
                    'operationId' => $config['operationId'],
                    'summary' => $config['summary'],
                    'description' => $config['description'],
                    'security' => $config['security'],
                    'tags' => $config['tags'],
                    'requestBodySchema' => $config['requestBodySchema'],
                    'requestBodyExample' => $config['requestBodyExample'],
                    'responses' => $config['responses'],
                ];
            }
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
                        'tags' => [],
                        'requestBodySchema' => null,
                        'requestBodyExample' => null,
                        'responses' => [],
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
            $status = $configured ? '<fg=green>Configured</fg=green>' : '<fg=gray>Not configured</fg=gray>';
            $choices[] = [
                'value' => $method,
                'label' => $this->currentOutput->getFormatter()->format(sprintf('  %s %-10s %s', $indicator, $method, $status)),
            ];
        }

        $hasConfig = !empty($state->methodsConfig);
        if ($hasConfig) {
            $choices[] = [
                'value' => '__generate__',
                'label' => $this->currentOutput->getFormatter()->format('  <info>▸ Generate</info>'),
            ];
        }

        $choices[] = [
            'value' => '__settings__',
            'label' => $this->currentOutput->getFormatter()->format('  <comment>⚙ Settings (Format: ' . strtoupper($state->format) . ')</comment>'),
        ];

        $choices[] = [
            'value' => '__back__',
            'label' => $this->currentOutput->getFormatter()->format('  <comment>← Back</comment>'),
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
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Configure  Esc Back</fg=gray>')));
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

            if ('__settings__' === $value) {
                $this->showRouteSettings($tui, $state, $onBack);

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

    private function showRouteSettings(Tui $tui, RouteTuiState $state, callable $onBack): void
    {
        $textInputCallback = static function (string $currentValue, callable $onDone) {
            $inputWidget = new InputWidget();
            $inputWidget->setValue($currentValue);
            $inputWidget->setPrompt('Input: ');
            $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone) {
                $onDone($event->getValue());
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $settingItems = [];
        $settingItems[] = new SettingItem('format', 'Format', $state->format, 'YAML or PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('output', 'Output Directory', $state->outputDir, 'Target directory', [], $textInputCallback);
        $settingItems[] = new SettingItem('action_save', 'Save', '✓ Save', 'Save and return to method list.', ['✓ Save']);
        $settingItems[] = new SettingItem('action_cancel', 'Cancel', '← Cancel', 'Return without saving.', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $routePath = $this->manager->getAllRoutes()[$state->routeName]['path'] ?? '';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  Route: {$state->routeName} — Settings</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>|  ' . $routePath . '</fg=gray>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Save    Esc Cancel</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $state, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() !== $settingsWidget) {
                return;
            }

            switch ($event->getId()) {
                case 'action_cancel':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodList($tui, $state, $onBack);
                    break;
                case 'action_save':
                    $state->format = $settingsWidget->getValue('format') ?? 'yaml';
                    $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();
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

    private function showMethodConfig(Tui $tui, string $method, RouteTuiState $state, callable $onBack): void
    {
        $defaultOperationId = $state->routeName . '_' . strtolower($method);
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => $defaultOperationId,
            'summary' => '',
            'description' => '',
            'security' => [],
            'tags' => [],
            'requestBodySchema' => null,
            'requestBodyExample' => null,
            'responses' => [],
        ];

        $textInputCallback = static function (string $currentValue, callable $onDone) {
            $inputWidget = new InputWidget();
            $inputWidget->setValue($currentValue);
            $inputWidget->setPrompt('Input: ');
            $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone) {
                $onDone($event->getValue());
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $schemas = $this->manager->getAvailableSchemas();
        $examples = $this->manager->getAvailableExamples();

        $settingItems = [];
        $settingItems[] = new SettingItem('operationId', 'Operation ID', $config['operationId'], 'Unique operation identifier', [], $textInputCallback);
        $settingItems[] = new SettingItem('summary', 'Summary', $config['summary'], 'Operation summary', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $config['description'], 'Detailed description', [], $textInputCallback);

        $secLabel = count($config['security']) > 0 ? 'Security (' . implode(', ', $config['security']) . ')' : 'Security (none)';
        $settingItems[] = new SettingItem('action_security', $secLabel, '[Configure]', 'Manage security schemes', ['[Configure]']);

        $tagLabel = count($config['tags']) > 0 ? 'Tags (' . implode(', ', $config['tags']) . ')' : 'Tags (none)';
        $settingItems[] = new SettingItem('action_tags', $tagLabel, '[Configure]', 'Manage tags', ['[Configure]']);

        $settingItems[] = new SettingItem('rb_schema', 'Schema RequestBody', $config['requestBodySchema'] ?? 'none', 'Choose a schema', array_merge(['none'], $schemas));

        $rbExampleLabel = $config['requestBodyExample'] ? "Example RB: {$config['requestBodyExample']}" : 'Example RB: none';
        $settingItems[] = new SettingItem('action_rb_example', $rbExampleLabel, '[Select]', 'Choose an example for request body', ['[Select]']);

        $responseCodes = array_keys($config['responses']);
        $responseLabel = count($responseCodes) > 0 ? 'Responses (' . implode(', ', array_map('strval', $responseCodes)) . ')' : 'Responses (none)';
        $settingItems[] = new SettingItem('action_responses', $responseLabel, '[Configure]', 'Manage response codes', ['[Configure]']);

        $settingItems[] = new SettingItem('action_validate', 'Save', '✓ Confirm', 'Save and return to the list.', ['✓ Confirm']);
        $settingItems[] = new SettingItem('action_delete', 'Delete', '✗ Delete', 'Delete this method from the route.', ['✗ Delete']);
        $settingItems[] = new SettingItem('action_cancel', 'Cancel', '← Cancel', 'Return without saving.', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$method} — {$state->routeName}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Save    ⌫ Delete    Esc Cancel</fg=gray>')));
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
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodSchemaSelection($tui, $method, 'requestBodySchema', $state, $onBack);
                    break;
                case 'action_rb_example':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showMethodExampleSelection($tui, $method, 'requestBodyExample', $state, $onBack);
                    break;
                case 'action_responses':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showResponseList($tui, $method, $state, $onBack);
                    break;
                case 'action_tags':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showTagSelection($tui, $method, $state, $onBack);
                    break;
                case 'action_security':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showSecuritySelection($tui, $method, $state, $onBack);
                    break;
                case 'action_validate':
                    $rbExample = $settingsWidget->getValue('rb_example') ?? 'none';
                    $rbExample = 'none' !== $rbExample ? $rbExample : null;

                    $state->methodsConfig[$method] = [
                        'operationId' => $settingsWidget->getValue('operationId') ?? '',
                        'summary' => $settingsWidget->getValue('summary') ?? '',
                        'description' => $settingsWidget->getValue('desc') ?? '',
                        'security' => $state->methodsConfig[$method]['security'] ?? [],
                        'tags' => $state->methodsConfig[$method]['tags'] ?? [],
                        'requestBodySchema' => 'none' !== ($settingsWidget->getValue('rb_schema') ?? 'none') ? $settingsWidget->getValue('rb_schema') : null,
                        'requestBodyExample' => $rbExample,
                        'responses' => $state->methodsConfig[$method]['responses'] ?? [],
                    ];

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
        $choices = [['value' => 'none', 'label' => 'None']];
        foreach ($schemas as $schema) {
            $choices[] = ['value' => $schema, 'label' => $schema];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>Select a schema for {$method} — {$field}</info>\n")));
        $container->add($selectWidget);
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $method, $field, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                $newValue = 'none' === $event->getValue() ? null : $event->getValue();

                // Preserve current values for other fields
                $defaultOperationId = $state->routeName . '_' . strtolower($method);
                $current = $state->methodsConfig[$method] ?? [
                    'operationId' => $defaultOperationId,
                    'summary' => '',
                    'description' => '',
                    'security' => [],
                    'tags' => [],
                    'requestBodySchema' => null,
                    'requestBodyExample' => null,
                    'responses' => [],
                ];
                $current[$field] = $newValue;
                $state->methodsConfig[$method] = $current; // @phpstan-ignore-line

                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($listener);
    }

    private function showMethodExampleSelection(Tui $tui, string $method, string $field, RouteTuiState $state, callable $onBack): void
    {
        $examples = $this->manager->getAvailableExamples();
        $choices = [['value' => 'none', 'label' => 'None']];
        foreach ($examples as $example) {
            $choices[] = ['value' => $example, 'label' => $example];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  Select Example for {$method}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Select  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $method, $field, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                $newValue = 'none' === $event->getValue() ? null : $event->getValue();

                // Preserve current values for other fields
                $defaultOperationId = $state->routeName . '_' . strtolower($method);
                $current = $state->methodsConfig[$method] ?? [
                    'operationId' => $defaultOperationId,
                    'summary' => '',
                    'description' => '',
                    'security' => [],
                    'tags' => [],
                    'requestBodySchema' => null,
                    'requestBodyExample' => null,
                    'responses' => [],
                ];
                $current[$field] = $newValue;
                $state->methodsConfig[$method] = $current; // @phpstan-ignore-line

                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $selectWidget, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                // Go back to method config without changing example
            }
        };

        $tui->addListener($listener);
        $tui->addListener($cancelListener);

        // Re-show method config on cancel (Esc)
        $cancelHandler = function (CancelEvent $event) use ($tui, $method, $state, $onBack) {
            $this->showMethodConfig($tui, $method, $state, $onBack);
        };
        $tui->addListener($cancelHandler);
    }

    private function showResponseExampleSelection(Tui $tui, string $method, int $statusCode, RouteTuiState $state, callable $onBack): void
    {
        $examples = $this->manager->getAvailableExamples();
        $choices = [['value' => 'none', 'label' => 'None']];
        foreach ($examples as $example) {
            $choices[] = ['value' => $example, 'label' => $example];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  Select Example for {$method} — {$statusCode}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Select  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $method, $statusCode, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                $newValue = 'none' === $event->getValue() ? null : $event->getValue();

                // Update the response example in state
                if (isset($state->methodsConfig[$method]['responses'][$statusCode])) {
                    $state->methodsConfig[$method]['responses'][$statusCode]['example'] = $newValue;
                }

                $this->showResponseConfig($tui, $method, $statusCode, $state, $onBack);
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $selectWidget, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
            }
        };

        $tui->addListener($listener);
        $tui->addListener($cancelListener);

        // Re-show response config on cancel (Esc)
        $cancelHandler = function (CancelEvent $event) use ($tui, $method, $statusCode, $state, $onBack) {
            $this->showResponseConfig($tui, $method, $statusCode, $state, $onBack);
        };
        $tui->addListener($cancelHandler);
    }

    private function showResponseList(Tui $tui, string $method, RouteTuiState $state, callable $onBack): void
    {
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => '',
            'summary' => '',
            'description' => '',
            'security' => [],
            'tags' => [],
            'requestBodySchema' => null,
            'requestBodyExample' => null,
            'responses' => [],
        ];
        $responses = $config['responses'];

        $formatter = $this->currentOutput->getFormatter();
        $choices = [];

        foreach ($responses as $statusCode => $responseData) {
            $schema = $responseData['schema'] ?? 'none';
            $desc = $responseData['description'];
            $label = sprintf('  %d — %s%s', $statusCode, $schema, $desc ? " ({$desc})" : '');
            $choices[] = [
                'value' => (string)$statusCode,
                'label' => $formatter->format($label),
            ];
        }

        $choices[] = [
            'value' => '__add__',
            'label' => $formatter->format('  <info>+ Add a response code</info>'),
        ];
        $choices[] = [
            'value' => '__back__',
            'label' => $formatter->format('  <comment>← Back</comment>'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($formatter->format("<info>|  Responses — {$method} — {$state->routeName}</info>")));
        $container->add(new TextWidget($formatter->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↑↓ Navigate  ↵ Configure  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $method, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() !== $selectWidget) {
                return;
            }

            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

            $value = $event->getValue();
            if ('__back__' === $value) {
                $this->showMethodConfig($tui, $method, $state, $onBack);

                return;
            }

            if ('__add__' === $value) {
                $this->showResponseConfig($tui, $method, null, $state, $onBack);

                return;
            }

            $statusCode = (int)$value;
            $this->showResponseConfig($tui, $method, $statusCode, $state, $onBack);
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $selectWidget, $method, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    /**
     * @param array<int, string> $selectedTags
     * @param array<int, string> $availableTags
     *
     * @return array<int, array{value: string, label: string|null}>
     */
    private function buildTagChoices(array $selectedTags, array $availableTags): array
    {
        $formatter = $this->currentOutput->getFormatter();
        $choices = [];
        foreach ($availableTags as $tag) {
            $isChecked = in_array($tag, $selectedTags, true);
            $indicator = $isChecked ? '<fg=green>✓</fg=green>' : '<fg=gray>○</fg=gray>';
            $choices[] = [
                'value' => $tag,
                'label' => $formatter->format(sprintf('  %s %s', $indicator, $tag)),
            ];
        }
        $choices[] = ['value' => '__save__', 'label' => $formatter->format('  <info>▸ Save</info>')];
        $choices[] = ['value' => '__back__', 'label' => $formatter->format('  <comment>← Back</comment>')];

        return $choices;
    }

    private function showTagSelection(Tui $tui, string $method, RouteTuiState $state, callable $onBack): void
    {
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => '',
            'summary' => '',
            'description' => '',
            'security' => [],
            'tags' => [],
            'requestBodySchema' => null,
            'requestBodyExample' => null,
            'responses' => [],
        ];
        $selectedTags = $config['tags'];
        $availableTags = $this->manager->getAvailableTags();

        $formatter = $this->currentOutput->getFormatter();
        $choices = $this->buildTagChoices($selectedTags, $availableTags);

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $tagCount = count($selectedTags);
        $headerWidget = new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>\n<info>|  Tags — {$method} — {$state->routeName}</info>\n<fg=gray>|  Selected: {$tagCount}</fg=gray>\n<info>+---------------------------------------------+</info>\n"));
        $container->add($headerWidget);
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↑↓ Navigate  ↵ Toggle  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $method, &$selectedTags, $availableTags, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() !== $selectWidget) {
                return;
            }

            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

            $value = $event->getValue();

            if ('__back__' === $value) {
                $this->showMethodConfig($tui, $method, $state, $onBack);

                return;
            }

            if ('__save__' === $value) {
                $this->showMethodConfig($tui, $method, $state, $onBack);

                return;
            }

            // Toggle tag
            if (in_array($value, $selectedTags, true)) {
                $selectedTags = array_values(array_filter($selectedTags, static fn ($t) => $t !== $value));
            } else {
                $selectedTags[] = $value;
            }

            $state->methodsConfig[$method]['tags'] = $selectedTags;

            // Update widget items in-place and re-register listener
            $currentIndex = $selectWidget->getSelectedItem();
            $newChoices = $this->buildTagChoices($selectedTags, $availableTags);
            $selectWidget->setItems($newChoices);

            // Restore cursor position
            if (null !== $currentIndex) {
                $newIndex = array_search($currentIndex['value'], array_column($newChoices, 'value'), true);
                if (false !== $newIndex) {
                    $selectWidget->setSelectedIndex($newIndex);
                }
            }

            $tui->requestRender();

            // Re-register listeners
            $tui->getEventDispatcher()->addListener(SelectEvent::class, $selectListener); // @phpstan-ignore-line
            $tui->getEventDispatcher()->addListener(CancelEvent::class, $cancelListener); // @phpstan-ignore-line
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $selectWidget, $method, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    /**
     * @param array<int, string> $selectedSchemes
     * @param array<int, string> $availableSchemes
     *
     * @return array<int, array{value: string, label: string|null}>
     */
    private function buildSecurityChoices(array $selectedSchemes, array $availableSchemes): array
    {
        $formatter = $this->currentOutput->getFormatter();
        $choices = [];
        foreach ($availableSchemes as $scheme) {
            $isChecked = in_array($scheme, $selectedSchemes, true);
            $indicator = $isChecked ? '<fg=green>✓</fg=green>' : '<fg=gray>○</fg=gray>';
            $choices[] = [
                'value' => $scheme,
                'label' => $formatter->format(sprintf('  %s %s', $indicator, $scheme)),
            ];
        }
        $choices[] = ['value' => '__save__', 'label' => $formatter->format('  <info>▸ Save</info>')];
        $choices[] = ['value' => '__back__', 'label' => $formatter->format('  <comment>← Back</comment>')];

        return $choices;
    }

    private function showSecuritySelection(Tui $tui, string $method, RouteTuiState $state, callable $onBack): void
    {
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => '',
            'summary' => '',
            'description' => '',
            'security' => [],
            'tags' => [],
            'requestBodySchema' => null,
            'requestBodyExample' => null,
            'responses' => [],
        ];
        $selectedSchemes = $config['security'];
        $availableSchemes = $this->manager->getSecuritySchemes();

        $formatter = $this->currentOutput->getFormatter();
        $choices = $this->buildSecurityChoices($selectedSchemes, $availableSchemes);

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $secCount = count($selectedSchemes);
        $headerWidget = new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>\n<info>|  Security — {$method} — {$state->routeName}</info>\n<fg=gray>|  Selected: {$secCount}</fg=gray>\n<info>+---------------------------------------------+</info>\n"));
        $container->add($headerWidget);
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↑↓ Navigate  ↵ Toggle  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $method, &$selectedSchemes, $availableSchemes, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() !== $selectWidget) {
                return;
            }

            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

            $value = $event->getValue();

            if ('__back__' === $value) {
                $this->showMethodConfig($tui, $method, $state, $onBack);

                return;
            }

            if ('__save__' === $value) {
                $this->showMethodConfig($tui, $method, $state, $onBack);

                return;
            }

            // Toggle scheme
            if (in_array($value, $selectedSchemes, true)) {
                $selectedSchemes = array_values(array_filter($selectedSchemes, static fn ($s) => $s !== $value));
            } else {
                $selectedSchemes[] = $value;
            }

            $state->methodsConfig[$method]['security'] = $selectedSchemes;

            // Update widget items in-place and re-register listener
            $currentIndex = $selectWidget->getSelectedItem();
            $newChoices = $this->buildSecurityChoices($selectedSchemes, $availableSchemes);
            $selectWidget->setItems($newChoices);

            // Restore cursor position
            if (null !== $currentIndex) {
                $newIndex = array_search($currentIndex['value'], array_column($newChoices, 'value'), true);
                if (false !== $newIndex) {
                    $selectWidget->setSelectedIndex($newIndex);
                }
            }

            $tui->requestRender();

            // Re-register listeners
            $tui->getEventDispatcher()->addListener(SelectEvent::class, $selectListener); // @phpstan-ignore-line
            $tui->getEventDispatcher()->addListener(CancelEvent::class, $cancelListener); // @phpstan-ignore-line
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $selectWidget, $method, $state, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showMethodConfig($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showResponseConfig(Tui $tui, string $method, ?int $statusCode, RouteTuiState $state, callable $onBack): void
    {
        $config = $state->methodsConfig[$method] ?? [
            'operationId' => '',
            'summary' => '',
            'description' => '',
            'security' => [],
            'tags' => [],
            'requestBodySchema' => null,
            'requestBodyExample' => null,
            'responses' => [],
        ];
        $responses = $config['responses'];
        $isNew = null === $statusCode;

        $currentResponse = $responses[$statusCode] ?? ['schema' => null, 'description' => '', 'example' => null];
        $schemas = $this->manager->getAvailableSchemas();
        $examples = $this->manager->getAvailableExamples();

        $textInputCallback = static function (string $currentValue, callable $onDone) {
            $inputWidget = new InputWidget();
            $inputWidget->setValue($currentValue);
            $inputWidget->setPrompt('Input: ');
            $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone) {
                $onDone($event->getValue());
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $formatter = $this->currentOutput->getFormatter();
        $settingItems = [];

        if ($isNew) {
            $settingItems[] = new SettingItem('statusCode', 'HTTP Code', '200', 'HTTP response code (e.g. 200, 404, 500)', [], $textInputCallback);
        }

        $settingItems[] = new SettingItem('res_schema', 'Schema', $currentResponse['schema'] ?? 'none', 'Response schema', array_merge(['none'], $schemas));

        $resExampleLabel = ($currentResponse['example'] ?? null) ? "Example: {$currentResponse['example']}" : 'Example: none';
        $settingItems[] = new SettingItem('action_res_example', $resExampleLabel, '[Select]', 'Choose a response example', ['[Select]']);

        $settingItems[] = new SettingItem('res_desc', 'Description', $currentResponse['description'], 'Response description', [], $textInputCallback);

        $settingItems[] = new SettingItem('action_validate', 'Save', '✓ Confirm', 'Save', ['✓ Confirm']);

        if (!$isNew) {
            $settingItems[] = new SettingItem('action_delete', 'Delete', '✗ Delete', 'Delete this response', ['✗ Delete']);
        }

        $settingItems[] = new SettingItem('action_cancel', 'Back', '← Cancel', 'Return without saving', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $title = $isNew ? 'New response' : "Response {$statusCode}";
        $container->add(new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($formatter->format("<info>|  {$title} — {$method}</info>")));
        $container->add(new TextWidget($formatter->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↵ Save    Esc Cancel</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $method, $statusCode, $isNew, $state, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() !== $settingsWidget) {
                return;
            }

            switch ($event->getId()) {
                case 'action_cancel':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showResponseList($tui, $method, $state, $onBack);
                    break;
                case 'action_delete':
                    unset($state->methodsConfig[$method]['responses'][$statusCode]);
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showResponseList($tui, $method, $state, $onBack);
                    break;
                case 'action_res_example':
                    // Save current values before going to example selection
                    if ($isNew) {
                        $newStatusCode = (int)($settingsWidget->getValue('statusCode') ?? 200);
                    } else {
                        $newStatusCode = (int)$statusCode;
                    }
                    $currentSchema = $settingsWidget->getValue('res_schema') ?? 'none';
                    $state->methodsConfig[$method]['responses'][$newStatusCode] = [
                        'schema' => 'none' !== $currentSchema ? $currentSchema : null,
                        'description' => $settingsWidget->getValue('res_desc') ?? '',
                        'example' => $state->methodsConfig[$method]['responses'][$statusCode]['example'] ?? null,
                    ];

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showResponseExampleSelection($tui, $method, $newStatusCode, $state, $onBack);
                    break;
                case 'action_validate':
                    $newStatusCode = $isNew ? (int)($settingsWidget->getValue('statusCode') ?? 200) : (int)$statusCode;
                    $schema = $settingsWidget->getValue('res_schema') ?? 'none';
                    $schema = 'none' !== $schema ? $schema : null;

                    $state->methodsConfig[$method]['responses'][$newStatusCode] = [
                        'schema' => $schema,
                        'description' => $settingsWidget->getValue('res_desc') ?? '',
                        'example' => $state->methodsConfig[$method]['responses'][$statusCode]['example'] ?? null,
                    ];

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showResponseList($tui, $method, $state, $onBack);
                    break;
            }
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $settingsWidget, $method, $state, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() === $settingsWidget) {
                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showResponseList($tui, $method, $state, $onBack);
            }
        };

        $tui->addListener($changeListener);
        $tui->addListener($cancelListener);
    }

    private function generateRoute(Tui $tui, RouteTuiState $state): void
    {
        $tui->stop();
        $this->currentOutput->writeln(sprintf('<info>Generating route "%s"...</info>', $state->routeName));

        $routeData = $this->manager->getAllRoutes()[$state->routeName];
        $builder = new \Ehyiah\ApiDocBundle\Builder\ApiDocBuilder();

        foreach ($state->methodsConfig as $method => $config) {
            if ($config['requestBodySchema']) {
                $this->manager->registerSchema($builder, $config['requestBodySchema']);
            }
            foreach ($config['responses'] as $responseData) {
                if ($responseData['schema']) {
                    $this->manager->registerSchema($builder, $responseData['schema']);
                }
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

            foreach ($config['tags'] as $tag) {
                $routeBuilder->tag($tag);
            }

            foreach ($config['security'] as $scheme) {
                $routeBuilder->security($scheme);
            }

            if ($config['requestBodySchema']) {
                $contentBuilder = $routeBuilder->requestBody()
                    ->content('application/json')
                    ->refByName($config['requestBodySchema'])
                ;
                if ($config['requestBodyExample']) {
                    $contentBuilder->addExample($config['requestBodyExample'])
                        ->ref('#/components/examples/' . $config['requestBodyExample'])
                    ;
                }
                $contentBuilder->end();
            }

            foreach ($config['responses'] as $statusCode => $responseData) {
                $responseBuilder = $routeBuilder->response($statusCode);
                if ($responseData['description']) {
                    $responseBuilder->description($responseData['description']);
                }
                if ($responseData['schema']) {
                    $contentBuilder = $responseBuilder->content('application/json')
                        ->refByName($responseData['schema'])
                    ;
                    if ($responseData['example'] ?? null) {
                        $contentBuilder->addExample($responseData['example'])
                            ->ref('#/components/examples/' . $responseData['example'])
                        ;
                    }
                    $contentBuilder->end();
                }
                $responseBuilder->end();
            }

            $routeBuilder->end();
        }

        $array = $builder->build();
        unset($array['components']);
        $array = ['documentation' => $array];

        $destination = ComponentType::Routes->value;

        $extension = 'yaml' === $state->format ? 'yaml' : 'php';

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->routeName . '.' . $extension;
            // Merge with existing config to preserve manually-added fields (YAML only)
            if ('php' !== pathinfo($state->loadedFrom, PATHINFO_EXTENSION)) {
                $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
                $existingPaths = $existingConfig['documentation']['paths'] ?? $existingConfig['paths'] ?? null;
                if (null !== $existingPaths) {
                    $array = array_replace_recursive($existingConfig, $array);
                }
            }
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
            $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($state->routeName, $destination);
            if (null !== $phpComponentFile) {
                $dumpLocation = $phpComponentFile->getPathname();
            } else {
                $dumpLocation = dirname($dumpLocation) . '/' . self::componentNameToClassName($state->routeName) . '.php';
            }
            $phpCode = $this->generatePhpBuilderCode($array, $state->routeName, $destination, $this->resolveNamespaceFromFile($dumpLocation));
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Route "%s" generated successfully in %s</info>', $state->routeName, $dumpLocation));
    }
}
