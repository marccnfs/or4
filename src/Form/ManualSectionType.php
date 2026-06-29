<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManualSection;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManualSectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Laissez vide pour générer le slug automatiquement depuis le titre.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icône',
                'required' => false,
                'help' => 'Nom, emoji ou classe CSS affichable côté public.',
            ])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Rubrique publiée',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ManualSection::class]);
    }
}
