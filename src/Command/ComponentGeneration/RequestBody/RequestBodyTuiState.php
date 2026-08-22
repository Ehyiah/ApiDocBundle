<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\RequestBody;

class RequestBodyTuiState
{
    public string $name = '';
    public string $description = '';
    public bool $required = false;
    public ?string $schemaRef = null;
    public string $contentType = 'application/json';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
