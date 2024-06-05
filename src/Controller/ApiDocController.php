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
        $urls = LoadApiDocConfigHelper::loadServerUrls($baseUrlParameter);
        $config = LoadApiDocConfigHelper::loadApiDocConfig($location, $this->kernel->getProjectDir(), $dumpLocation);
        $config = array_merge_recursive($config, $urls);

        return json_encode($config);
    }
}
