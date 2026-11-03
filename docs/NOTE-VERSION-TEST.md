# Note à la direction — Phase d'essai de l'application de suivi d'efficacité

**Objet** : choisir où et comment les administrateurs de la Fondation pourront tester l'application
avec les données réelles avant son installation définitive par CBC.
**Rédacteur** : William BAAL · **Date** : novembre 2026 · **Décision attendue** : option retenue,
liste des testeurs, durée.

## 1. Ce qui est proposé

Une **phase d'essai réservée aux administrateurs** (M. Flammant et les personnes qu'il désigne),
sur les données réelles de la Fondation, pendant une durée limitée (proposition : 4 à 6 semaines).
Les comptes Salarié et Coordinateur ne peuvent pas se connecter pendant cette phase ; ils reçoivent
un message explicite. À l'issue : liste des corrections souhaitées, puis installation définitive par
CBC et ouverture progressive aux coordinateurs puis aux salariés.

Objectifs : vérifier les chiffres sur plusieurs mois réels (recette), confirmer les règles encore
ouvertes (déduction des congés, tarif de certains types de suivi, attribution des heures par
tarif), se familiariser avec l'administration (référentiels, barème, organigramme).

## 2. Trois façons de l'héberger

| | **A. Portable de démonstration** | **B. Serveur provisoire loué** | **C. Poste d'un administrateur** |
|---|---|---|---|
| Où sont les données | Sur un poste chiffré, hors des locaux entre deux sessions | Chez un hébergeur européen (ex. Luxembourg, France, Allemagne), serveur dédié à la Fondation | Dans les locaux, sur un poste de la Fondation |
| Qui installe | Déjà fait | William (quelques heures), avec le même paquet que celui remis à CBC | William, sur place, avec l'accord de CBC (droits administrateur Windows, Docker Desktop) |
| Accès des testeurs | Sur rendez-vous, en présence de William | Depuis n'importe quel poste, par navigateur, HTTPS | Sur ce poste uniquement |
| Mise à jour des données | À chaque session (VPN depuis le portable) | Données chargées depuis le portable puis déposées sur le serveur (le serveur n'a pas le VPN) ; rafraîchissement hebdomadaire | VPN de la Fondation : import nocturne possible si le poste reste allumé |
| Coût | 0 | ≈ 10 à 20 €/mois, résiliable, à la charge de William ou de la Fondation | 0 (temps de CBC si configuration) |
| Protection des données | Un seul exemplaire, chiffré, effacé en fin de phase | Données nominatives hors des serveurs de la Fondation : **accord de la direction nécessaire**, chiffrement, comptes administrateurs seuls, suppression à la fin | Données dans le périmètre de la Fondation ; dépend de la sécurité du poste |
| Limites | Peu de temps de test réel ; dépend des disponibilités | Pas de connexion aux sources : instantanés, pas de « vraie » nuit d'import | Dépend des règles informatiques de CBC ; installation Docker souvent interdite sur les postes |
| Répète-t-il l'installation CBC ? | Non | **Oui** : même paquet, même guide, les problèmes sont trouvés avant CBC | Partiellement |

**Recommandation** : **A pour la démonstration au conseil d'administration**, puis **B pour la
phase d'essai** si la direction accepte un hébergement provisoire hors de ses serveurs ; sinon **C**
après accord de CBC. L'option B a l'avantage de répéter l'installation que fera CBC et de laisser
les testeurs travailler à leur rythme.

## 3. Mesures de protection communes à toutes les options

- Connexion chiffrée (HTTPS) et mots de passe exigeants (12 caractères, quatre types de caractères).
- Comptes nominatifs, réservés aux administrateurs désignés ; chaque import est journalisé avec son auteur.
- Données minimales : heures par compteur et prestations par type ; aucune donnée sur les
  bénéficiaires, aucune donnée d'état civil ou de paie. L'application ne modifie jamais DSK ni EpiLogg.
- Exports Excel : à conserver dans les espaces habituels de la Fondation, pas sur des postes personnels.
- Fin de phase : suppression de la base et des sauvegardes, attestée par écrit ; la version définitive
  est reconstruite par CBC à partir des sources.

## 4. Ce que la Fondation doit fournir ou décider

1. L'**option** d'hébergement (A, B ou C) et, pour B, l'accord écrit de la direction (et du référent
   protection des données s'il existe) pour un hébergement provisoire hors de ses serveurs.
2. La **liste des testeurs** (nom, adresse professionnelle) : trois à cinq administrateurs.
3. La **durée** de la phase et la date de bilan.
4. L'**accès à l'API DSK** (utilisateur API et ouverture réseau par CBC) : sans lui, les congés,
   maladies et formations ne sont pas déduits et l'efficacité est sous-estimée.
5. Les **réponses aux trois questions métier** (déduction des congés annuels ; tarif des types
   « INTER-C », « PROJET PILOTE », « SLEMO Développement » ; heures de référence par tarif).
6. Le **feu vert** pour transmettre à CBC le dossier d'installation définitive.

## 5. Calendrier indicatif

| Étape | Quand |
|---|---|
| Démonstration au conseil d'administration (option A) | à fixer |
| Décision sur l'option de la phase d'essai | la semaine suivante |
| Mise à disposition (B : 2 jours ; C : selon CBC) | sous 2 semaines |
| Phase d'essai | 4 à 6 semaines |
| Bilan, corrections, transmission du dossier à CBC | fin de la phase |
| Installation définitive par CBC, ouverture aux coordinateurs puis aux salariés | selon CBC |
