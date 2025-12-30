<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'apidocbundle:component:parameter',
    description: 'Generate a reusable parameter component'
)]
final class GenerateComponentParameterCommand extends AbstractGenerateComponentCommand
{
    public const COMPONENT_PARAMETERS = 'parameters';

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the parameter component');
        $this->addOption('in', null, InputOption::VALUE_REQUIRED, 'The location of the parameter (query, header, path, cookie)');
        $this->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'A description for the parameter');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The schema type of the parameter (e.g., string, integer)', 'string');
        $this->addOption('required', null, InputOption::VALUE_NONE, 'Mark the parameter as required');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $in = $input->getOption('in');
        $description = $input->getOption('description');
        $type = $input->getOption('type');
        $required = $input->getOption('required');

        if (empty($in)) {
            $in = $io->askQuestion(new ChoiceQuestion(
                'Please specify the location of the parameter (in)',
                ['query', 'header', 'path', 'cookie'],
                'query'
            ));
        }

        if (empty($description)) {
            $description = $io->askQuestion(new Question('Please provide a description for the parameter'));
        }

        $parameterArray = [
            'name' => $name,
            'in' => $in,
            'description' => $description,
            'required' => 'path' === $in || $required,
            'schema' => [
                'type' => $type,
            ],
        ];

        $array = self::createComponentArray();
        $array['documentation']['components'][self::COMPONENT_PARAMETERS][$name] = $parameterArray;

        if ($this->dumpLocation === $input->getOption('output')) {
            $destination = self::COMPONENT_PARAMETERS;
        }

        $format = $input->getOption('format');

        if ('yaml' === $format || 'both' === $format) {
            $this->generateYamlFile($array, $name, $input, $output, self::COMPONENT_PARAMETERS, $destination ?? null);
        }

        if ('php' === $format || 'both' === $format) {
            $this->generatePhpFile($array, $name, $input, $output, self::COMPONENT_PARAMETERS, $destination ?? null);
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<mixed> $array
     */
    protected function generatePhpBuilderCode(array $array, string $componentName, string $componentType): string
    {
        $parameter = $array['documentation']['components'][self::COMPONENT_PARAMETERS][$componentName];

        $code = "<?php\n\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Builder\\ApiDocBuilder;\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Interfaces\\ApiDocConfigInterface;\n\n";
        $code .= "return new class implements ApiDocConfigInterface {\n";
        $code .= "    public function configure(ApiDocBuilder \$builder): void\n";
        $code .= "    {\n";
        $code .= "        \$builder->addParameter('{$componentName}')\n";
        $code .= "            ->in('{$parameter['in']}')\n";
        $code .= "            ->description('{$parameter['description']}')\n";

        if ($parameter['required']) {
            $code .= "            ->required()\n";
        }

        $code .= "            ->schema(['type' => '{$parameter['schema']['type']}'])\n";
        $code .= "        ->end();\n";
        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }
}
