import spacy
import numpy as np
import random
import json
import os
import unidecode
from flask import Flask, request, jsonify
from spacy.lookups import Lookups
from sklearn.feature_extraction.text import TfidfVectorizer
from spacy.training.example import Example
from spacy.lang.fr.stop_words import STOP_WORDS

app = Flask(__name__)

# Charger le modèle de langue français
nlp = spacy.load("fr_core_news_sm")
nlp.vocab["l"].is_stop = True

# Initialiser les stop words et les normaliser en centralisant la gestion
french_stop_words = list(STOP_WORDS)
additional_stop_words = ['neuf', 'qu', 'quelqu']
french_stop_words.extend(additional_stop_words)
french_stop_words = list(set(french_stop_words))
normalized_stop_words = [unidecode.unidecode(word.lower()) for word in french_stop_words]

# Ajouter les stop words à vocab pour que spaCy reconnaisse également ces mots comme des stop words
for word in normalized_stop_words:
    lexeme = nlp.vocab[word]
    lexeme.is_stop = True

# Initialiser les lookup tables pour lemmatisation
lookups = Lookups()
lookups.add_table("lemma_lookup")
nlp.vocab.lookups = lookups

# Chemin vers le fichier JSON
DATA_FILE = os.path.join(os.path.dirname(__file__), '../public/base/intents_and_responses.json')
INTENTS_AND_QUESTIONS_FILE = "../public/base/intents_and_questions.json"
INTENTS_AND_RESPONSES_FILE = "../public/base/intents_and_responses.json"
API_KEY = "mdpOr4"
GLOSSARY_FILE = "../public/base/glossary.json"

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
        for intent, words in data.get("intents", {}).items():
            if not (isinstance(words, dict) and "direct" in words) and not isinstance(words, list):
                raise ValueError(f"L'intention '{intent}' a un format incorrect. Attendu : dictionnaire avec clé 'direct' ou liste.")
        return data
    except FileNotFoundError:
        return {"intents": {}, "responses": {}}  # Structure par défaut si le fichier est manquant
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {INTENTS_AND_RESPONSES_FILE}: {e}")

def load_glossary():
    try:
        with open(GLOSSARY_FILE, "r") as f:
            return json.load(f)
    except FileNotFoundError:
        return {"terms": {}}

@app.route('/glossary', methods=['POST'])
def get_glossary_term():
    term = request.json.get("term", "").strip()
    if not term:
        return jsonify({"error": "Aucun terme fourni."}), 400

    glossary = load_glossary()
    definition = glossary["terms"].get(term, None)
    if not definition:
        return jsonify({"error": f"Terme '{term}' introuvable dans le glossaire."}), 404

    return jsonify({"term": term, "definition": definition})

@app.route('/train', methods=['POST'])
def train():
    with open(INTENTS_AND_QUESTIONS_FILE, 'r') as f:
        training_data = json.load(f)

    # Vérifier que les données sont sous forme de liste
    if not isinstance(training_data, list):
        return jsonify({"error": "Les données de formation doivent être une liste."}), 400

    examples = []
    for item in training_data:
        # Vérifier que chaque élément est un dictionnaire avec les clés 'text' et 'intent'
        if not isinstance(item, dict) or 'text' not in item or 'intent' not in item:
            return jsonify({"error": "Chaque entrée de formation doit être un dictionnaire avec les clés 'text' et 'intent'."}), 400

        doc = nlp.make_doc(item["text"])
        examples.append(Example.from_dict(doc, {"cats": {item["intent"]: 1.0}}))

    # Initialiser l'optimiseur avec des paramètres ajustés
    optimizer = nlp.create_optimizer()
    optimizer.learn_rate = 0.001  # Ajuster le taux d'apprentissage

    # Ajouter un suivi des métriques de performance
    all_losses = []  # Liste pour enregistrer les pertes
    for epoch in range(20):  # Augmenter le nombre d'époques
        random.shuffle(examples)
        losses = {}
        for example in examples:
            nlp.update([example], sgd=optimizer, losses=losses)
        all_losses.append(losses)
        print(f"Epoch {epoch+1}, Loss: {losses}")  # Ajouter un affichage des pertes pour le suivi

    # Sauvegarder les métriques de perte dans un fichier pour une analyse ultérieure
    with open('training_losses.json', 'w') as f:
        json.dump(all_losses, f, indent=4)

    # Sauvegarder le modèle après l'entraînement
    nlp.to_disk('model_directory')

    return jsonify({"message": "Training completed and model saved"})

@app.route('/update-intent', methods=['POST'])
def update_intent():
    request_data = request.json
    question = request_data.get("text")
    new_intent = request_data.get("intent")

    if not question or not new_intent:
        return jsonify({"error": "Texte ou intention manquante."}), 400

    intents_and_questions = load_intents_and_questions()
    for entry in intents_and_questions:
        if entry["text"] == question:
            entry["intent"] = new_intent
            break
    else:
        return jsonify({"error": "Question introuvable."}), 404

    with open(INTENTS_AND_QUESTIONS_FILE, "w") as f:
        json.dump(intents_and_questions, f, indent=4)

    return jsonify({"message": "Intention mise à jour avec succès."})

