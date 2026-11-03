#Requires -Version 7
<#
.SYNOPSIS
    Gère la base de DONNÉES RÉELLES de la Fondation EPI sur le poste de démonstration,
    à côté de la base de démonstration fictive (même conteneur MariaDB, deux bases).

.DESCRIPTION
    Actions :
      preparer      crée la base réelle dans MariaDB, bascule .env dessus, joue les migrations
                    et le référentiel minimal (tarifs, barème). Ensuite : epi:administrateur,
                    epi:verifier, epi:import.
      basculer      -Vers reel|demo : change DB_DATABASE dans .env. L'application relit .env à
                    chaque requête (pas de cache de configuration en local) ; relancer le worker
                    `queue:work` s'il tourne, et se reconnecter dans le navigateur.
      etat          base active, existence de la base réelle, sauvegardes présentes.
      sauvegarder   exporte la base réelle (mariadb-dump) dans storage/app/private/sauvegardes/
                    (dossier non versionné). Le fichier est en clair : disque chiffré obligatoire.
      restaurer     -Fichier <nom.sql> : recharge une sauvegarde dans la base réelle.
      supprimer     détruit la base réelle ET ses sauvegardes (fin de la phase de démonstration :
                    obligation RGPD), après confirmation, puis rebascule sur la démo.

    Les mots de passe sont lus dans .env et transmis au client MariaDB par la variable
    d'environnement MYSQL_PWD : ils n'apparaissent ni dans la ligne de commande ni à l'écran.

.EXAMPLE
    .\scripts\donnees-reelles.ps1 preparer
    .\scripts\donnees-reelles.ps1 basculer -Vers demo
    .\scripts\donnees-reelles.ps1 sauvegarder
    .\scripts\donnees-reelles.ps1 restaurer -Fichier epi_reel-2026-11-10_1830.sql
#>
param(
    [Parameter(Mandatory, Position = 0)]
    [ValidateSet('preparer', 'basculer', 'etat', 'sauvegarder', 'restaurer', 'supprimer')]
    [string] $Action,

    [ValidateSet('reel', 'demo')]
    [string] $Vers,

    [string] $Fichier,

    [string] $BaseReelle = 'epi_reel',
    [string] $BaseDemo = 'laravel'
)

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $PSScriptRoot      # dossier `dev` (compose.yaml, .env)
Set-Location $racine
$envPath = Join-Path $racine '.env'
$dossierSauvegardes = Join-Path $racine 'storage\app\private\sauvegardes'

if (-not (Test-Path $envPath)) { throw ".env introuvable dans $racine" }

function Lire-Env([string] $cle) {
    $ligne = Get-Content $envPath | Where-Object { $_ -match "^\s*$cle=" } | Select-Object -First 1
    if (-not $ligne) { return $null }
    return ($ligne -replace "^\s*$cle=", '').Trim().Trim('"')
}

function Ecrire-Base([string] $nom) {
    $contenu = [IO.File]::ReadAllText($envPath)
    $contenu = [regex]::Replace($contenu, '(?m)^DB_DATABASE=.*$', "DB_DATABASE=$nom")
    [IO.File]::WriteAllText($envPath, $contenu, [Text.UTF8Encoding]::new($false))
    docker compose exec -T laravel.test php artisan config:clear | Out-Null
}

$motDePasseRoot = Lire-Env 'DB_PASSWORD'      # compose.yaml : MYSQL_ROOT_PASSWORD = DB_PASSWORD
$utilisateur = Lire-Env 'DB_USERNAME'
$baseActive = Lire-Env 'DB_DATABASE'

function Invoke-Sql([string] $sql) {
    docker compose exec -T -e MYSQL_PWD=$motDePasseRoot mariadb mariadb -uroot --batch --skip-column-names -e $sql
    if ($LASTEXITCODE -ne 0) { throw "Commande SQL en échec." }
}

function Invoke-Artisan([string[]] $arguments) {
    docker compose exec -T laravel.test php artisan @arguments
    if ($LASTEXITCODE -ne 0) { throw "artisan $($arguments -join ' ') en échec." }
}

function Base-Existe([string] $nom) {
    return [bool](Invoke-Sql "SHOW DATABASES LIKE '$nom';")
}

function Afficher-Etat {
    $baseActive = Lire-Env 'DB_DATABASE'
    $nature = if ($baseActive -eq $BaseReelle) { 'DONNÉES RÉELLES' } elseif ($baseActive -eq $BaseDemo) { 'démonstration fictive' } else { 'autre' }
    Write-Host "Base active (.env)      : $baseActive  [$nature]"
    Write-Host "Base réelle '$BaseReelle' : $(if (Base-Existe $BaseReelle) { 'présente' } else { 'absente' })"
    if (Test-Path $dossierSauvegardes) {
        $dumps = Get-ChildItem $dossierSauvegardes -Filter '*.sql' | Sort-Object LastWriteTime -Descending
        Write-Host "Sauvegardes ($($dumps.Count)) dans $dossierSauvegardes"
        foreach ($d in $dumps) { Write-Host ("  {0,-40} {1,10:N0} Ko  {2:yyyy-MM-dd HH:mm}" -f $d.Name, ($d.Length / 1KB), $d.LastWriteTime) }
    } else {
        Write-Host "Sauvegardes : aucune"
    }
}

