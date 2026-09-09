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
volume : c'est le mécanisme qui relie un dossier de ta machine à un dossier dans un conteneur. Tu le reverras souvent.