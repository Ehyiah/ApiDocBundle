<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

class RouteTuiState
{
    public string $routeName = '';
    public string $format = 'yaml';
    public string $outputDir = '';

    /**
     * Per-method configuration.
     * Key: HTTP method (GET, POST, PUT, DELETE, PATCH)
     * Value: method-specific config
     *
     * @var array<string, array{summary: string, description: string, security: string[], requestBodySchema: ?string, responseSchema: ?string}>
     */
    public array $methodsConfig = [];
}
