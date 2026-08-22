<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tag;

class TagTuiState
{
    public string $name = '';
    public string $description = '';
    public string $externalDocsUrl = '';
    public string $externalDocsDescription = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
