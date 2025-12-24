# python spacy_service.py
import spacy
import numpy as np
import random
import json
import os
import sklearn
import unidecode
from flask import Flask, request, jsonify
from spacy.lookups import Lookups
from sklearn.feature_extraction.text import TfidfVectorizer
from spacy.training.example import Example

app = Flask(__name__)

# Charger le modèle de langue français
nlp = spacy.load("fr_core_news_sm")
nlp.vocab["l"].is_stop = True

french_stop_words = list(spacy.blank('fr').Defaults.stop_words)
# Ajouter des mots supplémentaires
additional_stop_words = ['neuf', 'qu', 'quelqu']
french_stop_words.extend(additional_stop_words)

# Supprimer les doublons et normaliser la liste des stop words
french_stop_words = list(set(french_stop_words))
normalized_stop_words = [unidecode.unidecode(word.lower()) for word in french_stop_words]

lookups = Lookups()
lookups.add_table("lemma_lookup")
nlp.vocab.lookups = lookups

# Chemin vers le fichier JSON
DATA_FILE = os.path.join(os.path.dirname(__file__), '../public/base/intents_and_responses.json')
INTENTS_AND_QUESTIONS_FILE = "../public/base/intents_and_questions.json"
INTENTS_AND_RESPONSES_FILE = "../public/base/intents_and_responses.json"

def preprocess_text(text):
    doc = nlp(text)
    processed_text = ' '.join([token.text for token in doc if not token.is_stop])
    return processed_text if processed_text.strip() else None

def load_intents_and_questions():
    """
    Charge les données depuis intents_and_questions.json.
    """
    try:
        with open(INTENTS_AND_QUESTIONS_FILE, "r") as f:
            return json.load(f)
    except FileNotFoundError:
        return []  # Retourne une liste vide si le fichier n'existe pas
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {INTENTS_AND_QUESTIONS_FILE}: {e}")

def load_intents_and_responses():
    """
    Charge les données depuis intents_and_responses.json.
    """
    try:
        with open(INTENTS_AND_RESPONSES_FILE, "r") as f:
             data = json.load(f)

        # Vérifie que chaque intention a la clé "direct"
        for intent, words in data.get("intents", {}).items():
            if not (isinstance(words, dict) and "direct" in words) and not isinstance(words, list):
                raise ValueError(f"L'intention '{intent}' a un format incorrect. Attendu : dictionnaire avec clé 'direct' ou liste.")

        return data
    except FileNotFoundError:
        return {"intents": {}, "responses": {}}  # Structure par défaut si le fichier est manquant
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {INTENTS_AND_RESPONSES_FILE}: {e}")


@app.route('/train', methods=['POST'])
def train():

    # Charger les données d'entraînement
    with open(DATA_FILE, 'r') as f:
        training_data = json.load(f)

    # Préparer les exemples d'entraînement
    examples = []
    for item in training_data:
        doc = nlp.make_doc(item["text"])
        examples.append(Example.from_dict(doc, {"cats": {item["intent"]: 1.0}}))

    # Entraîner le modèle
    optimizer = nlp.initialize()
    for epoch in range(10):
        random.shuffle(examples)
        for example in examples:
            nlp.update([example], sgd=optimizer)

    return jsonify({"message": "Training completed"})

@app.route('/update-intent', methods=['POST'])
def update_intent():
    request_data = request.json
    question = request_data.get("text")
    new_intent = request_data.get("intent")

    if not question or not new_intent:
        return jsonify({"error": "Texte ou intention manquante."}), 400

    # Charger les données existantes
    intents_and_questions = load_intents_and_questions()

    # Trouver la question correspondante et mettre à jour l'intention
    for entry in data:
        if entry["text"] == question:
            entry["intent"] = new_intent
            break
    else:
        return jsonify({"error": "Question introuvable."}), 404

    # Sauvegarder les modifications
    with open(INTENTS_AND_QUESTIONS_FILE, "w") as f:
        json.dump(intents_and_questions, f, indent=4)

    return jsonify({"message": "Intention mise à jour avec succès."})



@app.route('/analyze_combined', methods=['POST'])
def analyze_question():
    user_message = request.json.get('message', '')
    """
    Analyse une question utilisateur et retourne les mots-clés et entités nommées.
    """
    # Charger les fichiers JSON
    intents_and_questions = load_intents_and_questions()
    intents_and_responses = load_intents_and_responses()
    # Construire le corpus à partir des questions connues
    corpus = [entry['text'] for entry in intents_and_questions]

    keywords = extract_keywords_combined(user_message, corpus)

    entities = extract_entities(user_message)
    detected_intent = None

 # Identifier une intention basée sur les mots-clés (simplifié ici)
    for intent, words in intents_and_responses["intents"].items():
        # Si "words" est un dictionnaire
        if isinstance(words, dict) and any(kw in words.get("direct", []) for kw in keywords):
            detected_intent = intent
            break
        # Si "words" est une liste
        elif isinstance(words, list) and any(kw in words for kw in keywords):
            detected_intent = intent
            break

    # Si aucune intention n'est détectée, enregistrer la question avec "unknown"
    if not detected_intent:
        new_entry = {"text": user_message, "intent": "unknown"}
        with open(INTENTS_AND_QUESTIONS_FILE, "r+") as f:
            data = json.load(f)
            data.append(new_entry)  # Ajouter la nouvelle question
            f.seek(0)
            json.dump(data, f, indent=4)
            f.truncate()

    response = intents_and_responses["responses"].get(
        detected_intent, "Je ne suis pas sûr de comprendre votre demande."
    )

    return jsonify({
        "response": response,
        "keywords": keywords,
        "entities": entities,
        "intent": detected_intent
    })


