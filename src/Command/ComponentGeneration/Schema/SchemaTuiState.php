<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Schema;

class SchemaTuiState
{
    public string $selectedClass = '';
    /** @var string[] */
    public array $propertiesToSkip = [];
    public string $format = 'yaml';
    public string $outputDir = '';

    public function __construct(string $defaultOutputDir)
    {
        $this->outputDir = $defaultOutputDir;
    }
}