switch ($Action) {

    'preparer' {
        Write-Host "Création de la base '$BaseReelle' (si absente) et droits pour '$utilisateur'…"
        Invoke-Sql "CREATE DATABASE IF NOT EXISTS ``$BaseReelle`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON ``$BaseReelle``.* TO '$utilisateur'@'%'; FLUSH PRIVILEGES;"
        Ecrire-Base $BaseReelle
        Write-Host "Migrations et référentiel minimal (tarifs, barème initial)…"
        Invoke-Artisan @('migrate', '--force')
        Invoke-Artisan @('db:seed', '--class=ReferentielSeeder', '--force')
        Write-Host ''
        Write-Host "Base réelle prête et active. Étapes suivantes (VPN connecté) :" -ForegroundColor Green
        Write-Host "  docker compose exec laravel.test php artisan epi:administrateur <votre.email@…>"
        Write-Host "  docker compose exec laravel.test php artisan epi:verifier --from=2025-01-01 --to=$(Get-Date -Format yyyy-MM-dd)"
        Write-Host "  docker compose exec laravel.test php artisan epi:import --from=2025-01-01 --to=$(Get-Date -Format yyyy-MM-dd)"
        Write-Host "Le seeder de démonstration (DemoSeeder) ne doit JAMAIS être joué sur cette base : utiliser uniquement --class=ReferentielSeeder."
    }

    'basculer' {
        if (-not $Vers) { throw "Préciser -Vers reel ou -Vers demo." }
        $cible = if ($Vers -eq 'reel') { $BaseReelle } else { $BaseDemo }
        if ($Vers -eq 'reel' -and -not (Base-Existe $BaseReelle)) { throw "La base '$BaseReelle' n'existe pas : lancer d'abord 'preparer'." }
        Ecrire-Base $cible
        Write-Host "DB_DATABASE=$cible. Relancer le worker (queue:work) s'il tourne, et se reconnecter dans le navigateur." -ForegroundColor Green
    }

    'etat' { Afficher-Etat }

    'sauvegarder' {
        if (-not (Base-Existe $BaseReelle)) { throw "La base '$BaseReelle' n'existe pas." }
        New-Item -ItemType Directory -Force $dossierSauvegardes | Out-Null
        $nom = "$BaseReelle-$(Get-Date -Format yyyy-MM-dd_HHmm).sql"
        # Le dump est écrit depuis le conteneur applicatif (client MariaDB inclus) dans le dossier
        # du projet monté en volume : pas de redirection PowerShell (qui réencoderait le fichier).
        docker compose exec -T -e MYSQL_PWD=$motDePasseRoot laravel.test sh -c "mariadb-dump -h mariadb -uroot --single-transaction --quick $BaseReelle > storage/app/private/sauvegardes/$nom"
        if ($LASTEXITCODE -ne 0) { throw "mariadb-dump en échec." }
        $taille = (Get-Item (Join-Path $dossierSauvegardes $nom)).Length / 1KB
        Write-Host ("Sauvegarde écrite : {0} ({1:N0} Ko). Fichier EN CLAIR contenant des données nominatives : disque chiffré, pas de copie ailleurs." -f $nom, $taille) -ForegroundColor Green
    }

    'restaurer' {
        if (-not $Fichier) { throw "Préciser -Fichier <nom.sql> (voir 'etat')." }
        $chemin = Join-Path $dossierSauvegardes $Fichier
        if (-not (Test-Path $chemin)) { throw "Sauvegarde introuvable : $chemin" }
        if (-not (Base-Existe $BaseReelle)) {
            Invoke-Sql "CREATE DATABASE ``$BaseReelle`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON ``$BaseReelle``.* TO '$utilisateur'@'%'; FLUSH PRIVILEGES;"
        }
        docker compose exec -T -e MYSQL_PWD=$motDePasseRoot laravel.test sh -c "mariadb -h mariadb -uroot $BaseReelle < storage/app/private/sauvegardes/$Fichier"
        if ($LASTEXITCODE -ne 0) { throw "Restauration en échec." }
        Write-Host "Base '$BaseReelle' restaurée depuis $Fichier." -ForegroundColor Green
    }

    'supprimer' {
        Afficher-Etat
        Write-Host ''
        Write-Host "Cette action DÉTRUIT la base '$BaseReelle' et toutes les sauvegardes du dossier. Irréversible." -ForegroundColor Red
        $confirmation = Read-Host "Taper SUPPRIMER pour confirmer"
        if ($confirmation -ne 'SUPPRIMER') { Write-Host 'Abandon.'; break }
        if (Base-Existe $BaseReelle) { Invoke-Sql "DROP DATABASE ``$BaseReelle``;" }
        if (Test-Path $dossierSauvegardes) { Remove-Item (Join-Path $dossierSauvegardes '*.sql') -Force -Confirm:$false }
        if ((Lire-Env 'DB_DATABASE') -eq $BaseReelle) { Ecrire-Base $BaseDemo }
        Write-Host "Base réelle et sauvegardes supprimées ; .env rebasculé sur '$BaseDemo'. Vider aussi la corbeille et les exports Excel téléchargés." -ForegroundColor Green
    }
}
