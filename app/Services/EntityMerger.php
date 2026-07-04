<?php

namespace App\Services;

use App\Models\Entity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges a duplicate entity (source) into a keeper: every reference — TRACER finance links,
 * connections, contacts, addresses, links, notes, positions, claims, story/document pivots, tags —
 * is repointed to the keeper, duplicates and self-loops are cleaned up, the source's name is kept
 * as an alias, and the source is then removed. Runs in one transaction.
 */
class EntityMerger
{
    public function merge(Entity $source, Entity $keeper): void
    {
        if ($source->getKey() === $keeper->getKey()) {
            throw new InvalidArgumentException('Cannot merge an entity into itself.');
        }
        if ($source->organization_id !== $keeper->organization_id) {
            throw new InvalidArgumentException('Entities belong to different organizations.');
        }

        $s = $source->getKey();
        $k = $keeper->getKey();
        $morph = $source->getMorphClass();

        DB::transaction(function () use ($s, $k, $morph, $source, $keeper): void {
            // 1. Finance links (contributor / committee / candidate).
            foreach (['contributor_entity_id', 'committee_entity_id', 'candidate_entity_id'] as $col) {
                DB::table('finance_transactions')->where($col, $s)->update([$col => $k]);
            }

            // 2. Simple one-to-many entity references.
            foreach ([
                ['positions_interests', 'entity_id'],
                ['claims', 'subject_entity_id'],
                ['contact_methods', 'entity_id'],
                ['addresses', 'entity_id'],
                ['links', 'entity_id'],
                ['contact_interactions', 'entity_id'],
                ['person_profiles', 'current_company_entity_id'],
            ] as [$table, $col]) {
                DB::table($table)->where($col, $s)->update([$col => $k]);
            }

            // 3. Many-to-many pivots — drop rows that would collide with the keeper, then repoint.
            $this->mergePivot('story_entities', 'story_id', $s, $k);
            $this->mergePivot('story_contacts', 'story_id', $s, $k);
            $this->mergePivot('document_entity_links', 'document_id', $s, $k);

            // Polymorphic pivots (tags, access grants).
            DB::table('taggables')->where('taggable_type', $morph)->where('taggable_id', $s)
                ->whereIn('tag_id', fn ($q) => $q->select('tag_id')->from('taggables')
                    ->where('taggable_type', $morph)->where('taggable_id', $k))
                ->delete();
            DB::table('taggables')->where('taggable_type', $morph)->where('taggable_id', $s)->update(['taggable_id' => $k]);
            DB::table('user_access_grants')->where('grantable_type', $morph)->where('grantable_id', $s)->update(['grantable_id' => $k]);

            // 4. Connections: repoint, delete resulting self-loops, then de-duplicate keeper edges.
            DB::table('relationships')->where('from_entity_id', $s)->update(['from_entity_id' => $k]);
            DB::table('relationships')->where('to_entity_id', $s)->update(['to_entity_id' => $k]);
            DB::table('relationships')->where('from_entity_id', $k)->where('to_entity_id', $k)->delete();
            DB::delete(
                'DELETE FROM relationships WHERE (from_entity_id = ? OR to_entity_id = ?)
                 AND id NOT IN (SELECT MIN(id) FROM relationships GROUP BY from_entity_id, to_entity_id, relationship_type_id)',
                [$k, $k],
            );

            // 5. Profiles: move the source's only if the keeper lacks one (otherwise keeper's wins).
            foreach (['person_profiles', 'organization_profiles'] as $table) {
                $keeperHas = DB::table($table)->where('entity_id', $k)->exists();
                if (! $keeperHas) {
                    DB::table($table)->where('entity_id', $s)->update(['entity_id' => $k]);
                }
            }

            // 6. Preserve the source's name as an alias of the keeper, then repoint its aliases.
            if (strcasecmp((string) $source->display_name, (string) $keeper->display_name) !== 0) {
                $dupe = DB::table('entity_aliases')->where('entity_id', $k)
                    ->whereRaw('LOWER(alias) = ?', [mb_strtolower((string) $source->display_name)])->exists();
                if (! $dupe) {
                    DB::table('entity_aliases')->insert([
                        'organization_id' => $keeper->organization_id,
                        'entity_id' => $k,
                        'alias' => $source->display_name,
                        'alias_type' => 'merged',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            DB::table('entity_aliases')->where('entity_id', $s)->update(['entity_id' => $k]);

            // 7. Remove the source (its remaining owned rows, if any, cascade away).
            $source->forceDelete();
        });
    }

    /** For a pivot unique on ($otherCol, entity_id): delete the source rows that would collide, then repoint. */
    private function mergePivot(string $table, string $otherCol, int $source, int $keeper): void
    {
        DB::table($table)->where('entity_id', $source)
            ->whereIn($otherCol, fn ($q) => $q->select($otherCol)->from($table)->where('entity_id', $keeper))
            ->delete();
        DB::table($table)->where('entity_id', $source)->update(['entity_id' => $keeper]);
    }
}
