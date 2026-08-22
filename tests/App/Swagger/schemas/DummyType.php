<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\schemas;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'DummyType', type: 'schemas')]
class DummyType implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addSchema('DummyType')
            ->type('object')
            ->addProperty('parent')
                ->type('string')
                ->nullable()
            ->end()
        ->end()
        ;
    }
}
