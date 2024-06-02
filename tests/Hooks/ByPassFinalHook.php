<?php

namespace Ehyiah\ApiDocBundle\Tests\Hooks;

use DG\BypassFinals;
use PHPUnit\Runner\BeforeTestHook;

class ByPassFinalHook implements BeforeTestHook
{
    public function executeBeforeTest(string $test): void
    {
        BypassFinals::enable();
    }
}
