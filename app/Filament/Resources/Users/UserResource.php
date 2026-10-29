<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $slug = 'comptes';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * La gestion des comptes n'a pas à encombrer le menu du conservateur.
     */
    public static function canAccess(): bool
    {
        return Auth::user()?->isAdmin() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Comptes';
    }

    public static function getModelLabel(): string
    {
        return 'compte';
    }

    public static function getPluralModelLabel(): string
    {
        return 'comptes';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nom affiché')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->label('Adresse électronique')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            Select::make('role')
                ->label('Rôle')
                ->options(UserRole::class)
                ->default(UserRole::Curator)
                ->native(false)
                ->required()
                ->helperText(collect(UserRole::cases())
                    ->map(fn (UserRole $role) => $role->getLabel().' — '.$role->description())
                    ->implode(' · ')),

            TextInput::make('password')
                ->label('Mot de passe')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                ->helperText('À la modification, laissez vide pour conserver le mot de passe actuel.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Adresse')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),

                TextColumn::make('created_drawings_count')
                    ->label('Fiches créées')
                    ->counts('createdDrawings'),
            ])
            ->recordActions([
                EditAction::make(),
                // On ne se supprime pas soi-même : le panneau deviendrait
                // inaccessible s'il ne restait aucun administrateur.
                DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== Auth::id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
