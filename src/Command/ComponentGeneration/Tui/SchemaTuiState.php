<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

class SchemaTuiState
{
    public string $selectedClass = '';
    /** @var string[] */
    public array $propertiesToSkip = [];
    public string $format = 'both';
    public string $outputDir = '';

    public function __construct(string $defaultOutputDir)
    {
        $this->outputDir = $defaultOutputDir;
    }
}
