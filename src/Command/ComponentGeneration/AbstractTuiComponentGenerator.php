<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Event\FocusEvent;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

abstract class AbstractTuiComponentGenerator implements TuiComponentGeneratorInterface
{
    use GenerateFileTrait;

    protected ?OutputInterface $currentOutput = null;

    private ?HelperSet $helperSet = null;

    public function __construct(
        protected KernelInterface $kernel,
        protected ParameterBagInterface $parameterBag,
        protected PropertyInfoExtractorInterface $propertyInfoExtractor,
        protected LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
    }

    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    protected function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    protected function getApiDocConfigHelper(): LoadApiDocConfigHelper
    {
        return $this->apiDocConfigHelper;
    }

    public function setHelperSet(HelperSet $helperSet): void
    {
        $this->helperSet = $helperSet;
    }

    public function getHelper(string $name): mixed
    {
        return $this->helperSet?->get($name);
    }

    /**
     * Add a Schema-style search input above a list and keep it filtered live.
     *
     * Focus stays on the list so arrows work immediately; pressing "/" jumps
     * into the search field, Esc returns focus to the list. The field shows
     * "Press / to search" until focused.
     *
     * @param array<int, array{value: string, label: string|null}> $choices
     */
    protected function attachSearchFilter(Tui $tui, ContainerWidget $container, SelectListWidget $list, array $choices): InputWidget
    {
        $search = new InputWidget();
        $search->setPrompt('Press / to search');

        $applyFilter = static function (string $query) use ($tui, $list, $choices): void {
            if ('' === $query) {
                $list->setItems($choices);
            } else {
                $filtered = array_values(array_filter(
                    $choices,
                    static fn (array $choice): bool => str_contains(strtolower((string)$choice['value']), strtolower($query))
                        || str_contains(strtolower(strip_tags((string)($choice['label'] ?? ''))), strtolower($query)),
                ));
                $list->setItems($filtered);
            }
            $tui->requestRender();
        };

        $search->onChange(static function (ChangeEvent $event) use ($applyFilter): void {
            $applyFilter($event->getValue());
        });
        $search->onSubmit(static function () use ($tui, $list): void {
            $tui->setFocus($list);
        });
        $search->onCancel(static function () use ($tui, $list): void {
            $tui->setFocus($list);
        });

        $container->add($search);
        $container->add(new TextWidget(''));

        // Pressing "/" while the list is focused jumps into the search field.
        // Guarded by current focus so listeners from previous screens stay inert.
        $tui->addListener(static function (InputEvent $event) use ($tui, $list, $search): void {
            if ('/' !== $event->getData()) {
                return;
            }
            if ($tui->getFocus() !== $list) {
                return;
            }
            $event->stopPropagation();
            $tui->setFocus($search);
        });

        // Swap the search prompt so the field advertises the "/" shortcut
        // whenever it is not focused. setPrompt() skips invalidation when the
        // value is unchanged, making unrelated focus switches cost-free.
        $tui->addListener(static function (FocusEvent $event) use ($search): void {
            if ($event->getTarget() === $search) {
                $search->setPrompt('Search: ');
            } else {
                $search->setPrompt('Press / to search');
            }
        });

        return $search;
    }

    /**
     * Full-screen yes/no confirmation; both callbacks receive no arguments.
     */
    protected function requestConfirm(Tui $tui, string $question, callable $onYes, callable $onNo): void
    {
        $formatter = $this->currentOutput->getFormatter();
        $select = new SelectListWidget([
            ['value' => 'yes', 'label' => $formatter->format('  <fg=green>✓ Yes</fg=green>')],
            ['value' => 'no', 'label' => $formatter->format('  <fg=gray>✗ No</fg=gray>')],
        ], 5);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(TuiUi::header('Confirmation'));

        $questionWidget = new TextWidget($question);
        $questionWidget->setStyle(new Style(bold: true));
        $container->add($questionWidget);
        $container->add(new TextWidget(''));
        $container->add($select);
        $container->add(new TextWidget(''));
        $container->add(TuiUi::hints('↑↓ Navigate · ↵ Confirm · Esc Cancel'));

        $tui->add($container);
        $tui->setFocus($select);

        TuiUi::onSelect(
            $tui,
            $select,
            static function (string $value) use ($onYes, $onNo): void {
                if ('yes' === $value) {
                    $onYes();
                } else {
                    $onNo();
                }
            },
            $onNo,
        );
    }

    /**
     * Full-screen generation outcome with a single continue action.
     */
    protected function showResult(Tui $tui, string $message, bool $success, callable $onContinue): void
    {
        $formatter = $this->currentOutput->getFormatter();

        if ($success) {
            $messageWidget = new TextWidget($formatter->format('<fg=green>✓ ' . $message . '</fg=green>'));
        } else {
            $messageWidget = new TextWidget($formatter->format('<fg=red>✗ ' . addslashes($message) . '</fg=red>'));
        }

        $select = new SelectListWidget([
            ['value' => 'continue', 'label' => $formatter->format('  ↵ Continue')],
        ], 3);

        $tui->clear();
        $container = new ContainerWidget();
        $container->expandVertically(true);
        $container->add(TuiUi::header($success ? 'Success' : 'Error'));
        $container->add($messageWidget);
        $container->add(new TextWidget(''));
        $container->add($select);
        $container->add(new TextWidget(''));
        $container->add(TuiUi::hints('↵ Continue · Esc Back'));

        $tui->add($container);
        $tui->setFocus($select);

        TuiUi::onSelect($tui, $select, static function () use ($onContinue): void {
            $onContinue();
        }, $onContinue);
    }
}
