<?php

namespace Ehyiah\ApiDocBundle\Helper;

use BackedEnum;
use DateTimeInterface;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

class SchemaHelper
{
    /**
     * @return array<mixed>
     */
    public static function createComponentArray(): array
    {
        return [
            'documentation' => [
                'components' => [],
            ],
        ];
    }

    /**
     * @param array<mixed> $schema
     */
    public static function addProperty(array &$schema, string $property, Type $type): void
    {
        $builtinType = self::getBuiltinTypeFromTypeInfo($type);
        if ('array' === $builtinType) {
            if ($type instanceof CollectionType) {
                $valueType = $type->getCollectionValueType();
                $itemClass = $valueType instanceof ObjectType ? $valueType->getClassName() : null;

                if (null !== $itemClass) {
                    $reflectionClass = new ReflectionClass($itemClass);
                    if (in_array(BackedEnum::class, $reflectionClass->getInterfaceNames())) {
                        self::handleEnum($schema, $reflectionClass, $property, 'array');

                        return;
                    }

                    $schema[$property]['items'] = ['$ref' => '#/components/schemas/' . $reflectionClass->getShortName()];
                }

                $schema[$property]['type'] = 'array';
                if (!isset($schema[$property]['items'])) {
                    $itemBuiltin = self::getBuiltinTypeFromTypeInfo($valueType);
                    if ('bool' === $itemBuiltin) {
                        $schema[$property]['items']['type'] = 'boolean';
                    } elseif ('int' === $itemBuiltin) {
                        $schema[$property]['items']['type'] = 'integer';
                    } else {
                        $schema[$property]['items']['type'] = $itemBuiltin;
                    }
                }

                return;
            }
            $schema[$property]['items']['type'] = 'string';
            $schema[$property]['type'] = 'array';

            return;
        }

        if ($type instanceof ObjectType) {
            $className = $type->getClassName();
            $reflectionClass = new ReflectionClass($className);
            $interfaces = $reflectionClass->getInterfaceNames();

            if (in_array(DateTimeInterface::class, $interfaces)) {
                $schema[$property]['type'] = 'string';
                $schema[$property]['format'] = 'date-time';

                return;
            }

            if (in_array(BackedEnum::class, $interfaces)) {
                self::handleEnum($schema, $reflectionClass, $property);

                return;
            }

            $schema[$property]['$ref'] = '#/components/schemas/' . $reflectionClass->getShortName();

            return;
        }

        if ('bool' === $builtinType) {
            $schema[$property]['type'] = 'boolean';
            $schema[$property]['description'] = '';

            return;
        }

        if ('int' === $builtinType) {
            $schema[$property]['type'] = 'integer';
            $schema[$property]['description'] = '';

            return;
        }

        $schema[$property]['type'] = $builtinType;
        $schema[$property]['description'] = '';
    }

    private static function getBuiltinTypeFromTypeInfo(Type $type): string
    {
        if ($type->isIdentifiedBy(TypeIdentifier::ARRAY)) {
            return 'array';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::OBJECT)) {
            return 'object';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::INT)) {
            return 'int';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::BOOL)) {
            return 'bool';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT)) {
            return 'float';
        }
        if ($type->isIdentifiedBy(TypeIdentifier::STRING)) {
            return 'string';
        }

        return 'string';
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
}
