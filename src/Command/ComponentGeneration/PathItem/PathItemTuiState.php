<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\PathItem;

class PathItemTuiState
{
    public string $name = '';
    public string $summary = '';
    public string $description = '';
    public string $ref = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
