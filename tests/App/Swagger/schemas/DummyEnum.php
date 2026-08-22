<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\schemas;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'DummyEnum', type: 'schemas')]
class DummyEnum implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addSchema('DummyEnum')
            ->type('object')
            ->addProperty('name')
                ->type('string')
                ->required()
            ->end()
            ->addProperty('value')
                ->type('string')
                ->required()
            ->end()
        ->end()
        ;
    }
}
