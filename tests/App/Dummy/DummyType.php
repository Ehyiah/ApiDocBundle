<?php

namespace Ehyiah\ApiDocBundle\Tests\Dummy;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class DummyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('textField', TextType::class)
            ->add('NumberField', NumberType::class)
            ->add('integerField', IntegerType::class)
            ->add('dateField', DateType::class)
            ->add('datetimeField', DateTimeType::class)
            ->add('birthDayField', BirthdayType::class)
            ->add('choiceMultipleField', ChoiceType::class, [
                'choices' => ['choice-1', 'choice-2', 'choice-3'],
                'multiple' => true,
            ])
            ->add('choiceNotMultipleField', ChoiceType::class, [
                'choices' => ['choice-1', 'choice-2', 'choice-3'],
                'multiple' => false,
            ])
            ->add('collectionField', CollectionType::class, [
                'entry_type' => DummyNumberType::class,
            ])
        ;
    }
}
