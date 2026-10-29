<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Création d'un compte et réinitialisation d'un mot de passe en ligne de
 * commande. L'application n'envoie pas de courriel de réinitialisation : avec
 * deux comptes, un accès SSH suffit et supprime une dépendance au SMTP.
 */
class ManageAccount extends Command
{
    protected $signature = 'filigrane:compte
                            {email? : Adresse électronique du compte}
                            {--nom= : Nom affiché}
                            {--role= : admin ou curator}
                            {--mot-de-passe= : Mot de passe (sinon demandé)}';

    protected $description = 'Crée un compte Filigrane ou réinitialise son mot de passe';

    public function handle(): int
    {
        $email = $this->argument('email') ?: text(
            label: 'Adresse électronique',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Adresse invalide.',
        );

        $user = User::where('email', $email)->first();

        $name = $this->option('nom') ?: ($user?->name ?: text(
            label: 'Nom affiché',
            placeholder: 'Lise',
            required: true,
        ));

        $role = $this->option('role') ?: ($user?->role->value ?? select(
            label: 'Rôle',
            options: [
                UserRole::Curator->value => UserRole::Curator->getLabel().' — '.UserRole::Curator->description(),
                UserRole::Admin->value => UserRole::Admin->getLabel().' — '.UserRole::Admin->description(),
            ],
            default: UserRole::Curator->value,
        ));

        if (! in_array($role, array_column(UserRole::cases(), 'value'), true)) {
            $this->components->error("Rôle inconnu : {$role}. Utilisez « admin » ou « curator ».");

            return self::FAILURE;
        }

        $plain = $this->option('mot-de-passe') ?: password(
            label: $user ? 'Nouveau mot de passe' : 'Mot de passe',
            required: true,
            validate: fn (string $value) => strlen($value) >= 8 ? null : 'Au moins 8 caractères.',
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'role' => $role, 'password' => Hash::make($plain)],
        );

        $this->components->info(sprintf(
            '%s : %s (%s)',
            $user->wasRecentlyCreated ? 'Compte créé' : 'Compte mis à jour',
            $user->email,
            $user->role->getLabel(),
        ));

        return self::SUCCESS;
    }
}
