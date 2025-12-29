<?php

namespace Ehyiah\ApiDocBundle\Tests\Dummy;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;

final class DummyNumberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subNumberField', NumberType::class)
        ;
    }
}
