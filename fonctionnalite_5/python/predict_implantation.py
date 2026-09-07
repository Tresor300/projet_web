"""
predict_implantation.py
=======================
Script de prédiction du TYPE D'IMPLANTATION d'une borne IRVE.
Utilise deux modèles entraînés : Random Forest (classifieur) et SVM (classifieur).

Entrée  : argument JSON en ligne de commande (sys.argv[1])
Sortie  : objet JSON sur stdout  { "random_forest": "...", "svm": "...", "accord": true|false }
Erreur  : objet JSON sur stdout  { "error": "message" }  + code retour 1
"""

import sys
import json
import os

import joblib
import pandas as pd
import numpy as np

# ─────────────────────────────────────────────────────────────────────────────
# CONSTANTES
# ─────────────────────────────────────────────────────────────────────────────

# Répertoire de ce script → les .pkl sont dans le même dossier
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))

# Chemins absolus vers les artefacts entraînés
PATH_RF      = os.path.join(SCRIPT_DIR, "modele_random_forest.pkl")
PATH_SVM     = os.path.join(SCRIPT_DIR, "modele_svm.pkl")
PATH_ENC_X   = os.path.join(SCRIPT_DIR, "encoders.pkl")
PATH_ENC_Y   = os.path.join(SCRIPT_DIR, "encoder_y.pkl")

# Colonnes dans l'ordre EXACT utilisé à l'entraînement
FEATURES_ORDRE = [
    "nbre_pdc",
    "puissance_nominale",
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "condition_acces",
    "reservation",
    "accessibilite_pmr",
    "restriction_gabarit",
    "horaires",
]

# Colonnes booléennes → valeur par défaut si absentes
BOOL_COLS = [
    "prise_type_2",
    "prise_type_combo_ccs",
    "prise_type_chademo",
    "paiement_acte",
    "reservation",
]

# ─────────────────────────────────────────────────────────────────────────────
# UTILITAIRES
# ─────────────────────────────────────────────────────────────────────────────

def sortie_erreur(message: str) -> None:
    """Affiche un JSON d'erreur sur stdout et termine avec code 1."""
    print(json.dumps({"error": message}, ensure_ascii=False))
    sys.exit(1)


def charger_modeles():
    """
    Charge les quatre artefacts depuis le disque.
    Lève une exception explicite si un fichier est manquant.
    """
    for chemin in [PATH_RF, PATH_SVM, PATH_ENC_X, PATH_ENC_Y]:
        if not os.path.isfile(chemin):
            sortie_erreur(
                f"Fichier modèle introuvable : {chemin}. "
                "Exécutez train_implantation.py avant d'utiliser ce script."
            )

    try:
        rf_model   = joblib.load(PATH_RF)
        svm_model  = joblib.load(PATH_SVM)
        encoders_X = joblib.load(PATH_ENC_X)
        le_y       = joblib.load(PATH_ENC_Y)
    except Exception as exc:
        sortie_erreur(f"Erreur lors du chargement des modèles : {exc}")

    return rf_model, svm_model, encoders_X, le_y


def lire_arguments() -> dict:
    """
    Lit l'unique argument passé en ligne de commande : soit le JSON lui-même,
    soit le chemin d'un fichier qui le contient.

    Le mode fichier sert à l'appel depuis PHP sous Windows, où escapeshellarg()
    remplace les guillemets d'un argument par des espaces et détruirait donc
    un JSON passé directement.

    Valide la présence de toutes les colonnes attendues.
    """
    if len(sys.argv) != 2:
        sortie_erreur(
            "Usage : python predict_implantation.py '<JSON ou chemin de fichier JSON>'\n"
            "Exemple : python predict_implantation.py '{\"nbre_pdc\":4,\"puissance_nominale\":22.0,...}'"
        )

    entree = sys.argv[1]

    if os.path.isfile(entree):
        try:
            with open(entree, "r", encoding="utf-8") as fichier:
                entree = fichier.read()
        except OSError as exc:
            sortie_erreur(f"Fichier d'entrée illisible : {exc}")

    try:
        data = json.loads(entree)
    except json.JSONDecodeError as exc:
        sortie_erreur(f"JSON invalide en entrée : {exc}")

    if not isinstance(data, dict):
        sortie_erreur("L'entrée JSON doit être un objet (dictionnaire).")

    # Vérification des clés manquantes
    manquantes = [col for col in FEATURES_ORDRE if col not in data]
    if manquantes:
        sortie_erreur(
            f"Champs manquants dans le JSON : {manquantes}. "
            f"Tous les champs requis : {FEATURES_ORDRE}"
        )

    return data


