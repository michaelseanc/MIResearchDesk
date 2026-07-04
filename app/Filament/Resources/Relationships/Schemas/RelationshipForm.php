<?php

namespace App\Filament\Resources\Relationships\Schemas;

use App\Models\Entity;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class RelationshipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Connection')
                ->columns(2)
                ->schema([
                    self::entitySelect('from_entity_id', 'From entity'),
                    self::entitySelect('to_entity_id', 'To entity'),
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
                        ->label('Issue context')
                        ->relationship('issueTag', 'name', fn ($query) => $query->where('kind', 'issue'))
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
                            'active' => 'Active', 'former' => 'Former', 'historical' => 'Historical',
                            'disputed' => 'Disputed', 'unknown' => 'Unknown',
                        ])->default('active')->required(),
                    Select::make('verification_state')
                        ->label('Verification')
                        // "verified" is not manually selectable — it only appears if already set,
                        // and is reached via the "Mark as Verified" action once evidence exists.
                        ->options(fn (?Model $record): array => array_merge([
                            'lead' => 'Lead (unverified)',
                            'reported' => 'Reported',
                            'corroborated' => 'Corroborated',
                            'disputed' => 'Disputed',
                            'disproven' => 'Disproven',
                        ], $record?->verification_state === 'verified' ? ['verified' => 'Verified'] : []))
                        ->default('lead')->required()
                        ->disableOptionWhen(fn (string $value): bool => $value === 'verified'),
                    DatePicker::make('start_date'),
                    DatePicker::make('end_date'),
                    Select::make('confidence')
                        ->options([1 => '1 — lowest', 2 => '2', 3 => '3', 4 => '4', 5 => '5 — highest'])->native(false),
                    Select::make('sensitivity')
                        ->options([
                            'public' => 'Public', 'internal' => 'Internal', 'confidential' => 'Confidential',
                        ])->default('internal')->required(),
                    Textarea::make('notes')->rows(2)->columnSpanFull()
                        ->helperText('What this connection does and does not establish.'),
                ]),
        ]);
    }

    /** A searchable entity picker (name / legal name / aliases) that avoids duplicates. */
    private static function entitySelect(string $field, string $label): Select
    {
        return Select::make($field)
            ->label($label)
            ->required()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Entity::query()
                ->when(! auth()->user()?->can('view_confidential_identity'), fn ($q) => $q->where('sensitivity', '!=', 'sealed'))
                ->where(fn ($q) => $q
                    ->where('display_name', 'like', "%{$search}%")
                    ->orWhere('legal_name', 'like', "%{$search}%")
                    ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', "%{$search}%")))
                ->orderBy('display_name')->limit(25)->get()
                ->mapWithKeys(fn (Entity $e): array => [$e->id => $e->display_name . ' — ' . ucfirst($e->entity_type)])
                ->all())
            ->getOptionLabelUsing(fn ($value): ?string => Entity::find($value)?->display_name);
    }
}
