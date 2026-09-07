# Installation en local (Windows)

Ce projet tournait sur un serveur Linux distant (`/var/www/tv_fowet/`). Il est
désormais paramétré pour tourner en local sur un poste Windows.

---

## 1. Pourquoi EasyPHP 5.3.6.0 ne suffit pas

EasyPHP 5.3.6.0 embarque **PHP 5.3.6 (2011)**. Le code du projet est écrit pour
**PHP 8** et utilise des syntaxes qui n'existent tout simplement pas en 5.3 :

| Syntaxe utilisée              | Version minimale requise |
| ----------------------------- | ------------------------ |
| Tableaux courts `[...]`       | PHP 5.4                  |
| `JSON_UNESCAPED_UNICODE`      | PHP 5.4                  |
| `??` (null coalescent)        | PHP 7.0                  |
| `declare(strict_types=1)`     | PHP 7.0                  |
| Types scalaires `string $msg` | PHP 7.0                  |
| Type de retour `: never`      | PHP 8.1                  |

Aucune page ne peut s'exécuter sur PHP 5.3 : le moteur refuse les fichiers dès
l'analyse syntaxique. Il faut donc un environnement récent.

---

## 2. Installer un environnement PHP 8

**Laragon** (recommandé, le plus simple) : <https://laragon.org/downl>
Prendre l'édition *Full*, qui inclut Apache, PHP 8 et MySoad/QL.

**XAMPP** est une alternative équivalente : <https://www.apachefriends.org/>
Prendre une version affichant **PHP 8.2** ou supérieur.

Les deux fournissent MySQL avec l'utilisateur `root` **sans mot de passe**,
ce qui correspond à la configuration déjà en place dans `config.php`.

---

## 3. Placer le projet

Copier le dossier `projet_web` dans la racine web :

| Stack   | Destination              | URL d'accès                    |
| ------- | ------------------------ | ------------------------------ |
| Laragon | `C:\laragon\www\projet_web`  | <http://localhost/projet_web/> |
| XAMPP   | `C:\xampp\htdocs\projet_web` | <http://localhost/projet_web/> |

Le projet détecte son propre emplacement : il fonctionne aussi bien dans un
sous-dossier que derrière un hôte virtuel pointant sur sa racine.

Démarrer ensuite **Apache** et **MySQL** depuis l'interface de Laragon/XAMPP.

---

## 4. Installer les dépendances Python

Python 3.13 est déjà installé sur ce poste avec `scikit-learn`, `joblib`,
`pandas` et `numpy`. Il manque uniquement le connecteur MySQL, utilisé par le
script d'import :

```powershell
py -m pip install mysql-connector-python
```

---

## 5. Créer et remplir la base de données

Aucun export SQL du serveur distant n'est nécessaire : tout est reconstruit à
partir de `partie_1/export_IA.csv`.

```powershell
cd C:\laragon\www\projet_web\partie_1
py import_fichier_csv.py
```

Le script crée la base `tv_fowet` si elle n'existe pas, puis les tables
`DEPARTEMENT`, `STATION`, `POINT_DE_CHARGE`, la vue `vue_dataset_ia` ainsi que
`PREDICTION_IMPLANTATION` et `PREDICTION_PUISSANCE`.

> Les deux tables de prédiction n'étaient créées nulle part auparavant : sans
> elles, les boutons de la fonctionnalité 5 échouaient.

---

## 6. Vérifier le chemin de Python

> **Rien à modifier si le chemin est déjà bon** — c'est une simple
> vérification, pas une manipulation. Sur ce poste, elle est déjà correcte :
> tu peux passer à l'étape 7.

### Pourquoi cette étape existe

Les fonctionnalités 4 et 5 exécutent des modèles d'IA écrits en Python. Ce
n'est pas toi qui lances ces scripts : c'est **PHP**, donc **Apache**, qui les
lance automatiquement quand tu cliques sur un bouton dans la page.

Or Apache ne tourne pas dans ta session Windows et n'hérite pas forcément de
ton `PATH`. Un simple `python` dans le code échouerait sans message explicite.
On indique donc le chemin **complet** vers `python.exe` dans `config.php` :

```php
define('PYTHON_EXE', 'C:/Users/PC/AppData/Local/Programs/Python/Python313/python.exe');
```

### Comment vérifier

Coller cette commande dans PowerShell. Elle compare le chemin déclaré à la
réalité et affiche quoi faire :

```powershell
$exe = 'C:/Users/PC/AppData/Local/Programs/Python/Python313/python.exe'
if (Test-Path $exe) { & $exe --version; "OK : rien a modifier" }
else { "A CORRIGER dans config.php -> "; py -c "import sys; print(sys.executable)" }
```

Si le chemin est faux, la commande affiche le bon : le recopier dans
`PYTHON_EXE`, en remplaçant les `\` par des `/`. C'est la seule ligne à
changer, et uniquement si tu déplaces ou mets à jour Python.

---

## 7. Lancer l'application

Page d'accueil : <http://localhost/projet_web/fonctionnalite_1/accueil.php>

| Fonctionnalité      | URL                                                     |
| ------------------- | ------------------------------------------------------- |
| 1 — Accueil         | `/projet_web/fonctionnalite_1/accueil.php`              |
| 2 — Carte           | `/projet_web/fonctionnalite_2/visualisation.php`        |
| 3 — Statistiques    | `/projet_web/fonctionnalite_3/php/statistiques.php`     |
| 4 — Clusters IA     | `/projet_web/fonctionnalite_4/php/clusters.php`         |
| 5 — Classification  | `/projet_web/fonctionnalite_5/html/prediction.php`      |

---

## Configuration : un seul fichier

Tous les identifiants et chemins sont dans **`config.php` à la racine du
projet**. Ils étaient auparavant dupliqués dans cinq fichiers différents.

```php
define('DB_HOST', '127.0.0.1');   // et non 'localhost' : voir ci-dessous
define('DB_NAME', 'tv_fowet');
define('DB_USER', 'root');
define('DB_PASS', '');
define('PYTHON_EXE', 'C:/.../python.exe');
```

On utilise `127.0.0.1` plutôt que `localhost` car, sous Windows, `localhost`
peut être résolu en IPv6 (`::1`) alors que MySQL n'écoute qu'en IPv4 — ce qui
provoque une longue attente suivie d'une erreur de connexion.

---

## En cas de problème

**« Erreur de connexion BDD »**
MySQL n'est pas démarré, ou la base n'a pas été importée (étape 5).

**La carte reste vide / le tableau affiche une erreur**
Ouvrir directement `/projet_web/fonctionnalite_2/php/get_points.php` : la
réponse JSON contient le message d'erreur exact.

**« Échec de l'appel Python »**
Vérifier `PYTHON_EXE` dans `config.php`, puis tester le script à la main :

```powershell
py fonctionnalite_4\IA\ScriptBesoin_2.py --latitude 48.8566 --longitude 2.3522
```

**Les prédictions de la fonctionnalité 5 échouent**
Vérifier que `PREDICTION_IMPLANTATION` et `PREDICTION_PUISSANCE` existent dans
phpMyAdmin. Sinon, relancer `import_fichier_csv.py`.
