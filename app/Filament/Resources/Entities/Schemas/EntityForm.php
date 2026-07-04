<?php

namespace App\Filament\Resources\Entities\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EntityForm
{
    public static function configure(Schema $schema): Schema
    {
        // Two-column layout of section "boxes". Rather than let the sections tile row-by-row (which
        // dropped "Classification & review" into a low, offset slot whenever the neighboring box was
        // taller), each column is an explicit, independent vertical stack. This keeps Classification &
        // review directly beneath the Person/Organization details box in the left column.
        return $schema
            ->columns(2)
            ->components([
                // ---- LEFT column: identity, the type-specific details, then classification/review ----
                Group::make([
                    Section::make('Identity')
                        ->columns(2)
                        ->schema([
                            FileUpload::make('photo_path')
                                ->label('Profile photo')
                                ->image()
                                ->avatar()
                                ->imageEditor()
                                ->circleCropper()
                                // avatar() enforces a 1:1 aspect-ratio VALIDATION that reads the file's
                                // dimensions; that read throws a 500 if the Livewire temp file is stale/gone.
                                // Null it out — the image is still auto-resized to 500x500 and shown circular.
                                ->imageAspectRatio(null)
                                ->disk('public')
                                ->directory('entity-photos')
                                ->visibility('public')
                                ->extraAttributes(['class' => 'mi-photo-upload'])
                                ->columnSpanFull(),
                            Select::make('entity_type')
                                ->label('Type')
                                ->options(\App\Models\Entity::TYPE_LABELS)
                                ->default('person')
                                ->required()
                                ->live()
                                ->native(false)
                                ->helperText('Government, PAC, and News use the organization fields.'),
                            Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'former' => 'Former',
                                    'dissolved' => 'Dissolved',
                                    'historical' => 'Historical',
                                    'deceased' => 'Deceased',
                                    'unknown' => 'Unknown',
                                ])
                                ->default('active')
                                ->required(),
                            TextInput::make('display_name')->required()->maxLength(255),
                            TextInput::make('legal_name')->label('Legal / formal name')->maxLength(255),
                        ]),

                    Section::make('Person details')
                        ->relationship('personProfile')
                        ->visible(fn (Get $get): bool => $get('entity_type') === 'person')
                        ->columns(2)
                        ->schema([
                            TextInput::make('full_name')->maxLength(255),
                            TextInput::make('professional_role')->label('Current role / title')->maxLength(255),
                            TextInput::make('current_company')->label('Current company / employer')->maxLength(255),
                            Select::make('current_company_entity_id')
                                ->label('↳ Link to a tracked organization (adds it to the graph)')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => \App\Models\Entity::query()
                                    ->organizationLike()
                                    ->where('display_name', 'like', "%{$search}%")
                                    ->orderBy('display_name')->limit(20)
                                    ->pluck('display_name', 'id')->all())
                                ->getOptionLabelUsing(fn ($value): ?string => \App\Models\Entity::find($value)?->display_name)
                                ->createOptionForm([
                                    TextInput::make('display_name')->label('Organization name')->required()->maxLength(255),
                                ])
                                ->createOptionUsing(fn (array $data): int => \App\Models\Entity::create([
                                    'entity_type' => 'organization',
                                    'display_name' => $data['display_name'],
                                ])->getKey())
                                ->helperText('Optional. Creates an “employed by” connection you can click through on the graph.'),
                            TextInput::make('linkedin_url')->label('LinkedIn / profile URL')->url()->maxLength(1024),
                            TextInput::make('known_names')->label('Known names / nicknames')->maxLength(255),
                            Select::make('source_status')
                                ->options([
                                    'official' => 'Official contact',
                                    'source' => 'Source',
                                    'subject' => 'Subject',
                                    'critic' => 'Critic',
                                    'advocate' => 'Advocate',
                                    'expert' => 'Expert',
                                    'resident' => 'Resident',
                                ])->native(false),
                            Select::make('confidentiality_status')
                                ->options([
                                    'on_record' => 'On the record',
                                    'background' => 'Background',
                                    'not_for_attribution' => 'Not for attribution',
                                    'off_the_record' => 'Off the record',
                                    'confidential' => 'Confidential',
                                ])->native(false),
                            Textarea::make('dossier_summary')->label('What this person matters for')->columnSpanFull()->rows(3),
                            Textarea::make('reliability_notes')
                                ->helperText('Internal only — never shown in public-facing tools.')
                                ->columnSpanFull()->rows(2),
                        ]),

                    Section::make('Organization details')
                        ->relationship('organizationProfile')
                        ->visible(fn (Get $get): bool => in_array($get('entity_type'), \App\Models\Entity::ORGANIZATION_TYPES, true))
                        ->columns(2)
                        ->schema([
                            TextInput::make('dba_name')->label('DBA / common name')->maxLength(255),
                            Select::make('org_subtype')
                                ->label('Organization type')
                                ->options([
                                    'business' => 'Business',
                                    'pac' => 'Political action committee',
                                    'committee' => 'Campaign committee',
                                    'nonprofit' => 'Nonprofit',
                                    'law_firm' => 'Law firm',
                                    'consulting' => 'Consulting firm',
                                    'hoa' => 'HOA / neighborhood group',
                                    'school_district' => 'School district',
                                    'agency' => 'Government agency / special district',
                                    'media' => 'Media organization',
                                ])->native(false)->searchable(),
                            TextInput::make('website')->url()->maxLength(255),
                            TextInput::make('registration_number')->maxLength(255),
                            TextInput::make('registered_agent')->maxLength(255),
                        ]),

                    Section::make('Classification & review')
                        ->columns(2)
                        ->schema([
                            Select::make('sensitivity')
                                ->options([
                                    'public' => 'Public',
                                    'internal' => 'Internal',
                                    'confidential' => 'Confidential',
                                    'sealed' => 'Sealed (source vault — restricted)',
                                ])
                                ->default('internal')
                                ->required()
                                ->helperText('Sealed records are hidden from ordinary lists, search, and exports.'),
                            DatePicker::make('last_reviewed_at')->label('Last reviewed'),
                        ]),
                ])->columnSpan(1),

