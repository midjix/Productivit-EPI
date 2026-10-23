# Guide de démonstration — Suivi d'efficacité (Fondation EPI)

> Pour lancer l'application sur le poste de développement et la présenter rôle par rôle
> (salarié, coordinateur, administrateur) avant le déploiement. Données de démonstration
> fictives : aucun vrai salarié, aucune donnée réelle de DSK ni d'EpiLogg.

## 1. Démarrer (une fois par session de démo)

Dans PowerShell, depuis le dossier `dev` :

```powershell
docker compose up -d                                   # conteneurs app + MariaDB
docker compose exec laravel.test php artisan migrate:fresh --seed --force   # base de démo neuve
docker compose exec laravel.test php artisan epi:recalculer                 # remplit le cache des statistiques
npm run build                                          # assets de production (ou `npm run dev` pour développer)
```

Puis ouvrir **http://127.0.0.1** (pas `localhost` : sous Windows, `localhost` essaie d'abord l'IPv6 et chaque appel attend plusieurs secondes).

Pour que les **imports manuels et recalculs** lancés depuis l'écran d'administration s'exécutent pendant la démo, garder un second terminal ouvert avec le worker de file d'attente :

```powershell
docker compose exec laravel.test php artisan queue:work --tries=1
```

(Sans worker, la demande est acceptée et visible « en cours » dans le journal, mais ne s'exécute pas.)

## 2. Comptes de démonstration (mot de passe : `password`)

| Rôle | Identifiant | Ce qu'il voit |
|---|---|---|
| Salarié (Follow Up) | `salarie.followup@epi.test` | son tableau de bord uniquement |
| Salarié (AISP / PSY / Logement) | `salarie.aisp@epi.test`, `salarie.psy@epi.test`, `salarie.logement@epi.test` | idem |
| Coordinateur (PSY) | `coord.psy@epi.test` | son tableau de bord + « Mon service » |
| Coordinateur (autres) | `coord.followup@epi.test`, `coord.aisp@epi.test`, `coord.logement@epi.test` | idem |
| Administrateur | `admin@epi.test` | tout + « Vue globale » + « Administration » |

La couleur du menu latéral change avec le rôle : orange (salarié), ocre (coordinateur), anthracite (administrateur).

## 3. Scénario conseillé (15 minutes)

### Salarié — `salarie.followup@epi.test`
1. Page de connexion : organisation familière (DSKnet), couleurs EPI, choix de la langue (FR / EN / DE) en haut à droite.
2. Tableau de bord : anneau d'efficacité annuelle avec le repère de l'objectif 75 %, quatre indicateurs, graphique mensuel avec la ligne d'objectif, tableau des mois en heures, explication de la cascade en bas.
3. Changer d'année avec « ‹ 2026 › » : une année sans données affiche un message clair.
4. Basculer le thème (icône demi-disque) : automatique → clair → sombre. Le graphique suit.
5. Changer la langue : tout se traduit, y compris les formats (« 128,0 h » / « 128.0 h ») et les noms de mois.
6. « Changer mon mot de passe » : l'ancien mot de passe est exigé.
7. À montrer : taper l'URL `/salaries/2` → « Vous n'avez pas accès aux données de ce salarié. » Le serveur refuse, pas seulement l'écran.

### Coordinateur — `coord.psy@epi.test`
1. Menu ocre, entrée « Mon service » en plus.
2. « Mon service » : synthèse du service (rapport des totaux, pas moyenne des ratios), barres par salarié du plus efficace au moins efficace, tableau avec lien vers le tableau de bord de chaque salarié.
3. Filtre « Toute l'année » → un mois précis.
4. À montrer : l'URL `/services/2` (un autre service) → refus 403 ; `/globales` → redirigé, la vue globale est réservée à l'administrateur.

### Administrateur — `admin@epi.test`
1. Menu anthracite, « Vue globale » : Total EPI, barres par service, tableau avec lien vers chaque service.
2. « Administration › Imports » : journal des imports, lancer un **recalcul** (quelques secondes avec le worker), puis un **import** : sans VPN, l'import EpiLogg échoue proprement et le journal affiche l'erreur (c'est volontaire : la supervision montre les échecs) ; avec le VPN connecté, il réussit en quelques secondes.
3. « Référentiels » : reclasser un compteur DSK (par ex. passer « Compteur technique » en « Maladie » + « dans le total ») → notification avec la période recalculée ; revenir au tableau de bord d'un salarié pour voir l'effet. Filtrer « non qualifiés » : un type EpiLogg attend son tarif.
4. « Matrice de conversion » : « Nouveau barème » sur le tarif 9.1 (par ex. 45 min au 1er du mois en cours) : l'ancien barème est clôturé à la veille, jamais écrasé, les mois concernés sont recalculés. Une date antérieure au barème en vigueur est refusée.
5. « Organigramme » : renommer un service, modifier le rôle d'un utilisateur, définir un mot de passe. À montrer : l'administrateur ne peut ni se rétrograder ni se désactiver.

## 4. Remettre la démo à neuf

```powershell
docker compose exec laravel.test php artisan migrate:fresh --seed --force
docker compose exec laravel.test php artisan epi:recalculer
```

## 5. Pièges connus en démo

- **Lenteur des premières requêtes** (3 à 7 s) sur un projet hébergé sur `D:\` : montage Docker en 9p. Charger chaque écran une première fois avant la présentation, ou héberger le projet dans WSL2 / un partage synchronisé Docker Desktop. Sans rapport avec la production.
- **Logo** : `public/images/logo-epi.png` est une capture provisoire (fond gris) ; remplacer par le logo officiel (PNG transparent ou SVG) sans rien changer d'autre.
- **Imports réels** : nécessitent le VPN WatchGuard (EpiLogg) ; l'API DSK n'est pas joignable via le VPN actuel (demande d'ouverture en cours).
- **Accents dans les formulaires** : fonctionnent dans le navigateur ; seul `curl` depuis Git Bash les encode mal.
- Après `migrate:fresh`, les sessions ouvertes sont invalidées : se reconnecter.
