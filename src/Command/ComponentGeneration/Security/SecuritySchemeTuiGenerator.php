<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security;

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
class SecuritySchemeTuiGenerator extends AbstractTuiComponentGenerator
{
    private ?OutputInterface $currentOutput = null;

    public function __construct(
        private readonly SecuritySchemeTuiManager $manager,
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $apiDocConfigHelper);
    }

    public function getLabel(): string
    {
        return 'Security Scheme';
    }

    public function getDescription(): string
    {
        return 'Générer un composant de sécurité';
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
            $info = $config ? $config['type'] . ' / ' . ($config['scheme'] ?: $config['name']) : '';
            $choices[] = ['value' => $name, 'label' => $this->currentOutput->getFormatter()->format(sprintf('  %-20s <fg=gray>%s</fg=gray>', $name, $info))];
        }
        $choices[] = ['value' => '__new__', 'label' => $this->currentOutput->getFormatter()->format('  <fg=green>[+ Nouveau]</fg=green>')];
        $choices[] = ['value' => '__back__', 'label' => $this->currentOutput->getFormatter()->format('  <comment>[← Retour]</comment>')];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<info>|  Security Schemes                          |</info>')));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($selectWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↑↓ Naviguer  ↵ Sélectionner  Échap Retour</fg=gray>')));
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

            $state = new SecuritySchemeTuiState();
            $state->outputDir = $this->manager->getDefaultDumpLocation();
            if ('__new__' !== $value) {
                $state->name = $value;
                $existing = $this->manager->loadComponentConfig($value);
                if (null !== $existing) {
                    $state->type = $existing['type'];
                    $state->scheme = $existing['scheme'];
                    $state->bearerFormat = $existing['bearerFormat'];
                    $state->apiKeyName = $existing['name'];
                    $state->apiKeyIn = $existing['in'];
                    $state->openIdConnectUrl = $existing['openIdConnectUrl'];
                    $state->description = $existing['description'];
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

    private function showForm(Tui $tui, SecuritySchemeTuiState $state, callable $onBack): void
    {
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
        $settingItems[] = new SettingItem('name', 'Nom', $state->name, 'Nom du schéma (ex: BearerAuth)', [], $textInputCallback);
        $settingItems[] = new SettingItem('type', 'Type', $state->type, 'Type d\'authentification', ['http', 'apiKey', 'openIdConnect']);
        $settingItems[] = new SettingItem('scheme', 'Scheme', $state->scheme, 'Schéma HTTP', ['bearer', 'basic']);
        $settingItems[] = new SettingItem('bearerFormat', 'Bearer Format', $state->bearerFormat, 'Format du token', [], $textInputCallback);
        $settingItems[] = new SettingItem('apiKeyName', 'API Key Name', $state->apiKeyName, 'Nom du header/query param', [], $textInputCallback);
        $settingItems[] = new SettingItem('apiKeyIn', 'API Key In', $state->apiKeyIn, 'Localisation de la clé', ['header', 'query', 'cookie']);
        $settingItems[] = new SettingItem('openIdConnectUrl', 'OpenID Connect URL', $state->openIdConnectUrl, 'URL de découverte', [], $textInputCallback);
        $settingItems[] = new SettingItem('desc', 'Description', $state->description, 'Description du schéma', [], $textInputCallback);

        $settingItems[] = new SettingItem('format_output', 'Format sortie', $state->format_output, 'YAML ou PHP', ['yaml', 'php']);
        $settingItems[] = new SettingItem('output', 'Dossier de sortie', $state->outputDir, 'Répertoire cible', [], $textInputCallback);
        $settingItems[] = new SettingItem('action_validate', 'Valider', '✓ Confirmer', 'Sauvegarder et revenir à la liste.', ['✓ Confirmer']);
        $settingItems[] = new SettingItem('action_delete', 'Supprimer', '✗ Supprimer', 'Supprimer ce composant.', ['✗ Supprimer']);
        $settingItems[] = new SettingItem('action_cancel', 'Annuler', '← Annuler', 'Retourner sans sauvegarder.', ['← Annuler']);

        $settingsWidget = new SettingsListWidget($settingItems, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $title = $state->name ? "Modifier: {$state->name}" : 'Nouveau Security Scheme';
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<info>+---------------------------------------------+</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>|  {$title}</info>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("<info>+---------------------------------------------+</info>\n")));
        $container->add($settingsWidget);
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format("\n<fg=gray>--------------------------------------------------</fg=gray>")));
        $container->add(new TextWidget($this->currentOutput->getFormatter()->format('<fg=gray>  ↵ Valider    ⌫ Supprimer    Échap Annuler</fg=gray>')));
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
                    $state->type = $settingsWidget->getValue('type') ?? 'http';
                    $state->scheme = $settingsWidget->getValue('scheme') ?? 'bearer';
                    $state->bearerFormat = $settingsWidget->getValue('bearerFormat') ?? 'JWT';
                    $state->apiKeyName = $settingsWidget->getValue('apiKeyName') ?? '';
                    $state->apiKeyIn = $settingsWidget->getValue('apiKeyIn') ?? 'header';
                    $state->openIdConnectUrl = $settingsWidget->getValue('openIdConnectUrl') ?? '';
                    $state->description = $settingsWidget->getValue('desc') ?? '';
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

    private function generateComponent(SecuritySchemeTuiState $state): void
    {
        $definition = ['type' => $state->type];

        if ('http' === $state->type) {
            $definition['scheme'] = $state->scheme;
            if ('bearer' === $state->scheme && '' !== $state->bearerFormat) {
                $definition['bearerFormat'] = $state->bearerFormat;
            }
        } elseif ('apiKey' === $state->type) {
            $definition['name'] = $state->apiKeyName;
            $definition['in'] = $state->apiKeyIn;
        } elseif ('openIdConnect' === $state->type) {
            $definition['openIdConnectUrl'] = $state->openIdConnectUrl;
        }

        if ('' !== $state->description) {
            $definition['description'] = $state->description;
        }

        $array = [
            'documentation' => [
                'components' => [
                    'securitySchemes' => [
                        $state->name => $definition,
                    ],
                ],
            ],
        ];

        $destination = ComponentType::SecuritySchemes->value;

        if (null !== $state->loadedFrom && file_exists($state->loadedFrom)) {
            $dumpLocation = dirname($state->loadedFrom) . '/' . $state->name . '.yaml';
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

        $this->currentOutput->writeln(sprintf('<info>Security Scheme "%s" généré avec succès dans %s</info>', $state->name, $dumpLocation));
    }
}
