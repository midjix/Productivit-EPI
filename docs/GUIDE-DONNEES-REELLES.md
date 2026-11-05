# Guide — Démonstration et version d'essai sur les données réelles (poste de démonstration)

> Pour charger les **vraies données** de la Fondation EPI (EpiLogg + DSK) sur le portable de
> démonstration, les contrôler, les présenter au conseil d'administration et les laisser
> tester aux administrateurs, puis les effacer. Complète `GUIDE-DEMO.md` (données fictives).
>
> **Cadre** : accord de la direction de la Fondation pour ce poste uniquement (03/11/2026).
> Les données sont nominatives (temps de travail, congés, maladie) : disque chiffré
> (BitLocker actif), session verrouillée dès que le poste est quitté, aucune copie ailleurs
> (clé USB, cloud, mail), suppression à la fin de la phase (§ 9).

## 1. Deux bases dans le même MariaDB

La base de **démonstration fictive** (`laravel`, remplie par `DemoSeeder`) reste disponible pour
les présentations publiques. La base **réelle** (`epi_reel`) vit à côté, dans le même conteneur.
On passe de l'une à l'autre en changeant `DB_DATABASE` dans `.env` ; le script
`scripts\donnees-reelles.ps1` fait tout cela sans toucher aux secrets :

| Action | Commande (PowerShell 7, depuis `dev`) |
|---|---|
| Créer la base réelle, migrer, référentiel minimal | `.\scripts\donnees-reelles.ps1 preparer` |
| Basculer | `.\scripts\donnees-reelles.ps1 basculer -Vers reel` / `-Vers demo` |
| Où en suis-je ? | `.\scripts\donnees-reelles.ps1 etat` |
| Sauvegarder la base réelle | `.\scripts\donnees-reelles.ps1 sauvegarder` |
| Restaurer | `.\scripts\donnees-reelles.ps1 restaurer -Fichier epi_reel-2026-11-10_1830.sql` |
| Fin de phase : tout effacer | `.\scripts\donnees-reelles.ps1 supprimer` |

Règle absolue : **jamais `migrate:fresh --seed` ni `DemoSeeder` sur la base réelle** (le script
n'utilise que `ReferentielSeeder` : tarifs 8.1 / 8.2 / 9.1 / AUTRE et barème initial). Après une
bascule, se reconnecter dans le navigateur et relancer le worker `queue:work` s'il tournait.

## 2. Prérequis le jour du chargement

- VPN WatchGuard connecté (EpiLogg) **et** accès à l'API DSK ouvert (utilisateur API fourni par
  DSK, route réseau vers `SRV-DATA` ouverte par CBC) — les deux peuvent être testés séparément (§ 4).
- `.env` complété par vous seul : `EPILOGG_DB_*`, `DSK_API_URL`, `DSK_API_LOGIN`, `DSK_API_PASSWORD`
  (ou `DSK_API_TOKEN`). Ces valeurs ne sont écrites nulle part ailleurs.
- Docker Desktop démarré, `docker compose up -d`, puis 30 à 40 s avant la première commande
  (montage 9p). Deux terminaux : un pour les commandes, un pour le worker si l'on passe par l'écran
  d'administration.

## 3. Préparer la base et le premier administrateur

```powershell
.\scripts\donnees-reelles.ps1 preparer
docker compose exec laravel.test php artisan epi:administrateur votre.email@fondation.lu
```

La commande demande le mot de passe de façon masquée (politique : 12 caractères, minuscule,
majuscule, chiffre, symbole). Si l'adresse existe déjà (compte importé de DSK), le compte est
promu ; sinon un compte technique est créé (il n'apparaît dans aucune statistique). Pour les
administrateurs testeurs, utiliser leur adresse professionnelle **après** l'import DSK : leur compte
sera apparié à leurs propres heures.

**Compte d'essai jetable** : un compte technique (adresse hors de la Fondation, par ex.
`admin.temporaire@epi.test`) n'est lié à aucune source et n'apparaît dans aucune statistique.
Il se supprime en une commande, jetons compris :

```powershell
docker compose exec laravel.test php artisan tinker --execute="App\Models\User::where('email','admin.temporaire@epi.test')->get()->each(function (\$u) { \$u->tokens()->delete(); \$u->delete(); }); echo 'comptes restants : '.App\Models\User::count();"
```

## 4. Répétition générale : `epi:verifier` (lecture seule)

Avant tout import, la commande interroge les sources exactement comme l'import le ferait, **sans
rien écrire** nulle part, et dit ce qui se passera :

```powershell
docker compose exec laravel.test php artisan epi:verifier --from=2025-01-01 --to=2026-11-30
docker compose exec laravel.test php artisan epi:verifier --source=dsk      # une seule source
docker compose exec laravel.test php artisan epi:verifier --json            # pour garder une trace
```

