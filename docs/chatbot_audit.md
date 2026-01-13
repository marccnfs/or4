# Audit – branche chabot/python

## Périmètre audité
- Backend Symfony (contrôleurs API + service spaCy).
- Frontend (pages Twig + scripts D3 chargés via Encore).
- Microservice Python spaCy (Flask) et données JSON associées.

## Fonctionnalités identifiées
### Accès UI
- **Accueil** : page publique `templates/public/index.html.twig` avec CTA et méta infos.
- **Chatbot principal + animations** : route `/ia/chat` (`Or4publicController::cluster`) rend `templates/newchat.html.twig`.
- **Autres vues** : `/ia/chat/api` (vue `templates/chat.html.twig`) et `/ia/chat/chatpotin` (vue `templates/chatpotin.html.twig`).

### Fonctionnement applicatif (Symfony)
- **Analyse NLP principale** : `/api/analyze` (`ChabotApiController::analyzeQuestion`) renvoie mots-clés, relations et réponse.
- **Analyse contextuelle** : `/api/analyze_context` (`ChabotApiController::analyzContext`) renvoie mots-clés, entités, intent, contexte et réponse.
- **Glossaire** : `/api/glossary` (`ChabotApiController::fetchGlossary`) renvoie la définition d’un terme.
- **Relations entre mots-clés** : `/api/relationships` (`ChabotApiController::calculateRelationships`).
- **Exploration** : `/api/explore_clusters` et `/api/statistics` pour les visualisations.
- **Fallback OpenAI** : `/api/chat` pour reformulation et interrogation de l’API OpenAI (si pas de réponse locale).

### Fonctionnement microservice spaCy (Python)
- Service Flask (ex. `Python_or4/spacy_service_v3.py`) expose :
    - `/analyze_combined` (mots-clés + intent + réponse)
    - `/analyze_context` (contexte + intent + entités)
    - `/extract_keywords` et `/calculate_relationships`
    - `/glossary`, `/statistics`, `/explore_clusters`
- Modèle spaCy FR et TF‑IDF (scikit-learn) pour les extractions.
- Données locales : `public/base/intents_and_questions.json`, `public/base/intents_and_responses.json`, `public/base/knowledge_base.json`.

## Logique d’animation et d’UI
- **Visualisations D3** (via `assets/js/d3.js` et variantes) :
    - génération de graphes à partir des mots‑clés et des relations pondérées,
    - affichage des clusters et des statistiques,
    - interaction (clic sur mots‑clés → glossaire, relance d’analyse).
- **Interface chat** intégrée dans `templates/newchat.html.twig` (onglets + zones d’animation).

## Technologies et dépendances
- **Backend** : Symfony, HttpClient + Guzzle, Twig.
- **Frontend** : D3.js (v7), Webpack Encore, CSS custom.
- **NLP** : Flask, spaCy (fr_core_news_sm), scikit‑learn (TF‑IDF), numpy.
- **Sources de connaissance** : JSON locaux + option OpenAI.

## Correctifs appliqués pour production
- Correction des signatures `JsonResponse` et suppression d’arguments invalides.
- Correction de la reformulation de la question pour OpenAI (utilise la saisie utilisateur).
- Suppression des `dump()` en production dans le service spaCy.

## Points d’attention pour mise en production
- **Service spaCy requis** : le backend Symfony dépend de l’API Flask locale `http://localhost:5000`.
- **Clé OpenAI** : requise pour `/api/chat` via `OPENAI_API_KEY`.
- **Données JSON** : vérification de la présence et de la structure des fichiers dans `public/base/`.
- **Monitoring** : prévoir une supervision du microservice Python et des fichiers de données.