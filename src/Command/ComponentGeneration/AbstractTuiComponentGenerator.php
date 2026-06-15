<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

abstract class AbstractTuiComponentGenerator implements TuiComponentGeneratorInterface
{
    use GenerateFileTrait;

    public function __construct(
        protected KernelInterface $kernel,
        protected ParameterBagInterface $parameterBag,
        protected PropertyInfoExtractorInterface $propertyInfoExtractor,
        protected LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
    }

    public function getKernel(): KernelInterface
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