| Ce qu'elle affiche | Ce que cela veut dire | Que faire |
|---|---|---|
| `INJOIGNABLE` + erreur | VPN, identifiants, route réseau ou API indisponible | Corriger avant d'importer ; `cURL error 6` = nom `SRV-DATA` non résolu (route VPN) ; `401` = identifiants DSK ; `transaction_read_only` = compte EpiLogg non protégé : refuser d'importer |
| Départements nouveaux | Codes DSK absents de l'organigramme | Normal au premier import : les services seront créés avec le libellé DSK, à renommer ensuite dans « Organigramme » |
| Compteurs nouveaux / à qualifier | Comptes d'heures DSK inconnus | Après l'import, les classer dans « Référentiels » (catégorie + « dans le total ») : tant qu'ils sont « autre, hors total », ils ne comptent pas |
| Salariés sans email | Pas d'appariement EpiLogg possible | Faire compléter l'email dans DSK (ou le saisir dans « Organigramme ») puis réimporter |
| Types sans tarif reconnu | `rate_name` EpiLogg sans « 8.1 / 8.2 / 9.1 » | Après l'import, choisir le tarif dans « Référentiels › Types de suivi » (ex. INTER-C, PROJET PILOTE : décision Fondation) |
| EpiLogg actifs sans DSK | Prestations qui seront ignorées | Vérifier l'email des deux côtés (même adresse, même orthographe) |
| DSK sans EpiLogg | Salariés sans prestations (administratif) | Normal pour l'administration ; vérifier pour un intervenant |
| Verdict « prêt » | Les sources répondent | Importer (§ 5) |

Code de retour 0 = prêt, 1 = une source ne répond pas : la commande sert aussi de test
d'installation (CBC l'exécutera après le déploiement).

### Ce que le premier chargement réel a appris (05/11/2026, sur place)

- **DSK ne fournit des compteurs calculés qu'à partir du 1ᵉʳ janvier 2026** : les années
  antérieures reviennent vides (une seule ligne de crédit annuel en 2025). L'efficacité est donc
  calculable sur 2026 ; EpiLogg, lui, remonte à décembre 2022 (prestations seules). À demander à
  DSK si l'historique antérieur peut être recalculé ou exposé.
- Le serveur nomme `date` et `val` ce que la spécification appelait `theDate` et `value`, et ignore
  la pagination : corrigé dans le connecteur, qui accepte les deux formes.
- Départements DSK : `ADMIN`, `AISP`, `FU`, `PSY` (pas de service logement). Le référentiel de départ
  avait `FOLLOWUP` et `LOGEMENT` : `FU` a été renommé « Follow Up », les deux autres désactivés.
- Cinq salariés n'ont pas d'email dans DSK et une adresse diffère entre DSK et EpiLogg : complétés
  à la main dans la base locale (à aligner dans DSK par la Fondation, sinon à refaire après chaque
  réimport DSK pour l'adresse différente).
- Qualification par défaut appliquée d'après la liste de la Fondation : 68 « H Normales » = normal ;
  1, 2, 12 = congé ; 4 = maladie ; 13, 15, 17 = formation ; 16, 18 = supervision ; tout le reste
  « autre, hors total ». **À valider avec M. Flammant** : jours fériés (61), télétravail effectif
  (8558), sorties en service (56), permanences (8557), et si le département ADMIN entre dans le
  « Total EPI ».

## 5. Charger l'historique

L'import remplace la fenêtre demandée (idempotent : relancer la même période donne le même
résultat). Charger **année par année** pour garder des lots raisonnables et un journal lisible :

```powershell
docker compose exec laravel.test php artisan epi:import --from=2025-01-01 --to=2025-12-31
docker compose exec laravel.test php artisan epi:import --from=2026-01-01 --to=2026-11-30
```

Chaque exécution écrit deux lignes (DSK, EpiLogg) dans le journal des imports, avec le résumé
(services, comptes, salariés, cas signalés) ; elle recalcule le cache des mois concernés.

Puis, dans l'application (menu Administration) :

1. **Référentiels › Compteurs DSK** : donner une catégorie à chaque compteur (normal, congé,
   maladie, formation, supervision, déplacement, autre) et cocher « dans le total » quand il
   compte dans le temps de travail. Chaque enregistrement recalcule les mois concernés.
2. **Référentiels › Types de suivi**, filtre « non qualifiés » : choisir le tarif ; donner aussi
   un **service** aux types facturables (8.1 AISP → AISP, 8.1 Follow Up → Follow Up, 9.1 → PSY…) :
   c'est ce rattachement qu'utilise le tableau « Par service » de l'écran Détail (un type sans
   service y apparaît comme activité interne). Fait le 05/11 pour quatre types ; « 8.1 Thérapie
   familiale » reste à rattacher par la Fondation.
3. **Organigramme** : renommer les services créés depuis les codes DSK, vérifier les rôles
   (coordinateurs), désactiver les comptes partis si DSK ne le fait pas.
