<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration\Tui;

use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

abstract class AbstractTuiComponentGenerator implements TuiComponentGeneratorInterface
{
    use GenerateFileTrait;

    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly ParameterBagInterface $parameterBag,
        protected readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        protected readonly LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
    }

    protected function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    protected function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    protected function getApiDocConfigHelper(): LoadApiDocConfigHelper
    {
        return $this->apiDocConfigHelper;
    }
}
