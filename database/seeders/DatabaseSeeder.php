<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de données de démarrage pour le développement local.
 * Les mots de passe sont volontairement triviaux : ce seeder n'a pas vocation
 * à être exécuté en ligne (voir la commande `filigrane:compte`).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'emmanuel@filigrane.test'],
            [
                'name' => 'Emmanuel',
                'role' => UserRole::Admin,
                'password' => Hash::make('filigrane'),
            ],
        );

        User::updateOrCreate(
            ['email' => 'lise@filigrane.test'],
            [
                'name' => 'Lise',
                'role' => UserRole::Curator,
                'password' => Hash::make('filigrane'),
            ],
        );

        foreach ([
            ['name' => 'Paysage', 'family' => 'sujet'],
            ['name' => 'Portrait', 'family' => 'sujet'],
            ['name' => 'Étude de draperie', 'family' => 'sujet'],
            ['name' => 'École française', 'family' => 'école'],
            ['name' => 'École italienne', 'family' => 'école'],
            ['name' => 'Carton 1', 'family' => 'emplacement'],
            ['name' => 'Encadré — salon', 'family' => 'emplacement'],
        ] as $tag) {
            Tag::updateOrCreate(['name' => $tag['name']], $tag);
        }
    }
}
