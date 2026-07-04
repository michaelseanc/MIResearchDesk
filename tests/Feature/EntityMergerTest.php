<?php

namespace Tests\Feature;

use App\Models\ContactMethod;
use App\Models\Entity;
use App\Models\EntityAlias;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipType;
use App\Models\User;
use App\Services\EntityMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EntityMergerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    }

    public function test_merge_repoints_everything_and_removes_source(): void
    {
        $keeper = Entity::create(['entity_type' => 'person', 'display_name' => 'Ryan Graham']);
        $source = Entity::create(['entity_type' => 'person', 'display_name' => 'Ryan A Graham']);
        $committee = Entity::create(['entity_type' => 'pac', 'display_name' => 'Committee X']);

        $donated = RelationshipType::where('name', 'donated_to')->first();
        $family = RelationshipType::where('name', 'family_of')->first();

        // Both keeper and source donated to the same committee → should dedupe to one after merge.
        Relationship::create(['from_entity_id' => $keeper->id, 'to_entity_id' => $committee->id, 'relationship_type_id' => $donated->id, 'verification_state' => 'reported']);
        Relationship::create(['from_entity_id' => $source->id, 'to_entity_id' => $committee->id, 'relationship_type_id' => $donated->id, 'verification_state' => 'reported']);
        // A connection between source and keeper → becomes a self-loop and must be removed.
        Relationship::create(['from_entity_id' => $source->id, 'to_entity_id' => $keeper->id, 'relationship_type_id' => $family->id, 'verification_state' => 'reported']);

        $txn = FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'contributor_name' => 'Ryan A Graham', 'committee_name' => 'Committee X',
            'contributor_entity_id' => $source->id, 'amount' => 250, 'transaction_date' => '2024-06-01', 'row_hash' => 'm1',
        ]);
        ContactMethod::create(['entity_id' => $source->id, 'method' => 'email', 'value' => 'ryan@example.com']);
        EntityAlias::create(['entity_id' => $source->id, 'alias' => 'R.A. Graham']);

        app(EntityMerger::class)->merge($source, $keeper);

        // Source is gone.
        $this->assertNull(Entity::withTrashed()->find($source->id));

        // Finance + contact repointed.
        $this->assertSame($keeper->id, $txn->fresh()->contributor_entity_id);
        $this->assertSame($keeper->id, ContactMethod::where('value', 'ryan@example.com')->first()->entity_id);

        // Connections: one deduped donation edge to the committee, no self-loop.
        $this->assertSame(1, Relationship::where('from_entity_id', $keeper->id)->where('to_entity_id', $committee->id)->where('relationship_type_id', $donated->id)->count());
        $this->assertSame(0, Relationship::where('from_entity_id', $keeper->id)->where('to_entity_id', $keeper->id)->count());

        // Aliases: source name preserved + its alias moved.
        $aliases = EntityAlias::where('entity_id', $keeper->id)->pluck('alias')->all();
        $this->assertContains('Ryan A Graham', $aliases);
        $this->assertContains('R.A. Graham', $aliases);
    }
}
