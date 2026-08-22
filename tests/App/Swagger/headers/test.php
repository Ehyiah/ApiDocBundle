<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\headers;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'test', type: 'headers')]
class test implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addHeader('test')
            ->description('test header')
            ->schema(['type' => 'string'])
        ->end()
        ;
    }
}
