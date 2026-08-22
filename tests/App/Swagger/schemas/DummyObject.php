<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\schemas;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'DummyObject', type: 'schemas')]
class DummyObject implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addSchema('DummyObject')
            ->type('object')
            ->addProperty('id')
                ->type('string')
                ->required()
            ->end()
            ->addProperty('skipedValue')
                ->type('string')
                ->required()
            ->end()
            ->addProperty('stringNotNullable')
                ->type('string')
                ->required()
            ->end()
            ->addProperty('intNotNullable')
                ->type('integer')
                ->required()
            ->end()
            ->addProperty('booleanNotNullable')
                ->type('boolean')
                ->required()
            ->end()
            ->addProperty('booleanNullable')
                ->type('boolean')
            ->end()
            ->addProperty('stringNullable')
                ->type('string')
            ->end()
            ->addProperty('datetimeNullable')
                ->type('string')
                ->format('date-time')
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
            ->end()
            ->addProperty('objectNotNullable')
                ->ref('#/components/schemas/DummyObject2')
                ->required()
            ->end()
            ->addProperty('objectNullable')
                ->ref('#/components/schemas/DummyObject2')
            ->end()
            ->addProperty('arrayString')
                ->type('array')
                ->items(['type' => 'string'])
            ->end()
            ->addProperty('arrayInteger')
                ->type('array')
                ->items(['type' => 'string'])
            ->end()
            ->addProperty('collectionOfDummyObject2')
                ->type('array')
                ->items(['$ref' => '#/components/schemas/Collection'])
                ->required()
            ->end()
        ->end()
        ;
    }
}
