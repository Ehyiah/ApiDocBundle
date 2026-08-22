<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Example;

class ExampleTuiState
{
    public string $name = '';
    public string $summary = '';
    public string $description = '';
    public string $value = '';
    public string $externalValue = '';
    public ?string $schemaRef = null;
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
