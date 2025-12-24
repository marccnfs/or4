# python spacy_service.py
import spacy
import numpy as np
import random
import json
import os
from flask import Flask, request, jsonify
from spacy.lookups import Lookups
from sklearn.feature_extraction.text import TfidfVectorizer
from spacy.training.example import Example

app = Flask(__name__)

# Chemin vers le fichier JSON
DATA_FILE = os.path.join(os.path.dirname(__file__), '../public/base/intents_and_responses.json')

def load_corpus_from_json():
    """
    Charge toutes les questions du fichier JSON pour constituer le corpus.
    """
    with open(DATA_FILE, 'r') as f:
        data = json.load(f)

    # Extraire uniquement les textes des questions
    corpus = [entry['text'] for entry in data]
    return corpus

# Fonction pour charger les données JSON pour train
def load_data():
    try:
        with open(DATA_FILE, 'r') as file:
            data = json.load(file)

            # Vérifier si les clés "intents" et "responses" existent
            if 'intents' not in data or 'responses' not in data:
                raise ValueError("Le fichier JSON doit contenir les clés 'intents' et 'responses'")

            return data
    except FileNotFoundError:
        raise FileNotFoundError(f"Fichier introuvable : {DATA_FILE}")
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans le fichier JSON : {e}")

try:
    data = load_data()
    print("Données chargées avec succès.")
except Exception as e:
    print(f"Erreur au chargement des données : {e}")
    data = None


# Charger le modèle de langue français
nlp = spacy.load("fr_core_news_sm")
nlp.vocab["l"].is_stop = True
lookups = Lookups()
lookups.add_table("lemma_lookup")
nlp.vocab.lookups = lookups

@app.route('/train', methods=['POST'])
def train():

    file_path = '../public/base/training_data.json'
    # Charger les données d'entraînement
    with open(file_path, 'r') as f:
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



@app.route('/analyze', methods=['POST'])
def analyze():
    user_message = request.json.get('message', '')

    # Assurer que data est correctement chargé
    global data
    if not data or 'intents' not in data or 'responses' not in data:
        return jsonify({"error": "Les données du chatbot ne sont pas correctement chargées."}), 500

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

    doc = nlp(text)

    # Extraire les mots-clés basés sur le type grammatical
    keywords = [
        {"keyword": token.text, "lemma": token.lemma_, "pos": token.pos_}
        for token in doc
        if token.pos_ in {"NOUN", "VERB", "ADJ"}  and not token.is_stop and len(token.text) > 2
    ]

    return jsonify({"keywords": keywords})

@app.route('/analyze_combined', methods=['POST'])
def analyze():
    user_message = request.json.get('message', '')
    result = analyze_question(user_message, corpus)
    return jsonify(result)

def analyze_question(text, corpus):
    """
    Analyse une question utilisateur et retourne les mots-clés et entités nommées.
    """
    keywords = extract_keywords_combined(text, corpus)
    entities = extract_entities(text)
    return {
        "keywords": keywords,
        "entities": entities
    }

# Exemple
#result = analyze_question("Quels sont vos horaires d'ouverture pour les ateliers ?", corpus)
#print(result)
# Résultat attendu :
# {
#     "keywords": ['horaire', 'ouverture', 'atelier'],
#     "entities": []
# }

def extract_keywords_combined(text, corpus):
    """
    Combine spaCy et TF-IDF pour extraire les mots-clés les plus pertinents.
    """
    # Étape 1 : Extraction de base avec spaCy
    doc = nlp(text)
    keywords_spacy = [token.lemma_ for token in doc if token.pos_ in ["NOUN", "VERB", "PROPN"]]

    # Étape 2 : Appliquer TF-IDF pour pondérer les mots
    vectorizer = TfidfVectorizer(stop_words="french", vocabulary=keywords_spacy)
    vectorizer.fit(corpus)  # Entraîne le modèle TF-IDF sur le corpus
    response_vector = vectorizer.transform([text])

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



if __name__ == "__main__":
    app.run(port=5000)
