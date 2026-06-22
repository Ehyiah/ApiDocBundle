<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter;

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
class ParameterTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly ParameterTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Parameter';
    }

    public function getDescription(): string
    {
        return 'Generate a request parameter component';
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
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Parameters                                |</info>')));
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

            $state = new ParameterTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->name = $existing['name'];
                    $state->in = $existing['in'];
                    $state->description = $existing['description'];
                    $state->required = $existing['required'];
                    $state->deprecated = $existing['deprecated'];
                    $state->allowEmptyValue = $existing['allowEmptyValue'];
                    $state->style = $existing['style'];
                    $state->explode = $existing['explode'];
                    $state->allowReserved = $existing['allowReserved'];
                    $state->schemaType = $existing['schemaType'];
                    $state->format = $existing['format'];
                    $state->example = $existing['example'];
                }
                $state->loadedFrom = $this->manager->findComponentFile($value);
                if (null !== $state->loadedFrom) {
                    $destination = ComponentType::Parameters->value;
                    $state->format_output = $this->detectComponentFormat($state->name, $destination);
                }
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

    private function showForm(Tui $tui, ParameterTuiState $state, callable $onBack): void
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
        $settingItems[] = new SettingItem('name', 'Name', $state->name, 'Parameter name', [], $textInputCallback);
        $settingItems[] = new SettingItem('in', 'In', $state->in, 'Parameter location', ['query', 'path', 'header', 'cookie']);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Parameter description', [], $textInputCallback);
        $settingItems[] = new SettingItem('required', 'Required', $state->required ? 'yes' : 'no', 'Required parameter', ['yes', 'no']);
        $settingItems[] = new SettingItem('deprecated', 'Deprecated', $state->deprecated ? 'yes' : 'no', 'Deprecated parameter', ['yes', 'no']);
        $settingItems[] = new SettingItem('allowEmptyValue', 'Allow Empty', $state->allowEmptyValue ? 'yes' : 'no', 'Allow empty value', ['yes', 'no']);
        $settingItems[] = new SettingItem('style', 'Style', $state->style, 'Parameter style (optional)', ['', 'simple', 'form', 'label', 'matrix', 'spaceDelimited', 'pipeDelimited', 'deepObject']);
        $settingItems[] = new SettingItem('explode', 'Explode', $state->explode, 'Explode (optional)', ['', 'true', 'false']);
        $settingItems[] = new SettingItem('allowReserved', 'Allow Reserved', $state->allowReserved ? 'yes' : 'no', 'Allow reserved characters', ['yes', 'no']);
        $settingItems[] = new SettingItem('schema_type', 'Type', $state->schemaType, 'Schema type', ['string', 'integer', 'number', 'boolean', 'array']);
        $settingItems[] = new SettingItem('format', 'Format', $state->format, 'Format (optional)', [], $textInputCallback);
        $settingItems[] = new SettingItem('example', 'Example', $state->example, 'Example value', [], $textInputCallback);

        $settingItems[] = new SettingItem('format_output', 'Output Format', $state->format_output, 'YAML, PHP or both', ['yaml', 'php', 'both']);
        $settingItems[] = new SettingItem('output', 'Output Directory', $state->outputDir, 'Target directory', [], $textInputCallback);
        $settingItems[] = new SettingItem('action_validate', 'Save', '✓ Confirm', 'Save and return to the list.', ['✓ Confirm']);
        if (null !== $state->loadedFrom) {
            $settingItems[] = new SettingItem('action_delete', 'Delete', '✗ Delete', 'Delete this component.', ['✗ Delete']);
        }
        $settingItems[] = new SettingItem('action_cancel', 'Cancel', '← Cancel', 'Return without saving.', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $title = $state->name ? "Edit: {$state->name}" : 'New Parameter';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$title}</info>")));
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
                    $this->showComponentList($tui, $onBack);
                    break;
                case 'action_validate':
                    $state->name = $settingsWidget->getValue('name') ?? '';
                    $state->in = $settingsWidget->getValue('in') ?? 'query';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $state->required = 'yes' === ($settingsWidget->getValue('required') ?? 'no');
                    $state->deprecated = 'yes' === ($settingsWidget->getValue('deprecated') ?? 'no');
                    $state->allowEmptyValue = 'yes' === ($settingsWidget->getValue('allowEmptyValue') ?? 'no');
                    $state->style = $settingsWidget->getValue('style') ?? '';
                    $state->explode = $settingsWidget->getValue('explode') ?? '';
                    $state->allowReserved = 'yes' === ($settingsWidget->getValue('allowReserved') ?? 'no');
                    $state->schemaType = $settingsWidget->getValue('schema_type') ?? 'string';
                    $state->format = $settingsWidget->getValue('format') ?? '';
                    $state->example = $settingsWidget->getValue('example') ?? '';
                    $state->format_output = $settingsWidget->getValue('format_output') ?? 'yaml';
                    $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $tui->stop();
                    $this->generateComponent($state);
                    break;
                case 'action_delete':
                    $this->deleteComponent($state);
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showComponentList($tui, $onBack);
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

    private function generateComponent(ParameterTuiState $state): void
    {
        $schema = ['type' => $state->schemaType];
        if ('' !== $state->format) {
            $schema['format'] = $state->format;
        }

        $parameter = [
            'name' => $state->name,
            'in' => $state->in,
            'description' => $state->description,
            'required' => $state->required,
            'schema' => $schema,
        ];

        if ($state->deprecated) {
            $parameter['deprecated'] = true;
        }
        if ($state->allowEmptyValue) {
            $parameter['allowEmptyValue'] = true;
        }
        if ('' !== $state->style) {
            $parameter['style'] = $state->style;
        }
        if ('' !== $state->explode) {
            $parameter['explode'] = 'true' === $state->explode;
        }
        if ($state->allowReserved) {
            $parameter['allowReserved'] = true;
        }
        if ('' !== $state->example) {
            $parameter['example'] = $state->example;
        }

        $array = [
            'documentation' => [
                'components' => [
                    'parameters' => [
                        $state->name => $parameter,
                    ],
                ],
            ],
        ];

        $destination = ComponentType::Parameters->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom) && !str_ends_with($state->loadedFrom, '.php')) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
            // Merge with existing config to preserve manually-added fields
            $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
            if (isset($existingConfig['documentation']['components']['parameters'][$state->name])) {
                $mergedParameter = array_merge($existingConfig['documentation']['components']['parameters'][$state->name], $parameter);
                $array['documentation']['components']['parameters'][$state->name] = $mergedParameter;
            }
        } else {
            $outputDir = u($state->outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpDirectory = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/');

            if (!is_dir($dumpDirectory)) {
                mkdir($dumpDirectory, 0755, true);
            }

            $dumpLocation = $dumpDirectory . $state->name . '.yaml';
        }

        if ('yaml' === $state->format_output || 'both' === $state->format_output) {
            $this->writeYamlFile($array, $dumpLocation, $this->currentOutput);
        }

        if ('php' === $state->format_output || 'both' === $state->format_output) {
            $array = $this->mergeWithExistingPhp($array, $state->name, $destination, 'addParameter');
            $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($state->name, $destination);
            if (null !== $phpComponentFile) {
                $dumpLocation = $phpComponentFile->getPathname();
            } else {
                $dumpLocation = dirname($dumpLocation) . '/' . self::componentNameToClassName($state->name) . '.php';
            }
            $phpCode = $this->generatePhpBuilderCode($array, $state->name, $destination, $this->resolveNamespaceFromFile($dumpLocation));
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Parameter "%s" generated successfully in %s</info>', $state->name, $dumpLocation));
    }

    private function deleteComponent(ParameterTuiState $state): void
    {
        if (null === $state->loadedFrom || !file_exists($state->loadedFrom)) {
            return;
        }

        if (str_ends_with($state->loadedFrom, '.php')) {
            unlink($state->loadedFrom);
            $this->currentOutput->writeln(sprintf('<info>Parameter "%s" deleted from %s</info>', $state->name, $state->loadedFrom));

            return;
        }

        $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
        if (!isset($existingConfig['documentation']['components']['parameters'])) {
            return;
        }

        unset($existingConfig['documentation']['components']['parameters'][$state->name]);

        $this->writeYamlFile($existingConfig, $state->loadedFrom, $this->currentOutput);
        $this->currentOutput->writeln(sprintf('<info>Parameter "%s" deleted from %s</info>', $state->name, $state->loadedFrom));
    }
}
