<?php

namespace App\Filament\Resources\Entities\Pages;

use App\Filament\Resources\Entities\EntityResource;
use App\Models\ContactInteraction;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Services\Enrichment\FinanceEnricher;
use App\Services\Enrichment\ProfileTextParser;
use App\Services\Finance\FinanceNetworkBuilder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Set;

class EditEntity extends EditRecord
{
    protected static string $resource = EntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->logContactAction(),
            $this->fillFromProfileAction(),
            $this->fillFromOrgProfileAction(),
            $this->setupCommitteeAction(),
            $this->buildDonorNetworkAction(),
            $this->enrichFromFinanceAction(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * Paste a public profile (e.g. a LinkedIn page the reporter is viewing) and structure it into
     * dossier fields for review. Parsing is best-effort; nothing saves until you confirm. No
     * scraping — the human obtained and pasted the text.
     */
    protected function fillFromProfileAction(): Action
    {
        return Action::make('fillFromProfile')
            ->label('Fill from pasted profile')
            ->icon('heroicon-o-clipboard-document')
            ->visible(fn (): bool => $this->record->entity_type === 'person')
            ->modalWidth('2xl')
            ->modalDescription('Paste a public profile; the fields below auto-fill for your review. Edit anything, then save. It never overwrites without your confirmation.')
            ->fillForm(fn (): array => [
                'linkedin_url' => $this->record->personProfile?->linkedin_url,
            ])
            ->schema([
                Textarea::make('source_text')
                    ->label('Paste profile text')
                    ->rows(6)
                    ->live(debounce: 600)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $parsed = app(ProfileTextParser::class)->parse((string) $state);
                        $set('full_name', $parsed['full_name']);
                        $set('professional_role', $parsed['professional_role']);
                        $set('current_company', $parsed['employer']);
                        $set('geography_detail', $parsed['geography']);
                        $set('dossier_summary', $parsed['summary']);
                    })
                    ->helperText('Best-effort structuring — always review the parsed fields.'),
                TextInput::make('linkedin_url')->label('LinkedIn / profile URL')->url()->maxLength(1024),
                TextInput::make('full_name')->label('Full name'),
                TextInput::make('professional_role')->label('Role / title'),
                TextInput::make('current_company')->label('Current company / employer'),
                TextInput::make('geography_detail')->label('Location'),
                Textarea::make('dossier_summary')->label('Summary')->rows(3),
            ])
            ->action(function (array $data): void {
                $this->record->personProfile()->updateOrCreate([], array_filter([
                    'full_name' => $data['full_name'] ?? null,
                    'professional_role' => $data['professional_role'] ?? null,
                    'current_company' => $data['current_company'] ?? null,
                    'geography_detail' => $data['geography_detail'] ?? null,
                    'dossier_summary' => $data['dossier_summary'] ?? null,
                    'linkedin_url' => $data['linkedin_url'] ?? null,
                ], fn ($v) => filled($v)));

                // Keep the profile link as provenance.
                if (! empty($data['linkedin_url'])) {
                    $this->record->links()->firstOrCreate(
                        ['url' => $data['linkedin_url']],
                        ['kind' => 'social', 'platform' => 'linkedin', 'title' => 'LinkedIn profile', 'sensitivity' => 'public'],
                    );
                }

                $this->refreshFormData(['personProfile']);
                Notification::make()->title('Record updated from pasted profile')->success()->send();
            });
    }

    /**
     * Prominent top-of-page shortcut to jot notes during a live call without scrolling to the
     * contact log. Writes straight into contact_interactions for this entity.
     */
    protected function logContactAction(): Action
    {
        return Action::make('logContact')
            ->label('Log contact')
            ->icon('heroicon-o-phone-arrow-up-right')
            ->color('primary')
            ->modalWidth('2xl')
            ->schema([
                Select::make('interaction_type')
                    ->label('Type')
                    ->options([
                        'call' => 'Call',
                        'email' => 'Email',
                        'meeting' => 'Meeting',
                        'tip' => 'Tip',
                        'interview' => 'Interview',
                        'public_comment' => 'Public comment',
                        'records_request' => 'Records request',
                    ])->default('call')->required()->native(false),
                DateTimePicker::make('occurred_at')->label('When')->default(now())->required()->seconds(false),
                Textarea::make('summary')
                    ->label('Notes')
                    ->rows(6)
                    ->placeholder('Notes from this contact — what was said, agreed, promised, or requested.'),
                Select::make('attribution_terms')
                    ->options([
                        'on_record' => 'On the record',
                        'background' => 'Background',
                        'deep_background' => 'Deep background',
                        'off_the_record' => 'Off the record',
                        'confidential' => 'Confidential',
                    ])->native(false)->placeholder('Not specified'),
                DateTimePicker::make('follow_up_at')->label('Follow up on')->seconds(false),
                Select::make('visibility')
                    ->options(['internal' => 'Internal', 'sealed' => 'Sealed (restricted)'])
                    ->default('internal')->required(),
            ])
            ->action(function (array $data): void {
                $interaction = new ContactInteraction($data);
                $interaction->entity_id = $this->record->getKey();
                $interaction->save();

                Notification::make()->title('Contact logged')->success()->send();
            });
    }

    /**
     * Organization counterpart: paste a company "About"/overview page and structure it into the
     * org fields (website, common name, location, summary) for review before saving.
     */
    protected function fillFromOrgProfileAction(): Action
    {
        return Action::make('fillFromOrgProfile')
            ->label('Fill from pasted profile')
            ->icon('heroicon-o-clipboard-document')
            ->visible(fn (): bool => $this->record->entity_type === 'organization')
            ->modalWidth('2xl')
            ->modalDescription('Paste a company About/overview page; the fields below auto-fill for your review. Edit anything, then save.')
            ->fillForm(fn (): array => [
                'website' => $this->record->organizationProfile?->website,
            ])
            ->schema([
                Textarea::make('source_text')
                    ->label('Paste company profile / About text')
                    ->rows(6)
                    ->live(debounce: 600)
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $parsed = app(ProfileTextParser::class)->parseOrganization((string) $state);
                        $summary = $parsed['summary'];
                        $facts = array_filter([
                            $parsed['industry'] ? 'Industry: ' . $parsed['industry'] : null,
                            $parsed['founded'] ? 'Founded: ' . $parsed['founded'] : null,
                            $parsed['size'] ? 'Size: ' . $parsed['size'] : null,
                        ]);
                        if ($facts) {
                            $summary = trim(($summary ? $summary . "\n\n" : '') . implode(' · ', $facts));
                        }
                        $set('dba_name', $parsed['name']);
                        $set('website', $parsed['website']);
                        $set('primary_geography', $parsed['geography']);
                        $set('public_summary', $summary);
                    })
                    ->helperText('Best-effort structuring — always review the parsed fields.'),
                TextInput::make('dba_name')->label('Common name (DBA)'),
                TextInput::make('website')->label('Website / profile URL')->url()->maxLength(1024),
                TextInput::make('primary_geography')->label('Headquarters / location'),
                Textarea::make('public_summary')->label('Public summary')->rows(4),
            ])
            ->action(function (array $data): void {
                if (filled($data['dba_name'] ?? null) || filled($data['website'] ?? null)) {
                    $this->record->organizationProfile()->updateOrCreate([], array_filter([
                        'dba_name' => $data['dba_name'] ?? null,
                        'website' => $data['website'] ?? null,
                    ], fn ($v) => filled($v)));
                }

                $this->record->fill(array_filter([
                    'primary_geography' => $data['primary_geography'] ?? null,
                    'public_summary' => $data['public_summary'] ?? null,
                ], fn ($v) => filled($v)))->save();

                if (! empty($data['website'])) {
                    $isLinkedIn = str_contains(strtolower($data['website']), 'linkedin.com');
                    $this->record->links()->firstOrCreate(
                        ['url' => $data['website']],
                        [
                            'kind' => $isLinkedIn ? 'social' : 'website',
                            'platform' => $isLinkedIn ? 'linkedin' : null,
                            'title' => $isLinkedIn ? 'LinkedIn page' : 'Website',
                            'sensitivity' => 'public',
                        ],
                    );
                }

                $this->refreshFormData(['organizationProfile', 'primary_geography', 'public_summary']);
                Notification::make()->title('Record updated from pasted profile')->success()->send();
            });
    }

    /**
     * For a candidate: create (or link) their campaign committee as an organization, connect it via
     * a "Campaign committee" relationship, and link the committee's TRACER contributions received.
     */
    protected function setupCommitteeAction(): Action
    {
        return Action::make('setupCommittee')
            ->label('Set up campaign committee')
            ->icon('heroicon-o-building-library')
            ->visible(fn (): bool => $this->record->entity_type === 'person')
            ->modalWidth('xl')
            ->modalDescription('Creates (or links) this candidate’s committee as an organization, connects it to them, and links the committee’s TRACER contributions received.')
            ->fillForm(fn (): array => ['committee_name' => $this->suggestedCommitteeName()])
            ->schema([
                Select::make('committee_name')
                    ->label('Committee (as it appears in TRACER)')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => FinanceTransaction::query()
                        ->whereNotNull('committee_name')->where('committee_name', 'like', "%{$search}%")
                        ->distinct()->orderBy('committee_name')->limit(30)
                        ->pluck('committee_name', 'committee_name')->all())
                    ->getOptionLabelUsing(fn ($value): string => (string) $value)
                    ->createOptionForm([\Filament\Forms\Components\TextInput::make('name')->label('Committee name')->required()])
                    ->createOptionUsing(fn (array $data): string => $data['name'])
                    ->helperText('Pre-filled from this candidate’s TRACER records when available; you can also add one not in TRACER.'),
            ])
            ->action(function (array $data): void {
                $name = trim((string) $data['committee_name']);
                if ($name === '') {
                    return;
                }

                $committee = Entity::query()->organizationLike()
                    ->whereRaw('LOWER(display_name) = ?', [mb_strtolower($name)])->first()
                    ?? Entity::create(['entity_type' => 'organization', 'display_name' => $name, 'sensitivity' => 'internal']);
                $committee->organizationProfile()->firstOrCreate([], ['org_subtype' => 'committee']);

                // Deliberately setting up a committee makes it a curated subject — adopt it out of the
                // hidden "imported finance actors" pool so it shows in the People & Organizations list.
                if ($committee->origin !== null) {
                    $committee->update(['origin' => null]);
                }

                $type = RelationshipType::firstOrCreate(
                    ['name' => 'candidate_committee'],
                    ['label' => 'Campaign committee', 'inverse_name' => 'Candidate', 'category' => 'campaign', 'is_directional' => true],
                );
                Relationship::firstOrCreate(
                    ['from_entity_id' => $this->record->id, 'to_entity_id' => $committee->id, 'relationship_type_id' => $type->id],
                    ['status' => 'active', 'verification_state' => 'reported', 'sensitivity' => 'internal', 'notes' => 'Candidate’s campaign committee.'],
                );

                // Link the committee's TRACER contributions received, and attribute this candidate.
                $linked = FinanceTransaction::whereNull('committee_entity_id')
                    ->where('data_type', 'contributions')->where('committee_name', $name)
                    ->update(['committee_entity_id' => $committee->id, 'match_state' => 'approved']);
                FinanceTransaction::whereNull('candidate_entity_id')
                    ->where('data_type', 'contributions')->where('committee_name', $name)
                    ->update(['candidate_entity_id' => $this->record->id]);

                Notification::make()
                    ->title('Campaign committee linked')
                    ->body("“{$committee->display_name}” connected to this candidate; {$linked} contribution(s) linked.")
                    ->success()->send();

                $this->redirect(EntityResource::getUrl('edit', ['record' => $committee]), navigate: true);
            });
    }

    /**
     * Turn this committee's received TRACER contributions into donor entities + aggregated "donated
     * to" connections, so its money shows up in the relationship graph. Idempotent — safe to re-run
     * as new contributions are imported; won't downgrade a human-verified edge.
     */
    protected function buildDonorNetworkAction(): Action
    {
        return Action::make('buildDonorNetwork')
            ->label('Build donor network')
            ->icon('heroicon-o-share')
            ->visible(fn (): bool => $this->record->isOrganizationLike() && $this->committeeContributionCount() > 0)
            ->modalWidth('lg')
            ->modalDescription('Promotes this committee’s TRACER donors into entities and draws one aggregated “donated to” connection per donor. This is what makes the money visible in the relationship graph.')
            ->schema([
                Select::make('min_total')
                    ->label('Include donors giving at least')
                    ->options([
                        '0' => 'Every donor',
                        '100' => '$100 or more',
                        '500' => '$500 or more',
                        '1000' => '$1,000 or more',
                    ])
                    ->default('0')
                    ->required()
                    ->helperText('Lower thresholds surface more donors but create more finance-origin entities (kept out of the curated dossier list).'),
            ])
            ->action(function (array $data): void {
                $min = (float) $data['min_total'];
                $builder = app(FinanceNetworkBuilder::class);

                $donors = 0;
                $connections = 0;
                foreach ($this->committeeContributionNames() as $name) {
                    $r = $builder->buildFromCommittee($name, $min, 500);
                    $donors += $r['donors_promoted'];
                    $connections += $r['connections'];
                }

                Notification::make()
                    ->title('Donor network built')
                    ->body("{$connections} donor connection(s) from {$donors} donor(s) now link to this committee.")
                    ->success()->send();

                $this->redirect(EntityResource::getUrl('edit', ['record' => $this->record]), navigate: true);
            });
    }

    /**
     * Tier 1 enrichment: fill blank dossier fields (role, employer, geography, internal summary)
     * from this entity's linked TRACER records. Derived from imported public-record data — no
     * scraping. See docs/design/enrichment-architecture.md.
     */
    protected function enrichFromFinanceAction(): Action
    {
        return Action::make('enrichFromFinance')
            ->label('Fill blanks from finance data')
            ->icon('heroicon-o-sparkles')
            ->visible(fn (): bool => $this->hasLinkedFinance())
            ->modalWidth('lg')
            ->modalDescription('Fills empty dossier fields — role/title, employer, geography, internal summary — from this entity’s linked TRACER records. Derived from public-record data you already imported; nothing is scraped.')
            ->schema([
                Toggle::make('overwrite')
                    ->label('Also refresh finance-derived fields that are already filled')
                    ->default(false),
            ])
            ->action(function (array $data): void {
                $filled = app(FinanceEnricher::class)->enrich($this->record, (bool) ($data['overwrite'] ?? false));

                if ($filled === []) {
                    Notification::make()
                        ->title('Nothing to fill')
                        ->body('No blank fields could be derived from this entity’s finance data.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Dossier enriched')
                    ->body('Filled from finance data: ' . implode(', ', $filled) . '.')
                    ->success()->send();

                $this->redirect(EntityResource::getUrl('edit', ['record' => $this->record]), navigate: true);
            });
    }

    /** Whether this entity has any TRACER contributions linked (as contributor or committee). */
    protected function hasLinkedFinance(): bool
    {
        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where(fn ($q) => $q->where('contributor_entity_id', $this->record->id)
                ->orWhere('committee_entity_id', $this->record->id))
            ->exists();
    }

    /** TRACER committee name(s) belonging to this entity — its linked rows, else its display name. */
    protected function committeeContributionNames(): array
    {
        $linked = FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where('committee_entity_id', $this->record->id)
            ->whereNotNull('committee_name')->where('committee_name', '!=', '')
            ->distinct()->pluck('committee_name')->all();

        return $linked ?: [$this->record->display_name];
    }

    /** Contributions received by this committee, whether already linked or matchable by name. */
    protected function committeeContributionCount(): int
    {
        return FinanceTransaction::query()
            ->where('data_type', 'contributions')
            ->where(fn ($q) => $q->where('committee_entity_id', $this->record->id)
                ->orWhere('committee_name', $this->record->display_name))
            ->count();
    }

    /** Most common TRACER committee for contributions that name this person as the candidate. */
    protected function suggestedCommitteeName(): ?string
    {
        $tokens = collect(preg_split('/\s+/', mb_strtolower(trim((string) $this->record->display_name))))
            ->filter(fn ($t) => strlen($t) > 2)->values();
        if ($tokens->isEmpty()) {
            return null;
        }

        $query = FinanceTransaction::query()->where('data_type', 'contributions')->whereNotNull('candidate_name');
        foreach ($tokens as $token) {
            $query->where('candidate_name', 'like', "%{$token}%");
        }

        return $query->selectRaw('committee_name, count(*) as c')->groupBy('committee_name')
            ->orderByDesc('c')->value('committee_name');
    }
}
