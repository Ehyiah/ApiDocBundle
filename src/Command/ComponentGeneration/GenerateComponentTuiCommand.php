<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui\SchemaTuiGenerator;
use Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui\TuiComponentGeneratorInterface;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

#[AsCommand(
    name: 'apidocbundle:component:tui',
    description: 'Interface interactive TUI pour générer des composants de documentation API'
)]
final class GenerateComponentTuiCommand extends AbstractGenerateComponentCommand
{
    /**
     * @param iterable<TuiComponentGeneratorInterface> $generators
     */
    public function __construct(
        KernelInterface $kernel,
        ParameterBagInterface $parameterBag,
        PropertyInfoExtractorInterface $propertyInfoExtractor,
        FormFactoryInterface $formFactory,
        LoadApiDocConfigHelper $apiDocConfigHelper,
        #[TaggedIterator('ehyiah_api_doc.tui_generator')]
        private readonly iterable $generators,
    ) {
        parent::__construct($kernel, $parameterBag, $propertyInfoExtractor, $formFactory, $apiDocConfigHelper);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tui = new Tui();

        // Passer le HelperSet de la console aux générateurs pour gérer la confirmation d'écrasement
        foreach ($this->generators as $generator) {
            if ($generator instanceof SchemaTuiGenerator) {
                $generator->setHelperSet($this->getHelperSet());
            }
        }

        $this->showMainMenu($tui, $input, $output);

        $tui->run();

        return Command::SUCCESS;
    }

    private function showMainMenu(Tui $tui, InputInterface $input, OutputInterface $output): void
    {
        $choices = [];
        $generatorsMap = [];

        $formatter = $output->getFormatter();

        // 1. Ajouter les générateurs enregistrés via DI
        foreach ($this->generators as $generator) {
            $choices[] = [
                'value' => $generator->getLabel(),
                'label' => $formatter->format(sprintf('%s - %s', $generator->getLabel(), $generator->getDescription())),
            ];
            $generatorsMap[$generator->getLabel()] = $generator;
        }

        // 2. Ajouter des placeholders pour les futurs générateurs
        $futureComponents = [
            'Request Body' => 'Générer un composant de corps de requête (RequestBody)',
            'Parameter' => 'Générer un composant de paramètre de requête (Parameter)',
            'Response' => 'Générer un composant de réponse HTTP (Response)',
            'Header' => 'Générer un composant d\'en-tête HTTP (Header)',
            'Security Scheme' => 'Générer un composant de sécurité (SecurityScheme)',
            'Example' => 'Générer un composant d\'exemple de données (Example)',
        ];

        foreach ($futureComponents as $label => $desc) {
            if (!isset($generatorsMap[$label])) {
                $choices[] = [
                    'value' => $label . '_disabled',
                    'label' => $formatter->format(sprintf('%s <fg=gray>[Bientôt]</fg=gray> - %s', $label, $desc)),
                ];
            }
        }

        // Option Quitter
        $choices[] = [
            'value' => 'quit',
            'label' => $formatter->format('Quitter le TUI'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("<info>=== ApiDocBundle TUI Generator ===</info>\n")));
        $container->add(new TextWidget("Sélectionnez le type de composant à générer :\n"));
        $container->add($selectWidget);
        $container->add(new TextWidget($formatter->format("\n<comment>Navigation : ↑/↓  Sélectionner : Entrée  Quitter : Échap</comment>")));

        $tui->add($container);
        $tui->setFocus($selectWidget);

        $selectListener = function (SelectEvent $event) use ($tui, $selectWidget, $input, $output, $generatorsMap, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $value = $event->getValue();

                if ('quit' === $value) {
                    $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                    $tui->stop();

                    return;
                }

                if (str_ends_with($value, '_disabled')) {
                    $this->showComingSoonMessage($tui, $input, $output);

                    return;
                }

                if (isset($generatorsMap[$value])) {
                    $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                    $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);

                    $generator = $generatorsMap[$value];
                    $generator->run($tui, $input, $output, function () use ($tui, $input, $output) {
                        $this->showMainMenu($tui, $input, $output);
                    });
                }
            }
        };

        $cancelListener = static function (CancelEvent $event) use ($tui, $selectWidget, &$selectListener, &$cancelListener) {
            if ($event->getTarget() === $selectWidget) {
                $tui->getEventDispatcher()->removeListener(SelectEvent::class, $selectListener);
                $tui->getEventDispatcher()->removeListener(CancelEvent::class, $cancelListener);
                $tui->stop();
            }
        };

        $tui->addListener($selectListener);
        $tui->addListener($cancelListener);
    }

    private function showComingSoonMessage(Tui $tui, InputInterface $input, OutputInterface $output): void
    {
        $formatter = $output->getFormatter();
        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(new TextWidget($formatter->format("<info>Ce générateur de composant sera disponible très bientôt !</info>\n")));

        $backWidget = new SelectListWidget([
            ['value' => 'back', 'label' => 'Retour au menu principal'],
        ]);
        $container->add($backWidget);
        $container->add(new TextWidget($formatter->format("\n<comment>Appuyez sur Entrée ou Échap pour revenir.</comment>")));

        $tui->add($container);
        $tui->setFocus($backWidget);

        $onBackSelect = function (SelectEvent $event) use ($tui, $input, $output, &$onBackSelect, &$onBackCancel) {
            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $onBackSelect);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $onBackCancel);
            $this->showMainMenu($tui, $input, $output);
        };

        $onBackCancel = function (CancelEvent $event) use ($tui, $input, $output, &$onBackSelect, &$onBackCancel) {
            $tui->getEventDispatcher()->removeListener(SelectEvent::class, $onBackSelect);
            $tui->getEventDispatcher()->removeListener(CancelEvent::class, $onBackCancel);
            $this->showMainMenu($tui, $input, $output);
        };

        $tui->addListener($onBackSelect);
        $tui->addListener($onBackCancel);
    }
}
