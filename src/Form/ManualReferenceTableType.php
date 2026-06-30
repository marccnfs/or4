<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ManualPage;
use App\Entity\ManualReferenceTable;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ManualReferenceTableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('slug', TextType::class, ['label' => 'Slug', 'required' => false, 'help' => 'Laissez vide pour générer le slug.'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('page', EntityType::class, [
                'label' => 'Page liée', 'class' => ManualPage::class, 'choice_label' => 'title', 'required' => false, 'placeholder' => 'Aucune page',
                'query_builder' => fn ($repo) => $repo->createQueryBuilder('page')->andWhere('page.type IN (:types)')->setParameter('types', [ManualPage::TYPE_REFERENTIEL, ManualPage::TYPE_NAMING_RULE])->orderBy('page.title', 'ASC'),
                'help' => 'Seules les pages de type référentiel ou règle de nommage sont proposées.',
            ])
            ->add('columnsJson', TextareaType::class, ['label' => 'Colonnes (JSON)', 'mapped' => false, 'attr' => ['rows' => 8], 'help' => 'Exemple : [{"key":"code","label":"Code"},{"key":"libelle","label":"Libellé"}]'])
            ->add('status', ChoiceType::class, ['label' => 'Statut', 'choices' => ['Brouillon' => ManualReferenceTable::STATUS_DRAFT, 'Publié' => ManualReferenceTable::STATUS_PUBLISHED, 'Archivé' => ManualReferenceTable::STATUS_ARCHIVED]])
            ->add('position', IntegerType::class, ['label' => 'Position']);
    }

    public function configureOptions(OptionsResolver $resolver): void { $resolver->setDefaults(['data_class' => ManualReferenceTable::class]); }
}
