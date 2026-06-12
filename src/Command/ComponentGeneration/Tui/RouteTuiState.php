<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

class RouteTuiState
{
    public string $routeName = '';
    public string $summary = '';
    public string $description = '';
    public string $format = 'yaml';
    public string $outputDir = '';
    /** @var string[] */
    public array $methods = [];
    /** @var array<string, string[]> */
    public array $security = [];
}
