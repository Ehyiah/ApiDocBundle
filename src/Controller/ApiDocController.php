<?php

namespace Ehyiah\ApiDocBundle\Controller;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class ApiDocController extends AbstractController
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoadApiDocConfigHelper $loadApiDocConfigHelper,
    ) {
    }

    public function index(): Response
    {
        $json = $this->loadConfigFiles();

        return $this->render('@EhyiahApiDoc/index.html.twig', [
            'json' => $json,
        ]);
    }

    private function loadConfigFiles(): string|false
    {
        $location = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }

        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('dumpLocation must be a string');
        }

        /** @var string $baseUrlParameter */
        $baseUrlParameter = $this->parameterBag->get('ehyiah_api_doc.site_urls');

        // Load from YAML files
        $config = LoadApiDocConfigHelper::loadApiDocConfig($location, $this->kernel->getProjectDir(), $dumpLocation);

        // Load from PHP config classes and merge
        $phpConfig = $this->loadApiDocConfigHelper->loadPhpConfigDoc();
        $config = array_merge_recursive($config, $phpConfig);

        // Add server URLs
        $urls = LoadApiDocConfigHelper::loadServerUrls($baseUrlParameter);
        $config = array_merge_recursive($config, $urls);

        return json_encode($config);
    }
}
