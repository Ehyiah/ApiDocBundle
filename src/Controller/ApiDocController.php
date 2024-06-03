<?php

namespace Ehyiah\ApiDocBundle\Controller;

use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

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
        $config = [];
        $finder = new Finder();

        /** @var string $baseUrlParameter */
        $baseUrlParameter = $this->parameterBag->get('ehyiah_api_doc.site_urls');
        $baseUrls = explode(',', $baseUrlParameter);
        foreach ($baseUrls as $index => $url) {
            $config['servers'][$index]['url'] = $url;
        }

        $location = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }

        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('dumpLocation must be a string');
        }

        $finder->files()
            ->in($this->kernel->getProjectDir() . $location)
            ->exclude($dumpLocation)
            ->name(['*.yaml', '*.yml'])
        ;

        if ($finder->hasResults()) {
            foreach ($finder->getIterator() as $import) {
                foreach (Yaml::parseFile($import) as $item) {
                    $config = array_merge_recursive($config, $item);
                }
            }
        }

        return json_encode($config);
    }
}
