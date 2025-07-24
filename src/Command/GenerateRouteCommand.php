<?php

namespace Ehyiah\ApiDocBundle\Command;

use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'apidocbundle:route:generate',
    description: 'Génère une route au format OpenAPI dans un fichier YAML'
)]
final class GenerateRouteCommand extends Command
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            name: 'route',
            mode: InputArgument::REQUIRED,
            description: 'Chemin de la route à générer (exemple: /api/users)'
        );

        $this->addArgument(
            name: 'method',
            mode: InputArgument::OPTIONAL,
            description: 'Méthode HTTP (GET, POST, PUT, DELETE, etc.)',
            default: 'GET'
        );

        $this->addOption(
            name: 'tag',
            shortcut: 't',
            mode: InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            description: 'Tag(s) à associer à la route',
            default: []
        );

        $this->addOption(
            name: 'description',
            shortcut: 'd',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Description de la route',
            default: 'Description de la route'
        );

        $this->addOption(
            name: 'response-schema',
            shortcut: 'rs',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Nom du schéma à utiliser pour la réponse'
        );

        $this->addOption(
            name: 'request-body',
            shortcut: 'rb',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Nom du requestBody à utiliser pour la requête'
        );

        $location = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }

        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Répertoire de sortie (relatif au répertoire du projet)',
            default: $location,
        );

        $this->addOption(
            name: 'filename',
            shortcut: 'f',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Nom du fichier YAML à générer (sans extension)',
            default: 'route'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $route = $input->getArgument('route');
        $method = strtolower($input->getArgument('method'));
        $tags = $input->getOption('tag');
        $description = $input->getOption('description');
        $responseSchema = $input->getOption('response-schema');
        $requestBody = $input->getOption('request-body');

        $outputDir = $input->getOption('output');
        $filename = $input->getOption('filename');

        $routeArray = $this->createRouteArray($route, $method, $tags, $description, $responseSchema, $requestBody);

        $this->generateYamlFile($routeArray, $filename, $outputDir, $output);

        return Command::SUCCESS;
    }

    /**
     * @param array<string> $tags
     *
     * @return array<mixed>
     */
    private function createRouteArray(string $route, string $method, array $tags, string $description, ?string $responseSchema, ?string $requestBody): array
    {
        $array = [
            'documentation' => [
                'paths' => [
                    $route => [
                        $method => [
                            'tags' => $tags ?: ['api'],
                            'description' => $description,
                            'security' => [
                                ['Bearer' => []],
                            ],
                            'responses' => [
                                '200' => [
                                    'description' => 'Succès de l\'opération',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($responseSchema) {
            $array['documentation']['paths'][$route][$method]['responses']['200']['content'] = [
                'application/json' => [
                    'schema' => [
                        '$ref' => '#/components/schemas/' . $responseSchema,
                    ],
                ],
            ];
        }

        if ($requestBody) {
            $array['documentation']['paths'][$route][$method]['requestBody'] = [
                '$ref' => '#/components/requestBodies/' . $requestBody,
            ];
        }

        return $array;
    }

    /**
     * @param array<mixed> $array
     */
    private function generateYamlFile(array $array, string $filename, string $outputDir, OutputInterface $output): void
    {
        $fileSystem = new Filesystem();

        $outputDir = rtrim($outputDir, '/') . '/';
        $fullPath = $this->kernel->getProjectDir() . '/' . ltrim($outputDir, '/');

        if (!file_exists($fullPath)) {
            $fileSystem->mkdir($fullPath);
        }

        $filePath = $fullPath . $filename . '.yaml';

        $yaml = Yaml::dump($array, 12, 4, 1024);
        $fileSystem->dumpFile($filePath, $yaml);

        $output->writeln('<info>Route générée avec succès dans le fichier:</info> ' . $filePath);
    }
}
