<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Header;

class HeaderTuiState
{
    public string $name = '';
    public string $description = '';
    public bool $required = false;
    public bool $deprecated = false;
    public string $schemaType = 'string';
    public string $format = '';
    public string $example = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
