<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InformationSheet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class InformationSheetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Sous-titre',
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Rubrique',
                'choices' => array_flip(InformationSheet::CATEGORY_LABELS),
            ])
            ->add('thematic', TextType::class, [
                'label' => 'Thématique',
                'help' => 'Exemple : sécurité, démarches, IA, compte utilisateur...',
            ])
            ->add('contentMarkdown', TextareaType::class, [
                'label' => 'Texte de la fiche (Markdown)',
                'attr' => [
                    'rows' => 14,
                    'placeholder' => "## Objectif\n\nExpliquez la fiche ici...",
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image d’illustration',
                'mapped' => false,
                'required' => false,
                'help' => 'Formats acceptés : JPG, PNG, WebP, GIF. Taille maximale : 4 Mo.',
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif',
                        ],
                        'mimeTypesMessage' => 'Merci de téléverser une image valide (JPG, PNG, WebP ou GIF).',
                    ]),
                ],
            ])
            ->add('imageAlt', TextType::class, [
                'label' => 'Texte alternatif de l’image',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InformationSheet::class,
        ]);
    }
}
