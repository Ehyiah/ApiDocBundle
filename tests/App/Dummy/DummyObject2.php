<?php

namespace Ehyiah\ApiDocBundle\Tests\Dummy;

use DateTime;

class DummyObject2
{
    public string $stringNotNullable;
    public bool $booleanNotNullable;
    public ?bool $booleanNullable = null;
    public ?string $stringNullable = null;
    public ?DateTime $datetimeNullable = null;
    public DateTime $datetimeNotNullable;
    public DummyEnum $enumNotNullable;
    public ?DummyEnum $enumNullable = null;
}