def valider_types(data: dict) -> dict:
    """
    Convertit et valide les types de chaque champ.
    - nbre_pdc et puissance_nominale → float
    - Tout le reste reste str après nettoyage
    """
    propre = {}

    # Champs numériques
    for col in ("nbre_pdc", "puissance_nominale"):
        try:
            propre[col] = float(data[col])
            if not np.isfinite(propre[col]):
                sortie_erreur(f"La valeur de '{col}' doit être un nombre fini, reçu : {data[col]}")
        except (TypeError, ValueError):
            sortie_erreur(f"'{col}' doit être numérique, reçu : {repr(data[col])}")

    # Champs texte
    for col in FEATURES_ORDRE:
        if col in ("nbre_pdc", "puissance_nominale"):
            continue
        valeur = data[col]
        if valeur is None:
            propre[col] = ""
        else:
            propre[col] = str(valeur).strip()

    # Normalisation des booléens textuels (robustesse)
    for col in BOOL_COLS:
        v = propre.get(col, "").upper()
        if v in ("1", "TRUE", "OUI", "YES"):
            propre[col] = "VRAI"
        elif v in ("0", "FALSE", "NON", "NO"):
            propre[col] = "FAUX"

    return propre


def encoder_features(df: pd.DataFrame, encoders_X: dict) -> pd.DataFrame:
    """
    Applique les LabelEncoders colonne par colonne.
    """
    for col, encoder in encoders_X.items():
        if col not in df.columns:
            continue

        valeur = str(df[col].iloc[0])
        classes_connues: list = list(encoder.classes_)

        if valeur in classes_connues:
            valeur_encodee = valeur
        elif "inconnu" in classes_connues:
            valeur_encodee = "inconnu"
        else:
            valeur_encodee = classes_connues[0]

        try:
            df[col] = encoder.transform([valeur_encodee])
        except Exception as exc:
            sortie_erreur(f"Échec d'encodage de la colonne '{col}' (valeur={valeur_encodee}) : {exc}")

    return df


# ─────────────────────────────────────────────────────────────────────────────
# PROGRAMME PRINCIPAL
# ─────────────────────────────────────────────────────────────────────────────

def main() -> None:
    # 1. Chargement des modèles
    rf_model, svm_model, encoders_X, le_y = charger_modeles()

    # 2. Lecture et validation de l'entrée
    data_brut  = lire_arguments()
    data_propre = valider_types(data_brut)

    # 3. Construction du DataFrame dans l'ordre exact d'entraînement
    try:
        df = pd.DataFrame([data_propre])[FEATURES_ORDRE]
    except KeyError as exc:
        sortie_erreur(f"Colonne manquante lors de la construction du DataFrame : {exc}")

    # Convertir les colonnes non-numériques en str (sécurité)
    for col in df.columns:
        if col not in ("nbre_pdc", "puissance_nominale"):
            df[col] = df[col].astype(str)

    # 4. Encodage des features catégorielles
    df = encoder_features(df, encoders_X)

    # 5. Vérification : aucune valeur nulle
    if df.isnull().any().any():
        nulles = df.columns[df.isnull().any()].tolist()
        sortie_erreur(f"Valeurs manquantes après encodage dans les colonnes : {nulles}")

    # 6. Prédictions
    try:
        pred_rf  = rf_model.predict(df)
        pred_svm = svm_model.predict(df)
    except Exception as exc:
        sortie_erreur(f"Erreur lors de la prédiction : {exc}")

    # 7. Décodage de l'étiquette de sortie
    try:
        label_rf  = str(le_y.inverse_transform(pred_rf)[0])
        label_svm = str(le_y.inverse_transform(pred_svm)[0])
    except Exception as exc:
        sortie_erreur(f"Erreur lors du décodage du résultat : {exc}")

    # 8. Résultat structuré
    resultat = {
        "random_forest": label_rf,
        "svm":           label_svm,
        "accord":        label_rf == label_svm,
    }

    print(json.dumps(resultat, ensure_ascii=False))


if __name__ == "__main__":
    main()