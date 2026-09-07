<?php
/**
 * config.php — Configuration centrale du projet INFRA-CHARGE
 * ==========================================================
 *
 * Point de configuration UNIQUE des 5 fonctionnalités : connexion MySQL,
 * exécution des scripts Python, résolution des chemins et des URL.
 *
 * Le projet tournait auparavant sur un serveur Linux distant. Il est ici
 * paramétré pour une exécution en local sous Windows (Laragon ou XAMPP).
 * Pour changer d'environnement, seules les constantes de ce fichier sont à
 * modifier : plus aucun identifiant ni chemin absolu n'est codé en dur ailleurs.
 */


/* =====================================================
   1. CHEMINS DU PROJET
===================================================== */

/** Racine du projet sur le disque (le dossier qui contient ce fichier). */
define('PROJET_ROOT', __DIR__);

/** Menu latéral partagé par toutes les pages. */
define('MENU_PATH', PROJET_ROOT . '/fonctionnalite_1/includes/menu.php');

/** Vrai sous Windows : le quoting du shell y diffère d'Unix. */
define('EST_WINDOWS', DIRECTORY_SEPARATOR === '\\');


/* =====================================================
   2. BASE DE DONNÉES
===================================================== */

/*
   Identifiants MySQL locaux.

   On utilise 127.0.0.1 plutôt que "localhost" : sous Windows, "localhost"
   peut être résolu en IPv6 (::1) alors que MySQL n'écoute qu'en IPv4, ce qui
   provoque une longue attente suivie d'une erreur de connexion.
*/
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'tv_fowet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');


/* =====================================================
   3. PYTHON
===================================================== */

/*
   Chemin absolu vers python.exe.

   Apache n'hérite pas forcément du PATH de ta session Windows : le "python3"
   du serveur Linux, et même un simple "python", échouent silencieusement.
   On pointe donc directement l'exécutable détecté sur ce poste.
   Mettre '' pour laisser la détection automatique s'en charger.
*/
define('PYTHON_EXE', 'C:/Users/PC/AppData/Local/Programs/Python/Python313/python.exe');

/** Durée maximale tolérée pour un script Python (secondes). */
define('PYTHON_TIMEOUT', 30);


/* =====================================================
   4. FONCTIONS UTILITAIRES
===================================================== */

/**
 * Détermine l'interpréteur Python à utiliser.
 *
 * Ordre de préférence : le chemin explicite ci-dessus, puis les installations
 * standards du profil utilisateur, puis le lanceur officiel `py`.
 */
function detecter_python(): string
{
    if (PYTHON_EXE !== '' && is_file(PYTHON_EXE)) {
        return PYTHON_EXE;
    }

    if (!EST_WINDOWS) {
        return 'python3';
    }

    $motif = str_replace('\\', '/', (string) getenv('LOCALAPPDATA'))
           . '/Programs/Python/Python3*/python.exe';

    $trouves = glob($motif);

    if (!empty($trouves)) {
        rsort($trouves); // version la plus récente en premier
        return $trouves[0];
    }

    return 'py';
}


/**
 * Préfixe URL du projet.
 *
 * Renvoie par exemple "/monprojet", ou une chaîne vide si un hôte virtuel
 * pointe directement sur la racine du projet. Évite de coder le nom du dossier
 * en dur dans le menu, qui est inclus par des pages situées à des profondeurs
 * différentes.
 *
 * Deux méthodes, dans cet ordre :
 *
 *  1. Déduction depuis l'URL de la page en cours. C'est la seule fiable quand
 *     le projet est atteint par un lien symbolique, une jonction ou un Alias
 *     Apache : dans ces cas, PHP résout les chemins disque vers l'emplacement
 *     réel, situé hors de la racine web, et toute comparaison de chemins
 *     échoue.
 *
 *  2. Repli par comparaison avec DOCUMENT_ROOT, pour les contextes où
 *     SCRIPT_NAME est absent ou inexploitable.
 */
