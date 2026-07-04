<?php

namespace App\Filament\Resources\Entities\RelationManagers;

use App\Filament\Resources\Relationships\RelationshipResource;
use App\Models\Entity;
use App\Models\Relationship;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * "Connections" panel on an entity dossier — the entity's full web in ONE place, showing both
 * outgoing (this entity → other) and incoming (other → this entity) relationships, each labelled
 * from this entity's point of view. New connections are created outgoing; incoming rows are shown
 * read-only here (edit them from the other entity's dossier) so directionality stays honest.
 *
 * The target picker searches EXISTING entities (name, legal name, aliases) to avoid duplicates;
 * creating a new entity is only a fallback.
 */
class RelationshipsFromRelationManager extends RelationManager
{
    protected static string $relationship = 'relationshipsFrom';

    protected static ?string $title = 'Connections';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-share';

    /** Show relationships in BOTH directions for the owner entity (create still uses the relationship). */
    protected function getTableQuery(): Builder|Relation|null
    {
        $ownerId = $this->getOwnerRecord()->getKey();

        return Relationship::query()
            ->with(['fromEntity', 'toEntity', 'type'])
            ->where(fn (Builder $q) => $q
                ->where('from_entity_id', $ownerId)
                ->orWhere('to_entity_id', $ownerId));
    }

    public function form(Schema $schema): Schema
    {
        $ownerId = $this->getOwnerRecord()?->getKey();
        $canSeeSealed = (bool) auth()->user()?->can('view_confidential_identity');

        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('to_entity_id')
                        ->label('Connected to')
                        ->required()
                        ->searchable()
                        ->helperText('Search existing people & organizations first — only create a new record if it genuinely does not exist yet.')
                        ->getSearchResultsUsing(function (string $search) use ($ownerId, $canSeeSealed): array {
                            return Entity::query()
                                ->when($ownerId, fn ($q) => $q->whereKeyNot($ownerId)) // no self-links
                                ->when(! $canSeeSealed, fn ($q) => $q->where('sensitivity', '!=', 'sealed'))
                                ->where(fn ($q) => $q
                                    ->where('display_name', 'like', "%{$search}%")
                                    ->orWhere('legal_name', 'like', "%{$search}%")
                                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', "%{$search}%")))
                                ->orderBy('display_name')
                                ->limit(25)
                                ->get()
                                ->mapWithKeys(fn (Entity $e): array => [
                                    $e->id => $e->display_name . ' — ' . ucfirst($e->entity_type),
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(fn ($value): ?string => Entity::find($value)?->display_name)
                        ->createOptionForm([
                            Select::make('entity_type')
                                ->label('Type')
                                ->options(['person' => 'Person', 'organization' => 'Organization'])
                                ->default('person')->required(),
                            TextInput::make('display_name')->required()->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data): int => Entity::create($data)->getKey())
                        ->columnSpanFull(),

                    Select::make('relationship_type_id')
                        ->label('Connection type')
                        ->relationship('type', 'label')
                        ->searchable()->preload()->required()
                        ->createOptionForm([
                            \Filament\Forms\Components\TextInput::make('label')->label('Display name')->required()->maxLength(255)
                                ->helperText('e.g. “Consultant to”, “Lawsuit against”.'),
                            \Filament\Forms\Components\TextInput::make('inverse_name')->label('Reverse label (optional)')->maxLength(255)
                                ->helperText('How it reads from the other side, e.g. “Client of”.'),
                            \Filament\Forms\Components\Toggle::make('is_directional')->label('Directional (A → B)')->default(true),
                            Select::make('category')
                                ->label('Category')
                                ->native(false)
                                ->options(\App\Models\RelationshipType::CATEGORY_OPTIONS),
                            Select::make('color')
                                ->label('Badge color')
                                ->native(false)
                                ->options(\App\Models\RelationshipType::COLOR_OPTIONS)
                                ->placeholder('Default (by category)'),
                        ])
                        ->createOptionUsing(fn (array $data): int => \App\Models\RelationshipType::createFromLabel($data)),

                    Select::make('issue_tag_id')
                        ->label('Issue context (optional)')
                        ->relationship('issueTag', 'name', fn (Builder $query) => $query->where('kind', 'issue'))
                        ->searchable()->preload()
                        ->createOptionForm([
                            \Filament\Forms\Components\TextInput::make('name')->label('Issue')->required()->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data): int => \App\Models\Tag::create([
                            'name' => $data['name'], 'kind' => 'issue',
                        ])->getKey()),
                ]),

            Section::make('Assertion detail')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'former' => 'Former',
                            'historical' => 'Historical',
                            'disputed' => 'Disputed',
                            'unknown' => 'Unknown',
                        ])->default('active')->required(),
                    Select::make('verification_state')
                        ->label('Verification')
                        ->options([
                            'lead' => 'Lead (unverified)',
                            'reported' => 'Reported',
                            'corroborated' => 'Corroborated',
                            'disputed' => 'Disputed',
                            'disproven' => 'Disproven',
                        ])
                        ->default('lead')->required()
                        ->helperText('“Verified” is set from the evidence panel once a citation is attached — it cannot be chosen manually.'),
                    DatePicker::make('start_date'),
                    DatePicker::make('end_date'),
                    Select::make('confidence')
                        ->options([1 => '1 — lowest', 2 => '2', 3 => '3', 4 => '4', 5 => '5 — highest'])
                        ->native(false),
                    Select::make('sensitivity')
                        ->options([
                            'public' => 'Public',
                            'internal' => 'Internal',
                            'confidential' => 'Confidential',
                        ])->default('internal')->required(),
                    Textarea::make('notes')
                        ->helperText('What this connection does and does not establish.')
                        ->rows(2)->columnSpanFull(),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->getKey();
        $isOutgoing = fn (Relationship $record): bool => (int) $record->from_entity_id === (int) $ownerId;

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('direction')
                    ->badge()
                    ->state(fn (Relationship $record): string => $isOutgoing($record) ? 'Outgoing' : 'Incoming')
                    ->color(fn (string $state): string => $state === 'Outgoing' ? 'primary' : 'gray')
                    ->icon(fn (string $state): string => $state === 'Outgoing' ? 'heroicon-m-arrow-right' : 'heroicon-m-arrow-left'),
                TextColumn::make('counterpart')
                    ->label('Connected entity')
                    ->weight('medium')
                    ->state(fn (Relationship $record): string => $isOutgoing($record)
                        ? ($record->toEntity?->display_name ?? '—')
                        : ($record->fromEntity?->display_name ?? '—')),
                TextColumn::make('relationship_label')
                    ->label('Relationship')
                    ->badge()
                    ->state(function (Relationship $record) use ($isOutgoing): string {
                        $type = $record->type;
                        if (! $type) {
                            return '—';
                        }

                        // Outgoing reads with the type label; incoming reads with the inverse.
                        return $isOutgoing($record)
                            ? ($type->label ?? $type->name)
                            : ($type->inverse_name ?? $type->label ?? $type->name);
                    })
                    // Per-type color (set on the connection type), with a category-based default.
                    ->color(fn (Relationship $record): string => $record->type?->badgeColor() ?? 'primary'),
                TextColumn::make('verification_state')
                    ->label('Verification')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'corroborated' => 'info',
                        'reported' => 'warning',
                        'lead' => 'gray',
                        'disputed', 'disproven' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')->badge()->toggleable(),
                TextColumn::make('issueTag.name')->label('Issue')->toggleable()->placeholder('—'),
                TextColumn::make('start_date')->date()->toggleable()->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add connection'),
            ])
            ->recordActions([
                // Manage evidence and promote to Verified on the full connection page.
                Action::make('evidence')
                    ->label('Evidence')
                    ->icon('heroicon-m-document-check')
                    ->url(fn (Relationship $record): string => RelationshipResource::getUrl('edit', ['record' => $record])),
                // Incoming rows are read-only here — edit them from the other entity's dossier.
                EditAction::make()->visible($isOutgoing),
                DeleteAction::make()->visible($isOutgoing),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
