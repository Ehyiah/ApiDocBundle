<?php

namespace Ehyiah\ApiDocBundle\Command\ComponentGeneration;

use BackedEnum;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Ehyiah\ApiDocBundle\Command\Traits\GenerateFileTrait;
use Ehyiah\ApiDocBundle\Helper\LoadApiDocConfigHelper;
use LogicException;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

use function Symfony\Component\String\u;

abstract class AbstractGenerateComponentCommand extends Command
{
    use GenerateFileTrait;

    public const COMPONENT_SCHEMAS = 'schemas';
    public const COMPONENT_REQUEST_BODIES = 'requestBodies';

    protected ?string $dumpLocation = null;
    /** @phpstan-ignore-next-line */
    protected ?ReflectionClass $reflectionClass = null;

    public function __construct(
        protected readonly KernelInterface $kernel,
        protected readonly ParameterBagInterface $parameterBag,
        protected readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        protected readonly FormFactoryInterface $formFactory,
        protected readonly LoadApiDocConfigHelper $apiDocConfigHelper,
    ) {
        parent::__construct();

        $this->initializeClass();
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

    protected function initializeClass(): void
    {
        $dumpLocation = $this->parameterBag->get('ehyiah_api_doc.source_path');
        if (!is_string($dumpLocation)) {
            throw new LogicException('Location must be a string');
        }

        $this->dumpLocation = u($dumpLocation)->ensureStart('/');
        $this->dumpLocation = u($dumpLocation)->ensureEnd('/');
    }

    protected function configure(): void
    {
        if (null === $this->dumpLocation) {
            $this->initializeClass();
        }

        $this->addOption(
            name: 'output',
            shortcut: 'o',
            mode: InputOption::VALUE_OPTIONAL,
            description: 'Output dir, pass a relative path to the kernel_project_dir',
            default: $this->dumpLocation,
        );

        $this->addFormatOption();
    }

    /**
     * @param array<mixed> $array
     */
    protected function generateYamlFile(array $array, string $componentName, InputInterface $input, OutputInterface $output, string $componentType, ?string $destination = null): void
    {
        $outputDir = $input->getOption('output');
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');

        $dumpPath = $this->parameterBag->get('ehyiah_api_doc.dump_path');
        if (!is_string($dumpPath)) {
            throw new LogicException('dumpLocation must be a string');
        }

        $existingConfigs = LoadApiDocConfigHelper::loadYamlConfigDoc(
            $this->dumpLocation,
            $this->kernel->getProjectDir(),
            $dumpPath,
        );

        $dumpLocation = null;

        // Check if component already exists in YAML config
        if (null !== $componentType && isset($existingConfigs['components'][$componentType][$componentName])) {
            $componentAlreadyExistFile = $this->apiDocConfigHelper->findYamlComponentFile($componentName, $componentType);
            if ($componentAlreadyExistFile) {
                $dumpLocation = $componentAlreadyExistFile->getPathname();
            }
        }

        if (null === $dumpLocation) {
            $dumpLocation = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/') . $componentName . '.yaml';
        }

        // Check if component already exists in YAML file (using trait for consistent interaction)
        if (!$this->checkExistingYamlFile($dumpLocation, $input, $output)) {
            return;
        }

        // Check if component already exists in PHP format
        $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($componentName, $componentType);
        if (null !== $phpComponentFile) {
            // Use trait method for warning, passing the found PHP file path
            if (!$this->warnAboutOtherFormat($phpComponentFile->getPathname(), 'yaml', $input, $output)) {
                return;
            }
        }

        $this->writeYamlFile($array, $dumpLocation, $output);
    }

    /**
     * @return array<mixed>
     */
    public static function createComponentArray(): array
    {
        return [
            'documentation' => [
                'components' => [
                ],
            ],
        ];
    }

    /**
     * @phpstan-ignore-next-line
     */
    protected function getReflectionClass(?InputInterface $input = null): ReflectionClass
    {
        if (null !== $input) {
            /* @var class-string $fqcn */
            $fqcn = $input->getArgument('class');
            if (null === $this->reflectionClass) {
                $this->reflectionClass = new ReflectionClass($fqcn);
            }
        }

        if (null === $this->reflectionClass) {
            throw new LogicException('Class not found');
        }

        return $this->reflectionClass;
    }

    protected function checkIfClassExists(InputInterface $input, OutputInterface $output): int|string
    {
        $fullClassName = $this->getReflectionClass($input)->getName();

        if (!class_exists($fullClassName)) {
            $output->writeln(sprintf('Class "%s" not found', $fullClassName));

            return Command::FAILURE;
        }

        return $fullClassName;
    }

    /**
     * @throws ReflectionException
     */
    protected function getShortClassName(): string
    {
        return $this->getReflectionClass()->getShortName();
    }

    /**
     * @param array<mixed> $schema
     */
    public static function addProperty(array &$schema, string $property, Type $type): void
    {
        if ('array' === $type->getBuiltinType()) {
            $arrayType = $type->getCollectionValueTypes();
            if (isset($arrayType[0])) {
                /** @var class-string $itemClass */
                $itemClass = $type->getCollectionValueTypes()[0]->getClassName();
                if (null !== $itemClass) {
                    $reflectionClass = (new ReflectionClass($itemClass));

                    if (in_array(BackedEnum::class, $reflectionClass->getInterfaceNames())) {
                        self::handleEnum($schema, $reflectionClass, $property, 'array');

                        return;
                    }

                    $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                }

                $schema[$property]['type'] = 'array';
                if (!isset($schema[$property]['items'])) {
                    if ('bool' === $arrayType[0]->getBuiltinType()) {
                        $schema[$property]['items']['type'] = 'boolean';
                    } elseif ('int' === $arrayType[0]->getBuiltinType()) {
                        $schema[$property]['items']['type'] = 'integer';
                    } else {
                        $schema[$property]['items']['type'] = $arrayType[0]->getBuiltinType();
                    }
                }

                return;
            }
            $schema[$property]['items']['type'] = 'string';

            $schema[$property]['type'] = 'array';

            return;
        }

        if (null !== $type->getClassName()) {
            /** @var class-string $className */
            $className = $type->getClassName();
            $reflectionClass = new ReflectionClass($className);
            $interfaces = $reflectionClass->getInterfaceNames();

            if (in_array(DateTimeInterface::class, $interfaces)) {
                $schema[$property]['type'] = 'string';
                $schema[$property]['format'] = 'date-time';

                return;
            }

            if ($type->isCollection()) {
                $collectionClass = $type->getCollectionValueTypes()[0]->getClassName();
                /** @var class-string $collectionClass */
                $reflectionClass = (new ReflectionClass($collectionClass));
                $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                $schema[$property]['type'] = 'array';

                return;
            }

            if (Collection::class === $type->getClassName()) {
                $schema[$property]['type'] = 'array';
                $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];

                return;
            }

            if (in_array(BackedEnum::class, $interfaces)) {
                self::handleEnum($schema, $reflectionClass, $property);

                return;
            }

            $schema[$property]['$ref'] = '#/components/schemas/' . $reflectionClass->getShortName();

            return;
        }

        if ('bool' === $type->getBuiltinType()) {
            $schema[$property]['type'] = 'boolean';
            $schema[$property]['description'] = '';

            return;
        }

        if ('int' === $type->getBuiltinType()) {
            $schema[$property]['type'] = 'integer';
            $schema[$property]['description'] = '';

            return;
        }

        $schema[$property]['type'] = $type->getBuiltinType();
        $schema[$property]['description'] = '';
    }

