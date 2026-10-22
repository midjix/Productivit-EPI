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
| | Calcul de la cascade d'efficacité (dispo EPI, dispo client, ratio, ratio brut, NULL si dénominateur ≤ 0) | `tests/Unit/Services/CalculEfficaciteTest` | 🔜 étape ETL |
| | Conversion forfaits × barème avec quantité décimale (1,5 × 45 = 67,50) | `tests/Unit/Services/ConversionForfaitsTest` | 🔜 étape ETL |
| **Intégration** — composants ↔ base / sources | Historisation : chaque date retombe sur le barème de son époque ; une seule règle ouverte ; délégation type → tarif | `tests/Feature/Persistance/HistorisationReglesTest` (5 tests) | ✅ |
| | Idempotence des imports : unicité `reference_externe`, unicité (salarié, compteur, jour), `upsert` sans doublon, décimal conservé | `tests/Feature/Persistance/IdempotenceImportsTest` (5 tests) | ✅ |
| | Seeders : barème conforme à l'Excel, référentiel idempotent sans réécriture de l'historique, cas limites de la démo, cache vide | `tests/Feature/Persistance/SeedersTest` (3 tests) | ✅ |
| | `ConnecteurDsk` sur réponses HTTP enregistrées (`Http::fake`) : login/token, pagination, minutes par compte, compte inconnu → catégorie « autre » | `tests/Feature/Connecteurs/ConnecteurDskTest` | 🔜 étape connecteurs |
| | `ConnecteurEpilogg` sur base de test simulant `attend_types` / `benefits` : appariement par email, prestation supprimée côté source → réconciliation | `tests/Feature/Connecteurs/ConnecteurEpiloggTest` | 🔜 étape connecteurs |
| | Changement de barème : clôture + création dans une transaction, refus du chevauchement | `tests/Feature/Services/MatriceConversionServiceTest` | 🔜 étape matrice |
| **Fonctionnels / acceptation** — cas d'utilisation de bout en bout | Un salarié ne lit que ses données (`GET /api/statistiques/me`) | `tests/Feature/Api/TableauDeBordSalarieTest` | 🔜 étape API |
| | Un coordinateur lit son service, pas les autres | `tests/Feature/Api/StatistiquesServiceTest` | 🔜 étape API |
| | Un import (manuel ou planifié) journalise son résultat dans `imports` et recalcule le cache | `tests/Feature/Etl/ImportTest` | 🔜 étape ETL |
| | Un changement de barème déclenche le recalcul des mois concernés | `tests/Feature/Etl/RecalculApresBaremeTest` | 🔜 étape ETL |
| | Export Excel : mêmes filtres et même périmètre que l'écran | `tests/Feature/Api/ExportExcelTest` | 🔜 étape export |
| **Sécurité** | 401 sans jeton ou avec jeton invalide / révoqué sur les routes protégées ; jeton révocable par appareil ; empreinte seule stockée | `tests/Feature/Securite/AuthentificationTest` (9 tests) | ✅ |
| | 403 par rôle avec héritage (matrice 3 rôles × 3 niveaux) ; 401 avant tout contrôle de rôle ; 403 sans trace ni classe d'exception (debug off) | `tests/Feature/Securite/AutorisationsTest` (11 tests) | ✅ |
| | 403 : salarié sur les données d'un autre, coordinateur hors de son service (policies des ressources) | `tests/Feature/Api/*` | 🔜 étape API |
| | Validation serveur : entrées invalides → 422, jamais 500 ; message identique email inconnu / mot de passe faux (pas d'énumération de comptes) ; compte désactivé → 403 | `AuthentificationTest` + chaque test d'endpoint | ✅ (login) / 🔜 (API) |
| | Force brute : 6ᵉ tentative en une minute → 429 | `AuthentificationTest` | ✅ |
| | Réponses sans données RH (minimisation RGPD : pas de matricule, noPers, id EpiLogg, dates d'entrée/sortie) | `AuthentificationTest` | ✅ |
| | Injection SQL : filtres passés par Eloquent / requêtes préparées (test avec payload `' OR 1=1 --`) | `tests/Feature/Securite/InjectionTest` | 🔜 étape API |
| | Écriture refusée sur la connexion `epilogg` (session `READ ONLY`) | `tests/Feature/Securite/LectureSeuleSourcesTest` | 🔜 étape connecteurs |
| | Veille vulnérabilités : `composer audit` (22/10/2026 : 6 avis → 0 après mise à jour), `npm audit` au premier `npm install`, vérification ChainDrop/Shai-Hulud du 23/09/2026 (voir §4) | manuel, à ajouter en CI | ✅ (ponctuel) / 🔜 CI |
| **Performance** | Consultation de 5 ans d'historique via le cache : < 1 s pour un salarié, < 2 s pour la vue « Total EPI » (jeu de 60 mois × 30 salariés) | `tests/Feature/Performance/CacheStatistiquesTest` | ⏸ étape API, si le contexte le justifie |
| **Jeu d'essai** | Fonctionnalité la plus représentative : calcul mensuel d'efficacité. Données d'entrée, résultat attendu (calcul manuel), résultat obtenu | §3 | 🔜 étape ETL |
| **Recette métier** | Un mois réel calculé à la main par la Fondation, comparé au résultat de l'application sur les vraies données importées | procès-verbal de recette | 🔜 après connecteurs, avec Michel Flammant |

## 3. Jeu d'essai de référence (à exécuter à l'étape ETL)

Source : exemple « Thomas Neumann, avril 2026 » du fichier « Données app EPI.xlsx » (noms remplacés par des données fictives dans les tests).

| Donnée d'entrée | Valeur |
|---|---|
| Heures DSK normales | 80 h |
| Congés annuels | 30 h |
| Récupération de solde | 2 h |
| Maladie | 8 h |
| Formation standard | 4 h |
| Supervision individuelle | 3 h |
| Prestations 8.1 Follow Up | 64 forfaits × 60 min |
| Activité interne (Comité des Fêtes 2, Connect & Grow 2) | 4 forfaits × 60 min |

| Résultat attendu | Calcul | Valeur |
|---|---|---|
| Total heures dispo | 80 + 30 + 2 + 8 + 4 + 3 | 127 h |
| Heures dispo EPI | 127 − (30 + 2) − 8 | 117 h |
| Heures dispo client | 117 − 4 − 3 − 4 | 106 h |
| Prestations facturables | 64 × 60 min | 64 h |
| **Efficacité réelle** | 64 / 106 | **0,6038** |
| Efficacité brute | (64 + 4) / 127 | 0,5354 |
| Objectif atteint ? | 0,6038 ≥ 0,75 | non |

Résultat obtenu : *à compléter lors de l'exécution du test `CalculEfficaciteTest`.*

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
| 22/10/2026 | 3 tests de sécurité en échec à la première exécution | (1) ressource JSON enveloppée dans `data` ; (2) le guard de test garde l'utilisateur résolu entre deux requêtes ; (3) `APP_DEBUG=true` ajoute `exception` aux réponses d'erreur | (1) `JsonResource::withoutWrapping()` ; (2) `forgetGuards()` entre les requêtes du test ; (3) le test force `app.debug=false` (comportement de production) | Tests `AuthentificationTest`, `AutorisationsTest` |

## 5. Synthèse des résultats

| Date | Suite | Résultat |
|---|---|---|
| 22/10/2026 | 8 classes, 39 tests, 78 assertions (unitaires + intégration persistance + seeders), MariaDB 12.3 | ✅ 39 réussis, 0 échec, 34 s |
| 22/10/2026 | 10 classes, 59 tests, 138 assertions (+ authentification et autorisations), après mise à jour de sécurité des dépendances | ✅ 59 réussis, 0 échec, 43 s ; CI GitHub Actions ✅ sur le commit précédent |

*(ligne ajoutée à chaque étape)*
