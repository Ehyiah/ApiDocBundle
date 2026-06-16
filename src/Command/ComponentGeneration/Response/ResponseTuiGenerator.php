<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Response;

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
class ResponseTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly ResponseTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Response';
    }

    public function getDescription(): string
    {
        return 'Generate an HTTP response component';
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
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Responses                                |</info>')));
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

            $state = new ResponseTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->name = $existing['name'];
                    $state->description = $existing['description'];
                    $state->schemaRef = $existing['schemaRef'];
                    $state->contentType = $existing['contentType'];
                    $state->headers = $existing['headers'];
                    $state->links = $existing['links'];
                }
                $state->loadedFrom = $this->manager->findComponentFile($value);
                if (null !== $state->loadedFrom) {
                    $state->format_output = str_ends_with($state->loadedFrom, '.php') ? 'php' : 'yaml';
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

    private function showForm(Tui $tui, ResponseTuiState $state, callable $onBack): void
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

        $schemas = $this->manager->getAvailableSchemas();
        $schemaChoices = array_merge(['none'], $schemas);

        $settingItems = [];
        $settingItems[] = new SettingItem('name', 'Name', $state->name, 'Response name (e.g. NotFound)', [], $textInputCallback);
        $settingItems[] = new SettingItem('statusCode', 'HTTP Code', $state->statusCode, 'Status code', ['200', '201', '204', '400', '401', '403', '404', '500']);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Response description', [], $textInputCallback);
        $settingItems[] = new SettingItem('schema_ref', 'Schema $ref', $state->schemaRef ?? 'none', 'Schema reference', $schemaChoices);
        $settingItems[] = new SettingItem('content_type', 'Content-Type', $state->contentType, 'Content type', ['application/json', 'application/xml', 'text/plain']);
        $settingItems[] = new SettingItem('headers', 'Headers (JSON)', $state->headers, 'Response headers as JSON object', [], $textInputCallback);
        $settingItems[] = new SettingItem('links', 'Links (JSON)', $state->links, 'Response links as JSON object', [], $textInputCallback);

        $settingItems[] = new SettingItem('format_output', 'Output Format', $state->format_output, 'YAML or PHP', ['yaml', 'php']);
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
        $title = $state->name ? "Edit: {$state->name}" : 'New Response';
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
                    $state->statusCode = $settingsWidget->getValue('statusCode') ?? '200';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
                    $schemaRef = $settingsWidget->getValue('schema_ref') ?? 'none';
                    $state->schemaRef = 'none' !== $schemaRef ? $schemaRef : null;
                    $state->contentType = $settingsWidget->getValue('content_type') ?? 'application/json';
                    $state->headers = $settingsWidget->getValue('headers') ?? '';
                    $state->links = $settingsWidget->getValue('links') ?? '';
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

    private function generateComponent(ResponseTuiState $state): void
    {
        $schema = [];
        if (null !== $state->schemaRef) {
            $schema['$ref'] = '#/components/schemas/' . $state->schemaRef;
        }

        $content = [];
        if (!empty($schema)) {
            $content[$state->contentType] = ['schema' => $schema];
        }

        $response = [
            'description' => $state->description,
        ];
        if (!empty($content)) {
            $response['content'] = $content;
        }

        if ('' !== $state->headers) {
            $decoded = json_decode($state->headers, true);
            if (null !== $decoded && is_array($decoded)) {
                $response['headers'] = $decoded;
            }
        }

        if ('' !== $state->links) {
            $decoded = json_decode($state->links, true);
            if (null !== $decoded && is_array($decoded)) {
                $response['links'] = $decoded;
            }
        }

        $array = [
            'documentation' => [
                'components' => [
                    'responses' => [
                        $state->name => $response,
                    ],
                ],
            ],
        ];

        $destination = ComponentType::Responses->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
            // Merge with existing config to preserve manually-added fields
            $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
            if (isset($existingConfig['documentation']['components']['responses'][$state->name])) {
                $mergedResponse = array_merge($existingConfig['documentation']['components']['responses'][$state->name], $response);
                $array['documentation']['components']['responses'][$state->name] = $mergedResponse;
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

        $this->currentOutput->writeln(sprintf('<info>Response "%s" generated successfully in %s</info>', $state->name, $dumpLocation));
    }

    private function deleteComponent(ResponseTuiState $state): void
    {
        if (null === $state->loadedFrom || !file_exists($state->loadedFrom)) {
            return;
        }

        $existingConfig = \Symfony\Component\Yaml\Yaml::parseFile($state->loadedFrom);
        if (!isset($existingConfig['documentation']['components']['responses'])) {
            return;
        }

        unset($existingConfig['documentation']['components']['responses'][$state->name]);

        $this->writeYamlFile($existingConfig, $state->loadedFrom, $this->currentOutput);
        $this->currentOutput->writeln(sprintf('<info>Response "%s" deleted from %s</info>', $state->name, $state->loadedFrom));
    }
}
