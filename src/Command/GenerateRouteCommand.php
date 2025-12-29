<?php

namespace Ehyiah\ApiDocBundle\Command;

use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'apidocbundle:route:generate',
    description: 'Generate a route in OpenAPI format (YAML/PHP file)'
)]
final class GenerateRouteCommand extends Command
{
    use GenerateFileTrait;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ParameterBagInterface $parameterBag,
        private readonly LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct();
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

    protected function configure(): void
    {
        $this->addArgument(
            name: 'route',
            mode: InputArgument::REQUIRED,
            description: 'Route path to generate (example: /api/users)'
        );

        $this->addArgument(
            name: 'method',
            mode: InputArgument::OPTIONAL,
            description: 'HTTP method (GET, POST, PUT, DELETE, etc.)',
            default: 'GET'
        );

        $this->addOption(
            name: 'tag',
            shortcut: 't',
            mode: InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
            description: 'Tag(s) to associate with the route',
            default: []
        );

        $this->addOption(
            name: 'description',
            shortcut: 'd',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Route description',
            default: 'Route description'
        );

        $this->addOption(
            name: 'response-schema',
            shortcut: 'rs',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Schema name to use for the response'
        );

        $this->addOption(
            name: 'request-body',
            shortcut: 'rb',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'RequestBody name to use for the request'
        );

        $location = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($location)) {
            throw new LogicException('Location must be a string');
        }

        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output directory (relative to project directory)',
            default: $location,
        );

        $this->addOption(
            name: 'filename',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Generated file name (without extension)',
            default: 'route'
        );

        $this->addFormatOption();
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
        $format = $input->getOption('format');

        $routeArray = $this->createRouteArray($route, $method, $tags, $description, $responseSchema, $requestBody);

        if ('yaml' === $format || 'both' === $format) {
            if (!$this->generateRouteYamlFile($routeArray, $filename, $outputDir, $input, $output, $format)) {
                return Command::FAILURE;
            }
        }

        if ('php' === $format || 'both' === $format) {
            if (!$this->generateRoutePhpFile($routeArray, $route, $method, $filename, $outputDir, $input, $output, $format)) {
                return Command::FAILURE;
            }
        }

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
                                    'description' => 'Successful operation',
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
    private function generateRouteYamlFile(
        array $array,
        string $filename,
        string $outputDir,
        InputInterface $input,
        OutputInterface $output,
        string $format,
    ): bool {
        $yamlPath = $this->buildOutputPath($outputDir, $filename, 'yaml');
        $phpPath = $this->buildOutputPath($outputDir, $filename, 'php');

        // Check if YAML file already exists
        if (!$this->checkExistingYamlFile($yamlPath, $input, $output, $array)) {
            return false;
        }

        // Warn about existing PHP file if not generating both
        if ('yaml' === $format && !$this->warnAboutOtherFormat($phpPath, 'yaml', $input, $output)) {
            return false;
        }

        $this->writeYamlFile($array, $yamlPath, $output);

        return true;
    }

    /**
     * @param array<mixed> $array
     */
    private function generateRoutePhpFile(
        array $array,
        string $route,
        string $method,
        string $filename,
        string $outputDir,
        InputInterface $input,
        OutputInterface $output,
        string $format,
    ): bool {
        $phpPath = $this->buildOutputPath($outputDir, $filename, 'php');
        $yamlPath = $this->buildOutputPath($outputDir, $filename, 'yaml');

        $phpCode = $this->generatePhpBuilderCode($array, $route, $method);

        // Check if PHP file already exists
        if (!$this->checkExistingPhpFile($phpPath, $input, $output, $phpCode)) {
            return false;
        }

        // Warn about existing YAML file if not generating both
        if ('php' === $format && !$this->warnAboutOtherFormat($yamlPath, 'php', $input, $output)) {
            return false;
        }

        $this->writePhpFile($phpCode, $phpPath, $output);

        return true;
    }

    /**
     * @param array<mixed> $array
     */
    private function generatePhpBuilderCode(array $array, string $route, string $method): string
    {
        $routeConfig = $array['documentation']['paths'][$route][$method];

        $code = "<?php\n\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Builder\\ApiDocBuilder;\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Interfaces\\ApiDocConfigInterface;\n\n";
        $code .= "return new class implements ApiDocConfigInterface {\n";
        $code .= "    public function configure(ApiDocBuilder \$builder): void\n";
        $code .= "    {\n";
        $code .= "        \$builder->addRoute()\n";
        $code .= "            ->path('{$route}')\n";
        $code .= "            ->method('" . strtoupper($method) . "')\n";

        // Add tags
        $tags = $routeConfig['tags'] ?? [];
        foreach ($tags as $tag) {
            $code .= "            ->tag('{$tag}')\n";
        }

        // Add description
        if (isset($routeConfig['description'])) {
            $description = addslashes($routeConfig['description']);
            $code .= "            ->description('{$description}')\n";
        }

        // Add security
        if (isset($routeConfig['security'])) {
            foreach ($routeConfig['security'] as $security) {
                foreach (array_keys($security) as $securityName) {
                    $code .= "            ->security('{$securityName}')\n";
                }
            }
        }

        // Add requestBody reference
        if (isset($routeConfig['requestBody']['$ref'])) {
            $ref = $routeConfig['requestBody']['$ref'];
            $code .= "            ->requestBodyRef('{$ref}')\n";
        }

        // Add responses
        if (isset($routeConfig['responses'])) {
            foreach ($routeConfig['responses'] as $statusCode => $responseConfig) {
                $code .= "            ->response({$statusCode})\n";

                if (isset($responseConfig['description'])) {
                    $responseDesc = addslashes($responseConfig['description']);
                    $code .= "                ->description('{$responseDesc}')\n";
                }

                if (isset($responseConfig['content']['application/json']['schema']['$ref'])) {
                    $schemaRef = $responseConfig['content']['application/json']['schema']['$ref'];
                    $code .= "                ->jsonContent()\n";
                    $code .= "                    ->ref('{$schemaRef}')\n";
                    $code .= "                ->end()\n";
                }

                $code .= "            ->end()\n";
            }
        }

        $code .= "        ->end();\n";
        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }
}
