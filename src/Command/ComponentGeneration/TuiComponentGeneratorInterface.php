<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Tui\Tui;

interface TuiComponentGeneratorInterface
{
    /**
     * Get the label for the generator (shown in the main menu).
     */
    public function getLabel(): string;

    /**
     * Get the description for the generator.
     */
    public function getDescription(): string;

    /**
     * Whether the generator is currently supported/implemented.
     */
    public function isSupported(): bool;

    /**
     * Runs the interactive TUI flow for this generator.
     *
     * @param Tui $tui The TUI instance
     * @param InputInterface $input Console input
     * @param OutputInterface $output Console output
     * @param callable $onBack Callback to go back to the main menu
     */
    public function run(Tui $tui, InputInterface $input, OutputInterface $output, callable $onBack): void;
}
