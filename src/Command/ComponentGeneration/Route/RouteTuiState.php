<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Route;

class RouteTuiState
{
    public string $routeName = '';
    public string $format = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;

    /**
     * Per-method configuration.
     * Key: HTTP method (GET, POST, PUT, DELETE, PATCH)
     * Value: method-specific config
     *
     * @var array<string, array{summary: string, description: string, security: string[], requestBodySchema: ?string, requestBodyExample: ?string, responses: array<int, array{schema: ?string, description: string, example: ?string}>, operationId: string}>
     */
    public array $methodsConfig = [];
}
