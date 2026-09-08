<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255),
            TextInput::make('country_code')
                ->label('Country Code')
                ->placeholder('+1')
                ->maxLength(10),
            TextInput::make('mobile')
                ->label('Phone Number')
                ->tel()
                ->maxLength(20),
            TextInput::make('password')
                ->password()
                ->required()
                ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->sortable()->searchable()
                    ->url(fn (User $record): string => static::getUrl('index', ['parent_user_id' => $record->id])),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('mobile')
                    ->label('Phone Number')
                    ->getStateUsing(fn (User $record): ?string => $record->mobile ? trim(($record->country_code ?? '') . ' ' . $record->mobile) : null)
                    ->placeholder('-')
                    ->searchable(['mobile', 'country_code'])
                    ->sortable(),
                TextColumn::make('sub_users_count')->label('Users Count')->counts('subUsers'),
                TextColumn::make('latestActivity.start_datetime')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('sub_users')
                    ->label('View Sub Users')
                    ->icon('heroicon-o-users')
                    ->url(fn (User $record): string => static::getUrl('index', ['parent_user_id' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(
                request()->query('parent_user_id'),
                fn ($query, $parentId) => $query->where('parent_user_id', $parentId),
                fn ($query) => $query->where('parent_user_id', 0)
            );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
