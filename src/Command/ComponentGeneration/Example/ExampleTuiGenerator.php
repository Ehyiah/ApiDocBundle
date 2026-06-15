<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractTuiComponentGenerator;
use Ehyiah\ApiDocBundle\Enum\ComponentType;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use stdClass;
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
class ExampleTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly ExampleTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Example';
    }

    public function getDescription(): string
    {
        return 'Generate a data example component';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function run(Tui $tui, InputInterface $input, OutputInterface $output, callable $onBack): void
    {
        $this->currentOutput = $output;
        $this->showComponentList($tui, $onBack);
    }

    private function showComponentList(Tui $tui, callable $onBack): void
    {
        $existing = $this->manager->getExistingComponents();
        $choices = [];
        foreach ($existing as $name) {
            $choices[] = ['value' => $name, 'label' => $this->currentOutput->getFormatter()->format(sprintf('  %-20s', $name))];
        }
        $choices[] = ['value' => '__new__', 'label' => $this->currentOutput->getFormatter()->format('  <fg=green>[+ New]</fg=green>')];
        $choices[] = ['value' => '__back__', 'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Back]</comment>')];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Examples                                 |</info>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Select  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() !== $selectWidget) {
                return;
            }
            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

            $value = $event->getValue();
            if ('__back__' === $value) {
                $onBack();

                return;
            }

            $state = new ExampleTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->name = $existing['name'];
                    $state->summary = $existing['summary'];
                    $state->description = $existing['description'];
                    $state->value = $existing['value'];
                    $state->externalValue = $existing['externalValue'];
                }
                $state->loadedFrom = $this->manager->findComponentFile($value);
            }
            $this->showForm($tui, $state, $onBack);
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

    private function showForm(Tui $tui, ExampleTuiState $state, callable $onBack): void
    {
        // Track full JSON value separately (preview text is just for display)
        $fullJsonValue = $state->value;

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

        $valueInputCallback = static function (string $currentValue, callable $onDone) use (&$fullJsonValue) {
            $inputWidget = new InputWidget();
            $inputWidget->setValue($fullJsonValue);
            $inputWidget->setPrompt('JSON: ');
            $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone, &$fullJsonValue) {
                $fullJsonValue = $event->getValue();
                $onDone('JSON object defined');
            });
            $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                $onDone(null);
            });

            return $inputWidget;
        };

        $settingItems = [];
        $settingItems[] = new SettingItem('name', 'Name', $state->name, 'Example name (e.g. SuccessfulLogin)', [], $textInputCallback);
        $settingItems[] = new SettingItem('summary', 'Summary', $state->summary, 'Short summary', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Detailed description', [], $textInputCallback);

        $schemaLabel = $state->schemaRef ? "Schema: {$state->schemaRef}" : 'Schema: none';
        $settingItems[] = new SettingItem('action_schema', $schemaLabel, '[Select]', 'Choose a schema to auto-generate example', ['[Select]']);

        // Show preview of JSON value (single line)
        $valuePreview = '' !== $state->value ? 'JSON object defined' : '(empty)';
        $settingItems[] = new SettingItem('value', 'Value (JSON)', $valuePreview, 'Press Enter to edit JSON value', [], $valueInputCallback);
        $settingItems[] = new SettingItem('externalValue', 'External Value (URL)', $state->externalValue, 'URL pointing to the example value', [], $textInputCallback);

        $settingItems[] = new SettingItem('format_output', 'Output Format', $state->format_output, 'YAML or PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('output', 'Output Directory', $state->outputDir, 'Target directory', [], $textInputCallback);
        $settingItems[] = new SettingItem('action_validate', 'Save', '✓ Confirm', 'Save and return to the list.', ['✓ Confirm']);
        $settingItems[] = new SettingItem('action_cancel', 'Cancel', '← Cancel', 'Return without saving.', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $title = $state->name ? "Edit: {$state->name}" : 'New Example';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$title}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Save    Esc Cancel</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $state, $onBack, &$fullJsonValue, &$changeListener, &$cancelListener) {
            if ($event->getTarget() !== $settingsWidget) {
                return;
            }

            switch ($event->getId()) {
                case 'action_cancel':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showComponentList($tui, $onBack);
                    break;
                case 'action_schema':
                    // Save current values before going to schema selection
                    $state->name = $settingsWidget->getValue('name') ?? '';
                    $state->summary = $settingsWidget->getValue('summary') ?? '';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $state->value = $fullJsonValue;
                    $state->externalValue = $settingsWidget->getValue('externalValue') ?? '';
                    $state->format_output = $settingsWidget->getValue('format_output') ?? 'yaml';
                    $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showSchemaSelection($tui, $state, $onBack);
                    break;
                case 'action_validate':
                    $state->name = $settingsWidget->getValue('name') ?? '';
                    $state->summary = $settingsWidget->getValue('summary') ?? '';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $state->value = $fullJsonValue;
                    $state->externalValue = $settingsWidget->getValue('externalValue') ?? '';
                    $state->format_output = $settingsWidget->getValue('format_output') ?? 'yaml';
                    $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $tui->stop();
                    $this->generateComponent($state);
                    break;
            }
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $settingsWidget, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() === $settingsWidget) {
                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showComponentList($tui, $onBack);
            }
        };

        $tui->addListener($changeListener);
        $tui->addListener($cancelListener);
    }

    private function showSchemaSelection(Tui $tui, ExampleTuiState $state, callable $onBack): void
    {
        $schemas = $this->manager->getAvailableSchemas();
        $choices = [['value' => 'none', 'label' => 'None (clear schema)']];
        foreach ($schemas as $schema) {
            $choices[] = ['value' => $schema, 'label' => $schema];
        }

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Select a Schema</info>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  Select a schema to auto-generate the example value</fg=gray>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  or choose "None" to clear</fg=gray>\n')));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Navigate  ↵ Select  Esc Back</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $listener = function (SelectEvent $event) use ($tui, $selectWidget, $state, $onBack, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);

                $selectedSchema = $event->getValue();
                if ('none' === $selectedSchema) {
                    $state->schemaRef = null;
                } else {
                    $state->schemaRef = $selectedSchema;
                    // Auto-generate example from schema
                    $schemaConfig = $this->manager->loadSchemaConfig($selectedSchema);
                    if (null !== $schemaConfig) {
                        $example = $this->generateExampleFromSchema($selectedSchema, $schemaConfig);
                        $json = json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $state->value = false !== $json ? $json : '';
                    }
                }

                $this->showForm($tui, $state, $onBack);
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $selectWidget, &$listener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $listener);
                // Go back to form without changing schema
            }
        };

        $tui->addListener($listener);
        $tui->addListener($cancelListener);

        // Re-show form on cancel (Esc)
        $cancelHandler = function (CancelEvent $event) use ($tui, $state, $onBack) {
            $this->showForm($tui, $state, $onBack);
        };
        $tui->addListener($cancelHandler);
    }

    /**
     * Generate an example value from a schema definition.
     *
     * @param array<string, mixed> $schema
     */
    private function generateExampleFromSchema(string $schemaName, array $schema): mixed
    {
        if (isset($schema['example'])) {
            return $schema['example'];
        }

        if (isset($schema['$ref'])) {
            $refName = str_replace('#/components/schemas/', '', $schema['$ref']);
            $refSchema = $this->manager->loadSchemaConfig($refName);

            return null !== $refSchema ? $this->generateExampleFromSchema($refName, $refSchema) : [];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && [] !== $schema['enum']) {
            return $schema['enum'][0];
        }

        $type = $schema['type'] ?? 'object';

        return match ($type) {
            'string' => $this->generateStringExample($schema),
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            'array' => $this->generateArrayExample($schema),
            'object' => $this->generateObjectExample($schema),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function generateStringExample(array $schema): string
    {
        return match ($schema['format'] ?? '') {
            'email' => 'user@example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'date' => '2024-01-15',
            'date-time' => '2024-01-15T12:00:00Z',
            'uri', 'url' => 'https://example.com',
            'hostname' => 'example.com',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:db8::1',
            'binary' => 'base64EncodedString',
            'byte' => 'dGVzdA==',
            'password' => '••••••••',
            default => 'string',
        };
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<mixed>
     */
    private function generateArrayExample(array $schema): array
    {
        if (!isset($schema['items'])) {
            return [];
        }

        $items = $schema['items'];

        if (isset($items['$ref'])) {
            $refName = str_replace('#/components/schemas/', '', $items['$ref']);
            $refSchema = $this->manager->loadSchemaConfig($refName);

            return [null !== $refSchema ? $this->generateExampleFromSchema($refName, $refSchema) : []];
        }

        $itemType = $items['type'] ?? 'string';
        $itemExample = match ($itemType) {
            'string' => $this->generateStringExample($items),
            'integer' => 0,
            'number' => 0.0,
            'boolean' => true,
            'object' => $this->generateObjectExample($items),
            default => 'string',
        };

        return [$itemExample];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function generateObjectExample(array $schema): array
    {
        if (!isset($schema['properties']) || !is_array($schema['properties'])) {
            return [];
        }

        $example = [];
        foreach ($schema['properties'] as $propName => $propDef) {
            if (isset($propDef['$ref'])) {
                $refName = str_replace('#/components/schemas/', '', $propDef['$ref']);
                $refSchema = $this->manager->loadSchemaConfig($refName);
                $example[$propName] = null !== $refSchema ? $this->generateExampleFromSchema($refName, $refSchema) : new stdClass();
            } elseif (isset($propDef['enum']) && is_array($propDef['enum']) && [] !== $propDef['enum']) {
                $example[$propName] = $propDef['enum'][0];
            } else {
                $propType = $propDef['type'] ?? 'string';
                $example[$propName] = match ($propType) {
                    'string' => $this->generateStringExample($propDef),
                    'integer' => 0,
                    'number' => 0.0,
                    'boolean' => true,
                    'array' => $this->generateArrayExample($propDef),
                    'object' => $this->generateObjectExample($propDef),
                    default => null,
                };
            }
        }

        return $example;
    }

    private function generateComponent(ExampleTuiState $state): void
    {
        $example = [];
        if ('' !== $state->summary) {
            $example['summary'] = $state->summary;
        }
        if ('' !== $state->description) {
            $example['description'] = $state->description;
        }
        if ('' !== $state->value && '' === $state->externalValue) {
            $decoded = json_decode($state->value, true);
            $example['value'] = null !== $decoded ? $decoded : $state->value;
        }
        if ('' !== $state->externalValue) {
            $example['externalValue'] = $state->externalValue;
        }

        $destination = ComponentType::Examples->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
            // Merge with existing config to preserve manually-added fields
            $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
            if (isset($existingConfig['documentation']['components']['examples'][$state->name])) {
                $mergedExample = array_merge($existingConfig['documentation']['components']['examples'][$state->name], $example);
            } else {
                $mergedExample = $example;
            }
            $array = [
                'documentation' => [
                    'components' => [
                        'examples' => [
                            $state->name => $mergedExample,
                        ],
                    ],
                ],
            ];
        } else {
            $outputDir = u($state->outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpDirectory = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/');

            if (!is_dir($dumpDirectory)) {
                mkdir($dumpDirectory, 0755, true);
            }

            $dumpLocation = $dumpDirectory . $state->name . '.yaml';
            $array = [
                'documentation' => [
                    'components' => [
                        'examples' => [
                            $state->name => $example,
                        ],
                    ],
                ],
            ];
        }

        if ('yaml' === $state->format_output) {
            $this->writeYamlFile($array, $dumpLocation, $this->currentOutput);
        } else {
            $dumpLocation = str_replace('.yaml', '.php', $dumpLocation);
            $phpCode = $this->generatePhpBuilderCode($array, $state->name, $destination);
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Example "%s" generated successfully in %s</info>', $state->name, $dumpLocation));
    }
}
