<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ManualPage;
use App\Entity\ManualSection;
use App\Repository\ManualPageRepository;
use App\Repository\ManualSectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fixtures:manual-demo',
    description: 'Load demo sections and pages for the SI manual without deleting existing data.',
)]
class LoadManualDemoFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManualSectionRepository $sectionRepository,
        private readonly ManualPageRepository $pageRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $sections = [];
        $createdSections = 0;
        $updatedSections = 0;
        $createdPages = 0;
        $updatedPages = 0;

        foreach ($this->sectionFixtures() as $sectionData) {
            $section = $this->sectionRepository->findOneBy(['slug' => $sectionData['slug']]);

            if (!$section instanceof ManualSection) {
                $section = new ManualSection();
                $section->setCreatedAt($now);
                ++$createdSections;
            } else {
                $section->setUpdatedAt($now);
                ++$updatedSections;
            }

            $section
                ->setTitle($sectionData['title'])
                ->setSlug($sectionData['slug'])
                ->setDescription($sectionData['description'])
                ->setIcon($sectionData['icon'])
                ->setPosition($sectionData['position'])
                ->setIsPublished(true);

            $this->entityManager->persist($section);
            $sections[$sectionData['slug']] = $section;
        }

        $this->entityManager->flush();

        foreach ($this->pageFixtures() as $pageData) {
            $section = $sections[$pageData['sectionSlug']] ?? null;

            if (!$section instanceof ManualSection) {
                throw new \RuntimeException(sprintf('Unknown manual section "%s".', $pageData['sectionSlug']));
            }

            $page = $this->pageRepository->findOneBy([
                'section' => $section,
                'slug' => $pageData['slug'],
            ]);

            if (!$page instanceof ManualPage) {
                $page = new ManualPage();
                $page->setCreatedAt($now);
                ++$createdPages;
            } else {
                $page->setUpdatedAt($now);
                ++$updatedPages;
            }

            $page
                ->setSection($section)
                ->setTitle($pageData['title'])
                ->setSlug($pageData['slug'])
                ->setSummary($pageData['summary'])
                ->setType($pageData['type'])
                ->setContentMarkdown($pageData['contentMarkdown'])
                ->setTags($pageData['tags'])
                ->setPosition($pageData['position'])
                ->setStatus(ManualPage::STATUS_PUBLISHED)
                ->setPublishedAt($now)
                ->setReviewedAt($now);

            $this->entityManager->persist($page);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Manual demo fixtures loaded: %d section(s) created, %d section(s) updated, %d page(s) created, %d page(s) updated.',
            $createdSections,
            $updatedSections,
            $createdPages,
            $updatedPages,
        ));

        return Command::SUCCESS;
    }

    /** @return array<int, array{title: string, slug: string, description: string, icon: string, position: int}> */
    private function sectionFixtures(): array
    {
        return [
            ['title' => 'Référentiel technique', 'slug' => 'referentiel-technique', 'description' => 'Socle documentaire des standards, codes et règles techniques de la commune.', 'icon' => 'bi-diagram-3', 'position' => 10],
            ['title' => 'Matériel informatique', 'slug' => 'materiel-informatique', 'description' => 'Inventaire type, cycle de vie et règles de gestion des postes, écrans et périphériques.', 'icon' => 'bi-pc-display', 'position' => 20],
            ['title' => 'Comptes et accès', 'slug' => 'comptes-et-acces', 'description' => 'Procédures de création, modification et retrait des comptes utilisateurs.', 'icon' => 'bi-person-badge', 'position' => 30],
            ['title' => 'Microsoft 365', 'slug' => 'microsoft-365', 'description' => 'Bonnes pratiques pour Teams, SharePoint, Exchange Online et les groupes collaboratifs.', 'icon' => 'bi-cloud', 'position' => 40],
            ['title' => 'Sécurité', 'slug' => 'securite', 'description' => 'Consignes de protection des comptes, données et équipements municipaux.', 'icon' => 'bi-shield-lock', 'position' => 50],
            ['title' => 'Procédures internes', 'slug' => 'procedures-internes', 'description' => 'Modes opératoires récurrents pour les demandes SI et l’assistance aux agents.', 'icon' => 'bi-list-check', 'position' => 60],
            ['title' => 'Règles de nommage', 'slug' => 'regles-de-nommage', 'description' => 'Conventions de nommage applicables aux objets techniques et collaboratifs.', 'icon' => 'bi-tags', 'position' => 70],
            ['title' => 'Cycle de vie des équipements', 'slug' => 'cycle-de-vie-des-equipements', 'description' => 'Étapes de préparation, affectation, maintenance, renouvellement et réforme.', 'icon' => 'bi-arrow-repeat', 'position' => 80],
        ];
    }

    /** @return array<int, array{sectionSlug: string, title: string, slug: string, summary: string, type: string, tags: string[], position: int, contentMarkdown: string}> */
    private function pageFixtures(): array
    {
        return [
            [
                'sectionSlug' => 'regles-de-nommage',
                'title' => 'Convention de nommage des postes informatiques',
                'slug' => 'convention-nommage-postes-informatiques',
                'summary' => 'Format recommandé pour identifier les postes fixes, portables et postes partagés.',
                'type' => ManualPage::TYPE_NAMING_RULE,
                'tags' => ['naming', 'poste', 'inventaire'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## Format attendu

Utiliser le format `PC-[SITE]-[SERVICE]-[NUMERO]` pour les postes fixes et `LT-[SITE]-[SERVICE]-[NUMERO]` pour les portables.

## Exemples

- `PC-MAI-ACC-001` : poste fixe de l’accueil mairie.
- `LT-CTM-TEC-004` : portable du centre technique municipal.
- `PC-MED-PUB-002` : poste public de la médiathèque.

## Règles

- Le numéro est unique par site et par type d’équipement.
- Le nom est attribué avant l’intégration au domaine.
- Tout changement de service doit être tracé dans l’inventaire.
MD,
            ],
            [
                'sectionSlug' => 'regles-de-nommage',
                'title' => 'Convention de nommage des imprimantes',
                'slug' => 'convention-nommage-imprimantes',
                'summary' => 'Règles de nommage pour les imprimantes réseau et files d’impression.',
                'type' => ManualPage::TYPE_NAMING_RULE,
                'tags' => ['naming', 'imprimante', 'reseau'],
                'position' => 20,
                'contentMarkdown' => <<<'MD'
## Format attendu

Utiliser `PRN-[SITE]-[ETAGE]-[USAGE]` pour le périphérique et la file d’impression associée.

## Exemples

- `PRN-MAI-RDC-ACCUEIL`
- `PRN-ECO-1ER-SALLEPROFS`
- `PRN-CTM-RDC-ATELIER`

## Points de contrôle

- Renseigner l’adresse IP réservée dans le référentiel réseau.
- Documenter le modèle, le numéro de série et le contrat de maintenance.
- Supprimer les anciennes files après remplacement.
MD,
            ],
            [
                'sectionSlug' => 'regles-de-nommage',
                'title' => 'Convention de nommage des groupes Active Directory',
                'slug' => 'convention-nommage-groupes-active-directory',
                'summary' => 'Structure des groupes AD pour droits applicatifs, partages et listes de diffusion.',
                'type' => ManualPage::TYPE_NAMING_RULE,
                'tags' => ['active-directory', 'groupes', 'droits'],
                'position' => 30,
                'contentMarkdown' => <<<'MD'
## Préfixes

- `GG_` : groupe global de population.
- `DL_` : groupe local de domaine pour une ressource.
- `M365_` : groupe synchronisé ou lié à un espace collaboratif.

## Exemples

- `GG_FINANCES_AGENTS`
- `DL_PARTAGE_RH_LECTURE`
- `DL_PARTAGE_RH_MODIFICATION`

## Bonnes pratiques

- Ne jamais donner un droit directement à un utilisateur.
- Associer un propriétaire métier à chaque groupe sensible.
- Réviser les membres au moins une fois par an.
MD,
            ],
            [
                'sectionSlug' => 'cycle-de-vie-des-equipements',
                'title' => 'Cycle de vie d’un poste informatique',
                'slug' => 'cycle-de-vie-poste-informatique',
                'summary' => 'Étapes de gestion d’un poste depuis l’achat jusqu’à la réforme.',
                'type' => ManualPage::TYPE_PROCEDURE,
                'tags' => ['poste', 'cycle-de-vie', 'inventaire'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## 1. Acquisition

Le poste est commandé selon le catalogue validé par le service SI. Dès réception, il est enregistré dans l’inventaire avec son numéro de série et sa garantie.

## 2. Préparation

- Installation de l’image standard.
- Chiffrement du disque si applicable.
- Ajout au domaine et application des stratégies de groupe.
- Installation des logiciels nécessaires au service demandeur.

## 3. Affectation

La remise à l’agent est tracée avec le site, le service et les accessoires fournis.

## 4. Maintenance et renouvellement

Un diagnostic est réalisé avant tout remplacement. La durée cible d’usage est de cinq ans pour un poste fixe et quatre ans pour un portable.

## 5. Réforme

Les données sont effacées, le matériel est sorti de l’inventaire et orienté vers la filière de recyclage agréée.
MD,
            ],
            [
                'sectionSlug' => 'procedures-internes',
                'title' => 'Création d’un nouvel agent',
                'slug' => 'creation-nouvel-agent',
                'summary' => 'Checklist SI à suivre lors de l’arrivée d’un agent municipal.',
                'type' => ManualPage::TYPE_CHECKLIST,
                'tags' => ['arrivee', 'agent', 'onboarding'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## Informations nécessaires

- Nom, prénom et service d’affectation.
- Date d’arrivée et durée du contrat si connue.
- Responsable hiérarchique.
- Applications métier et partages requis.

## Checklist SI

- Créer le compte Active Directory.
- Créer ou synchroniser la boîte Microsoft 365.
- Ajouter les groupes de sécurité validés par le responsable.
- Préparer le poste et les accessoires.
- Transmettre les consignes de première connexion.

## Validation

Le ticket d’arrivée est clôturé après confirmation du responsable et première connexion réussie de l’agent.
MD,
            ],
            [
                'sectionSlug' => 'securite',
                'title' => 'Bonnes pratiques de sécurité des comptes',
                'slug' => 'bonnes-pratiques-securite-comptes',
                'summary' => 'Rappels essentiels pour limiter les risques liés aux comptes utilisateurs.',
                'type' => ManualPage::TYPE_MEMO,
                'tags' => ['securite', 'compte', 'mot-de-passe'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## Principes

- Utiliser un mot de passe unique pour les services municipaux.
- Activer l’authentification multifacteur lorsqu’elle est disponible.
- Verrouiller sa session en quittant son poste.
- Ne jamais partager son compte avec un collègue ou un prestataire.

## Signalement

Toute suspicion de phishing, perte de matériel ou connexion inhabituelle doit être signalée immédiatement au service SI.

## Comptes à privilèges

Les comptes administrateurs sont nominatifs, dédiés aux tâches d’administration et ne doivent pas servir à la messagerie quotidienne.
MD,
            ],
            [
                'sectionSlug' => 'microsoft-365',
                'title' => 'Organisation Teams et SharePoint',
                'slug' => 'organisation-teams-sharepoint',
                'summary' => 'Modèle simple pour structurer les équipes Teams et les sites SharePoint.',
                'type' => ManualPage::TYPE_PAGE,
                'tags' => ['microsoft-365', 'teams', 'sharepoint'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## Organisation cible

Une équipe Teams correspond à un service, un projet transversal ou une instance de gouvernance. Les documents pérennes sont classés dans SharePoint avec des bibliothèques par usage.

## Règles de création

- Définir au moins deux propriétaires.
- Nommer l’équipe avec un intitulé explicite : `Service - Finances` ou `Projet - Budget participatif`.
- Limiter les canaux privés aux besoins justifiés.
- Archiver les équipes inactives après validation métier.

## Partage externe

Le partage externe doit être limité dans le temps et validé par le responsable de service.
MD,
            ],
            [
                'sectionSlug' => 'referentiel-technique',
                'title' => 'Référentiel des codes services',
                'slug' => 'referentiel-codes-services',
                'summary' => 'Codes courts utilisés dans les noms d’équipements, groupes et ressources.',
                'type' => ManualPage::TYPE_REFERENTIEL,
                'tags' => ['referentiel', 'services', 'codes'],
                'position' => 10,
                'contentMarkdown' => <<<'MD'
## Codes services principaux

| Code | Service |
| --- | --- |
| ACC | Accueil et état civil |
| FIN | Finances |
| RH | Ressources humaines |
| URB | Urbanisme |
| TEC | Services techniques |
| MED | Médiathèque |
| ECO | Écoles |
| POL | Police municipale |

## Usage

Ces codes sont utilisés dans les noms de postes, groupes Active Directory, files d’impression et rapports d’inventaire. Toute création d’un nouveau code doit être validée par le service SI.
MD,
            ],
        ];
    }
}
