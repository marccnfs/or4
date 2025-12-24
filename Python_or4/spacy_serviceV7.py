import spacy
import numpy as np
import random
import json
import os
import unidecode
from flask import Flask, request, jsonify, Response
from spacy.lookups import Lookups
from sklearn.feature_extraction.text import TfidfVectorizer
from spacy.training.example import Example
from spacy.lang.fr.stop_words import STOP_WORDS
from sklearn.model_selection import train_test_split
from sklearn.naive_bayes import MultinomialNB
from sklearn.feature_extraction.text import CountVectorizer

app = Flask(__name__)

# Charger le modèle de langue français
nlp = spacy.load("fr_core_news_md")
#nlp.vocab["l"].is_stop = True

# Charger les intentions et questions
INTENTS_AND_QUESTIONS_FILE = "../public/base/intents_and_questions.json"
INTENTS_AND_RESPONSES_FILE = "../public/base/intents_and_responses.json"
CLUSTERS_FILE = "../public/base/clusters.json"
STATISTICS_FILE = "../public/base/statistics.json"
API_KEY = "mdpOr4"
GLOSSARY_FILE = "../public/base/glossary.json"

# Initialiser les stop words et les normaliser en centralisant la gestion
french_stop_words = list(STOP_WORDS)
additional_stop_words = ['neuf', 'qu', 'quelqu']
french_stop_words.extend(additional_stop_words)
french_stop_words = list(set(french_stop_words))
normalized_stop_words = [unidecode.unidecode(word.lower()) for word in french_stop_words]

# Ajuster les stop words
stop_words_to_remove = ["public", "artificielle", "potins", "numérique"]
for word in stop_words_to_remove:
    if word in normalized_stop_words:
        normalized_stop_words.remove(word)

# Ajouter les stop words à vocab pour que spaCy reconnaisse également ces mots comme des stop words
for word in normalized_stop_words:
    lexeme = nlp.vocab[word]
    lexeme.is_stop = True

# Initialiser les lookup tables pour lemmatisation
lookups = Lookups()
lookups.add_table("lemma_lookup")
nlp.vocab.lookups = lookups