                // ---- RIGHT column: contact methods + geography ----
                Group::make([
                    // Kept near the top — contact details are referenced constantly.
                    Section::make('Contact methods')
                        ->description('Phone, email, Signal, socials. Referenced often, so they live up here.')
                        ->schema([
                            Repeater::make('contactMethods')
                                ->relationship()
                                ->hiddenLabel()
                                ->schema([
                                    Select::make('method')
                                        ->options([
                                            'phone' => 'Phone',
                                            'email' => 'Email',
                                            'signal' => 'Signal',
                                            'social' => 'Social account',
                                            'in_person' => 'In person',
                                        ])->required()->native(false),
                                    TextInput::make('value')->required()->maxLength(255),
                                    Toggle::make('is_preferred')->label('Preferred')->inline(false),
                                    Select::make('restrictions')
                                        ->options([
                                            'do_not_call' => 'Do not call',
                                            'text_only' => 'Text only',
                                            'no_voicemail' => 'No voicemail',
                                            'source_safe' => 'Source-safe channel',
                                        ])->native(false)->placeholder('None'),
                                    Select::make('sensitivity')
                                        ->options([
                                            'public' => 'Public',
                                            'internal' => 'Internal',
                                            'confidential' => 'Confidential',
                                        ])->default('internal')->required()->native(false),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => filled($state['value'] ?? null)
                                    ? ucfirst($state['method'] ?? 'contact') . ': ' . $state['value']
                                    : null)
                                ->collapsed()
                                ->cloneable()
                                ->addActionLabel('Add contact method')
                                ->defaultItems(0),
                        ]),

                    Section::make('Geography & significance')
                        ->columns(2)
                        ->schema([
                            TextInput::make('primary_geography')
                                ->helperText('e.g. Monument, Colorado Springs, El Paso County')
                                ->maxLength(255),
                            Select::make('primary_jurisdiction_id')
                                ->label('Primary jurisdiction')
                                ->relationship('jurisdiction', 'name')
                                ->searchable()->preload(),
                            Textarea::make('public_summary')->label('Public summary (publishable)')->columnSpanFull()->rows(2),
                            Textarea::make('internal_summary')->label('Internal summary (private)')->columnSpanFull()->rows(2),
                            Textarea::make('why_it_matters')->label('Why this matters (newsroom note)')->columnSpanFull()->rows(2),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}
