<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback;

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
class CallbackTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly CallbackTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Callback';
    }

    public function getDescription(): string
    {
        return 'Generate a callback component';
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
            $info = $config ? $config['expression'] : '';
            $choices[] = ['value' => $name, 'label' => $this->currentOutput->getFormatter()->format(sprintf('  %-20s <fg=gray>%s</fg=gray>', $name, $info))];
        }
        $choices[] = ['value' => '__new__', 'label' => $this->currentOutput->getFormatter()->format('  <fg=green>[+ New]</fg=green>')];
        $choices[] = ['value' => '__back__', 'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Back]</comment>')];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Callbacks                                |</info>')));
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

            $state = new CallbackTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->expression = $existing['expression'];
                    $state->path = $existing['path'];
                    $state->method = $existing['method'];
                    $state->description = $existing['description'];
                    $state->operationId = $existing['operationId'];
                    $state->requestBodyRef = $existing['requestBodyRef'];
                    $state->responseDescription = $existing['responseDescription'];
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

    private function showForm(Tui $tui, CallbackTuiState $state, callable $onBack): void
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
        $settingItems[] = new SettingItem('name', 'Name', $state->name, 'Callback name (e.g. OnOrderCreated)', [], $textInputCallback);
        $settingItems[] = new SettingItem('expression', 'Expression', $state->expression, 'Path expression (e.g. {$request.body#/callbackUrl})', [], $textInputCallback);
        $settingItems[] = new SettingItem('path', 'Path', $state->path, 'Callback path (e.g. /callback)', [], $textInputCallback);
        $settingItems[] = new SettingItem('method', 'Method', $state->method, 'HTTP method', ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace']);
        $settingItems[] = new SettingItem('operationId', 'Operation ID', $state->operationId, 'Operation ID', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Operation description', [], $textInputCallback);
        $settingItems[] = new SettingItem('requestBodyRef', 'Request Body Ref', $state->requestBodyRef, 'Schema $ref (e.g. #/components/schemas/Order)', [], $textInputCallback);
        $settingItems[] = new SettingItem('responseDesc', 'Response Description', $state->responseDescription, 'Response description', [], $textInputCallback);

        $settingItems[] = new SettingItem('format_output', 'Output Format', $state->format_output, 'YAML or PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('output', 'Output Directory', $state->outputDir, 'Target directory', [], $textInputCallback);
        $settingItems[] = new SettingItem('action_validate', 'Save', '✓ Confirm', 'Save and return to the list.', ['✓ Confirm']);
        $settingItems[] = new SettingItem('action_delete', 'Delete', '✗ Delete', 'Delete this component.', ['✗ Delete']);
        $settingItems[] = new SettingItem('action_cancel', 'Cancel', '← Cancel', 'Return without saving.', ['← Cancel']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $title = $state->name ? "Edit: {$state->name}" : 'New Callback';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$title}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Save    ⌫ Delete    Esc Cancel</fg=gray>')));
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
                    $this->showComponentList($tui, $onBack);
                    break;
                case 'action_validate':
                    $state->name = $settingsWidget->getValue('name') ?? '';
                    $state->expression = $settingsWidget->getValue('expression') ?? '';
                    $state->path = $settingsWidget->getValue('path') ?? '';
                    $state->method = $settingsWidget->getValue('method') ?? 'post';
                    $state->operationId = $settingsWidget->getValue('operationId') ?? '';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $state->requestBodyRef = $settingsWidget->getValue('requestBodyRef') ?? '';
                    $state->responseDescription = $settingsWidget->getValue('responseDesc') ?? '';
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

    private function generateComponent(CallbackTuiState $state): void
    {
        $operation = [];
        if ('' !== $state->operationId) {
            $operation['operationId'] = $state->operationId;
        }
        if ('' !== $state->description) {
            $operation['description'] = $state->description;
        }
        if ('' !== $state->requestBodyRef) {
            $operation['requestBody'] = [
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => $state->requestBodyRef,
                        ],
                    ],
                ],
            ];
        }
        if ('' !== $state->responseDescription) {
            $operation['responses'] = [
                '200' => [
                    'description' => $state->responseDescription,
                ],
            ];
        }

        $pathItem = [
            $state->method => $operation,
        ];

        $expression = '' !== $state->expression ? $state->expression : '{$request.body#/callbackUrl}';
        $callback = [
            $expression => $pathItem,
        ];

        $array = [
            'documentation' => [
                'components' => [
                    'callbacks' => [
                        $state->name => $callback,
                    ],
                ],
            ],
        ];

        $destination = ComponentType::Callbacks->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
            // Merge with existing config to preserve manually-added fields
            $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
            if (isset($existingConfig['documentation']['components']['callbacks'][$state->name])) {
                $mergedCallback = array_replace_recursive($existingConfig['documentation']['components']['callbacks'][$state->name], $callback);
                $array['documentation']['components']['callbacks'][$state->name] = $mergedCallback;
            }
        } else {
            $outputDir = u($state->outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpDirectory = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/');

            if (!is_dir($dumpDirectory)) {
                mkdir($dumpDirectory, 0755, true);
            }

            $dumpLocation = $dumpDirectory . $state->name . '.yaml';
        }

        if ('yaml' === $state->format_output) {
            $this->writeYamlFile($array, $dumpLocation, $this->currentOutput);
        } else {
            $dumpLocation = str_replace('.yaml', '.php', $dumpLocation);
            $phpCode = $this->generatePhpBuilderCode($array, $state->name, $destination);
            $this->writePhpFile($phpCode, $dumpLocation, $this->currentOutput);
        }

        $this->currentOutput->writeln(sprintf('<info>Callback "%s" generated successfully in %s</info>', $state->name, $dumpLocation));
    }
}
