<?php
/**
 * predict_cluster.php
 * -------------------
 * Fonctionnalité 4 — Prédiction des clusters
 *
 * Stratégie : un seul appel Python pour tous les points (via fichier JSON temporaire)
 * au lieu d'un appel par point — beaucoup plus rapide.
 *
 * Méthode : GET
 * Réponse : JSON [{ id_pdc, latitude, longitude, cluster, ... }, ...]
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ──────────────────────────────────────────
// 1. Configuration de la base de données
//    (identifiants et chemins centralisés)
// ──────────────────────────────────────────
require_once __DIR__ . '/../../config.php';

try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Connexion BDD échouée : ' . $e->getMessage()]);
    exit;
}

// ──────────────────────────────────────────
// 2. Récupération des points de charge
//    avec les coordonnées GPS de la station
// ──────────────────────────────────────────
$sql = "
    SELECT
        pdc.id_pdc,
        pdc.puissance_nominale,
        pdc.condition_acces,
        s.id_station,
        s.nom_enseigne,
        s.adresse_station,
        s.latitude,
        s.longitude,
        s.code_departement
    FROM POINT_DE_CHARGE pdc
    JOIN STATION s ON pdc.id_station = s.id_station
    WHERE s.latitude IS NOT NULL
      AND s.longitude IS NOT NULL
    LIMIT 200
";

try {
    $stmt = $pdo->query($sql);
    $points = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur requête SQL : ' . $e->getMessage()]);
    exit;
}

if (empty($points)) {
    echo json_encode(['error' => 'Aucun point de charge trouvé en base.']);
    exit;
}

// ──────────────────────────────────────────
// 3. Chemin vers le script Python
//    (relatif à la racine du projet : plus de
//     chemin serveur codé en dur)
// ──────────────────────────────────────────
$python_script = PROJET_ROOT . '/fonctionnalite_4/IA/ScriptBesoin_2.py';

if (!file_exists($python_script)) {
    http_response_code(500);
    echo json_encode(['error' => "Script Python introuvable : $python_script"]);
    exit;
}

// ──────────────────────────────────────────
// 4. Appel Python une seule fois par batch
//    via un fichier JSON temporaire
// ──────────────────────────────────────────

// Prépare les coordonnées à envoyer au script Python
$coords = [];
foreach ($points as $point) {
    $coords[] = [
        'id_pdc'    => $point['id_pdc'],
        'latitude'  => (float)$point['latitude'],
        'longitude' => (float)$point['longitude'],
    ];
}

// Écrit le fichier d'entrée temporaire
$tmp_input  = tempnam(sys_get_temp_dir(), 'irve_in_');
$tmp_output = tempnam(sys_get_temp_dir(), 'irve_out_');
file_put_contents($tmp_input, json_encode($coords));

// Appelle le script Python en mode batch (un seul lancement pour tous les points)
$resultat_exec = executer_python([
    $python_script,
    '--batch',  $tmp_input,
    '--output', $tmp_output,
]);

// Vérifie si le fichier de sortie existe et est valide.
// clearstatcache() : le fichier a été créé vide par tempnam() puis rempli par
// un processus externe, on ne veut pas d'une taille mise en cache par PHP.
clearstatcache(true, $tmp_output);

if (!file_exists($tmp_output) || filesize($tmp_output) === 0) {
    // L'ancienne version repliait ici sur une prédiction point par point.
    // ScriptBesoin_2.py gère le mode batch, donc un échec vient forcément de
    // l'appel Python lui-même (interpréteur introuvable, module manquant,
    // modèle .pkl absent). On remonte sa sortie plutôt que de relancer
    // 200 processus Python voués au même échec.
    @unlink($tmp_input);
    @unlink($tmp_output);

    $detail = trim(implode("\n", $resultat_exec['sortie']));

    http_response_code(500);
    echo json_encode([
        'error' => "Échec de l'appel Python (code {$resultat_exec['code']}) : "
                 . ($detail !== '' ? $detail : 'aucune sortie. Vérifie PYTHON_EXE dans config.php.'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Lecture des résultats du mode batch
$batch_result = json_decode(file_get_contents($tmp_output), true);
$clusters_predits = [];
foreach ($batch_result as $item) {
    $clusters_predits[$item['id_pdc']] = $item['cluster'];
}

// Nettoyage des fichiers temporaires
@unlink($tmp_input);
@unlink($tmp_output);

// ──────────────────────────────────────────
// 5. Construction du JSON final
// ──────────────────────────────────────────
$resultats = [];
foreach ($points as $point) {
    $resultats[] = [
        'id_pdc'             => $point['id_pdc'],
        'id_station'         => $point['id_station'],
        'nom_enseigne'       => $point['nom_enseigne'],
        'adresse_station'    => $point['adresse_station'],
        'puissance_nominale' => $point['puissance_nominale'],
        'condition_acces'    => $point['condition_acces'],
        'code_departement'   => $point['code_departement'],
        'latitude'           => (float)$point['latitude'],
        'longitude'          => (float)$point['longitude'],
        'cluster'            => $clusters_predits[$point['id_pdc']] ?? -1,
    ];
}

echo json_encode($resultats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);