<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManualReferenceRow;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManualReferenceRowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dataJson', TextareaType::class, ['label' => 'Données (JSON)', 'mapped' => false, 'attr' => ['rows' => 8], 'help' => 'Les clés doivent correspondre aux colonnes du référentiel.'])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('isActive', CheckboxType::class, ['label' => 'Ligne active', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ManualReferenceRow::class]); }
}
