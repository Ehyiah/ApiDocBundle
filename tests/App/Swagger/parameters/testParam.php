<?php

namespace Ehyiah\ApiDocBundle\Tests\App\Swagger\parameters;

use Ehyiah\ApiDocBundle\Attributes\ApiDocConfig;
use Ehyiah\ApiDocBundle\Builder\ApiDocBuilder;
use Ehyiah\ApiDocBundle\Interfaces\ApiDocConfigInterface;

#[ApiDocConfig(component: 'testParam', type: 'parameters')]
class testParam implements ApiDocConfigInterface
{
    public function configure(ApiDocBuilder $builder): void
    {
        $builder->addParameter('testParam')
            ->in('cookie')
            ->description('')
            ->schema(['type' => 'string'])
        ->end()
        ;
    }
}