    /**
     * @param array<mixed> $array
     */
    public static function addRequirement(array &$array, string $property): void
    {
        $array[] = $property;
    }

    /**
     * @param array<mixed> $array
     *
     * @phpstan-ignore-next-line
     */
    public static function handleEnum(array &$array, ReflectionClass $reflectionClass, string $property, string $type = 'string'): void
    {
        $values = [];
        $enumCases = $reflectionClass->getConstants();
        /** @var BackedEnum $enumCase */
        foreach ($enumCases as $enumCase) {
            $values[] = $enumCase->value;
        }
        $array[$property]['type'] = $type;
        $array[$property]['enum'] = $values;
    }

    /**
     * @param array<mixed> $property
     *
     * @return array<mixed>
     */
    public function guessTypeFromFormPrefix(FormInterface $form, ?array &$property = null): array
    {
        $config = $form->getConfig();
        $type = $config->getType();
        $blockPrefix = $type->getBlockPrefix();

        if (null === $property) {
            $property = [];
        }

        if ('text' === $blockPrefix) {
            $property['type'] = 'string';

            return $property;
        }

        if ('number' === $blockPrefix) {
            $property['type'] = 'number';

            return $property;
        }

        if ('integer' === $blockPrefix) {
            $property['type'] = 'integer';

            return $property;
        }

        if ('date' === $blockPrefix) {
            $property['type'] = 'string';
            $property['format'] = 'date';

            return $property;
        }

        if ('datetime' === $blockPrefix) {
            $property['type'] = 'string';
            $property['format'] = 'date-time';

            return $property;
        }

        if ('checkbox' === $blockPrefix) {
            $property['type'] = 'boolean';

            return $property;
        }

        if ('password' === $blockPrefix) {
            $property['type'] = 'string';
            $property['format'] = 'password';

            return $property;
        }

        if ('choice' === $blockPrefix) {
            if (true === $config->getOption('multiple')) {
                $property['type'] = 'array';
                $choices = $config->getOption('choices');
                if (is_array($choices) && count($choices) > 0) {
                    $property['enum'] = $choices;
                } else {
                    $property['enum'] = [];
                }
            } else {
                $property['type'] = 'string';
                $choices = $config->getOption('choices');
                if (is_array($choices) && count($choices) > 0) {
                    $property['enum'] = $choices;
                } else {
                    $property['enum'] = [];
                }
            }

            return $property;
        }

        if ('repeated' === $blockPrefix) {
            $property['type'] = 'object';
            $property['properties']['first'] = [];
            $property['properties']['second'] = [];

            return $property;
        }

        if ('collection' === $blockPrefix) {
            $property['type'] = 'array';
            $property['items'] = [];

            return $property;
        }

        return $property;
    }

