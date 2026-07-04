<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $title = 'Addresses';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-map-pin';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('label')
                        ->label('Type')
                        ->options([
                            'home' => 'Home',
                            'work' => 'Work',
                            'mailing' => 'Mailing',
                            'registered' => 'Registered / business',
                            'property' => 'Property',
                            'other' => 'Other',
                        ])->default('home')->required()->native(false),
                    Toggle::make('is_primary')->label('Primary address')->inline(false),
                    TextInput::make('line1')->label('Street address')->maxLength(255)->columnSpanFull(),
                    TextInput::make('line2')->label('Suite / unit')->maxLength(255)->columnSpanFull(),
                    TextInput::make('city')->maxLength(255),
                    TextInput::make('state')->label('State')->maxLength(64),
                    TextInput::make('postal_code')->label('ZIP / postal code')->maxLength(20),
                    TextInput::make('country')->default('US')->maxLength(2),
                    Select::make('sensitivity')
                        ->options([
                            'public' => 'Public',
                            'internal' => 'Internal',
                            'confidential' => 'Confidential',
                        ])->default('internal')->required(),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('line1')
            ->columns([
                TextColumn::make('label')->label('Type')->badge(),
                TextColumn::make('one_line')->label('Address')->searchable(['line1', 'city', 'postal_code'])->wrap(),
                IconColumn::make('is_primary')->label('Primary')->boolean(),
                TextColumn::make('sensitivity')->badge()->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add address'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
