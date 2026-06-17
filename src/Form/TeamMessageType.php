<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\TeamMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class TeamMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, ['label' => 'Message', 'required' => false, 'attr' => ['rows' => 1, 'placeholder' => 'Message au groupe...']])
            ->add('image', FileType::class, ['label' => 'Photo', 'mapped' => false, 'required' => false, 'constraints' => [new File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], mimeTypesMessage: 'Ajoutez une image JPG, PNG, WebP ou GIF.')]]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => TeamMessage::class]); }
}