    /**
     * @param array<mixed> $array
     * @param array<mixed> $informations
     */
    protected static function addPropertyFromFormType(array &$array, string $property, array $informations): bool
    {
        if (isset($informations['type'])) {
            $type = $informations['type'];
            $array[$property]['type'] = $type;

            if (isset($informations['format'])) {
                $format = $informations['format'];
                $array[$property]['format'] = $format;
            }

            if (isset($informations['enum'])) {
                $enum = $informations['enum'];
                $array[$property]['enum'] = $enum;
            }

            if (isset($informations['items'])) {
                $enum = $informations['items'];
                $array[$property]['items'] = $enum;
            }
        }

        return false;
    }

    /**
     * @param array<mixed> $array
     */
    protected function generatePhpFile(array $array, string $componentName, InputInterface $input, OutputInterface $output, string $componentType, ?string $destination = null): void
    {
        $outputDir = $input->getOption('output');
        $outputDir = u($outputDir)->ensureStart('/')->ensureEnd('/');

        // Logic to determine PHP file path
        $dumpLocation = null;
        $phpComponentFile = $this->apiDocConfigHelper->findPhpComponentFile($componentName, $componentType);

        if (null !== $phpComponentFile) {
            $dumpLocation = $phpComponentFile->getPathname();
        } else {
            $dumpDirectory = $this->kernel->getProjectDir() . $outputDir . u($destination)->ensureEnd('/');
            $dumpLocation = $dumpDirectory . $componentName . '.php';
        }

        // Check if component already exists in PHP file (using trait)
        if (!$this->checkExistingPhpFile($dumpLocation, $input, $output)) {
            return;
        }

        // Check if component already exists in YAML format
        $yamlComponentFile = $this->apiDocConfigHelper->findYamlComponentFile($componentName, $componentType);
        if (null !== $yamlComponentFile) {
            if (!$this->warnAboutOtherFormat($yamlComponentFile->getPathname(), 'php', $input, $output)) {
                return;
            }
        }

        $phpCode = $this->generatePhpBuilderCode($array, $componentName, $componentType);
        $this->writePhpFile($phpCode, $dumpLocation, $output);
    }

    /**
     * @param array<mixed> $array
     */
    protected function generatePhpBuilderCode(array $array, string $componentName, string $componentType): string
    {
        $code = "<?php\n\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Builder\\ApiDocBuilder;\n";
        $code .= "use Ehyiah\\ApiDocBundle\\Interfaces\\ApiDocConfigInterface;\n\n";
        $code .= "return new class implements ApiDocConfigInterface {\n";
        $code .= "    public function configure(ApiDocBuilder \$builder): void\n";
        $code .= "    {\n";

        if (self::COMPONENT_SCHEMAS === $componentType) {
            $schema = $array['documentation']['components']['schemas'][$componentName] ?? [];
            $code .= $this->buildSchemaCode($componentName, $schema, 2);
        } elseif (self::COMPONENT_REQUEST_BODIES === $componentType) {
            $requestBody = $array['documentation']['components']['requestBodies'][$componentName] ?? [];
            $code .= $this->buildRequestBodyCode($componentName, $requestBody, 2);
        }

        $code .= "    }\n";
        $code .= "};\n";

        return $code;
    }

