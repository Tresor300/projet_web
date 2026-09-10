# Journal de construction

## Étape 1 — Conteneurisation

**Problème résolu.** L'application ne tournait que sur ma machine
avec XAMPP. Elle tourne maintenant à l'identique dans un conteneur
Linux, reconstructible de zéro par n'importe qui.

**Mesure.** L'ordre des instructions du Dockerfile change le temps
de rebuild : 42 s quand COPY précède RUN, 5,7 s dans l'ordre
inverse. Docker rejoue toutes les couches situées après la première
qui a changé ; comme le code change souvent et les extensions
jamais, les extensions doivent venir en premier.

**Encore flou.** À compléter.
pour l'instant l'image à été crée mais malheurement ell n'a pas accès a la base de donnée, ce qui est encore flou c'et l'essence même du projet je sais que l'on veut devollppuer un pipeline qui sera en mesure de controller les de potentiel erreur dans le flux de transmission des donnée mais j'usque le je ne visualise toujours pas comment 
**Encore flou.** Mon image contient 191 paquets logiciels alors que
je n'en ai choisi qu'un. Je ne sais pas encore lesquels sont
vulnérables ni comment le savoir automatiquement.
##volume : c'est le mécanisme qui relie un dossier de ta machine à un dossier dans un conteneur. Tu le reverras souvent.

## Étape 2 — Base de données conteneurisée

**Problème résolu.** L'application dépendait d'un MySQL installé
sur ma machine. Elle embarque maintenant sa propre base : le projet
entier démarre avec `docker compose up`, sans rien installer.

**Ce qui casserait sans.** Le conteneur web chercherait sa base à
127.0.0.1, c'est-à-dire chez lui, où rien n'écoute.

**Ce que j'ai appris en cassant.** Windows ignore la casse, Linux
non — ni pour les fichiers, ni pour les tables. Une application qui
tourne sous XAMPP peut casser dans un conteneur pour cette seule
raison.

**Encore flou.** À compléter.

## Étape 3 — Intégration continue

**Problème résolu.** L'image ne se construisait que sur ma machine.
Elle est maintenant reconstruite à chaque commit sur une machine
neuve chez GitHub, ce qui prouve qu'elle est reproductible ailleurs
que chez moi.

**Ce que j'ai compris.** `runs-on: ubuntu-latest` désigne la machine
qui exécute le build, pas le système à l'intérieur de l'image. Mon
image reste sur Debian quel que soit l'hôte : c'est justement ce que
garantit la conteneurisation.

**Encore flou.** À compléter

## Étape 4 — Mesure du volume brut

**Mesure A : 1911 vulnérabilités** détectées par Trivy dans l'image,
réparties sur 191 paquets dont un seul (PHP) a été choisi
explicitement.

**Ce que j'ai observé.** 1911 alertes brutes, dont 14 critiques,
qui ne sont en fait que 5 failles distinctes comptées plusieurs
fois. Aucune n'a de correctif disponible, et aucune n'est
atteignable depuis mon application.

## Étape 4 — Analyse automatisée

**Problème résolu.** L'image est scannée à chaque commit, plus
seulement quand j'y pense.

**Mesure A : 1911 vulnérabilités**, dont 14 critiques, qui ne sont
en réalité que 5 failles distinctes comptées plusieurs fois.

**Limite atteinte.** Le scan affiche mais ne bloque rien. Une faille
critique passerait inaperçue dans les logs.

**Encore flou.** À compléter.

**Règle 2 : faire échouer le build.**
Le scan ne se contente plus d'afficher : il arrête la chaîne quand
il trouve des vulnérabilités corrigeables. Un rapport dont la
lecture est facultative n'est pas lu — c'est vérifiable sur mon
propre projet. En faisant échouer le build, le traitement des
failles devient obligatoire.

**Règle 3 : l'atteignabilité.**
Sur 42 lignes, il n'y a que 2 failles réelles : une dans OpenSSL et
une quarantaine dans linux-libc-dev. Or linux-libc-dev ne contient
que des en-têtes de compilation — un conteneur n'a pas de noyau, il
emprunte celui de l'hôte. Ces failles ne sont donc pas atteignables.
Bilan : 1911 alertes brutes, 1 seule méritant attention.