<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Entities\EntityResource;
use App\Filament\Resources\Relationships\RelationshipResource;
use App\Models\Entity;
use App\Models\RelationshipType;
use App\Models\SavedGraphView;
use App\Models\Tag;
use App\Services\Graph\GraphBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Interactive relationship map — the platform's primary visual investigation tool. Traverses the
 * connection web from a focus entity, styles edges by verification state, and links each node/edge
 * back to its dossier / evidence page.
 */
class RelationshipGraph extends Page
{
    protected string $view = 'filament.pages.relationship-graph';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Relationship graph';

    protected static string|\UnitEnum|null $navigationGroup = 'Research';

    protected static ?int $navigationSort = 2;

    // --- Filter state (bound live in the view) ---
    public ?int $focusEntityId = null;
    public int $depth = 1;
    /** @var array<int> */
    public array $types = [];
    /** @var array<string> */
    public array $verificationStates = [];
    public ?int $issueTagId = null;

    /** The currently-loaded saved view, if any (null = unsaved / custom filters). */
    public ?int $currentViewId = null;

    /** @var array{nodes:array,edges:array,meta:array} */
    public array $graph = ['nodes' => [], 'edges' => [], 'meta' => []];

    public function mount(): void
    {
        $this->rebuild();
    }

    /**
     * Header actions: save the current filter state as a named view, and (when one is loaded)
     * delete it. Loading is handled by the "Saved views" picker in the filter bar.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveView')
                ->label('Save view')
                ->icon(Heroicon::OutlinedBookmark)
                ->schema([
                    TextInput::make('name')
                        ->label('View name')
                        ->placeholder('e.g. Buc-ee\'s network, 2027 county commission')
                        ->required()
                        ->maxLength(120),
                ])
                ->action(function (array $data): void {
                    $view = SavedGraphView::create([
                        'user_id' => auth()->id(),
                        'name' => $data['name'],
                        'params' => $this->currentParams(),
                    ]);

                    $this->currentViewId = $view->id;

                    Notification::make()
                        ->title('View saved')
                        ->body("“{$view->name}” will reload these filters in one click.")
                        ->success()
                        ->send();
                }),

            Action::make('updateView')
                ->label('Update view')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => (bool) $this->currentViewId)
                ->requiresConfirmation()
                ->modalHeading('Overwrite saved view?')
                ->modalDescription('This replaces the saved filters with what you have on screen now.')
                ->action(function (): void {
                    $view = SavedGraphView::find($this->currentViewId);
                    if (! $view) {
                        return;
                    }
                    $view->update(['params' => $this->currentParams()]);

                    Notification::make()->title('View updated')->success()->send();
                }),

            Action::make('deleteView')
                ->label('Delete view')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => (bool) $this->currentViewId)
                ->requiresConfirmation()
                ->action(function (): void {
                    SavedGraphView::whereKey($this->currentViewId)->delete();
                    $this->currentViewId = null;

                    Notification::make()->title('View deleted')->success()->send();
                }),
        ];
    }

    /** @return array{focusEntityId:?int, depth:int, types:array, verificationStates:array, issueTagId:?int} */
    protected function currentParams(): array
    {
        return [
            'focusEntityId' => $this->focusEntityId,
            'depth' => $this->depth,
            'types' => $this->types,
            'verificationStates' => $this->verificationStates,
            'issueTagId' => $this->issueTagId,
        ];
    }

    /** Load a saved view's filters and redraw. Bound to the "Saved views" picker (live). */
    public function updatedCurrentViewId($value): void
    {
        if (! $value) {
            return;
        }

        $view = SavedGraphView::find($value);
        if (! $view) {
            $this->currentViewId = null;

            return;
        }

        $p = $view->params ?? [];
        $this->focusEntityId = $p['focusEntityId'] ?? null;
        $this->depth = $p['depth'] ?? 2;
        $this->types = $p['types'] ?? [];
        $this->verificationStates = $p['verificationStates'] ?? [];
        $this->issueTagId = $p['issueTagId'] ?? null;

        // Sync the wire:ignore client-side pickers to the loaded selection.
        $this->dispatch('types-loaded', types: $this->types);
        $this->dispatch('focus-loaded', id: $this->focusEntityId, label: $this->focusEntityLabel());
        $this->rebuild();
    }

    /** @return array<int, string> */
    public function getSavedViewOptions(): array
    {
        return SavedGraphView::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['focusEntityId', 'depth', 'types', 'verificationStates', 'issueTagId'], true)) {
            $this->rebuild();
        }
    }

    public function resetFilters(): void
    {
        $this->focusEntityId = null;
        $this->depth = 1;
        $this->types = [];
        $this->verificationStates = [];
        $this->issueTagId = null;
        $this->currentViewId = null;
        $this->dispatch('filters-reset'); // clear the client-side type picker (it's wire:ignore)
        $this->rebuild();
    }

    public function rebuild(): void
    {
        $graph = app(GraphBuilder::class)->build($this->focusEntityId, $this->depth, [
            'types' => $this->types,
            'verificationStates' => $this->verificationStates,
            'issueTagId' => $this->issueTagId,
        ]);

        // Enrich with click-through URLs (kept out of the pure builder).
        foreach ($graph['nodes'] as &$node) {
            $node['data']['url'] = EntityResource::getUrl('edit', ['record' => $node['data']['id']]);
        }
        unset($node);
        foreach ($graph['edges'] as &$edge) {
            $edge['data']['url'] = RelationshipResource::getUrl('edit', ['record' => $edge['data']['rid']]);
        }
        unset($edge);

        $this->graph = $graph;
        $this->dispatch('graph-updated', graph: $graph);
    }

    /**
     * Server-side search for the "Start from" picker. Queries the full entity table as the user
     * types, so it scales past the old client-side 500-row cap (production has thousands).
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function searchEntities(string $q = ''): array
    {
        $q = trim($q);

        return Entity::query()
            ->when(! auth()->user()?->can('view_confidential_identity'), fn ($query) => $query->where('sensitivity', '!=', 'sealed'))
            ->when($q !== '', fn ($query) => $query->where('display_name', 'like', "%{$q}%"))
            ->orderBy('display_name')
            ->limit(30)
            ->get(['id', 'display_name'])
            ->map(fn ($e): array => ['id' => (int) $e->id, 'name' => $e->display_name])
            ->all();
    }

    /** Display name of the currently-focused entity — so the picker button shows it without a full list. */
    public function focusEntityLabel(): ?string
    {
        if (! $this->focusEntityId) {
            return null;
        }

        return Entity::query()->whereKey($this->focusEntityId)->value('display_name');
    }

    /** @return array<int, string> */
    public function getTypeOptions(): array
    {
        return RelationshipType::query()->orderBy('label')->pluck('label', 'id')->all();
    }

    /** @return array<int, string> */
    public function getIssueOptions(): array
    {
        return Tag::query()->where('kind', 'issue')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    public function getVerificationOptions(): array
    {
        return [
            'verified' => 'Verified',
            'corroborated' => 'Corroborated',
            'reported' => 'Reported',
            'lead' => 'Lead',
            'disputed' => 'Disputed',
            'disproven' => 'Disproven',
        ];
    }
}