def extract_keywords_combined(text, corpus):
    """
    Combine spaCy et TF-IDF pour extraire les mots-clés les plus pertinents.
    """
    global nlp
    processed_text = preprocess_text(text)

    if processed_text is None or processed_text.strip() == "":
                return "Le texte ne contient pas assez d'informations après prétraitement.", 400

    # Étape 1 : Extraction de base avec spaCy
    doc = nlp(processed_text)
    keywords_spacy = [token.lemma_ for token in doc if token.pos_ in ["NOUN", "VERB", "PROPN"]]

    if not keywords_spacy:
        return "Le vocabulaire est vide, veuillez fournir un ensemble de mots à utiliser.", 400

    # Étape 2 : Appliquer TF-IDF pour pondérer les mots
    vectorizer = TfidfVectorizer(stop_words=normalized_stop_words, vocabulary=keywords_spacy)

    vectorizer.fit(corpus)  # Entraîne le modèle TF-IDF sur le corpus
    response_vector = vectorizer.transform([processed_text])

    scores = response_vector.toarray().flatten()
    feature_names = vectorizer.get_feature_names_out()

    # Associer chaque mot à son score
    keywords_with_scores = [(feature_names[i], scores[i]) for i in range(len(scores)) if scores[i] > 0]

    # Trier par score décroissant
    sorted_keywords = sorted(keywords_with_scores, key=lambda x: x[1], reverse=True)
    return [kw[0] for kw in sorted_keywords]  # Retourne uniquement les mots-clés triés



def extract_entities(text):
    """
    Extrait les entités nommées (dates, lieux, etc.) avec spaCy.
    """
    global nlp
    doc = nlp(text)
    entities = [(ent.text, ent.label_) for ent in doc.ents]
    return entities


@app.route("/calculate_relationships", methods=["POST"])
def calculate_relationships():
    keywords = request.json.get("keywords", [])

    if len(keywords) < 2:
        return jsonify({"relationships": []})  # Pas de relations possibles avec moins de 2 mots

    # Calcul des relations pondérées
    relationships = []
    for i in range(len(keywords)):
        for j in range(i + 1, len(keywords)):
            vec1 = nlp(keywords[i]).vector
            vec2 = nlp(keywords[j]).vector
            similarity = np.dot(vec1, vec2) / (np.linalg.norm(vec1) * np.linalg.norm(vec2))
            if similarity > 0.2:  # Seulement si la similarité est significative
                relationships.append({
                    "source": keywords[i],
                    "target": keywords[j],
                    "weight": float(round(similarity, 2))  # Limiter la précision à 2 décimales
                })

    return jsonify({"relationships": relationships})


API_KEY = "mdpOr4"

@app.route('/reload-data', methods=['POST'])
def reload_data():
    key = request.headers.get('Authorization')
    if key != API_KEY:
        return jsonify({"error": "Clé API invalide"}), 403

    global data
    try:
        data = load_data()
        return jsonify({"message": "Données rechargées avec succès."})
    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/analyze', methods=['POST'])
def analyze():
    user_message = request.json.get('message', '')

    # Assurer que data est correctement chargé
    global data
    if not data or 'intents' not in data or 'responses' not in data:
        return jsonify({"error": "Les données du chatbot ne sont pas correctement chargées."}), 500
    global nlp
    # Analyse avec spaCy
    doc = nlp(user_message)
    tokens = [token.lemma_ for token in doc]

    # Détecter l'intention
    detected_intent = None
    for intent, keywords in data['intents'].items():
        if any(token in keywords for token in tokens):
            detected_intent = intent
            break

    # Réponse par défaut si intention non détectée
    response = data['responses'].get(detected_intent, "Je ne suis pas sûr de comprendre votre demande.")

    # Si l'intention est inconnue, ajouter la question aux données d'entraînement
    if detected_intent is None:
        new_entry = {"text": user_message, "intent": "unknown"}
        file_path = '../public/base/training_data.json'
        with open(file_path, 'r+') as f:
            training_data = json.load(f)
            training_data.append(new_entry)  # Ajouter la nouvelle donnée
            f.seek(0)
            json.dump(training_data, f, indent=4)

    return jsonify({
        "response": response,
        "intent": detected_intent
    })

@app.route("/extract_keywords", methods=["POST"])
def extract_keywords():
    text = request.json.get("text", "")
    if not text:
        return jsonify({"error": "Text is required"}), 400
    global nlp
    doc = nlp(text)

    # Extraire les mots-clés basés sur le type grammatical
    keywords = [
        {"keyword": token.text, "lemma": token.lemma_, "pos": token.pos_}
        for token in doc
        if token.pos_ in {"NOUN", "VERB", "ADJ"}  and not token.is_stop and len(token.text) > 2
    ]

    return jsonify({"keywords": keywords})

if __name__ == "__main__":
    app.run(port=5000)
