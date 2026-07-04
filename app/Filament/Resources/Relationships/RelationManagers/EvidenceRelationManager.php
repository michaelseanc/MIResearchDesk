<?php

namespace App\Filament\Resources\Relationships\RelationManagers;

use App\Models\DocumentCitation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Evidence backing a relationship: each row points the connection at a specific document citation
 * (page/quote). Attaching evidence here is what unlocks promoting the connection to "Verified".
 */
class EvidenceRelationManager extends RelationManager
{
    protected static string $relationship = 'evidence';

    protected static ?string $title = 'Evidence';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-document-check';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('document_citation_id')
                ->label('Citation')
                ->required()
                ->searchable()
                ->helperText('Search citations by document title or quoted text. Add citations from the document’s page first.')
                ->getSearchResultsUsing(fn (string $search): array => DocumentCitation::query()
                    ->with('document')
                    ->where(fn ($q) => $q
                        ->where('quote', 'like', "%{$search}%")
                        ->orWhereHas('document', fn ($d) => $d->where('title', 'like', "%{$search}%")))
                    ->limit(25)->get()
                    ->mapWithKeys(fn (DocumentCitation $c): array => [
                        $c->id => trim(($c->document?->title ?? 'Document')
                            . ($c->page ? ' — p.' . $c->page : '')
                            . ($c->quote ? ' — “' . Str::limit($c->quote, 40) . '”' : '')),
                    ])
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => optional(DocumentCitation::with('document')->find($value))?->label)
                ->columnSpanFull(),
            Textarea::make('note')->label('How this supports the connection')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('citation.document.title')->label('Document')->wrap()->weight('medium'),
                TextColumn::make('citation.page')->label('Page')->placeholder('—'),
                TextColumn::make('citation.quote')->label('Quote')->limit(60)->wrap()->placeholder('—'),
                TextColumn::make('note')->limit(50)->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Attach evidence'),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
