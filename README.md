Chaîne DevSecOps — Triage automatisé des vulnérabilités
Une chaîne d'intégration continue qui analyse une application à chaque commit, trie les vulnérabilités trouvées pour ne garder que celles qui comptent réellement, propose les correctifs, et empêche le déploiement de ce qui n'est pas conforme.

Projet en cours de construction. L'avancement réel est indiqué plus bas. Les étapes non cochées ne sont pas encore implémentées.

Le problème
Les outils d'analyse de sécurité remontent plusieurs centaines de vulnérabilités par application. Une équipe ne peut pas les traiter toutes, alors elle finit par les ignorer en bloc — et de vraies failles exploitables partent en production, noyées dans le bruit.

Le problème n'est pas de détecter : les scanners font déjà très bien ce travail. Le problème est de savoir lesquelles comptent vraiment, et d'empêcher les autres de passer.

Le résultat visé
Faire en sorte qu'une équipe traite les quelques failles réellement exploitables au lieu d'en ignorer plusieurs centaines.

Mesure	Valeur
Vulnérabilités brutes remontées par les scanners	à mesurer (étape 4)
Vulnérabilités réellement actionnables après triage	à mesurer (étape 5)
Réduction du bruit	à calculer
Ces chiffres seront mesurés sur l'application cible, pas estimés.

Comment ça marche
Déclenchement — un push ou une pull request lance la chaîne.
Analyse — l'image et le code sont scannés, un inventaire des dépendances (SBOM) est généré.
Triage déterministe — chaque vulnérabilité est notée sur des critères vérifiables : probabilité d'exploitation (EPSS), présence au catalogue des failles activement exploitées (CISA KEV), atteignabilité réelle du composant vulnérable, et présence dans l'image d'exécution plutôt qu'à l'étape de build.
Enrichissement — un modèle de langage explique les failles retenues et rédige les pull requests de correctif.
Blocage — le cluster Kubernetes refuse toute image non signée ou porteuse d'une faille critique.
Suivi — un tableau de bord expose la couverture, le délai moyen de remédiation et le taux de conformité.
Le rôle du modèle de langage
Le tri est fait par des règles déterministes et vérifiables, jamais par le modèle. Celui-ci n'intervient qu'après la sélection, pour expliquer une faille en langage clair et proposer un correctif. Une décision de sécurité doit être reproductible et auditable ; une génération de texte ne l'est pas.

Application cible
La partie web du projet IRVE (PHP / MySQL), une application existante réutilisée ici comme cobaye. Son code n'est pas modifié : l'objet de ce dépôt est la chaîne, pas l'application.

Avancement
 1. Conteneurisation — Dockerfile de l'application, build reproductible
 2. Orchestration locale — base de données conteneurisée, docker compose
 3. Intégration continue — build automatique à chaque push (GitHub Actions)
 4. Analyse — scanners branchés, inventaire des dépendances, sorties normalisées → mesure du volume brut
 5. Moteur de triage — scoring EPSS / KEV / atteignabilité → mesure du volume actionnable
 6. Enrichissement et remédiation — explication des failles, pull requests de correctif
 7. Déploiement Kubernetes — cluster local, manifestes
 8. Contrôle d'admission — politiques Kyverno, signature d'images Cosign
 9. Observabilité — tableau de bord des indicateurs de sécurité
Stack
Docker · GitHub Actions · Trivy · Syft · Semgrep · Checkov · Kubernetes (k3d) · Kyverno · Cosign · Prometheus · Grafana

Démarrage
# à compléter à la fin de l'étape 2
Journal de construction
Chaque étape est documentée dans journal.md : le problème résolu, ce qui casserait sans elle, et les points encore ouverts.

Auteur
Trésor Virenque Fowe Takam — élève ingénieur ISEN Nantes, orientation DevOps / Cloud LinkedIn · GitHub
