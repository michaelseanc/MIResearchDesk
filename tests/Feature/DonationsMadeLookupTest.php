<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\RelationManagers\DonationsMadeRelationManager;
use App\Models\Entity;
use App\Models\FinanceTransaction;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DonationsMadeLookupTest extends TestCase
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

    private function suggestFor(Entity $entity): array
    {
        $rm = new DonationsMadeRelationManager();
        $prop = new \ReflectionProperty($rm, 'ownerRecord');
        $prop->setAccessible(true);
        $prop->setValue($rm, $entity);

        $method = new \ReflectionMethod($rm, 'suggestedContributorNames');
        $method->setAccessible(true);

        return $method->invoke($rm);
    }

    private function contribution(string $name, string $hash): void
    {
        FinanceTransaction::create([
            'source' => 'tracer', 'data_type' => 'contributions', 'year' => 2024,
            'committee_name' => 'Some Committee', 'contributor_name' => $name,
            'amount' => 500, 'transaction_date' => '2024-06-01', 'row_hash' => $hash,
        ]);
    }

    public function test_business_lookup_includes_legal_name_and_dba(): void
    {
        $org = Entity::create([
            'entity_type' => 'organization',
            'display_name' => 'Acme Development LLC',
            'legal_name' => 'Acme Holdings Company',
        ]);
        $org->organizationProfile()->create(['dba_name' => 'Acme Group']);

        $this->contribution('ACME DEVELOPMENT LLC', 'h1'); // matches display name
        $this->contribution('ACME HOLDINGS COMPANY', 'h2'); // matches legal name
        $this->contribution('ACME GROUP', 'h3');            // matches DBA
        $this->contribution('ZEBRA CORP', 'h4');            // matches nothing

        $names = $this->suggestFor($org);

        $this->assertContains('ACME DEVELOPMENT LLC', $names);
        $this->assertContains('ACME HOLDINGS COMPANY', $names, 'legal name should surface for a business');
        $this->assertContains('ACME GROUP', $names, 'DBA should surface for a business');
        $this->assertNotContains('ZEBRA CORP', $names);
    }

    public function test_stopwords_and_suffixes_are_not_required_to_match(): void
    {
        // "The Platinum Group" should match a TRACER donor filed as "PLATINUM GROUP" (no "The"),
        // and a name with a corporate suffix shouldn't require that suffix in the record.
        $org = Entity::create(['entity_type' => 'organization', 'display_name' => 'The Platinum Group']);

        $this->contribution('PLATINUM GROUP', 'x1');
        $this->contribution('PLATINUM GROUP LLC', 'x2');
        $this->contribution('SOMETHING ELSE', 'x3');

        $names = $this->suggestFor($org);

        $this->assertContains('PLATINUM GROUP', $names, '"The" must not be required');
        $this->assertContains('PLATINUM GROUP LLC', $names);
        $this->assertNotContains('SOMETHING ELSE', $names);
    }

    public function test_person_lookup_uses_personal_name_only(): void
    {
        $person = Entity::create([
            'entity_type' => 'person',
            'display_name' => 'Jane Q Public',
            'legal_name' => 'Acme Holdings Company', // must NOT be used for a person
        ]);

        $this->contribution('JANE Q PUBLIC', 'p1');
        $this->contribution('ACME HOLDINGS COMPANY', 'p2');

        $names = $this->suggestFor($person);

        $this->assertContains('JANE Q PUBLIC', $names);
        $this->assertNotContains('ACME HOLDINGS COMPANY', $names, 'legal name is not a business lookup for a person');
    }
}
