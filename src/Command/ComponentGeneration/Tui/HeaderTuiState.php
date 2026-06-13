<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

class HeaderTuiState
{
    public string $name = '';
    public string $description = '';
    public string $schemaType = 'string';
    public string $format = '';
    public string $example = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
}
