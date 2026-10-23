# Plan de tests — Application de suivi d'efficacité (Fondation EPI)

> Document vivant, mis à jour à chaque étape de développement. Il répond à la compétence **AT3 — Préparer le déploiement d'une application sécurisée** (tests, plan de tests, DevOps) du référentiel CDA.

## 1. Stratégie

- **Outil** : PHPUnit 12 via `php artisan test`, exécuté dans le conteneur Sail (`docker compose exec laravel.test php artisan test`).
- **Base de test** : MariaDB 12.3, base `testing` dédiée (créée par Sail), remise à zéro par `RefreshDatabase`. Jamais SQLite : les contraintes d'unicité, les décimaux et les clés étrangères doivent être testés sur le moteur de production.
- **Pyramide** : beaucoup de tests unitaires (purs, sans base, rapides), des tests d'intégration ciblés sur la persistance et les connecteurs, quelques tests fonctionnels de bout en bout par cas d'utilisation, et des tests de sécurité systématiques sur chaque endpoint.
- **Données** : factories (états nommés) pour les tests ; `DemoSeeder` pour les recettes manuelles et la soutenance ; `ReferentielSeeder` pour le barème réel.
- **Critère de réussite global** : suite verte en local **et** en intégration continue avant tout merge sur `main`.
- **Automatisation** : GitHub Actions (`.github/workflows/tests.yml`) rejoue la suite à chaque push et pull request contre un service MariaDB 12.3.

## 2. Couverture par type de test

Légende : ✅ effectué · 🔜 prévu (étape indiquée) · ⏸ conditionnel

