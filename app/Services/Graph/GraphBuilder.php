<?php

namespace App\Services\Graph;

use App\Models\Entity;
use App\Models\Relationship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds Cytoscape-ready nodes/edges from the relationships graph. Traverses outward from a focus
 * entity to a given degree (following BOTH directions), applying relationship-type / verification /
 * issue filters, honoring tenancy (via the model global scope) and hiding sealed records from users
 * without vault access. Node count is capped so a dense hub can't return an unbounded graph.
 */
class GraphBuilder
{
    public function build(?int $focusEntityId, int $depth = 2, array $opts = []): array
    {
        $depth = max(1, min(3, $depth));
        $maxNodes = (int) ($opts['maxNodes'] ?? 250);
        $canSeeSealed = (bool) auth()->user()?->can('view_confidential_identity');

        /** @var Collection<int, Relationship> $edges keyed by relationship id */
        $edges = collect();
        $nodeIds = collect();
        $truncated = false;

        if ($focusEntityId === null) {
            // Whole-network view (capped): everything the tenant can see.
            foreach ($this->relationshipQuery($opts, $canSeeSealed)->limit(400)->get() as $r) {
                $edges->put($r->id, $r);
                $nodeIds->push($r->from_entity_id)->push($r->to_entity_id);
            }
            $truncated = $nodeIds->unique()->count() > $maxNodes;
        } else {
            $frontier = collect([$focusEntityId]);
            $nodeIds->push($focusEntityId);

            for ($level = 0; $level < $depth; $level++) {
                if ($frontier->isEmpty()) {
                    break;
                }

                $rels = $this->relationshipQuery($opts, $canSeeSealed)
                    ->where(fn (Builder $q) => $q
                        ->whereIn('from_entity_id', $frontier)
                        ->orWhereIn('to_entity_id', $frontier))
                    ->get();

                $next = collect();
                foreach ($rels as $r) {
                    if ($edges->has($r->id)) {
                        continue;
                    }
                    $edges->put($r->id, $r);

                    foreach ([$r->from_entity_id, $r->to_entity_id] as $eid) {
                        if (! $nodeIds->contains($eid)) {
                            $nodeIds->push($eid);
                            $next->push($eid);
                        }
                    }

                    if ($nodeIds->unique()->count() >= $maxNodes) {
                        $truncated = true;
                        break;
                    }
                }

                if ($truncated) {
                    break;
                }
                $frontier = $next->unique()->values();
            }
        }

        // Resolve entity nodes; drop sealed ones (and any edges touching them) for unpermitted users.
        $entities = Entity::query()
            ->with(['personProfile', 'organizationProfile'])
            ->whereIn('id', $nodeIds->unique()->all())
            ->when(! $canSeeSealed, fn (Builder $q) => $q->where('sensitivity', '!=', 'sealed'))
            ->get()
            ->keyBy('id');

        $nodes = $entities->map(fn (Entity $e): array => [
            'data' => [
                'id' => (string) $e->id,
                'label' => $e->display_name,
                'type' => $e->entity_type,
                'typeLabel' => Entity::TYPE_LABELS[$e->entity_type] ?? ucfirst($e->entity_type),
                // person vs org for styling — all org-like types render as "org".
                'kind' => $e->entity_type === 'person' ? 'person' : 'org',
                'sub' => $e->entity_type === 'person'
                    ? ($e->personProfile?->professional_role)
                    : ($e->organizationProfile?->org_subtype),
                'sensitivity' => $e->sensitivity,
                'focus' => $e->id === $focusEntityId,
            ],
        ])->values()->all();

        $edgeData = $edges
            ->filter(fn (Relationship $r): bool => $entities->has($r->from_entity_id) && $entities->has($r->to_entity_id))
            ->map(fn (Relationship $r): array => [
                'data' => [
                    'id' => 'r' . $r->id,
                    'rid' => $r->id,
                    'source' => (string) $r->from_entity_id,
                    'target' => (string) $r->to_entity_id,
                    'label' => $r->type?->label ?? $r->type?->name ?? '',
                    'verification' => $r->verification_state,
                    'directed' => (bool) $r->is_directional,
                    'fromLabel' => $entities->get($r->from_entity_id)?->display_name,
                    'toLabel' => $entities->get($r->to_entity_id)?->display_name,
                    'notes' => $r->notes,
                    'start' => $r->start_date?->toDateString(),
                    'end' => $r->end_date?->toDateString(),
                    'confidence' => $r->confidence,
                ],
            ])->values()->all();

        return [
            'nodes' => $nodes,
            'edges' => $edgeData,
            'meta' => [
                'truncated' => $truncated,
                'nodeCount' => count($nodes),
                'edgeCount' => count($edgeData),
                'focus' => $focusEntityId,
            ],
        ];
    }

    private function relationshipQuery(array $opts, bool $canSeeSealed): Builder
    {
        $q = Relationship::query()->with('type');

        if (! empty($opts['types'])) {
            $q->whereIn('relationship_type_id', $opts['types']);
        }
        if (! empty($opts['verificationStates'])) {
            $q->whereIn('verification_state', $opts['verificationStates']);
        }
        if (! empty($opts['issueTagId'])) {
            $q->where('issue_tag_id', $opts['issueTagId']);
        }
        if (! $canSeeSealed) {
            $q->where('sensitivity', '!=', 'sealed');
        }

        return $q;
    }
}
