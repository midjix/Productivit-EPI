<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Premier accès après une installation (ou après un import réel) : donne le rôle
 * Administrateur à un compte et définit son mot de passe.
 *
 *   php artisan epi:administrateur prenom.nom@fondation.lu            (compte importé de DSK : promu)
 *   php artisan epi:administrateur admin@fondation.lu --nom="Compte technique"   (compte créé)
 *
 * Pourquoi une commande : les salariés arrivent par l'import DSK avec un mot de passe
 * aléatoire et le rôle Salarié ; seul un Administrateur peut définir un mot de passe
 * depuis l'écran Organigramme… il faut donc un premier Administrateur. Le mot de passe
 * est demandé de façon masquée (l'option --mot-de-passe existe pour les scripts,
 * mais laisse une trace dans l'historique du shell).
 */
class CreerAdministrateur extends Command
{
    protected $signature = 'epi:administrateur
        {email : adresse email du compte (identifiant de connexion)}
        {--nom= : nom affiché, nécessaire seulement pour créer un compte qui n\'existe pas encore}
        {--mot-de-passe= : mot de passe (sinon demandé de façon masquée) ; doit respecter la politique de l\'application}';

    protected $description = 'Donne le rôle Administrateur à un compte (importé ou nouveau) et définit son mot de passe';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->components->error("« {$email} » n'est pas une adresse email valide.");

            return self::INVALID;
        }

        $user = User::where('email', $email)->first();
        $nouveau = $user === null;

        if ($nouveau) {
            $nom = trim((string) ($this->option('nom') ?: $this->ask('Compte inconnu : nom affiché du nouveau compte')));

            if ($nom === '') {
                $this->components->error('Un nom est nécessaire pour créer un compte (--nom).');

                return self::INVALID;
            }

            $user = new User(['name' => $nom, 'email' => $email]);
        }

        $motDePasse = (string) ($this->option('mot-de-passe') ?? $this->secret('Mot de passe (12 caractères, minuscule, majuscule, chiffre, symbole)'));
        $validation = Validator::make(['password' => $motDePasse], ['password' => ['required', Password::defaults()]]);

        if ($validation->fails()) {
            $this->components->error('Mot de passe refusé :');
            $this->components->bulletList($validation->errors()->all());

            return self::INVALID;
        }

        $user->role = Role::Administrateur;
        $user->actif = true;
        $user->password = $motDePasse; // cast « hashed »
        $user->save();

        // Changement de mot de passe : les sessions ouvertes avec l'ancien sont fermées.
        $user->tokens()->delete();

        $this->components->info(sprintf(
            'Compte %s %s : rôle Administrateur, mot de passe défini.',
            $user->email, $nouveau ? 'créé' : 'mis à jour',
        ));

        if ($nouveau) {
            $this->components->warn('Compte technique sans lien avec DSK ni EpiLogg : il n\'apparaît dans aucune statistique et ne sera pas touché par les imports.');
        }

        return self::SUCCESS;
    }
}
