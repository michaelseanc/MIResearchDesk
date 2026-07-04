<?php

namespace Tests\Feature;

use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use App\Services\Enrichment\FinanceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceEnricherTest extends TestCase
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

    private function gift(array $attrs): void
    {
        FinanceTransaction::create(array_merge([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'amount' => 100, 'transaction_date' => '2024-06-01',
        ], $attrs));
    }

    public function test_person_dossier_filled_from_linked_contributions(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Ryan Graham']);

        // Two gifts: employer/occupation/city derived by frequency; summary aggregates all.
        $this->gift(['contributor_entity_id' => $person->id, 'committee_name' => 'GROW FREEDOM FUND',
            'employer' => 'The Platinum Group', 'occupation' => 'Realtor', 'city' => 'Monument', 'state' => 'CO',
            'amount' => 625, 'row_hash' => 'g1']);
        $this->gift(['contributor_entity_id' => $person->id, 'committee_name' => 'EL PASO COUNTY GOP',
            'employer' => 'The Platinum Group', 'occupation' => 'Realtor', 'city' => 'Monument', 'state' => 'CO',
            'amount' => 100, 'row_hash' => 'g2']);

        $filled = app(FinanceEnricher::class)->enrich($person);

        $person->refresh();
        $this->assertSame('Realtor', $person->personProfile->professional_role);
        $this->assertSame('The Platinum Group', $person->personProfile->current_company);
        $this->assertSame('Monument, CO', $person->primary_geography);
        $this->assertStringContainsString('gave $725 across 2 contributions', $person->internal_summary);
        $this->assertStringContainsString('GROW FREEDOM FUND', $person->internal_summary);
        $this->assertContains('employer', $filled);
    }

    public function test_is_non_destructive_by_default_but_overwrites_when_asked(): void
    {
        $person = Entity::create([
            'entity_type' => 'person', 'display_name' => 'Jane Doe', 'primary_geography' => 'Curated City',
        ]);
        $person->personProfile()->create(['professional_role' => 'Curated Role']);
        $this->gift(['contributor_entity_id' => $person->id, 'committee_name' => 'X',
            'occupation' => 'Engineer', 'city' => 'Denver', 'state' => 'CO', 'row_hash' => 'j1']);

        // Blanks-only: existing curated values are preserved.
        app(FinanceEnricher::class)->enrich($person);
        $person->refresh();
        $this->assertSame('Curated Role', $person->personProfile->professional_role);
        $this->assertSame('Curated City', $person->primary_geography);

        // Overwrite: finance-derived values replace them.
        app(FinanceEnricher::class)->enrich($person, overwrite: true);
        $person->refresh();
        $this->assertSame('Engineer', $person->personProfile->professional_role);
        $this->assertSame('Denver, CO', $person->primary_geography);
    }

    public function test_committee_gets_received_summary(): void
    {
        $committee = Entity::create(['entity_type' => 'election_committee', 'display_name' => 'Committee To Elect X']);
        $this->gift(['committee_entity_id' => $committee->id, 'contributor_name' => 'Big Donor', 'amount' => 5000, 'row_hash' => 'c1']);
        $this->gift(['committee_entity_id' => $committee->id, 'contributor_name' => 'Small Donor', 'amount' => 250, 'row_hash' => 'c2']);

        app(FinanceEnricher::class)->enrich($committee);

        $committee->refresh();
        $this->assertStringContainsString('received $5,250 across 2 contributions', $committee->internal_summary);
        $this->assertStringContainsString('Big Donor', $committee->internal_summary);
    }

    public function test_entity_with_no_finance_data_is_untouched(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Nobody Special']);

        $filled = app(FinanceEnricher::class)->enrich($person);

        $this->assertSame([], $filled);
        $this->assertNull($person->fresh()->internal_summary);
    }
}
