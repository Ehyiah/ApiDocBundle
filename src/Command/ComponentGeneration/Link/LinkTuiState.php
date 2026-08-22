<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Link;

class LinkTuiState
{
    public string $name = '';
    public string $operationRef = '';
    public string $operationId = '';
    public string $parameters = '';
    public string $requestBody = '';
    public string $description = '';
    public string $serverUrl = '';
    public string $serverDescription = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
