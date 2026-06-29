<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManualPage;
use App\Entity\ManualSection;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManualPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('section', EntityType::class, [
                'label' => 'Rubrique',
                'class' => ManualSection::class,
                'choice_label' => 'title',
                'placeholder' => 'Choisir une rubrique',
            ])
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'help' => 'Laissez vide pour générer le slug automatiquement depuis le titre.',
            ])
            ->add('summary', TextType::class, ['label' => 'Résumé', 'required' => false])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de page',
                'choices' => [
                    'Page' => ManualPage::TYPE_PAGE,
                    'Procédure' => ManualPage::TYPE_PROCEDURE,
                    'Mémo' => ManualPage::TYPE_MEMO,
                    'Checklist' => ManualPage::TYPE_CHECKLIST,
                    'Référentiel' => ManualPage::TYPE_REFERENTIEL,
                    'Règle de nommage' => ManualPage::TYPE_NAMING_RULE,
                    'Fiche équipement' => ManualPage::TYPE_EQUIPMENT_SHEET,
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => ManualPage::STATUS_DRAFT,
                    'Publié' => ManualPage::STATUS_PUBLISHED,
                    'Archivé' => ManualPage::STATUS_ARCHIVED,
                ],
            ])
            ->add('position', IntegerType::class, ['label' => 'Position'])
            ->add('tagsText', TextType::class, [
                'label' => 'Tags',
                'mapped' => false,
                'required' => false,
                'help' => 'Séparez les tags par des virgules.',
            ])
            ->add('contentMarkdown', TextareaType::class, [
                'label' => 'Contenu Markdown',
                'attr' => ['rows' => 18],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ManualPage::class]);
    }
}
