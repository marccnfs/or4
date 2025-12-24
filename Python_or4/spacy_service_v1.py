from flask import Flask, request, jsonify
import spacy
from spacy.lookups import Lookups
import numpy as np
import random
import json
import os
from spacy.training.example import Example

app = Flask(__name__)

# Charger le modèle de langue français
nlp = spacy.load("fr_core_news_sm")
nlp.vocab["l"].is_stop = True
lookups = Lookups()
lookups.add_table("lemma_lookup")
nlp.vocab.lookups = lookups

# Intents et réponses
intents = {
    "horaires": ["horaire", "ouverture", "fermeture"],
    "ateliers": ["atelier", "formation", "cours"],
    "contact": ["contact", "email", "téléphone"]
}

responses = {
    "horaires": "Nous sommes ouverts de 9h à 18h du lundi au samedi.",
    "ateliers": "Nous proposons des ateliers sur la réalité virtuelle et le coding.",
    "contact": "Vous pouvez nous contacter par email à contact@potinsnumeriques.fr."
}

@app.route('/train', methods=['POST'])
def train():

    file_path = 'public/base/training_data.json'
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
    data = request.json
    user_message = data.get('message', '')

    # Analyse avec spaCy
    doc = nlp(user_message)
    tokens = [token.lemma_ for token in doc]

    # Détecter l'intention
    detected_intent = None
    for intent, keywords in intents.items():
        if any(token in keywords for token in tokens):
            detected_intent = intent
            break

    # Réponse par défaut si intention non détectée
    response = responses.get(detected_intent, "Je ne suis pas sûr de comprendre votre demande.")

    return jsonify({
        "response": response,
        "intent": detected_intent
    })

@app.route("/extract_keywords", methods=["POST"])
def extract_keywords():
    data = request.json
    text = data.get("text", "")
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



@app.route("/calculate_relationships", methods=["POST"])
def calculate_relationships():
    data = request.json
    keywords = data.get("keywords", [])

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


if __name__ == "__main__":
    app.run(port=5000)
