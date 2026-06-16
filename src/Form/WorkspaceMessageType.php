<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InformationSheetWorkspaceMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WorkspaceMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => 'Message',
            'attr' => ['rows' => 4, 'placeholder' => 'Écrire un message dans la conversation...'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InformationSheetWorkspaceMessage::class]);
    }
}