| Type | Objet | Tests | État |
|---|---|---|---|
| **Unitaires** — composants métier et accès aux données | Héritage de droits `Role::auMoins()` | `tests/Unit/Enums/RoleTest` (7 cas) | ✅ |
| | Lien catégorie d'heures → colonne du cache | `tests/Unit/Enums/CategorieHeureTest` | ✅ |
| | Normalisation du code tarif EpiLogg (« T 8.1 », NULL) | `tests/Unit/Models/TarifTest` (7 cas) | ✅ |
| | Intervalle de validité d'une règle (`couvre`, `estOuverte`) | `tests/Unit/Models/RegleConversionTest` (6 cas) | ✅ |
| | Calcul de la cascade d'efficacité (dispo EPI, dispo client, ratio, ratio brut, NULL si dénominateur ≤ 0, décimaux exacts) — jeu d'essai de la Fondation | `tests/Unit/Etl/CalculEfficaciteTest` (5 tests) | ✅ |
| | Conversion forfaits × barème à la date de chaque prestation (30 min en 2025, 45 min en 2026), quantité décimale, type non qualifié ignoré | `tests/Feature/Etl/ConsolidationServiceTest` (4 tests) | ✅ |
| **Intégration** — composants ↔ base / sources | Historisation : chaque date retombe sur le barème de son époque ; une seule règle ouverte ; délégation type → tarif | `tests/Feature/Persistance/HistorisationReglesTest` (5 tests) | ✅ |
| | Idempotence des imports : unicité `reference_externe`, unicité (salarié, compteur, jour), `upsert` sans doublon, décimal conservé | `tests/Feature/Persistance/IdempotenceImportsTest` (5 tests) | ✅ |
| | Seeders : barème conforme à l'Excel, référentiel idempotent sans réécriture de l'historique, cas limites de la démo, cache vide | `tests/Feature/Persistance/SeedersTest` (3 tests) | ✅ |
| | `DskHttp` sur réponses HTTP enregistrées (`Http::fake`, d'après l'OpenAPI DSK) : login et jeton en cache, jeton statique, pagination, reconnexion sur 401, minutes par compte, minimisation des champs, refus de connexion | `tests/Feature/Connecteurs/DskHttpTest` (5 tests) | ✅ |
| | `EpiloggSql` sur une base `testing_epilogg` reproduisant le schéma réel (benefits → attends → attend_types, interventions, users) : session READ ONLY et écriture refusée, types archivés, statut des comptes, bornes de la fenêtre, quantité décimale, forfait déplacement | `tests/Feature/Connecteurs/EpiloggSqlTest` (4 tests) | ✅ |
| | Changement de barème : clôture + création en transaction, refus du chevauchement et des valeurs nulles, recalcul des mois concernés sans toucher au passé | `tests/Feature/Etl/MatriceConversionServiceTest` (3 tests) | ✅ |
| **Fonctionnels / acceptation** — cas d'utilisation de bout en bout | Tableau de bord salarié (`GET /api/statistiques/me`, `/salaries/{id}`) : 12 mois dans l'ordre, exemple 1 de la Fondation restitué (0,8367), synthèse annuelle = cascade sur les sommes, années disponibles, année courante par défaut, année vide → synthèse nulle, identité minimale (RGPD) | `tests/Feature/Api/TableauDeBordSalarieTest` (8 tests) | ✅ |
| | Statistiques d'un service (`GET /api/statistiques/services/{id}`, `GET /api/services`) : synthèse par salarié triée, total du service = rapport des sommes, filtre mois, liste des services selon le rôle | `tests/Feature/Api/StatistiquesServiceTest` (5 tests) | ✅ |
| | Vue globale (`GET /api/statistiques/globales`) : synthèse par service + « Total EPI » (0,7391 = rapport des totaux, et non 0,6684 = moyenne des ratios), salariés sans service regroupés | `tests/Feature/Api/StatistiquesGlobalesTest` (3 tests) | ✅ |
| | Import DSK / EpiLogg de bout en bout (sources simulées) : référentiels synchronisés, appariement par email persisté, cas signalés (sans correspondance, sans email, type sans tarif, noPers inconnu), tarif de l'Administrateur jamais écrasé, **fenêtre remplacée** (annulation et correction reflétées, aucun doublon), journal `imports` (succès / échec avec message), consolidation du cache | `tests/Feature/Etl/EtlServiceTest` (6 tests) | ✅ |
| | Un changement de barème déclenche le recalcul des mois concernés | `MatriceConversionServiceTest` | ✅ |
| | **Administration** — supervision des imports (journal paginé, filtres, résumé / erreur, déclencheur), import manuel mis en file d'attente (période par défaut, job exécuté avec sources simulées : journal au nom de l'Administrateur), recalcul à la demande | `tests/Feature/Admin/ImportsAdminTest` (5 tests) | ✅ |
| | Qualification d'un compteur DSK et d'un type de suivi : appliquée ET cache des mois concernés recalculé (compteur « autre » → maladie : total et dispo EPI changent ; type non qualifié → 8.1 : prestations valorisées) ; aucun recalcul sans données ; entrées invalides refusées sans effet | `tests/Feature/Admin/QualificationAdminTest` (4 tests) | ✅ |
| | Matrice via l'API : historique par tarif, nouveau barème = clôture à la veille + création + recalcul (mars à 30 min, avril à 45 min), date incohérente / minutes nulles refusées sans création | `tests/Feature/Admin/MatriceAdminTest` (3 tests) | ✅ |
| | Organigramme : liste filtrable des utilisateurs, affectation rôle / service / email d'appariement, désactivation = jetons révoqués, garde-fou (un administrateur ne se rétrograde ni ne se désactive), mot de passe initial (politique 12 caractères, connexion possible ensuite, anciens jetons révoqués), services renommables sans toucher au code DSK | `tests/Feature/Admin/OrganigrammeAdminTest` (6 tests) | ✅ |
| | Import **réel** EpiLogg (VPN, base de production en lecture seule, 01/09 → 22/10/2026) : 19 types synchronisés avec le bon tarif, 3 265 prestations lues, 33 comptes sans correspondance signalés (aucun salarié DSK en base) | manuel, `php artisan epi:import --source=epilogg` | ✅ 22/10/2026 |
| | Import réel DSK | bloqué : `SRV-DATA` injoignable via le VPN actuel (tous ports filtrés) — à ouvrir avec CBC | ⏸ |
| | Export Excel : mêmes filtres et même périmètre que l'écran | `tests/Feature/Api/ExportExcelTest` | 🔜 étape export |
| **Sécurité** | 401 sans jeton ou avec jeton invalide / révoqué sur les routes protégées ; jeton révocable par appareil ; empreinte seule stockée | `tests/Feature/Securite/AuthentificationTest` (9 tests) | ✅ |
| | 403 par rôle avec héritage (matrice 3 rôles × 3 niveaux) ; 401 avant tout contrôle de rôle ; 403 sans trace ni classe d'exception (debug off) | `tests/Feature/Securite/AutorisationsTest` (11 tests) | ✅ |
| | 403 par **périmètre** (policies) : salarié sur les données d'un autre, coordinateur sur un autre service, coordinateur sans service (pas de « null = null »), salarié sur `/services` et `/globales` ; 403 JSON sans trace ; 404 sur identifiant inconnu ou non numérique | `TableauDeBordSalarieTest`, `StatistiquesServiceTest`, `StatistiquesGlobalesTest` | ✅ |
| | Routes d'administration (8 routes) : 401 sans jeton, 403 pour Coordinateur et Salarié | `tests/Feature/Admin/AccesAdminTest` (8 cas) | ✅ |
| | Validation serveur : entrées invalides → 422, jamais 500 (année non entière, hors bornes 2000..N+1, mois hors 1..12) ; message identique email inconnu / mot de passe faux (pas d'énumération de comptes) ; compte désactivé → 403 | `AuthentificationTest`, `TableauDeBordSalarieTest` | ✅ |
| | Force brute : 6ᵉ tentative en une minute → 429 | `AuthentificationTest` | ✅ |
| | Réponses sans données RH (minimisation RGPD : pas de matricule, noPers, id EpiLogg, dates d'entrée/sortie) | `AuthentificationTest` | ✅ |
| | Injection SQL : 6 charges (`' OR 1=1 --`, `1 OR 1=1`, `UNION SELECT`, `; DROP TABLE`, flottant, tableau) rejetées en 422 par la validation, table intacte ; charge dans un identifiant de route → 404 (`whereNumber`, sans coercition MariaDB) ; preuve par le journal SQL que la valeur valide voyage en **paramètre lié** et n'est jamais concaténée | `tests/Feature/Securite/InjectionTest` (8 tests) | ✅ |
| | Écriture refusée sur la connexion `epilogg` (session `READ ONLY`) ; l'import refuse de démarrer si la session n'est pas en lecture seule | `EpiloggSqlTest` | ✅ |
| | Veille vulnérabilités : `composer audit` (22/10/2026 : 6 avis → 0 après mise à jour), `npm audit` au premier `npm install`, vérification ChainDrop/Shai-Hulud du 23/09/2026 (voir §4) | manuel, à ajouter en CI | ✅ (ponctuel) / 🔜 CI |
| **Performance** | Consultation de 5 ans d'historique via le cache (1 800 lignes = 30 salariés × 60 mois, 2022 → 2026) : < 1 s pour un salarié, < 2 s pour la vue « Total EPI ». Justifié par le contexte (5 ans d'historique à servir) ; seuils larges pour détecter une régression d'ordre de grandeur, pas une variation de 10 % | `tests/Feature/Performance/CacheStatistiquesTest` (1 test, ≈ 1,8 s peuplement compris) | ✅ |
| **Jeu d'essai** | Fonctionnalité la plus représentative : calcul mensuel d'efficacité. Données d'entrée, résultat attendu (calcul manuel de la Fondation), résultat obtenu | §3 | ✅ |
| **Recette métier** | Un mois réel calculé à la main par la Fondation, comparé au résultat de l'application sur les vraies données importées | procès-verbal de recette | 🔜 après connecteurs, avec Michel Flammant |

## 3. Jeu d'essai de référence — exécuté le 22/10/2026

Source : **exemple 1** du fichier « Données app EPI.xlsx » (avril 2026, un salarié du service AISP — nom retiré). C'est l'exemple retenu car il est arithmétiquement cohérent ; l'exemple « 127 h » du même fichier liste 30 h de congés annuels mais n'en déduit que 2 (incohérence du fichier, signalée à la Fondation). L'exemple 2 (« Total EPI », T1 2026) est également rejoué.

| Donnée d'entrée | Valeur |
|---|---|
| Heures normales (DSK) | 100 h |
| Congés annuels | 12 h |
| Récupération de solde | 4 h |
| Maladie | 6 h |
| Congé formation ministère | 4 h |
| Supervision individuelle | 2 h |
| Prestations facturables (forfaits 8.1 × 60 min) | 82 h |
| Activité interne (forfaits « Autres » × 60 min) | 2 h |

| Résultat attendu (calcul manuel de la Fondation) | Calcul | Valeur | Résultat obtenu (moteur) |
|---|---|---|---|
| Total heures dispo | 100 + 12 + 4 + 6 + 4 + 2 | 128 h | 7 680 min ✅ |
| Heures congé / récup. prises | 12 + 4 | 16 h | 960 min ✅ |
| Heures dispo EPI | 128 − 16 − 6 | 106 h | 6 360 min ✅ |
| Heures dispo client | 106 − 4 − 2 − 2 | 98 h | 5 880,00 min ✅ |
| **Efficacité réelle** | 82 / 98 | **0,8367** | **0,8367** ✅ |
| Efficacité brute | (82 + 2) / 128 | 0,6563 | 0,6562 ✅ (troncature à 4 décimales) |
| Objectif atteint ? | 0,8367 ≥ 0,75 | oui | `atteintObjectif()` = vrai |

Exemple 2 (Total EPI, T1 2026) : total 9 232 h → dispo EPI 9 078 h → dispo client 8 774 h ; facturables 7 220 h ; efficacité réelle **0,8228** (fichier : 0,82288…) ✅ ; brute **0,8065** (fichier : 0,80654…) ✅.

Tests : `tests/Unit/Etl/CalculEfficaciteTest` (objet-valeur pur) et `tests/Feature/Etl/ConsolidationServiceTest` (même jeu d'essai rejoué depuis les flux bruts en base, avec le barème à la date de chaque prestation).

## 4. Anomalies tracées

| Date | Anomalie | Cause | Correction | Test de non-régression |
|---|---|---|---|---|
| 15/08/2026 | `composer create-project` a installé Laravel 12 au lieu de 13, sans erreur | Composer résout la version compatible avec le PHP qui l'exécute (XAMPP 8.2) ; Laravel 13 exige PHP ≥ 8.3 | Re-scaffolding dans un conteneur PHP 8.4 ; diagnostic `composer why-not laravel/framework v13.0.0` | Version figée dans `composer.lock` ; CI sur PHP 8.5 |
| 15/08/2026 | Erreur 500 `tempnam()` sur les vues compilées | Squelette créé par `root`, conteneur applicatif en utilisateur `sail` (UID 1000) | `chown -R sail:sail storage bootstrap/cache` | — (environnement) |
| 15/08/2026 | MariaDB refuse de démarrer : `bind: access permissions` sur 3306 | Plages de ports réservées par Hyper-V/WinNAT sous Windows | `FORWARD_DB_PORT=13306` (port publié côté hôte uniquement) | — (environnement) |
| 22/10/2026 | Migration `restructure_tarifs_and_statistics` : `Cannot drop index … needed in a foreign key constraint` | MariaDB avait adossé la clé étrangère à l'index composite existant au lieu d'en créer un dédié | Supprimer la contrainte avant l'index, puis la colonne (`up` et `down`) | Cycle `migrate` / `rollback` / `migrate` joué deux fois |
| 22/10/2026 | Après l'échec ci-dessus, `Table 'tarifs' already exists` | Le DDL MySQL/MariaDB n'est pas transactionnel : état partiel après échec | Base de dev recréée (`migrate:fresh`) ; règle : tester chaque migration en `up` et `down` sur base jetable avant commit | Idem |
| 23/09/2026 | Vérification de l'attaque npm « ChainDrop » (worm keyv/cacheable du 04/08/2026) | — | Aucun `node_modules` dans le projet, aucun indicateur de compromission sur la machine (hooks `.claude`/`.vscode`, fichiers `setup.mjs`/`Math_Symbol.js`, cache npm, dépôts GitHub) | Procédure consignée dans PREPARATION-JURY ; `npm install --ignore-scripts` recommandé au premier install |
| 22/10/2026 | `composer audit` : 6 avis sur 5 paquets (Laravel < 13.30 XSS page de debug, commonmark DoS, flysystem, symfony http-foundation et yaml ReDoS) | Dépendances figées au 15/08 par `composer.lock` | `composer update` ciblé sur les 5 paquets (Laravel 13.25 → 13.38), audit → 0 avis, suite de tests verte | `composer audit` à chaque étape ; à automatiser en CI |
| 22/10/2026 | L'exemple « 127 h » du fichier de la Fondation est incohérent (30 h de congés listées, 2 h déduites) ; il avait été repris tel quel dans la documentation | Exemple de maquette non recalculé | Jeu d'essai basé sur les exemples 1 et 2, cohérents ; documentation corrigée ; point à confirmer avec M. Flammant (les congés annuels sont bien déduits, comme dans les deux autres exemples) | `CalculEfficaciteTest` |
| 22/10/2026 | 3 tests ETL en échec à la première exécution | (1) un cas de test oubliait que la formation compte dans le total ; (2) cas d'énumération utilisés comme clés de tableau (TypeError PHP) ; (3) deux doublures dans un seul fichier, invisibles pour l'autoload PSR-4 ; (4) attente fausse : une prestation d'un type non qualifié EST conservée brute, elle n'est exclue qu'au calcul | Tests corrigés ; le moteur n'a pas changé | `EtlServiceTest`, `ConsolidationServiceTest` |
| 22/10/2026 | 3 tests de sécurité en échec à la première exécution | (1) ressource JSON enveloppée dans `data` ; (2) le guard de test garde l'utilisateur résolu entre deux requêtes ; (3) `APP_DEBUG=true` ajoute `exception` aux réponses d'erreur | (1) `JsonResource::withoutWrapping()` ; (2) `forgetGuards()` entre les requêtes du test ; (3) le test force `app.debug=false` (comportement de production) | Tests `AuthentificationTest`, `AutorisationsTest` |

## 5. Synthèse des résultats

| Date | Suite | Résultat |
|---|---|---|
| 22/10/2026 | 8 classes, 39 tests, 78 assertions (unitaires + intégration persistance + seeders), MariaDB 12.3 | ✅ 39 réussis, 0 échec, 34 s |
| 22/10/2026 | 10 classes, 59 tests, 138 assertions (+ authentification et autorisations), après mise à jour de sécurité des dépendances | ✅ 59 réussis, 0 échec, 43 s ; CI GitHub Actions ✅ |
| 22/10/2026 | 16 classes, 86 tests, 279 assertions (+ moteur de calcul, consolidation, matrice, ETL de bout en bout, connecteurs DSK et EpiLogg) | ✅ 86 réussis, 0 échec, 55 s ; CI GitHub Actions ✅ |
| 22/10/2026 | 21 classes, 111 tests, 444 assertions (+ API de consultation : tableau de bord, service, globale ; policies de périmètre ; injection SQL ; performance du cache) | ✅ 111 réussis, 0 échec, 64 s ; CI GitHub Actions ✅ |
| 23/10/2026 | 26 classes, 137 tests, 668 assertions (+ administration : supervision et import manuel en file d'attente, recalcul, qualification des référentiels, matrice, organigramme, accès) | ✅ 137 réussis, 0 échec, 90 s |

*(ligne ajoutée à chaque étape)*
