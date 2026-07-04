<?php

namespace Tests\Feature;

use App\Filament\Resources\Relationships\Pages\EditRelationship;
use App\Models\Document;
use App\Models\DocumentCitation;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\Relationship;
use App\Models\RelationshipEvidence;
use App\Models\RelationshipType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EvidenceVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Relationship $rel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $a = Entity::create(['entity_type' => 'person', 'display_name' => 'Donor A']);
        $b = Entity::create(['entity_type' => 'organization', 'display_name' => 'Committee B']);
        $type = RelationshipType::where('name', 'donated_to')->first();

        $this->rel = Relationship::create([
            'from_entity_id' => $a->id,
            'to_entity_id' => $b->id,
            'relationship_type_id' => $type->id,
            'verification_state' => 'reported',
        ]);
    }

    public function test_cannot_verify_without_evidence(): void
    {
        Livewire::test(EditRelationship::class, ['record' => $this->rel->getKey()])
            ->callAction('markVerified');

        $this->assertSame('reported', $this->rel->fresh()->verification_state, 'Stays unverified without evidence');
    }

    public function test_can_verify_after_attaching_evidence(): void
    {
        $doc = Document::create(['title' => 'TRACER filing 2026', 'sensitivity' => 'public']);
        $citation = DocumentCitation::create([
            'document_id' => $doc->id,
            'page' => 3,
            'quote' => 'Contribution of $2,500 from Donor A to Committee B.',
        ]);
        RelationshipEvidence::create([
            'relationship_id' => $this->rel->id,
            'document_citation_id' => $citation->id,
            'note' => 'Line item on filing.',
        ]);

        Livewire::test(EditRelationship::class, ['record' => $this->rel->getKey()])
            ->callAction('markVerified');

        $this->assertSame('verified', $this->rel->fresh()->verification_state, 'Verified once evidence exists');
    }
}
