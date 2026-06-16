<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InformationSheetWorkspace;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class InformationSheetWorkspaceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de votre dossier',
            ])
            ->add('personalNotes', TextareaType::class, [
                'label' => 'Commentaires / notes personnelles',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => 'Vos observations, décisions, synthèses...'],
            ])
            ->add('questions', TextareaType::class, [
                'label' => 'Questions à poser au modérateur / professeur',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Les points à éclaircir...'],
            ])
            ->add('additionalElements', TextareaType::class, [
                'label' => 'Éléments ajoutés',
                'required' => false,
                'attr' => ['rows' => 5, 'placeholder' => 'Liens, ressources, exemples, actions à mener...'],
            ])
            ->add('attachments', FileType::class, [
                'label' => 'Images à téléverser',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        new File(
                            maxSize: '8M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                            mimeTypesMessage: 'Merci de téléverser uniquement des images.'
                        ),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InformationSheetWorkspace::class]);
    }
}