    /**
     * @param array<mixed> $schema
     */
    protected function buildSchemaCode(string $name, array $schema, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addSchema('{$name}')\n";

        if (isset($schema['type'])) {
            $code .= "{$pad}    ->type('{$schema['type']}')\n";
        }

        if (isset($schema['description'])) {
            $description = addslashes($schema['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $propName => $propDef) {
                $code .= $this->buildPropertyCode($propName, $propDef, $schema['required'] ?? [], $indent + 1);
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }

    /**
     * @param array<mixed> $propDef
     * @param array<string> $requiredFields
     */
    protected function buildPropertyCode(string $name, array $propDef, array $requiredFields, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}->addProperty('{$name}')\n";

        if (isset($propDef['$ref'])) {
            $ref = $propDef['$ref'];
            $code .= "{$pad}    ->ref('{$ref}')\n";
        } else {
            if (isset($propDef['type'])) {
                $code .= "{$pad}    ->type('{$propDef['type']}')\n";
            }

            if (isset($propDef['format'])) {
                $code .= "{$pad}    ->format('{$propDef['format']}')\n";
            }

            if (isset($propDef['description']) && '' !== $propDef['description']) {
                $description = addslashes($propDef['description']);
                $code .= "{$pad}    ->description('{$description}')\n";
            }

            if (isset($propDef['enum'])) {
                $enumValues = array_map(function ($v) {
                    return is_string($v) ? "'" . addslashes($v) . "'" : $v;
                }, $propDef['enum']);
                $code .= "{$pad}    ->enum([" . implode(', ', $enumValues) . "])\n";
            }

            if (isset($propDef['items'])) {
                if (isset($propDef['items']['$ref'])) {
                    $code .= "{$pad}    ->items(['\$ref' => '{$propDef['items']['$ref']}'])\n";
                } elseif (isset($propDef['items']['type'])) {
                    $code .= "{$pad}    ->items(['type' => '{$propDef['items']['type']}'])\n";
                }
            }

            if (isset($propDef['nullable']) && $propDef['nullable']) {
                $code .= "{$pad}    ->nullable()\n";
            }
        }

        if (in_array($name, $requiredFields, true)) {
            $code .= "{$pad}    ->required()\n";
        }

        $code .= "{$pad}->end()\n";

        return $code;
    }

    /**
     * @param array<mixed> $requestBody
     */
    protected function buildRequestBodyCode(string $name, array $requestBody, int $indent): string
    {
        $pad = str_repeat('    ', $indent);
        $code = "{$pad}\$builder->addRequestBody('{$name}')\n";

        if (isset($requestBody['description'])) {
            $description = addslashes($requestBody['description']);
            $code .= "{$pad}    ->description('{$description}')\n";
        }

        if (isset($requestBody['required']) && $requestBody['required']) {
            $code .= "{$pad}    ->required()\n";
        }

        if (isset($requestBody['content'])) {
            foreach ($requestBody['content'] as $mediaType => $content) {
                if ('application/json' === $mediaType) {
                    $code .= "{$pad}    ->jsonContent()\n";
                } else {
                    $code .= "{$pad}    ->content('{$mediaType}')\n";
                }

                if (isset($content['schema'])) {
                    if (isset($content['schema']['$ref'])) {
                        $code .= "{$pad}        ->ref('{$content['schema']['$ref']}')\n";
                    } elseif (isset($content['schema']['properties'])) {
                        $code .= "{$pad}        ->schema()\n";
                        $code .= "{$pad}            ->type('object')\n";
                        foreach ($content['schema']['properties'] as $propName => $propDef) {
                            $code .= $this->buildPropertyCode($propName, $propDef, $content['schema']['required'] ?? [], $indent + 3);
                        }
                        $code .= "{$pad}        ->end()\n";
                    }
                }

                $code .= "{$pad}    ->end()\n";
            }
        }

        $code .= "{$pad}->end();\n";

        return $code;
    }
}
