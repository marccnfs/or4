# Escape-Team – Plan de développement (référence Codex)

Ce document sert de référence centrale pour les améliorations et la suite du développement du projet **Escape-Team**. Il décrit le périmètre fonctionnel, l’état du repo, les axes techniques, et une feuille de route priorisée.

## Objectifs
- Construire un jeu en ligne **mobile-first** basé sur des étapes successives (A–F) et une finale.
- Permettre l’administration complète par un **compte admin** (création, pilotage, reset).
- Visualiser la **progression des équipes** sur un écran de projection.
- Exploiter **Stimulus** pour la logique front (progression, étapes, score).

## État actuel du repo (constats)
- Projet Symfony avec Doctrine configuré (PostgreSQL attendu via `DATABASE_URL`).
- Sécurité minimale : provider mémoire, pas d’entité utilisateur persistée.
- Stimulus présent, mais seulement un controller de démo (`assets/controllers/hello_controller.js`).
- Aucun modèle métier (entités) dédié à Escape-Team.

## Périmètre fonctionnel
### Parcours joueur (public)
1. **Inscription d’équipe**
    - Entrée par **code** et/ou **QR code**.
    - Limite **max 10 équipes** par session.
2. **Salle d’attente**
    - Attente du lancement du jeu par l’admin.
3. **Jeu : 6 étapes + finale**
    - A–D : saisir une lettre (si correcte → étape validée).
   - E : séquence de **5 QR codes** (unique par équipe). Les 4 premiers affichent un message, le dernier révèle une lettre.
   - F : les lettres des étapes A–E forment la combinaison d’un cryptex numérique pour révéler un message.
4. **Page finale**
    - Affiche victoire + score.

### Parcours admin (backoffice)
- **Création** d’un Escape-Team + lettres des étapes 1 à 5 + configuration des QR (8 équipes × 5 QR) + message du cryptex.
- **Liste** des Escape-Team.
- **Pilotage** : ouverture inscription, démarrage, arrêt, réinitialisation, édition, suppression.

### Affichage public (grand écran)
- Page de **progression des équipes** avec scores et étapes.

## Modèle de données (cible)
Entités proposées :
- `EscapeGame` : nom, statut, timestamps, configuration.
- `Team` : nom, code d’inscription, qr_token, score, statut.
- `Step` : type (A–F), solution, ordre.
- `TeamStepProgress` : team, step, état, timestamps, lettre validée.
- `TeamQrSequence` : team, ordre, qr_code, indice, validé.

Contraintes clés :
- Max 10 équipes par `EscapeGame`.
- Code d’inscription unique par équipe.
- Séquence QR unique par équipe (5 QR par équipe).
- Sélecteur de message parmi 9 lieux pour les 4 premiers QR.

## Architecture technique
### Backend (Symfony)
- **Controllers**
    - Public : `GameController` (inscription, attente, étapes, finale).
    - Admin : `AdminEscapeController` (CRUD + pilotage).
    - Scoreboard : `ScoreboardController` (projection).
- **API JSON**
    - `POST /api/step/validate`
    - `POST /api/qr/scan`
    - `POST /api/final/check`
    - `GET /scoreboard/data`
- **Sécurité**
    - Provider Doctrine avec entité `User`.
    - `/admin/*` protégé par `ROLE_ADMIN`.

### Frontend (Stimulus)
- `game_controller.js` : validation étapes A–D.
- `qr_controller.js` : séquence QR + indices.
- `final_controller.js` : validation combinaison finale.
- `scoreboard_controller.js` : refresh périodique (polling).

### Templates (Twig)
- `templates/game/*` : pages publiques (mobile-first).
- `templates/admin/escape/*` : backoffice.
- `templates/scoreboard/index.html.twig` : grand écran.

## Feuille de route (priorisée)
### Phase 1 — Socle admin + sécurité
- Créer l’entité `User` et configurer le provider Doctrine.
- Ajouter login + protection `/admin`.
- Créer la base de données (migrations).

### Phase 2 — Modèle métier + endpoints
- Implémenter les entités Escape-Team.
- Ajouter endpoints JSON pour validation des étapes.
- Mettre en place le scoring et les états.

### Phase 3 — UI publique + Stimulus
- Construire les pages mobiles publiques (inscription → finale).
- Ajouter la logique Stimulus (validation, progression, QR).

### Phase 4 — Backoffice complet
- CRUD Escape-Team + pilotage (ouvrir, lancer, stop, reset).
- Gestion des solutions (lettres, QR sequence).

### Phase 5 — Scoreboard
- Page grand écran + API de progression.
- Rafraîchissement automatique.

## Règles UX / mobile
- Mobile-first, navigation simple (1 action principale par écran).
- Limiter la saisie (auto-focus, gros boutons).
- QR : prévoir fallback manuel (code texte) si caméra indisponible.

## Tests (cible)
- Tests unitaires de validation (lettres, QR, combinaison finale).
- Tests d’intégration (inscription, progression, reset).

## Notes techniques
- La configuration Doctrine est déjà prête pour PostgreSQL.
- Le frontend Stimulus est déjà câblé via `assets/controllers`.

---

**Référence Codex** : ce document doit être mis à jour à chaque évolution majeure.