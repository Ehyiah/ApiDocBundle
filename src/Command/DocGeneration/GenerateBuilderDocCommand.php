<?php

namespace Ehyiah\ApiDocBundle\Command\DocGeneration;

use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'apidocbundle:doc:generate-builders',
    description: 'Generates documentation for the PHP builders.'
)]
final class GenerateBuilderDocCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path', '../../docs/BUILDER_REFERENCE.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = $input->getOption('output');
        $builderDir = __DIR__ . '/../../Builder';

        $finder = new Finder();
        $finder->in($builderDir)->files()->name('*.php')->sortByName();

        $builderDocs = [];
        $toc = ['## Table of Contents', ''];

        foreach ($finder as $file) {
            $className = 'Ehyiah\\ApiDocBundle\\Builder\\' . $file->getBasename('.php');
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isInterface() || $reflection->isAbstract()) {
                continue;
            }

            $shortName = $reflection->getShortName();
            $toc[] = '- [' . $shortName . '](#' . strtolower($shortName) . ')';

            $doc = [];
            $doc[] = '## ' . $shortName;
            $doc[] = '';
            $doc[] = $this->getCleanedDocComment($reflection->getDocComment());
            $doc[] = '';

            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if ($method->isConstructor() || str_starts_with($method->getName(), '__')) {
                    continue;
                }
                $doc[] = $this->buildMethodDoc($method);
            }
            $builderDocs[] = implode("\n", $doc);
        }

        $markdown = ['# PHP Builder Reference', ''];
        $markdown[] = '> This file is auto-generated. Do not edit it manually. Run `bin/console apidocbundle:doc:generate-builders` to update it.';
        $markdown[] = '';
        $markdown = array_merge($markdown, $toc);
        $markdown[] = '';
        $markdown[] = '---';
        $markdown[] = '';
        $markdown = array_merge($markdown, $builderDocs);

        $filesystem = new Filesystem();
        $filesystem->dumpFile($outputFile, implode("\n", $markdown));

        $output->writeln('<info>Builder documentation generated successfully at ' . $outputFile . '</info>');

        return Command::SUCCESS;
    }

    private function buildMethodDoc(ReflectionMethod $method): string
    {
        $doc = '### `->' . $method->getName() . '()`';
        $doc .= "\n\n";
        $doc .= $this->getCleanedDocComment($method->getDocComment());
        $doc .= "\n";

        $params = $method->getParameters();
        if (!empty($params)) {
            $doc .= "**Parameters:**\n\n";
            foreach ($params as $param) {
                $type = $this->getTypeName($param->getType());
                $doc .= '- `$' . $param->getName() . '` (`' . $type . '`)';
                if ($param->isOptional()) {
                    $default = $param->getDefaultValue();
                    $doc .= ' (optional, default: `' . var_export($default, true) . '`)';
                }
                $doc .= "\n";
            }
        }

        $returnType = $this->getTypeName($method->getReturnType());
        if ('self' === $returnType) {
            $returnType = 'Builder instance';
        }
        $doc .= "\n**Returns:** `" . $returnType . "`\n\n---\n";

        return $doc;
    }

    private function getTypeName(\ReflectionType|null $type): string
    {
        if (null === $type) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map([$this, 'getTypeName'], $type->getTypes()));
        }

        if ($type instanceof ReflectionIntersectionType) {
            return implode('&', array_map([$this, 'getTypeName'], $type->getTypes()));
        }

        return 'unknown';
    }

    private function getCleanedDocComment(string|false $docComment): string
    {
        if (false === $docComment) {
            return '';
        }
        $lines = explode("\n", $docComment);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, '/* ');
            $line = rtrim($line, ' */');
            if (str_starts_with($line, '@')) {
                continue;
            }
            if (!empty($line)) {
                $cleanedLines[] = $line;
            }
        }

        return implode(' ', $cleanedLines);
    }
}
