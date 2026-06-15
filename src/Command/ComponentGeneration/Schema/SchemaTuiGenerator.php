<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Schema;

use Ehyiah\ApiDocBundle\Attributes\AsTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractTuiComponentGenerator;
use Ehyiah\ApiDocBundle\Enum\ComponentType;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Ehyiah\ApiDocBundle\Helper\SchemaHelper;
use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\ChangeEvent;
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
use Symfony\Component\TypeInfo\TypeIdentifier;

#[AsTuiGenerator]
class SchemaTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?InputInterface $currentInput = null;
    private ?OutputInterface $currentOutput = null;
    // Helper properties to implement getHelper() needed by GenerateFileTrait
    private ?HelperSet $helperSet = null;

    public function __construct(
        private readonly SchemaTuiManager $manager,
        \Symfony\Component\HttpKernel\KernelInterface $kernel,
        \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag,
        \Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Schema';
    }

    public function getDescription(): string
    {
        return 'Générer un composant de schéma à partir d\'une classe PHP';
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function setHelperSet(HelperSet $helperSet): void
    {
        $this->helperSet = $helperSet;
    }

    public function getHelper(string $name): mixed
    {
        return $this->helperSet?->get($name);
    }

    public function run(Tui $tui, InputInterface $input, OutputInterface $output, callable $onBack): void
    {
        $this->currentInput = $input;
        $this->currentOutput = $output;
        $formatter = $output->getFormatter();

        $choices = [
            ['value' => 'class', 'label' => $formatter->format('  Depuis une classe PHP')],
            ['value' => 'composition', 'label' => $formatter->format('  Composition (allOf / anyOf / oneOf)')],
            ['value' => '__back__', 'label' => $formatter->format('  <comment>[← Retour]</comment>')],
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($formatter->format('<info>|  Génération de Schéma</info>')));
        $container->add(new TextWidget($formatter->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↑↓ Naviguer  ↵ Sélectionner  Échap Retour</fg=gray>')));
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

                if ('composition' === $value) {
                    $this->showCompositionType($tui, $onBack);

                    return;
                }

                $this->showClassSelection($tui, $onBack);
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

    private function showClassSelection(Tui $tui, callable $onBack): void
    {
        $formatter = $this->currentOutput->getFormatter();
        $classes = $this->manager->getAllClasses();
        if (empty($classes)) {
            $tui->clear();
            $container = new ContainerWidget();
            $container->expandVertically(true);
            $container->add(new TextWidget($formatter->format("<error>Erreur : Aucune classe trouvée dans les répertoires configurés.</error>\n")));

            $backWidget = new SelectListWidget([
                ['value' => 'back', 'label' => 'Retour au menu principal'],
            ]);
            $container->add($backWidget);
            $container->add(new TextWidget($formatter->format("\n<comment>Appuyez sur Entrée ou Échap pour revenir.</comment>")));

            $tui->add($container);
            $tui->setFocus($backWidget);

            $onBackSelect = static function (SelectEvent $event) use ($tui, $onBack, &$onBackSelect, &$onBackCancel) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $onBackSelect);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $onBackCancel);
                $onBack();
            };

            $onBackCancel = static function (CancelEvent $event) use ($tui, $onBack, &$onBackSelect, &$onBackCancel) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $onBackSelect);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $onBackCancel);
                $onBack();
            };

            $tui->addListener($onBackSelect);
            $tui->addListener($onBackCancel);

            return;
        }

        $choices = [];
        foreach ($classes as $className) {
            $parts = explode('\\', $className);
            $shortName = end($parts);

            $formats = [];
            if ($this->apiDocConfigHelper->findYamlComponentFile($shortName, ComponentType::Schemas->value)) {
                $formats[] = 'YAML';
            }
            if ($this->apiDocConfigHelper->findPhpComponentFile($shortName, ComponentType::Schemas->value)) {
                $formats[] = 'PHP';
            }

            $label = $className;
            if (!empty($formats)) {
                $label .= sprintf(' (Existe en : %s)', implode(', ', $formats));
            }

            $choices[] = [
                'value' => $className,
                'label' => $formatter->format($label),
            ];
        }

        $classListWidget = new SelectListWidget($choices, 12);
        $searchWidget = new InputWidget();
        $searchWidget->setPrompt($formatter->format('Rechercher une classe : '));

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("<info>Génération de Schéma : Sélection de classe</info>\n")));
        $container->add($searchWidget);
        $container->add(new TextWidget(''));
        $container->add($classListWidget);
        $container->add(new TextWidget($formatter->format("\n<comment>Navigation :\n- Tapez pour filtrer les classes\n- Entrée : Valider la recherche et passer à la liste\n- Échap : Retour au menu principal</comment>")));

        $tui->add($container);
        $tui->setFocus($searchWidget);

        $updateList = static function (string $query) use ($tui, $classListWidget, $choices) {
            $filteredChoices = array_filter($choices, static function ($choice) use ($query) {
                return empty($query) || str_contains(strtolower($choice['value']), strtolower($query));
            });
            $classListWidget->setItems(array_values($filteredChoices));
            $tui->requestRender();
        };

        $searchWidget->onChange(static function (ChangeEvent $event) use ($updateList) {
            $updateList($event->getValue());
        });

        $searchWidget->onSubmit(static function (SubmitEvent $event) use ($tui, $classListWidget) {
            $tui->setFocus($classListWidget);
        });

        $searchWidget->onCancel(function (CancelEvent $event) use ($tui, $onBack) {
            $this->run($tui, $this->currentInput, $this->currentOutput, $onBack);
        });

        $selectListener = function (SelectEvent $event) use ($tui, $classListWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $classListWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                $selectedClass = $event->getValue();
                $this->showConfigurationDashboard($tui, $selectedClass, $onBack);
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $classListWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $classListWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $onBack();
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showCompositionType(Tui $tui, callable $onBack): void
    {
        $formatter = $this->currentOutput->getFormatter();

        $choices = [
            ['value' => 'allOf', 'label' => $formatter->format('  allOf  — Tous les schémas doivent matcher (AND)')],
            ['value' => 'anyOf', 'label' => $formatter->format('  anyOf  — Au moins un schéma doit matcher (OR)')],
            ['value' => 'oneOf', 'label' => $formatter->format('  oneOf  — Un seul schéma doit matcher (XOR)')],
            ['value' => '__back__', 'label' => $formatter->format('  <comment>[← Retour]</comment>')],
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($formatter->format('<info>|  Type de composition</info>')));
        $container->add(new TextWidget($formatter->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↑↓ Naviguer  ↵ Sélectionner  Échap Retour</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                $value = $event->getValue();
                if ('__back__' === $value) {
                    $this->run($tui, $this->currentInput, $this->currentOutput, $onBack);

                    return;
                }

                $this->showCompositionConfig($tui, $value, $onBack);
            }
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $selectWidget, $onBack, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->run($tui, $this->currentInput, $this->currentOutput, $onBack);
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showCompositionConfig(Tui $tui, string $compositionType, callable $onBack): void
    {
        $formatter = $this->currentOutput->getFormatter();
        $schemas = $this->manager->getAvailableSchemas();

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
        $settingItems[] = new SettingItem('schemaName', 'Nom du schéma', '', 'Nom du nouveau schéma de composition', [], $textInputCallback);

        $schemaChoices = array_merge([''], $schemas);
        $settingItems[] = new SettingItem('schema_1', 'Schéma 1', '', 'Premier schéma à composer', $schemaChoices);
        $settingItems[] = new SettingItem('schema_2', 'Schéma 2', '', 'Deuxième schéma à composer', $schemaChoices);
        $settingItems[] = new SettingItem('schema_3', 'Schéma 3', '', 'Troisième schéma (optionnel)', $schemaChoices);

        $defaultDumpLocation = $this->manager->getDefaultDumpLocation();
        $settingItems[] = new SettingItem('format', 'Format', 'both', 'Format de sortie', ['both', 'php', 'yaml']);
        $settingItems[] = new SettingItem('output', 'Dossier de sortie', $defaultDumpLocation, 'Répertoire cible', [], $textInputCallback);

        $settingItems[] = new SettingItem('action_generate', 'Générer', '✓ Confirmer', 'Lancer la génération', ['✓ Confirmer']);
        $settingItems[] = new SettingItem('action_cancel', 'Retour', '← Annuler', 'Retourner', ['← Annuler']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($formatter->format("<info>|  Composition : {$compositionType}</info>")));
        $container->add(new TextWidget($formatter->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($formatter->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($formatter->format('<fg=gray>  ↵ Valider    Échap Annuler</fg=gray>')));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $compositionType, $schemas, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() !== $settingsWidget) {
                return;
            }

            switch ($event->getId()) {
                case 'action_cancel':
                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $this->showCompositionType($tui, $onBack);
                    break;
                case 'action_generate':
                    $schemaName = $settingsWidget->getValue('schemaName') ?? '';
                    if ('' === $schemaName) {
                        break;
                    }

                    $refs = [];
                    foreach (['schema_1', 'schema_2', 'schema_3'] as $key) {
                        $selected = $settingsWidget->getValue($key) ?? '';
                        if ('' !== $selected && in_array($selected, $schemas, true)) {
                            $refs[] = ['$ref' => '#/components/schemas/' . $selected];
                        }
                    }

                    if (count($refs) < 2) {
                        break;
                    }

                    $format = $settingsWidget->getValue('format') ?? 'both';
                    $outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();

                    $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $tui->stop();

                    $this->generateComposition($schemaName, $compositionType, $refs, $format, $outputDir);
                    break;
            }
        };

        $cancelListener = function (CancelEvent $event) use ($tui, $settingsWidget, $onBack, &$changeListener, &$cancelListener) {
            if ($event->getTarget() === $settingsWidget) {
                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $this->showCompositionType($tui, $onBack);
            }
        };

        $tui->addListener($changeListener);
        $tui->addListener($cancelListener);
    }

    /** @param array<array<string, string>> $refs */
    private function generateComposition(string $schemaName, string $compositionType, array $refs, string $format, string $outputDir): void
    {
        $output = $this->currentOutput;
        $input = $this->currentInput;

        $array = SchemaHelper::createComponentArray();
        $array['documentation']['components']['schemas'][$schemaName][$compositionType] = $refs;

        $destination = ComponentType::Schemas->value;

        if ('yaml' === $format || 'both' === $format) {
            $outputDirClean = (string)\Symfony\Component\String\u($outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpPath = $this->parameterBag->get('ehyiah_api_doc.dump_path');
            $sourcePath = $this->parameterBag->get('ehyiah_api_doc.source_path');
            $dumpLocation = null;

            if (is_string($sourcePath) && is_string($dumpPath)) {
                $sourcePath = (string)\Symfony\Component\String\u($sourcePath)->ensureStart('/')->ensureEnd('/');
                $existingConfigs = LoadApiDocConfigHelper::loadYamlConfigDoc(
                    $sourcePath,
                    $this->kernel->getProjectDir(),
                    $dumpPath,
                );

                if (isset($existingConfigs['components'][$destination][$schemaName])) {
                    $componentAlreadyExistFile = $this->apiDocConfigHelper->findYamlComponentFile($schemaName, $destination);
                    if ($componentAlreadyExistFile) {
                        $dumpLocation = $componentAlreadyExistFile->getPathname();
                    }
                }
            }

            if (null === $dumpLocation) {
                $dumpLocation = $this->kernel->getProjectDir() . $outputDirClean . \Symfony\Component\String\u($destination)->ensureEnd('/') . $schemaName . '.yaml';
            }

            if ($this->checkExistingYamlFile($dumpLocation, $input, $output, $array, interactive: false)) {
                $this->writeYamlFile($array, $dumpLocation, $output);
            }
        }

        if ('php' === $format || 'both' === $format) {
            $outputDirClean = (string)\Symfony\Component\String\u($outputDir)->ensureStart('/')->ensureEnd('/');
            $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($schemaName, $destination);

            if (null !== $phpComponentFile) {
                $dumpLocation = $phpComponentFile->getPathname();
            } else {
                $dumpDirectory = $this->kernel->getProjectDir() . $outputDirClean . \Symfony\Component\String\u($destination)->ensureEnd('/');
                $dumpLocation = $dumpDirectory . $schemaName . '.php';
            }

            $phpCode = $this->generatePhpBuilderCode($array, $schemaName, $destination);

            if ($this->checkExistingPhpFile($dumpLocation, $input, $output, $phpCode, interactive: false)) {
                $this->writePhpFile($phpCode, $dumpLocation, $output);
            }
        }

        $output->writeln("\n<info>Composition \"{$schemaName}\" générée avec succès !</info>");
    }

    private function showConfigurationDashboard(Tui $tui, string $selectedClass, callable $onBack): void
    {
        if (!method_exists($this->manager, 'getClassProperties')) {
            throw new Exception('Manager does not have getClassProperties');
        }
        $properties = $this->manager->getClassProperties($selectedClass);

        $settingItems = [];

        // 1. Liste des propriétés de la classe (Boutons inclure/exclure)
        foreach ($properties as $property) {
            $settingItems[] = new SettingItem(
                'prop_' . $property,
                'Propriété : ' . $property,
                'inclure',
                'Inclure ou exclure la propriété "' . $property . '" du schéma généré.',
                ['inclure', 'exclure']
            );
        }

        // 2. Options globales pour les propriétés
        $settingItems[] = new SettingItem(
            'action_select_all',
            'Propriétés : TOUT inclure',
            '[Action]',
            'Appuyez sur Entrée pour inclure toutes les propriétés ci-dessus.',
            ['[Action]']
        );

        $settingItems[] = new SettingItem(
            'action_deselect_all',
            'Propriétés : TOUT exclure',
            '[Action]',
            'Appuyez sur Entrée pour exclure toutes les propriétés ci-dessus.',
            ['[Action]']
        );

        // 3. Options de format
        $settingItems[] = new SettingItem(
            'format',
            'Format de sortie',
            'both',
            'Format sous lequel générer le composant.',
            ['both', 'php', 'yaml']
        );

        // 3. Dossier de sortie (modifiable)
        $defaultDumpLocation = $this->manager->getDefaultDumpLocation();
        $settingItems[] = new SettingItem(
            'output',
            'Dossier de sortie',
            $defaultDumpLocation,
            'Répertoire cible pour l\'écriture du fichier. Appuyez sur Entrée pour modifier.',
            [],
            static function (string $currentValue, callable $onDone) {
                $inputWidget = new InputWidget();
                $inputWidget->setValue($currentValue);
                $inputWidget->setPrompt('Chemin : ');
                $inputWidget->onSubmit(static function (SubmitEvent $event) use ($onDone) {
                    $onDone($event->getValue());
                });
                $inputWidget->onCancel(static function (CancelEvent $event) use ($onDone) {
                    $onDone(null);
                });

                return $inputWidget;
            }
        );

        // 4. Actions
        $settingItems[] = new SettingItem(
            'action_generate',
            'Générer le Schéma',
            '✓ Confirmer',
            'Appuyez sur Entrée pour lancer la génération physique des fichiers.',
            ['✓ Confirmer']
        );

        $settingItems[] = new SettingItem(
            'action_cancel',
            'Retour',
            '← Annuler',
            'Retourner à la liste de sélection des classes.',
            ['← Annuler']
        );

        $formatter = $this->currentOutput->getFormatter();
        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format('<info>Configuration du Schéma pour : ' . $selectedClass . "</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($formatter->format("\n<comment>Navigation : ↑/↓  Modifier : Espace/Gauche/Droite  Valider : Entrée  Retour : Échap</comment>")));
        $tui->add($container);
        $tui->setFocus($settingsWidget);

        $changeListener = function (SettingChangeEvent $event) use ($tui, $settingsWidget, $selectedClass, $properties, $onBack, &$changeListener, &$cancelListener) {
            $this->handleSettingChange($event, $tui, $settingsWidget, $selectedClass, $properties, $onBack, $changeListener, $cancelListener);
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
        string $selectedClass,
        array $properties,
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
                $this->run($tui, $this->currentInput, $this->currentOutput, $onBack);
                break;
            case 'action_select_all':
                foreach ($properties as $property) {
                    $settingsWidget->updateValue('prop_' . $property, 'inclure');
                }
                $tui->requestRender();
                break;
            case 'action_deselect_all':
                foreach ($properties as $property) {
                    $settingsWidget->updateValue('prop_' . $property, 'exclure');
                }
                $tui->requestRender();
                break;
            case 'action_generate':
                $format = $settingsWidget->getValue('format') ?? 'both';
                $outputDir = $settingsWidget->getValue('output') ?? $this->manager->getDefaultDumpLocation();
                $propertiesToSkip = [];
                foreach ($properties as $property) {
                    if ('exclure' === $settingsWidget->getValue('prop_' . $property)) {
                        $propertiesToSkip[] = $property;
                    }
                }

                $tui->getEventDispatcher()->removeListener(SettingChangeEvent::class, $changeListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                $tui->stop();

                $this->generateFiles($selectedClass, $format, $outputDir, $propertiesToSkip);
                break;
        }
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
            $this->run($tui, $this->currentInput, $this->currentOutput, $onBack);
        }
    }

    private function generateFiles(string $className, string $format, string $outputDir, array $propertiesToSkip): void
    {
        $output = $this->currentOutput;
        $input = $this->currentInput;

        $reflectionClass = new ReflectionClass($className);
        $shortClassName = $reflectionClass->getShortName();

        $array = SchemaHelper::createComponentArray();
        $properties = $this->manager->getClassProperties($className);

        $propertiesArray = [];
        $requiredProperties = [];

        foreach ($properties as $property) {
            if (in_array($property, $propertiesToSkip, true)) {
                continue;
            }

            $type = $this->propertyInfoExtractor->getType($className, $property);
            $types = null !== $type ? [$type] : [];

            if (empty($types)) {
                try {
                    $reflectionProperty = new ReflectionProperty($className, $property);
                    $reflectionType = $reflectionProperty->getType();

                    if ($reflectionType instanceof ReflectionNamedType) {
                        $typeName = $reflectionType->getName();
                        $nullable = $reflectionType->allowsNull();

                        if (class_exists($typeName) || interface_exists($typeName)) {
                            $types = [\Symfony\Component\TypeInfo\Type::object($typeName)];
                        } else {
                            $types = [\Symfony\Component\TypeInfo\Type::builtin(TypeIdentifier::from($typeName))];
                        }
                    }
                } catch (ReflectionException) {
                    // Ignorer
                }
            }

            if (empty($types)) {
                $types = [\Symfony\Component\TypeInfo\Type::builtin(TypeIdentifier::STRING)];
            }

            /** @var \Symfony\Component\TypeInfo\Type $firstTypeInfo */
            $firstTypeInfo = $types[0];
            $isNullable = $firstTypeInfo->isNullable();
            if ($firstTypeInfo instanceof \Symfony\Component\TypeInfo\Type\NullableType) {
                $firstTypeInfo = $firstTypeInfo->getWrappedType();
            }

            $array['documentation']['components']['schemas'][$shortClassName]['type'] = 'object';
            SchemaHelper::addProperty($propertiesArray, $property, $firstTypeInfo);

            if ($firstTypeInfo->isIdentifiedBy(TypeIdentifier::OBJECT) || $firstTypeInfo->isIdentifiedBy(TypeIdentifier::STRING)) {
                $propClass = $firstTypeInfo instanceof \Symfony\Component\TypeInfo\Type\ObjectType ? $firstTypeInfo->getClassName() : null;
                if (null !== $propClass && class_exists($propClass)) {
                    $propReflection = new ReflectionClass($propClass);
                    if ($propReflection->isEnum()) {
                        SchemaHelper::handleEnum($propertiesArray, $propReflection, $property);
                    }
                }
            }

            if ($isNullable) {
                $propertiesArray[$property]['nullable'] = true;
            }

            if (!$isNullable) {
                SchemaHelper::addRequirement($requiredProperties, $property);
            }
        }

        // Compléter la structure
        $array['documentation']['components']['schemas'][$shortClassName]['required'] = $requiredProperties;
        $array['documentation']['components']['schemas'][$shortClassName]['properties'] = $propertiesArray;

        $destination = ComponentType::Schemas->value;

        if ('yaml' === $format || 'both' === $format) {
            $outputDirClean = (string)\Symfony\Component\String\u($outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpPath = $this->parameterBag->get('ehyiah_api_doc.dump_path');

            $sourcePath = $this->parameterBag->get('ehyiah_api_doc.source_path');
            $dumpLocation = null;

            if (is_string($sourcePath) && is_string($dumpPath)) {
                $sourcePath = (string)\Symfony\Component\String\u($sourcePath)->ensureStart('/')->ensureEnd('/');
                $existingConfigs = LoadApiDocConfigHelper::loadYamlConfigDoc(
                    $sourcePath,
                    $this->kernel->getProjectDir(),
                    $dumpPath,
                );

                if (isset($existingConfigs['components'][$destination][$shortClassName])) {
                    $componentAlreadyExistFile = $this->apiDocConfigHelper->findYamlComponentFile($shortClassName, $destination);
                    if ($componentAlreadyExistFile) {
                        $dumpLocation = $componentAlreadyExistFile->getPathname();
                    }
                }
            }

            if (null === $dumpLocation) {
                $dumpLocation = $this->kernel->getProjectDir() . $outputDirClean . \Symfony\Component\String\u($destination)->ensureEnd('/') . $shortClassName . '.yaml';
            }

            if ($this->checkExistingYamlFile($dumpLocation, $input, $output, $array, interactive: false)) {
                $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($shortClassName, $destination);
                if (null !== $phpComponentFile) {
                    if ($this->warnAboutOtherFormat($phpComponentFile->getPathname(), 'yaml', $input, $output, interactive: false)) {
                        $this->writeYamlFile($array, $dumpLocation, $output);
                    }
                } else {
                    $this->writeYamlFile($array, $dumpLocation, $output);
                }
            }
        }

        if ('php' === $format || 'both' === $format) {
            $outputDirClean = (string)\Symfony\Component\String\u($outputDir)->ensureStart('/')->ensureEnd('/');
            $dumpLocation = null;
            $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($shortClassName, $destination);

            if (null !== $phpComponentFile) {
                $dumpLocation = $phpComponentFile->getPathname();
            } else {
                $dumpDirectory = $this->kernel->getProjectDir() . $outputDirClean . \Symfony\Component\String\u($destination)->ensureEnd('/');
                $dumpLocation = $dumpDirectory . $shortClassName . '.php';
            }

            $phpCode = $this->generatePhpBuilderCode($array, $shortClassName, $destination);

            if ($this->checkExistingPhpFile($dumpLocation, $input, $output, $phpCode, interactive: false)) {
                $yamlComponentFile = $this->apiDocConfigHelper->findYamlComponentFile($shortClassName, $destination);
                if (null !== $yamlComponentFile) {
                    if ($this->warnAboutOtherFormat($yamlComponentFile->getPathname(), 'php', $input, $output, interactive: false)) {
                        $this->writePhpFile($phpCode, $dumpLocation, $output);
                    }
                } else {
                    $this->writePhpFile($phpCode, $dumpLocation, $output);
                }
            }
        }

        $output->writeln("\n<info>Génération terminée avec succès !</info>");
    }
}