# Charger le classificateur et le vectoriseur pour l'intention
def load_intents_and_questions():
    try:
        with open(INTENTS_AND_QUESTIONS_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return []  # Retourne une liste vide si le fichier n'existe pas
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {INTENTS_AND_QUESTIONS_FILE}: {e}")

# Charger les intentions et réponses
def load_intents_and_responses():
    try:
        with open(INTENTS_AND_RESPONSES_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return {"intents": {}, "responses": {}}
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {INTENTS_AND_RESPONSES_FILE}: {e}")

def load_glossary():
    try:
        with open(GLOSSARY_FILE, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return {"terms": {}}

def load_json_file(file_path):
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        return {}
    except json.JSONDecodeError as e:
        raise ValueError(f"Erreur de syntaxe dans {file_path}: {e}")  
    
def extract_entities(text):
    doc = nlp(text)
    entities = [(ent.text, ent.label_) for ent in doc.ents]
    return entities

def train_intent_classifier():
    intents_and_questions = load_intents_and_questions()
    texts = [entry["text"] for entry in intents_and_questions]
    labels = [entry["intent"] for entry in intents_and_questions]

    # Convertir les textes en vecteurs
    vectorizer = CountVectorizer()
    X = vectorizer.fit_transform(texts)
    y = labels

    # Entraîner un modèle Naive Bayes
    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
    classifier = MultinomialNB()
    classifier.fit(X_train, y_train)

     # Retourner le classificateur entraîné et le vectoriseur pour les prédictions futures
    return classifier, vectorizer

# Charger le classificateur et le vectoriseur
intent_classifier, vectorizer = train_intent_classifier()

# Prédire l'intention avec le classificateur
def predict_intent(text):
    X = vectorizer.transform([text])
    predicted_intent = intent_classifier.predict(X)
    return predicted_intent[0]

# Fonction d'extraction des mots-clés améliorée
# Inclure les types de mots, puis appliquer une pondération contextuelle

def extract_keywords_refined(text, corpus):
    processed_text = preprocess_text(text)

    if processed_text is None or processed_text.strip() == "":
        return []

    # Extraire les mots-clés avec des types élargis
    keywords_spacy = [
        token.lemma_ for token in nlp(processed_text)
        if token.pos_ in ["NOUN", "VERB", "PROPN", "ADJ", "PRON"]  or token.text.lower() in ["potins", "numériques"]
    ]

    # Forcer l'ajout des mots "potins" et "numériques" s'ils apparaissent dans le texte original
    if "potins" in text.lower() and "potins" not in keywords_spacy:
        keywords_spacy.append("potins")
    if "numériques" in text.lower() and "numériques" not in keywords_spacy:
        keywords_spacy.append("numériques")
    

    # Étape 1 : Calcul du score TF-IDF sur le corpus pour pondérer les mots-clés
    vectorizer = TfidfVectorizer(stop_words=normalized_stop_words, vocabulary=keywords_spacy)
    vectorizer.fit(corpus)
    response_vector = vectorizer.transform([processed_text])

    scores = response_vector.toarray().flatten()
    feature_names = vectorizer.get_feature_names_out()
    keywords_with_scores = [(feature_names[i], scores[i]) for i in range(len(scores)) if scores[i] > 0]
    sorted_keywords = sorted(keywords_with_scores, key=lambda x: x[1], reverse=True)

    # Étape 2 : Ajouter la similarité des vecteurs pour filtrer les mots les plus pertinents
    full_vector = nlp(processed_text).vector
    keyword_scores = []
    for keyword, tfidf_score in sorted_keywords:
        similarity = nlp(keyword).vector @ full_vector / (np.linalg.norm(nlp(keyword).vector) * np.linalg.norm(full_vector))
        final_score = 0.5 * tfidf_score + 0.5 * similarity  # Pondération égale entre TF-IDF et similarité
        keyword_scores.append((keyword, final_score))

    # Trier les mots-clés par score final décroissant
    refined_keywords = sorted(keyword_scores, key=lambda x: x[1], reverse=True)
    return [kw[0] for kw in refined_keywords if kw[1] > 0.3]

# Fonction de prétraitement
def preprocess_text(text):
    if not text:
        return ""

     # Conserver les expressions interrogatives pour éviter de perdre du contexte
    question_keywords = ["c'est quoi", "qu'est-ce que", "qu'est ce que", "quel", "comment"]
    for keyword in question_keywords:
        if keyword in text.lower():
            text = text.lower().replace(keyword, keyword)  # Conserver l'expression interrogative

    text=text.encode('utf-8').decode('utf-8')

    # Analyse des mots clés pour les conserver
    doc = nlp(text)
    processed_tokens = [
        token.text for token in doc 
        if not token.is_stop or token.text.lower() in ["potins", "numériques"]
    ]
    
    return ' '.join(processed_tokens)

@app.route('/explore_clusters', methods=['GET'])
def explore_clusters():
    clusters = load_json_file(CLUSTERS_FILE)
    if not clusters:
        return jsonify({"error": "Clusters introuvables."}), 404
    #return jsonify(clusters)
    return Response(json.dumps(clusters, ensure_ascii=False), mimetype='application/json; charset=utf-8')

@app.route('/statistics', methods=['GET'])
def get_statistics():
    statistics = load_json_file(STATISTICS_FILE)
    if not statistics:
        return jsonify({"error": "Statistiques introuvables."}), 404
    #return jsonify(statistics)
    return Response(json.dumps(statistics, ensure_ascii=False), mimetype='application/json; charset=utf-8')
      

@app.route('/analyze_context', methods=['POST'])
def analyze_context():
    user_message = request.json.get('message', '').strip()
    if not user_message:
         return Response(
            json.dumps({
            "response": "Message vide.",
            "keywords": [],
            "intent": "unknown",
            "context": "unknown",
            "explanation": "Aucune analyse contextuelle n'a pu être effectuée."}, ensure_ascii=False),
            content_type='application/json; charset=utf-8')

     # Charger les intentions et réponses
    intents_and_responses = load_intents_and_responses()
    intents_and_questions = load_intents_and_questions()
    corpus = [entry['text'] for entry in intents_and_questions]

    # Étape 1 : Extraction des mots-clés affinée avec TF-IDF et similarité
    keywords = extract_keywords_refined(user_message, corpus)
    if not keywords:
        return jsonify({
            "response": "Aucun mot-clé détecté.",
            "keywords": [],
            "intent": "unknown",
            "context": "unknown",
            "explanation": "Aucune analyse contextuelle n'a pu être effectuée."
        }), 200

    entities = extract_entities(user_message)

    # Étape 2 : Détection de l'intention à l'aide du classificateur
    intent = predict_intent(user_message)

    # Étape 3 : Générer la réponse
    response = intents_and_responses["responses"].get(intent, "Je ne suis pas sûr de comprendre votre demande.")
    explanation = f"Les mots-clés détectés sont : {', '.join(keywords)}. L'intention détectée est : {intent}."
    # Créer une réponse JSON
    response_data={
        "response": response,
        "keywords": keywords,
        "intent": intent or "unknown",
        "context": intent or "unknown",
        "entities": entities,
        "explanation": explanation
   }
    print(json.dumps(response_data, ensure_ascii=False))
    return Response(json.dumps(response_data, ensure_ascii=False).encode('utf-8'), mimetype='application/json; charset=utf-8')


@app.route("/calculate_relationships", methods=["POST"])
def calculate_relationships():
    keywords = request.json.get("keywords", [])

    if len(keywords) < 2:
        return jsonify({"relationships": []})

    relationships = []
    similarities = []

     # Calcul des similarités pour déterminer la médiane
    for i in range(len(keywords)):
        for j in range(i + 1, len(keywords)):
            vec1 = nlp(keywords[i]).vector
            vec2 = nlp(keywords[j]).vector
            similarity = np.dot(vec1, vec2) / (np.linalg.norm(vec1) * np.linalg.norm(vec2))
            similarities.append(similarity)

    # Calculer un seuil dynamique basé sur la médiane
    if similarities:
        dynamic_threshold = np.median(similarities)
    else:
        dynamic_threshold = 0.2  # valeur par défaut si aucune similarité calculée

    # Création des relations si la similarité dépasse le seuil dynamique
    for i in range(len(keywords)):
        for j in range(i + 1, len(keywords)):
            vec1 = nlp(keywords[i]).vector
            vec2 = nlp(keywords[j]).vector
            similarity = np.dot(vec1, vec2) / (np.linalg.norm(vec1) * np.linalg.norm(vec2))
            if similarity > dynamic_threshold:
                relationships.append({
                    "source": keywords[i],
                    "target": keywords[j],
                    "weight": float(round(similarity, 2))
                })

    #return jsonify({"relationships": relationships})
    response_data={"relationships": relationships}
    return Response(json.dumps(response_data, ensure_ascii=False), mimetype='application/json; charset=utf-8')


@app.route('/glossary', methods=['POST'])
def get_glossary_term():
    term = request.json.get("term", "").strip()
    if not term:
        return jsonify({"error": "Aucun terme fourni."}), 400

    glossary = load_glossary()
    definition = glossary["terms"].get(term, None)
    if not definition:
        return jsonify({"error": f"Terme '{term}' introuvable dans le glossaire."}), 404

    #return jsonify({"term": term, "definition": definition})
    response_data={"term": term, "definition": definition}
    return Response(json.dumps(response_data, ensure_ascii=False), mimetype='application/json; charset=utf-8')


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

if __name__ == "__main__":
    app.run(port=5000)
