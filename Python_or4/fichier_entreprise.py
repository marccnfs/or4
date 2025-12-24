import requests
import json

def rechercher_entreprises_par_code_postal(code_postal):
    url = "https://recherche-entreprises.api.gouv.fr/search"
    params = {
        "code_postal": code_postal,  # Correction ici !
        "per_page": 25  # Nombre max de résultats
    }
    headers = {
        "Accept": "application/json"
    }

    try:
        response = requests.get(url, params=params, headers=headers)
        response.raise_for_status()
        data = response.json()

        if "results" in data and data["results"]:
            entreprises = []
            for entreprise in data["results"]:
                entreprises.append({
                    "siren": entreprise.get("siren"),
                    "nom_complet": entreprise.get("nom_complet"),
                    "adresse": entreprise.get("siege", {}).get("adresse"),
                    "code_postal": entreprise.get("siege", {}).get("code_postal"),
                    "ville": entreprise.get("siege", {}).get("libelle_commune"),
                    "activite_principale": entreprise.get("activite_principale"),
                    "etat_administratif": entreprise.get("etat_administratif"),
                    "date_creation": entreprise.get("date_creation")
                })

            # Sauvegarde des résultats
            with open("resultats.json", "w", encoding="utf-8") as f:
                json.dump(entreprises, f, ensure_ascii=False, indent=4)

            print(f"✅ {len(entreprises)} entreprises trouvées pour {code_postal} et sauvegardées dans 'resultats.json'")
            return entreprises
        else:
            print(f"Aucune entreprise trouvée pour le code postal {code_postal}.")
            return []

    except requests.exceptions.RequestException as e:
        print(f"⚠️ Erreur lors de la requête : {e}")
        return []

# 🔎 Test avec le code postal 44830
rechercher_entreprises_par_code_postal("44830")
