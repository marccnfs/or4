<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\EscapeGame;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class EscapeGameType extends AbstractType
{
    private const QR_MESSAGE_CHOICES = [
        'WC Hommes' => 'WC Hommes',
        'Bar' => 'Bar',
        'Patio' => 'Patio',
        'Salle danse' => 'Salle danse',
        'Salle théâtre' => 'Salle théâtre',
        'Salle musique' => 'Salle musique',
        'Loges' => 'Loges',
        'Vestiaires' => 'Vestiaires',
        'Grande salle/pendrillons' => 'Grande salle/pendrillons',
    ];

    private const TOTAL_TEAMS = 8;

    private const QR_CODES_PER_TEAM = 5;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $escapeGame = $options['data'];
        $storedOptions = $escapeGame instanceof EscapeGame ? $escapeGame->getOptions() : [];
        $stepsByType = [];
        if ($escapeGame instanceof EscapeGame) {
            foreach ($escapeGame->getSteps() as $step) {
                $stepsByType[$step->getType()] = $step;
            }
        }

        $getOption = static function (array $data, array $path, mixed $default = null): mixed {
            $current = $data;
            foreach ($path as $key) {
                if (!is_array($current) || !array_key_exists($key, $current)) {
                    return $default;
                }
                $current = $current[$key];
            }

            return $current;
        };

        $letterConstraint = new Regex([
            'pattern' => '/^[A-Za-z]$/',
            'message' => 'Merci de saisir une seule lettre (A-Z).',
        ]);

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('team1Code', TextType::class, [
                'label' => 'Code équipe 1',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 1], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team2Code', TextType::class, [
                'label' => 'Code équipe 2',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 2], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team3Code', TextType::class, [
                'label' => 'Code équipe 3',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 3], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team4Code', TextType::class, [
                'label' => 'Code équipe 4',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 4], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team5Code', TextType::class, [
                'label' => 'Code équipe 5',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 5], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team6Code', TextType::class, [
                'label' => 'Code équipe 6',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 6], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team7Code', TextType::class, [
                'label' => 'Code équipe 7',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 7], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('team8Code', TextType::class, [
                'label' => 'Code équipe 8',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['team_codes', 8], ''),
                'constraints' => [new NotBlank()],
            ])
            ->add('step1Letter', TextType::class, [
                'label' => 'Étape 1 - Lettre attendue',
                'mapped' => false,
                'data' => $getOption(
                    $storedOptions,
                    ['steps', 'A', 'solution'],
                    ($stepsByType['A'] ?? null)?->getSolution() ?? ''
                ),
                'constraints' => [new NotBlank(), $letterConstraint],
                'attr' => [
                    'maxlength' => 1,
                ],
            ])
            ->add('step2Letter', TextType::class, [
                'label' => 'Étape 2 - Lettre attendue',
                'mapped' => false,
                'data' => $getOption(
                    $storedOptions,
                    ['steps', 'B', 'solution'],
                    ($stepsByType['B'] ?? null)?->getSolution() ?? ''
                ),
                'constraints' => [new NotBlank(), $letterConstraint],
                'attr' => [
                    'maxlength' => 1,
                ],
            ])
            ->add('step3Letter', TextType::class, [
                'label' => 'Étape 3 - Lettre attendue',
                'mapped' => false,
                'data' => $getOption(
                    $storedOptions,
                    ['steps', 'C', 'solution'],
                    ($stepsByType['C'] ?? null)?->getSolution() ?? ''
                ),
                'constraints' => [new NotBlank(), $letterConstraint],
                'attr' => [
                    'maxlength' => 1,
                ],
            ])
            ->add('step4Letter', TextType::class, [
                'label' => 'Étape 4 - Lettre attendue',
                'mapped' => false,
                'data' => $getOption(
                    $storedOptions,
                    ['steps', 'D', 'solution'],
                    ($stepsByType['D'] ?? null)?->getSolution() ?? ''
                ),
                'constraints' => [new NotBlank(), $letterConstraint],
                'attr' => [
                    'maxlength' => 1,
                ],
            ])
            ->add('step5Letter', TextType::class, [
                'label' => 'Étape 5 - Lettre finale QR',
                'mapped' => false,
                'data' => $getOption(
                    $storedOptions,
                    ['steps', 'E', 'letter'],
                    ($stepsByType['E'] ?? null)?->getLetter() ?? ''
                ),
                'constraints' => [new NotBlank(), $letterConstraint],
                'attr' => [
                    'maxlength' => 1,
                ],
            ])
            ->add('cryptexMessage', TextareaType::class, [
                'label' => 'Étape 6 - Message à révéler (cryptex)',
                'mapped' => false,
                'data' => $getOption($storedOptions, ['cryptex_message'], ''),
                'constraints' => [new NotBlank()],
            ]);

        for ($team = 1; $team <= self::TOTAL_TEAMS; $team++) {
            for ($index = 1; $index <= self::QR_CODES_PER_TEAM; $index++) {
                $codeField = sprintf('team%d_qr%d_code', $team, $index);
                $builder->add($codeField, TextType::class, [
                    'label' => sprintf('Équipe %d - QR %d (code)', $team, $index),
                    'mapped' => false,
                    'data' => $getOption($storedOptions, ['qr_sequences', 'teams', $team, $index - 1, 'code'], ''),
                    'constraints' => [new NotBlank()],
                ]);

                if ($index === self::QR_CODES_PER_TEAM) {
                    continue;
                }

                $messageField = sprintf('team%d_qr%d_message', $team, $index);
                $builder->add($messageField, ChoiceType::class, [
                    'label' => sprintf('Équipe %d - QR %d (message)', $team, $index),
                    'mapped' => false,
                    'choices' => self::QR_MESSAGE_CHOICES,
                    'placeholder' => 'Sélectionner un lieu',
                    'data' => $getOption($storedOptions, ['qr_sequences', 'teams', $team, $index - 1, 'message'], null),
                    'constraints' => [new NotBlank()],
                ]);
            }
        }

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EscapeGame::class,
        ]);
    }

}