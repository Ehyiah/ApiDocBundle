<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Parameter;

class ParameterTuiState
{
    public string $name = '';
    public string $in = 'query';
    public string $description = '';
    public bool $required = false;
    public bool $deprecated = false;
    public bool $allowEmptyValue = false;
    public string $style = '';
    public string $explode = '';
    public bool $allowReserved = false;
    public string $schemaType = 'string';
    public string $format = '';
    public string $example = '';
    public string $format_output = 'yaml';
    public string $outputDir = '';
    public ?string $loadedFrom = null;
}
