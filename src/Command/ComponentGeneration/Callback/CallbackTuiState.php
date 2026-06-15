<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Callback;

class CallbackTuiState
{
    public string $name = '';
    public string $expression = '';
    public string $path = '';
    public string $method = 'post';
    public string $description = '';
    public string $operationId = '';
    public string $requestBodyRef = '';
    public string $responseDescription = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
