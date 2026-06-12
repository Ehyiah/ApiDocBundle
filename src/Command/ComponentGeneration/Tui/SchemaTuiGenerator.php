<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\AbstractGenerateComponentCommand;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
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
            if ($this->apiDocConfigHelper->findYamlComponentFile($shortName, AbstractGenerateComponentCommand::COMPONENT_SCHEMAS)) {
                $formats[] = 'YAML';
            }
            if ($this->apiDocConfigHelper->findPhpComponentFile($shortName, AbstractGenerateComponentCommand::COMPONENT_SCHEMAS)) {
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

        $searchWidget->onCancel(static function (CancelEvent $event) use ($onBack) {
            $onBack();
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
            '[Confirmer]',
            'Appuyez sur Entrée pour lancer la génération physique des fichiers.',
            ['[Confirmer]']
        );

        $settingItems[] = new SettingItem(
            'action_cancel',
            'Retour',
            '[Annuler]',
            'Retourner à la liste de sélection des classes.',
            ['[Annuler]']
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

        $array = AbstractGenerateComponentCommand::createComponentArray();
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
            if ($firstTypeInfo instanceof \Symfony\Component\TypeInfo\Type\NullableType) {
                $firstTypeInfo = $firstTypeInfo->getWrappedType();
            }

            $array['documentation']['components']['schemas'][$shortClassName]['type'] = 'object';
            AbstractGenerateComponentCommand::addProperty($propertiesArray, $property, $firstTypeInfo);

            if (TypeIdentifier::OBJECT->value === $firstTypeInfo->getTypeIdentifier()->value || TypeIdentifier::STRING->value === $firstTypeInfo->getTypeIdentifier()->value) {
                $propClass = $firstTypeInfo instanceof \Symfony\Component\TypeInfo\Type\ObjectType ? $firstTypeInfo->getClassName() : null;
                if (null !== $propClass && class_exists($propClass)) {
                    $propReflection = new ReflectionClass($propClass);
                    if ($propReflection->isEnum()) {
                        AbstractGenerateComponentCommand::handleEnum($propertiesArray, $propReflection, $property);
                    }
                }
            }

            if (!$firstTypeInfo->isNullable()) {
                AbstractGenerateComponentCommand::addRequirement($requiredProperties, $property);
            }
        }

        // Compléter la structure
        $array['documentation']['components']['schemas'][$shortClassName]['required'] = $requiredProperties;
        $array['documentation']['components']['schemas'][$shortClassName]['properties'] = $propertiesArray;

        $destination = AbstractGenerateComponentCommand::COMPONENT_SCHEMAS;

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

            if ($this->checkExistingYamlFile($dumpLocation, $input, $output, $array)) {
                $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($shortClassName, $destination);
                if (null !== $phpComponentFile) {
                    if ($this->warnAboutOtherFormat($phpComponentFile->getPathname(), 'yaml', $input, $output)) {
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

            if ($this->checkExistingPhpFile($dumpLocation, $input, $output, $phpCode)) {
                $yamlComponentFile = $this->apiDocConfigHelper->findYamlComponentFile($shortClassName, $destination);
                if (null !== $yamlComponentFile) {
                    if ($this->warnAboutOtherFormat($yamlComponentFile->getPathname(), 'php', $input, $output)) {
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
