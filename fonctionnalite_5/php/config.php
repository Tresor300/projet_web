<?php
/**
 * config.php
 * ==========
 * Configuration de la fonctionnalité 5.
 *
 * Les identifiants MySQL et le chemin de l'interpréteur Python vivent
 * désormais dans le config.php à la racine du projet, partagé par les
 * 5 fonctionnalités : il n'y a plus qu'un seul endroit à modifier pour
 * changer d'environnement (local Windows ↔ serveur distant).
 *
 * Ce fichier est conservé pour que les `require_once __DIR__ . '/config.php'`
 * des scripts voisins continuent de fonctionner.
 *
 * Il expose : DB_*, PYTHON_BIN, PYTHON_TIMEOUT, get_db_connection()
 * et executer_python().
 */

require_once __DIR__ . '/../../config.php';
