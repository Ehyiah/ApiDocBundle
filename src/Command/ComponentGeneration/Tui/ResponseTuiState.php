<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

class ResponseTuiState
{
    public string $name = '';
    public string $statusCode = '200';
    public string $description = '';
    public ?string $schemaRef = null;
    public string $contentType = 'application/json';
    public string $format_output = 'yaml';
    public string $outputDir = '';
}