function base_url(): string
{
    $normaliser = static function (string $chemin): string {
        return rtrim(str_replace('\\', '/', $chemin), '/');
    };

    $racine_projet = realpath(PROJET_ROOT);

    // ── Méthode 1 : depuis l'URL du script en cours ──
    if ($racine_projet !== false
        && isset($_SERVER['SCRIPT_FILENAME'], $_SERVER['SCRIPT_NAME'])) {

        $script_disque = realpath($_SERVER['SCRIPT_FILENAME']);

        if ($script_disque !== false) {
            $script_disque = str_replace('\\', '/', $script_disque);
            $racine        = $normaliser($racine_projet);

            // Chemin du script relatif au projet, ex. "/fonctionnalite_1/accueil.php"
            if (strncasecmp($script_disque, $racine . '/', strlen($racine) + 1) === 0) {
                $relatif    = substr($script_disque, strlen($racine));
                $url_script = $_SERVER['SCRIPT_NAME'];

                // Ce suffixe retiré de l'URL, il ne reste que le préfixe du projet.
                if (strlen($url_script) >= strlen($relatif)
                    && strcasecmp(substr($url_script, -strlen($relatif)), $relatif) === 0) {
                    return rtrim(
                        substr($url_script, 0, strlen($url_script) - strlen($relatif)),
                        '/'
                    );
                }
            }
        }
    }

    // ── Méthode 2 : repli par comparaison avec la racine web ──
    $racine_web = isset($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT'])
        : false;

    if ($racine_web === false || $racine_projet === false) {
        return '';
    }

    $racine_web    = $normaliser($racine_web);
    $racine_projet = $normaliser($racine_projet);

    // Comparaisons insensibles à la casse : les chemins Windows le sont.

    // Cas d'un hôte virtuel pointant directement sur la racine du projet.
    if (strcasecmp($racine_projet, $racine_web) === 0) {
        return '';
    }

    // On compare avec le séparateur, sinon un dossier voisin nommé "www2"
    // passerait pour un sous-dossier de "www".
    if (strncasecmp($racine_projet, $racine_web . '/', strlen($racine_web) + 1) !== 0) {
        return '';
    }

    return substr($racine_projet, strlen($racine_web));
}


/**
 * Ouvre une connexion PDO vers la base du projet.
 *
 * Laisse volontairement remonter la PDOException : les appelants la
 * rattrapent pour répondre en JSON. Un die() ici casserait le JSON attendu
 * par le JavaScript côté navigateur.
 *
 * @throws PDOException si la connexion échoue
 */
function get_db_connection(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST
         . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME
         . ';charset=' . DB_CHARSET;

    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}


/**
 * Lance un script Python et récupère sa sortie (stdout + stderr).
 *
 * Chaque morceau est passé par escapeshellarg(), qui l'entoure de guillemets.
 * Deux points de vigilance sous Windows :
 *
 *  1. On n'utilise PAS escapeshellcmd() sur le chemin de l'interpréteur :
 *     sous Windows, cette fonction REMPLACE les antislashs par des espaces,
 *     ce qui détruit le chemin. C'était le cas dans la version précédente.
 *
 *  2. On n'ajoute PAS de paire de guillemets englobante autour de la commande.
 *     Ce contournement, souvent recommandé pour cmd.exe, fait ici échouer
 *     l'appel ("La syntaxe du nom de fichier [...] est incorrecte") ; vérifié
 *     avec un chemin contenant espaces et parenthèses, le quoting par argument
 *     suffit.
 *
 * @param string[]    $arguments  Chemin du script, puis ses arguments.
 * @param string|null $repertoire Répertoire de travail le temps de l'appel.
 * @return array{sortie: string[], code: int}
 */
function executer_python(array $arguments, ?string $repertoire = null): array
{
    $morceaux = array_map(
        'escapeshellarg',
        array_merge([PYTHON_BIN], $arguments)
    );

    $commande = implode(' ', $morceaux) . ' 2>&1';

    $repertoire_precedent = null;

    if ($repertoire !== null && is_dir($repertoire)) {
        $repertoire_precedent = getcwd();
        chdir($repertoire);
    }

    $sortie = [];
    $code   = 0;

    exec($commande, $sortie, $code);

    if ($repertoire_precedent !== false && $repertoire_precedent !== null) {
        chdir($repertoire_precedent);
    }

    return ['sortie' => $sortie, 'code' => $code];
}


/**
 * Écrit un payload dans un fichier JSON temporaire et renvoie son chemin.
 *
 * Les scripts IA attendent un objet JSON. On ne peut pas le passer
 * directement en ligne de commande sous Windows : escapeshellarg() y remplace
 * les guillemets (ainsi que % et !) par des espaces, ce qui rendrait le JSON
 * illisible. Les scripts Python acceptent donc un chemin de fichier, qui lui
 * ne contient aucun caractère problématique.
 *
 * L'appelant est responsable de la suppression du fichier après usage.
 */
function ecrire_payload_json(array $payload): string
{
    $chemin = tempnam(sys_get_temp_dir(), 'irve');

    file_put_contents($chemin, json_encode($payload, JSON_UNESCAPED_UNICODE));

    return $chemin;
}


/* =====================================================
   5. CONSTANTES DÉRIVÉES
===================================================== */

/** Interpréteur Python effectivement utilisé pour les appels IA. */
define('PYTHON_BIN', detecter_python());

/** Préfixe URL du projet, utilisé par le menu latéral. */
define('BASE_URL', base_url());
