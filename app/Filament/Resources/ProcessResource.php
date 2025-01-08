<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProcessResource\Pages;
use App\Filament\Resources\ProcessResource\RelationManagers;
use App\Models\Process;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProcessResource extends Resource
{
    protected static ?string $model = Process::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('process_name')
                    ->required()
                    ->label('Process Name'),

                Forms\Components\Select::make('type')
                    ->required()
                    ->label('Type')
                    ->options([
                        'APPLICATION' => 'Application',
                        'WEBSITE' => 'Website',
                        'BROWSER' => 'Browser',
                    ]),

                    FileUpload::make('icon')
                    ->label('Icon')
                    ->image() // Ensures only image files are uploaded
                    ->disk('public') // Disk to store the files (ensure public disk is configured)
                    ->directory('uploads/processes') // Folder where images will be stored
                    ->maxSize(1024) // Maximum file size in KB
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('process_name')
                    ->label('Process Name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->sortable(),

                ImageColumn::make('icon')
                    ->label('Icon')
                    ->size(40) // Adjust size as needed
                    ->disk('public') // Use the public disk
                    ->url(fn ($record) => config('app.asset_url'). '/' . $record->icon)
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcesses::route('/'),
            'create' => Pages\CreateProcess::route('/create'),
            'edit' => Pages\EditProcess::route('/{record}/edit'),
        ];
    }
    
 
}
