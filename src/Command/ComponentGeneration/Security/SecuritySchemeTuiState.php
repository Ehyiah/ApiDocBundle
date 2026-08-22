<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Security;

class SecuritySchemeTuiState
{
    public string $name = '';
    public string $type = 'http';
    public string $scheme = 'bearer';
    public string $bearerFormat = 'JWT';
    public string $apiKeyName = '';
    public string $apiKeyIn = 'header';
    public string $openIdConnectUrl = '';
    public string $description = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
