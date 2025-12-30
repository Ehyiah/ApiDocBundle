<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Symfony\Component\String\u;

#[AsCommand(
    name: 'apidocbundle:component:response',
    description: 'Generate a reusable response component'
)]
final class GenerateComponentResponseCommand extends AbstractGenerateComponentCommand
{
    public const COMPONENT_RESPONSES = 'responses';

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the response component (e.g., NotFound)');
        $this->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A description for the response');
        $this->addOption('status-code', 's', InputOption::VALUE_OPTIONAL, 'The HTTP status code', '200');
        $this->addOption('json-content-schema', 'j', InputOption::VALUE_OPTIONAL, 'Reference to a schema for a JSON response (e.g., User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $description = $input->getOption('description');
        $statusCode = $input->getOption('status-code');
        $jsonContentSchema = $input->getOption('json-content-schema');

        if (empty($description)) {
            $description = $io->askQuestion(new Question('Please provide a description for the response'));
        }

        $responseArray = [
            'description' => $description,
        ];

        if ($jsonContentSchema) {
            $responseArray['content']['application/json']['schema']['$ref'] = '#/components/schemas/' . $jsonContentSchema;
        }

        $array = self::createComponentArray();
        $array['documentation']['components'][self::COMPONENT_RESPONSES][$name] = $responseArray;

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_RESPONSES;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $name, $input, $output, self::COMPONENT_RESPONSES, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFileWithStatusCode($array, $name, $statusCode, $input, $output, self::COMPONENT_RESPONSES, $destination ?? null);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $array
     */
    private function generatePhpFileWithStatusCode(array $array, string $componentName, string $statusCode, InputInterface $input, OutputInterface $output, string $componentType, ?string $destination = null): void
    {
        $phpCode = $this->generatePhpBuilderCodeWithStatusCode($array, $componentName, $statusCode, $componentType);

        $outputDir = $input->getOption('output');
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');
        $dumpLocation = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/') . $componentName . '.php';

        if (!$this->checkExistingPhpFile($dumpLocation, $input, $output, $phpCode)) {
            return;
        }

        $this->writePhpFile($phpCode, $dumpLocation, $output);
    }

    /**
     * @param array<mixed> $array
     */
    private function generatePhpBuilderCodeWithStatusCode(array $array, string $componentName, string $statusCode, string $componentType): string
    {
        $response = $array['documentation']['components'][self::COMPONENT_RESPONSES][$componentName];

        $code = "<?php\n\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Builder\\ApiDocBuilder;\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Interfaces\\ApiDocConfigInterface;\n\n";
        $code .= "return new class implements ApiDocConfigInterface {\n";
        $code .= "    public function configure(ApiDocBuilder \$builder): void\n";
        $code .= "    {\n";
        $code .= "        \$builder->addResponse('{$componentName}')\n";
        $code .= "            ->description('{$response['description']}')\n";
        $code .= "            ->statusCode({$statusCode})\n";

        if (isset($response['content']['application/json']['schema']['$ref'])) {
            $ref = $response['content']['application/json']['schema']['$ref'];
            $code .= "            ->jsonContent()\n";
            $code .= "                ->ref('{$ref}')\n";
            $code .= "            ->end()\n";
        }

        $code .= "        ->end();\n";
        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }
}
