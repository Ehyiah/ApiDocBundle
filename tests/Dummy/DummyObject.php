<?php

namespace Ehyiah\ApiDocBundle\Tests\Dummy;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class DummyObject
{
    public string $id;
    public string $skipedValue;
    public string $stringNotNullable;
    public int $intNotNullable;
    public bool $booleanNotNullable;
    public ?bool $booleanNullable = null;
    public ?string $stringNullable = null;
    public ?DateTime $datetimeNullable = null;
    public DateTime $datetimeNotNullable;
    public DummyEnum $enumNotNullable;
    public ?DummyEnum $enumNullable = null;
    public DummyObject2 $objectNotNullable;
    public ?DummyObject2 $objectNullable = null;
    /**
     * @var Collection<DummyObject2>
     */
    public Collection $collectionOfDummyObject2;

    public function __construct()
    {
        $this->collectionOfDummyObject2 = new ArrayCollection();
    }
}
