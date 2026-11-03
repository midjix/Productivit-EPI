# Démonstration au conseil d'administration — déroulé et préparation

> Présentation de l'application sur les **données réelles** de la Fondation, devant le conseil
> d'administration (public non technique, décideurs), à la demande de M. Flammant.
> Durée visée : 20 minutes de démonstration + 10 minutes de questions.
> Prérequis techniques : `GUIDE-DONNEES-REELLES.md` (données chargées, contrôlées, sauvegardées).

## 1. Le message en une phrase

« Le calcul d'efficacité que la Fondation faisait à la main dans Excel est désormais produit
automatiquement chaque nuit, à partir des pointages DSK et des prestations EpiLogg, avec la même
règle de calcul, visible par chacun selon son rôle, et exportable en Excel. »

Trois idées à faire passer : **fiable** (même cascade de calcul que le fichier, barème historisé,
lecture seule des sources), **sûr** (chacun ne voit que son périmètre, données RH minimales),
**prêt** (il reste à l'installer chez l'hébergeur de la Fondation).

## 2. Préparation

**J-7**
- Choisir avec M. Flammant les écrans à montrer et **quels salariés** apparaîtront (ou décider de ne
  montrer que des vues agrégées : Total EPI, services). Le CA verra des noms réels : la direction
  décide de ce qui est acceptable en séance.
- Contrôle des chiffres de deux salariés sur un mois (guide, § 6). Noter les écarts expliqués.
- Lister les trois points ouverts à présenter comme des décisions à prendre (§ 5).

**J-1**
- VPN : `epi:import` (fenêtre glissante) puis `sauvegarder`. Déconnecter le VPN : la démo est locale.
- Charger chaque écran une fois ; thème clair ; zoom 125 % ; langue française ; fermer les autres
  applications et les notifications Windows.
- Préparer un export Excel de la vue globale, ouvert dans Excel (plan B si le projecteur refuse le navigateur).
- Batterie chargée + chargeur ; adaptateur HDMI ; mot de passe administrateur connu par cœur.

**H-1**
- `docker compose up -d`, attendre 40 s, se connecter, parcourir les écrans dans l'ordre du § 3.
- Laisser l'écran « Vue globale » affiché au début de la séance.

## 3. Déroulé (20 minutes)

| Min | Écran | Ce que l'on dit | Ce que l'on montre |
|---|---|---|---|
| 0–2 | (aucun) | Le point de départ : le fichier Excel manuel, son temps de préparation, ses risques d'erreur. Ce que l'application change : import nocturne automatique, règle unique, accès par rôle. | Une diapositive ou le fichier Excel actuel |
| 2–4 | Connexion | Identifiant = adresse professionnelle ; mots de passe exigeants ; trois langues. | Choisir la langue, se connecter en administrateur |
| 4–8 | Vue globale | « Total EPI » = rapport des totaux, pas moyenne des ratios ; chaque service en barre ; l'objectif 75 %. Expliquer la cascade en une phrase : total → moins congés et maladie → moins formation, supervision, interne → heures disponibles pour les clients ; efficacité = prestations facturables / ces heures. | Survoler les barres ; cliquer sur un service |
| 8–11 | Mon service | Le coordinateur voit son équipe, du plus efficace au moins efficace ; filtre d'un mois ; lien vers un salarié. Rappeler : un coordinateur **ne peut pas** ouvrir un autre service (le serveur refuse). | Filtre « mois », cliquer un salarié convenu |
| 11–14 | Tableau de bord salarié | Ce que chaque salarié verra de lui-même, et seulement de lui-même : anneau annuel, indicateurs, mois par mois, explication du calcul en bas de page. | Changer d'année ; basculer le thème sombre et revenir |
| 14–17 | Détail | « Comprendre un chiffre » : période libre, par type de suivi, par tarif, par compteur DSK ; chaque ligne explique son calcul au survol. | Survoler deux lignes ; filtrer un type |
| 17–19 | Export Excel | Le classeur reprend l'écran avec la colonne « Calcul » : la Fondation garde Excel pour ce qu'il fait bien, l'analyse. | Télécharger, ouvrir, montrer la synthèse |
| 19–20 | Administration › Matrice | La « règle d'or » : changer un barème n'écrase jamais le passé, l'historique reste calculé avec le barème de son époque. Les imports sont journalisés. | Afficher l'historique d'un tarif (ne rien modifier en séance) |

Ne pas montrer en séance : la définition d'un mot de passe, la qualification des référentiels,
un import manuel (sans VPN il échouerait, ce qui est normal mais déroute).

## 4. Questions probables du CA et réponses courtes

- **« Les chiffres sont-ils les mêmes que dans notre fichier ? »** Même cascade, même barème ; deux
  salariés et un mois ont été recalculés et comparés (donner l'écart constaté et son explication).
  Les différences restantes viennent de décisions à prendre (§ 5), pas du calcul.
- **« Qui voit quoi ? »** Un salarié : lui seul. Un coordinateur : son service. L'administrateur :
  tout. Le serveur refuse les autres accès, l'écran ne fait que le refléter.
- **« Et les données personnelles ? »** Seules les données nécessaires au calcul sont lues
  (heures par compteur, prestations par type) ; rien sur les bénéficiaires, rien de l'état civil ;
  l'application ne modifie jamais DSK ni EpiLogg. Pour cette démonstration, les données sont sur
  un seul poste chiffré et seront effacées.
- **« Où cela tournera-t-il, et qui l'installe ? »** Sur le serveur de la Fondation, installé par
  CBC avec un guide prêt ; une phase d'essai réservée aux administrateurs est proposée avant
  l'ouverture à tous (note d'options remise à la direction).
- **« Que se passe-t-il si DSK ou EpiLogg change ? »** Chaque source est derrière un connecteur
  isolé : on adapte le connecteur, pas le calcul.
- **« Combien cela coûte-t-il ? »** Logiciels libres sans licence ; l'hébergement est celui de la
  Fondation ; le coût est le temps d'installation de CBC et la maintenance.

## 5. Les décisions à demander (à présenter comme telles)

1. Les congés annuels sont-ils déduits du temps disponible ? (le fichier le fait dans deux
   exemples sur trois).
2. Quel tarif pour les types « INTER-C », « PROJET PILOTE », « SLEMO Développement » ?
   Aujourd'hui : activité interne.
3. Sur quelles heures DSK rapporter les prestations d'un tarif (écran Détail, périmètre tarif) ?
   Aujourd'hui : les heures des salariés qui ont réalisé ces prestations.

Et l'étape suivante : le **feu vert** pour transmettre le dossier d'installation à CBC, et la liste
des administrateurs qui participeront à la phase d'essai.

## 6. Plans B

| Problème | Réponse |
|---|---|
| Accès DSK non ouvert à temps | Montrer les heures facturables réelles (EpiLogg) et annoncer que les déductions arriveront avec DSK ; **dire** que l'efficacité affichée est alors sous-estimée. Compléter avec la base fictive pour la cascade complète. |
| Docker ne démarre pas | Export Excel préparé à J-1 + captures d'écran des six écrans (dossier de secours). |
| Projecteur sans navigateur lisible | Zoom 150 % ; thème clair ; sinon captures en plein écran. |
| Question sur un chiffre inattendu | Ouvrir l'écran Détail sur la période et lire l'explication au survol : c'est précisément ce qu'il sert à faire. |
