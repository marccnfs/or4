<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, ['label' => 'Email du membre à inviter', 'attr' => ['placeholder' => 'membre@exemple.fr']]);
    }
    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['csrf_token_id' => 'team_invitation']); }
}
