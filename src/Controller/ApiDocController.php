<?php

namespace Ehyiah\ApiDocBundle\Controller;

use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
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

    public function index(Request $request): Response
    {
        $json = $this->loadConfigFiles();
        $ui = $request->query->getString('ui') ?: $this->parameterBag->get('ehyiah_api_doc.ui');

        if (!in_array($ui, ['swagger', 'redoc'], true)) {
            $ui = 'swagger';
        }

        return $this->render('@EhyiahApiDoc/index.html.twig', [
            'json' => $json,
            'ui' => $ui,
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

        /** @var string|null $baseUrlParameter */
        $baseUrlParameter = $this->parameterBag->get('ehyiah_api_doc.site_urls');

        $config = LoadApiDocConfigHelper::loadYamlConfigDoc($location, $this->kernel->getProjectDir(), $dumpLocation);
        $phpConfig = $this->loadApiDocConfigHelper->loadPhpConfigDoc();
        $config = LoadApiDocConfigHelper::mergeConfigs($config, $phpConfig);

        // Add server URLs
        $urls = LoadApiDocConfigHelper::loadServerUrls($baseUrlParameter);
        $config = LoadApiDocConfigHelper::mergeConfigs($config, $urls);

        return json_encode($config);
    }
}
