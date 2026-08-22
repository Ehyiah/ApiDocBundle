<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\schemas;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'DummyObject2', type: 'schemas')]
class DummyObject2 implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addSchema('DummyObject2')
            ->type('object')
            ->addProperty('stringNotNullable')
                ->type('string')
                ->required()
            ->end()
            ->addProperty('booleanNotNullable')
                ->type('boolean')
                ->required()
            ->end()
            ->addProperty('booleanNullable')
                ->type('boolean')
                ->nullable()
            ->end()
            ->addProperty('stringNullable')
                ->type('string')
                ->nullable()
            ->end()
            ->addProperty('datetimeNullable')
                ->type('string')
                ->format('date-time')
                ->nullable()
            ->end()
            ->addProperty('datetimeNotNullable')
                ->type('string')
                ->format('date-time')
                ->required()
            ->end()
            ->addProperty('enumNotNullable')
                ->type('string')
                ->enum(['enum_value_1', 'enum_value_2'])
                ->required()
            ->end()
            ->addProperty('enumNullable')
                ->type('string')
                ->enum(['enum_value_1', 'enum_value_2'])
                ->nullable()
            ->end()
        ->end()
        ;
    }
}