@app.route('/analyze_combined', methods=['POST'])
def analyze_question():
    user_message = request.json.get('message', '')
    intents_and_questions = load_intents_and_questions()
    intents_and_responses = load_intents_and_responses()
    corpus = [entry['text'] for entry in intents_and_questions]

    keywords = extract_keywords_combined(user_message, corpus)
    if not isinstance(keywords, list):
        return jsonify({"error": "Impossible d'extraire des mots-clés."}), 400

    entities = extract_entities(user_message)

    detected_intent = None
    for intent, words in intents_and_responses["intents"].items():
        if isinstance(words, dict) and any(kw in words.get("direct", []) for kw in keywords):
            detected_intent = intent
            break
        elif isinstance(words, list) and any(kw in words for kw in keywords):
            detected_intent = intent
            break

    if not detected_intent:
        new_entry = {"text": user_message, "intent": "unknown"}
        with open(INTENTS_AND_QUESTIONS_FILE, "r+") as f:
            data = json.load(f)
            data.append(new_entry)
            f.seek(0)
            json.dump(data, f, indent=4)
            f.truncate()

    response = intents_and_responses["responses"].get(detected_intent, "Je ne suis pas sûr de comprendre votre demande.")

    return jsonify({
        "response": response,
        "keywords": keywords,
        "entities": entities,
        "intent": detected_intent
    })

def extract_keywords_combined(text, corpus):
    processed_text = preprocess_text(text)

    if processed_text is None or processed_text.strip() == "":
        return []

    keywords_spacy = [token.lemma_ for token in nlp(processed_text) if token.pos_ in ["NOUN", "VERB", "PROPN"]]
    if not keywords_spacy:
        return []

    vectorizer = TfidfVectorizer(stop_words=normalized_stop_words, vocabulary=keywords_spacy)
    vectorizer.fit(corpus)
    response_vector = vectorizer.transform([processed_text])

    scores = response_vector.toarray().flatten()
    feature_names = vectorizer.get_feature_names_out()
    keywords_with_scores = [(feature_names[i], scores[i]) for i in range(len(scores)) if scores[i] > 0]
    sorted_keywords = sorted(keywords_with_scores, key=lambda x: x[1], reverse=True)
    return [kw[0] for kw in sorted_keywords]

def extract_entities(text):
    doc = nlp(text)
    entities = [(ent.text, ent.label_) for ent in doc.ents]
    return entities

@app.route("/calculate_relationships", methods=["POST"])
def calculate_relationships():
    keywords = request.json.get("keywords", [])

    if len(keywords) < 2:
        return jsonify({"relationships": []})

    relationships = []
    for i in range(len(keywords)):
        for j in range(i + 1, len(keywords)):
            vec1 = nlp(keywords[i]).vector
            vec2 = nlp(keywords[j]).vector
            similarity = np.dot(vec1, vec2) / (np.linalg.norm(vec1) * np.linalg.norm(vec2))
            if similarity > 0.2:
                relationships.append({
                    "source": keywords[i],
                    "target": keywords[j],
                    "weight": float(round(similarity, 2))
                })

    return jsonify({"relationships": relationships})

@app.route('/analyze_context', methods=['POST'])
def analyze_context():
    user_message = request.json.get('message', '').strip()
    if not user_message:
        return jsonify({"error": "Message vide."}), 400
    user_message = request.json.get('message', '')
    intents_and_questions = load_intents_and_questions()
    intents_and_responses = load_intents_and_responses()
    corpus = [entry['text'] for entry in intents_and_questions]

    # Étape 1 : Extraction des mots-clés
    keywords = extract_keywords_combined(user_message, corpus)
    if not keywords:
        return jsonify({
            "response": "Aucun mot-clé détecté.",
            "keywords": [],
            "intent": "unknown",
            "context": "unknown"
        }), 200

    entities = extract_entities(user_message)

    # Étape 2 : Détection d'intention avancée
    detected_intent = None
    for intent, words in intents_and_responses["intents"].items():
        if any(kw in user_message.lower() for kw in words.get("direct", [])):
            detected_intent = intent
            break

    # Étape 3 : Analyse contextuelle
    context_type = "définition" if detected_intent == "definition" else "exploration"

    # Générer une réponse adaptée
    response = intents_and_responses["responses"].get(detected_intent, "Je ne suis pas sûr de comprendre votre demande.")

    # Toujours inclure 'detected_intent' dans la réponse
    return jsonify({
        "response": response,
        "keywords": keywords,
        "intent": detected_intent or "unknown",
        "context": context_type
    }), 200

if __name__ == "__main__":
    app.run(port=5000)
