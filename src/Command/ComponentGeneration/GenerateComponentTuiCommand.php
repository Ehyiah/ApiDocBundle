<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Tui\Event\CancelEvent;
use Symfony\Component\Tui\Event\SelectEvent;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

use function Symfony\Component\String\u;

#[AsCommand(
    name: 'apidocbundle:component:tui',
    description: 'Interactive TUI for generating API documentation components'
)]
final class GenerateComponentTuiCommand extends Command
{
    use GenerateFileTrait;

    protected ?string $dumpLocation = null;

    /**
     * @param iterable<TuiComponentGeneratorInterface> $generators
     */
    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly ParameterBagInterface $parameterBag,
        #[TaggedIterator('ehyiah_api_doc.tui_generator')]
        private readonly iterable $generators,
    ) {
        parent::__construct();

        $this->initializeClass();
    }

    protected function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    protected function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    protected function getApiDocConfigHelper(): LoadApiDocConfigHelper
    {
        throw new LogicException('Not used in TUI command');
    }

    private function initializeClass(): void
    {
        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('Location must be a string');
        }

        $this->dumpLocation = u($dumpLocation)->ensureStart('/');
        $this->dumpLocation = u($this->dumpLocation)->ensureEnd('/');
    }

    protected function configure(): void
    {
        if (null === $this->dumpLocation) {
            $this->initializeClass();
        }

        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output dir, pass a relative path to the kernel_project_dir',
            default: $this->dumpLocation,
        );

        $this->addFormatOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tui = new Tui();
        TuiUi::applyTheme($tui);

        // Pass the console HelperSet to generators to handle overwrite confirmation
        foreach ($this->generators as $generator) {
            $generator->setHelperSet($this->getHelperSet());
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

        // 1. Add generators registered via DI
        foreach ($this->generators as $generator) {
            if (!$generator->isSupported()) {
                continue;
            }
            $choices[] = [
                'value' => $generator->getLabel(),
                'label' => $formatter->format(sprintf('  <fg=cyan>›</> %-13s <fg=gray>%s</fg=gray>', $generator->getLabel(), $generator->getDescription())),
            ];
            $generatorsMap[$generator->getLabel()] = $generator;
        }

        // Quit option
        $choices[] = [
            'value' => 'quit',
            'label' => $formatter->format('  <fg=red>Quit</fg=red>'),
        ];

        $selectWidget = new SelectListWidget($choices, 12);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(TuiUi::banner());
        $container->add(new TextWidget(''));

        $prompt = new TextWidget('Choose the component type:');
        $prompt->setStyle(new Style(bold: true));
        $container->add($prompt);

        $container->add($selectWidget);
        $container->add(new TextWidget(''));
        $container->add(TuiUi::hints('↑↓ Navigate · ↵ Select · Esc Quit'));

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
}
