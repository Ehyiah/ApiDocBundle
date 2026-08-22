<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractTuiComponentGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\TuiUi;
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
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SettingItem;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TextWidget;
use Throwable;

use function Symfony\Component\String\u;

#[AsTuiGenerator]
class PathItemTuiGenerator extends AbstractTuiComponentGenerator
{
    public function __construct(
        private readonly PathItemTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Path Item';
    }

    public function getDescription(): string
    {
        return 'Generate a path item component';
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
            $config = $this->manager->loadComponentConfig($name);
            $info = $config ? $config['summary'] ?: $config['description'] : '';
            $choices[] = ['value' => $name, 'label' => $this->currentOutput->getFormatter()->format(sprintf('  %-20s <fg=gray>%s</fg=gray>', $name, $info))];
        }
        $choices[] = ['value' => '__new__', 'label' => $this->currentOutput->getFormatter()->format('  <fg=green>[+ New]</fg=green>')];
        $choices[] = ['value' => '__back__', 'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Back]</comment>')];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(TuiUi::header('Path Items'));
        $searchWidget = $this->attachSearchFilter($tui, $container, $selectWidget, $choices);

        $container->add($selectWidget);
        $container->add(new TextWidget(''));
        $container->add(TuiUi::hints('↑↓ Navigate · ↵ Select · / Search · Esc Back'));
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

            $state = new PathItemTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->summary = $existing['summary'];
                    $state->description = $existing['description'];
                    $state->ref = $existing['ref'];
                }
                $state->loadedFrom = $this->manager->findComponentFile($value);
                if (null !== $state->loadedFrom) {
                    $destination = ComponentType::PathItems->value;
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

    private function showForm(Tui $tui, PathItemTuiState $state, callable $onBack): void
    {
        $textInputCallback = TuiUi::textInput();

        $settingItems = [];
        $settingItems[] = new SettingItem('name', 'Name', $state->name, 'Path Item name (e.g. UserOperations)', [], $textInputCallback);
        $settingItems[] = new SettingItem('summary', 'Summary', $state->summary, 'Short summary', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Detailed description', [], $textInputCallback);
        $settingItems[] = new SettingItem('ref', '$ref', $state->ref, 'Reference to external path item', [], $textInputCallback);

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
        $title = $state->name ? "Edit: {$state->name}" : 'New Path Item';
        $container->add(TuiUi::header($title));
        $container->add($settingsWidget);
        $container->add(new TextWidget(''));
        $container->add(TuiUi::hints('↵ Save · ⌫ Delete · Esc Cancel'));
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
                case 'action_delete':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                    $this->requestConfirm($tui, sprintf('Delete "%s" permanently?', $state->name), function () use ($tui, $state, $onBack): void {
                        $this->deleteComponent($state);
                        $this->showComponentList($tui, $onBack);
                    }, function () use ($tui, $onBack): void { $this->showComponentList($tui, $onBack); });
                    break;
                case 'action_validate':
                    $state->name = $settingsWidget->getValue('name') ?? '';
                    $state->summary = $settingsWidget->getValue('summary') ?? '';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $state->ref = $settingsWidget->getValue('ref') ?? '';
                    $state->format_output = $settingsWidget->getValue('format_output') ?? 'yaml';
                    $state->outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                    try {
                        $this->generateComponent($state);
                        $this->showResult($tui, sprintf('"%s" was generated successfully.', $state->name), true, function () use ($tui, $onBack): void { $this->showComponentList($tui, $onBack); });
                    } catch (Throwable $error) {
                        $this->showResult($tui, $error->getMessage(), false, function () use ($tui, $onBack): void { $this->showComponentList($tui, $onBack); });
                    }
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

    private function generateComponent(PathItemTuiState $state): void
    {
        $pathItem = [];
        if ('' !== $state->ref) {
            $pathItem['$ref'] = $state->ref;
        }
        if ('' !== $state->summary) {
            $pathItem['summary'] = $state->summary;
        }
        if ('' !== $state->description) {
            $pathItem['description'] = $state->description;
        }

        $array = [
            'documentation' => [
                'components' => [
                    'pathItems' => [
                        $state->name => $pathItem,
                    ],
                ],
            ],
        ];

        $destination = ComponentType::PathItems->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom) && !str_ends_with($state->loadedFrom, '.php')) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
            // Merge with existing config to preserve manually-added fields
            $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
            if (isset($existingConfig['documentation']['components']['pathItems'][$state->name])) {
                $mergedPathItem = array_merge($existingConfig['documentation']['components']['pathItems'][$state->name], $pathItem);
                $array['documentation']['components']['pathItems'][$state->name] = $mergedPathItem;
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
            $array = $this->mergeWithExistingPhp($array, $state->name, $destination, 'addPathItem');
            $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($state->name, $destination);
            if (null !== $phpComponentFile) {
                $dumpLocation = $phpComponentFile->getPathname();
            } else {
                $dumpLocation = dirname($dumpLocation) . '/' . self::componentNameToClassName($state->name) . '.php';
            }
            $phpCode = $this->generatePhpBuilderCode($array, $state->name, $destination, $this->resolveNamespaceFromFile($dumpLocation));
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Path Item "%s" generated successfully in %s</info>', $state->name, $dumpLocation));
    }

    private function deleteComponent(PathItemTuiState $state): void
    {
        if (null === $state->loadedFrom || !file_exists($state->loadedFrom)) {
            return;
        }

        if (str_ends_with($state->loadedFrom, '.php')) {
            unlink($state->loadedFrom);
            $this->currentOutput->writeln(sprintf('<info>Path Item "%s" deleted from %s</info>', $state->name, $state->loadedFrom));

            return;
        }

        $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
        if (!isset($existingConfig['documentation']['components']['pathItems'])) {
            return;
        }

        unset($existingConfig['documentation']['components']['pathItems'][$state->name]);

        $this->writeYamlFile($existingConfig, $state->loadedFrom, $this->currentOutput);
        $this->currentOutput->writeln(sprintf('<info>Path Item "%s" deleted from %s</info>', $state->name, $state->loadedFrom));
    }
}