4. **Imports › Recalculer** (ou `php artisan epi:recalculer`) pour reconsolider toute la période.

Si l'accès DSK arrive après EpiLogg : importer `--source=epilogg` d'abord, puis `--source=dsk`
quand il est ouvert ; l'appariement par email se fait au second import.

## 6. Contrôler les chiffres avant de les montrer (recette)

Choisir avec M. Flammant **deux salariés et un mois** déjà calculés à la main dans le fichier
Excel de la Fondation, et comparer avec l'écran « Détail » (période = le mois, périmètre = le
salarié) :

| Ligne | Attendu | Si écart |
|---|---|---|
| Total des heures | heures DSK « dans le total » | compteur mal classé ou hors total (§ 5.1) |
| Congés / maladie / formation / supervision | mêmes heures que le fichier | catégorie d'un compteur |
| Heures facturables | forfaits × barème (60 / 60 / 30 min) | type sans tarif, ou barème à une autre date (Matrice) |
| Efficacité | même ratio à 1 % près | point ouvert métier (voir ci-dessous) |

Points encore ouverts avec la Fondation, à mentionner si le CA pose la question : déduction des
congés annuels (confirmée par deux des trois exemples du fichier), valorisation du forfait
déplacement, tarif des types « INTER-C », « PROJET PILOTE », « SLEMO Développement »,
règle d'attribution des heures DSK à un tarif (écran Détail, périmètre tarif).

Consigner le résultat (date, salariés, écart constaté, explication) : c'est le procès-verbal de
recette attendu par le plan de tests (§ 2, ligne « Recette métier »).

## 7. Avant la présentation

- J-1, VPN connecté : `epi:import` sur la fenêtre glissante (sans option) pour rafraîchir les
  deux derniers mois, puis `.\scripts\donnees-reelles.ps1 sauvegarder`.
- Charger chaque écran une fois (cache de l'application et du navigateur) ; la démo fonctionne
  ensuite **sans VPN ni réseau** : tout est local.
- Thème **clair** pour le projecteur, zoom du navigateur 125 %, langue française.
- Déroulé et argumentaire : `DEMO-CA.md`.

## 8. Version d'essai réservée aux administrateurs

Si le poste (ou plus tard un serveur) est laissé aux administrateurs :

```
EPI_ROLE_MINIMUM_CONNEXION=administrateur
```

dans `.env` : les comptes Salarié et Coordinateur reçoivent à la connexion « Version d'essai :
seuls les comptes « Administrateur » peuvent se connecter pour le moment » (traduit), aucun jeton
n'est émis. Les administrateurs testeurs reçoivent leur mot de passe par `epi:administrateur`
(ou par « Organigramme › Définir un mot de passe » depuis votre compte). Revenir à `salarie`
(ou retirer la ligne) pour la mise en service. Les options d'hébergement de cette phase sont
comparées dans `NOTE-VERSION-TEST.md`.

## 9. Fin de la phase : effacer

```powershell
.\scripts\donnees-reelles.ps1 supprimer       # base réelle + sauvegardes, retour à la démo fictive
```

Puis supprimer les exports Excel téléchargés (dossier Téléchargements), vider la corbeille,
et retirer de `.env` les identifiants DSK / EpiLogg s'ils ne servent plus. La base de production
sera reconstruite par CBC depuis les sources : rien de ce poste n'a besoin d'être transféré.

## 10. Dépannage

| Symptôme | Cause probable | Remède |
|---|---|---|
| `cURL error 6: Could not resolve host: srv-data` ou délai dépassé sur 192.168.50.6:80 | SRV-DATA (serveur DSK.net, géré par DSK) est sur le réseau local de la Fondation ; l'accès VPN créé par CBC ne laisse passer que la base EpiLogg | Faire l'import **depuis les locaux de la Fondation** (portable sur leur réseau, aucun VPN nécessaire), ou demander à CBC d'ajouter `srv-data:80` à la politique du VPN ; en attendant `--source=epilogg` |
| `DSK injoignable ou authentification refusée (HTTP 401)` | Identifiants / jeton DSK | Vérifier `DSK_API_LOGIN` / `DSK_API_PASSWORD` ; un jeton statique remplace le login |
| `SQLSTATE[HY000] [2002]` sur EpiLogg | VPN coupé | Reconnecter le VPN, relancer |
| `La session EpiLogg n'est pas en lecture seule : import refusé` | Compte SQL non restreint | Ne pas forcer : demander à CBC un compte en lecture seule |
| Import « succès » mais tableaux vides | Compteurs « hors total » ou types sans tarif | § 5.1 et 5.2, puis recalculer |
| Écran encore sur l'ancienne base après bascule | Jeton de session de l'autre base | Se déconnecter / reconnecter ; `config:clear` est fait par le script |
| Lenteur 4 à 6 s par écran | Montage 9p de `D:\` | Charger les écrans avant la présentation (§ 7) |
