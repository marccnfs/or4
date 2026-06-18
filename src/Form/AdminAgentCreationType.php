<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AdminAgentCreationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('displayName', TextType::class, [
                'label' => 'Nom affiché',
                'required' => false,
                'attr' => ['maxlength' => 120, 'placeholder' => 'Prénom Nom ou pseudonyme'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email agent',
                'constraints' => [new NotBlank(message: 'Renseignez un email agent.')],
                'attr' => ['placeholder' => 'prenom.nom@domaine.fr'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
