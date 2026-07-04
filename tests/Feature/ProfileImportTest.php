<?php

namespace Tests\Feature;

use App\Filament\Resources\Entities\Pages\EditEntity;
use App\Models\Entity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Enrichment\ProfileTextParser;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProfileImportTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = <<<'TXT'
        Jane Q. Official
        Director of Planning at Town of Monument
        Town of Monument · Full-time
        Monument, CO
        500+ connections
        Contact info

        About
        Public servant focused on land use and water policy in El Paso County.
        Previously a civil engineer.

        Experience
        Director of Planning
        TXT;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $owner = User::where('email', 'owner@monumentindependent.com')->first();
        $this->actingAs($owner);
        Organization::useOrganization(1);
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    }

    public function test_parser_structures_pasted_profile(): void
    {
        $parsed = app(ProfileTextParser::class)->parse(self::SAMPLE);

        $this->assertSame('Jane Q. Official', $parsed['full_name']);
        $this->assertSame('Director of Planning', $parsed['professional_role']);
        $this->assertSame('Town of Monument', $parsed['employer']);
        $this->assertSame('Monument, CO', $parsed['geography']);
        $this->assertStringContainsString('land use and water policy', $parsed['summary']);
    }

    private const ORG_SAMPLE = <<<'TXT'
        Acme Development LLC
        Real estate developer serving the Front Range
        Overview
        Acme Development builds master-planned communities across El Paso County.
        Website
        https://www.acmedev.com
        Industry
        Real Estate
        Company size
        51-200 employees
        Headquarters
        Colorado Springs, CO
        Founded
        2004
        TXT;

    public function test_parser_structures_org_profile(): void
    {
        $parsed = app(ProfileTextParser::class)->parseOrganization(self::ORG_SAMPLE);

        $this->assertSame('Acme Development LLC', $parsed['name']);
        $this->assertSame('https://www.acmedev.com', $parsed['website']);
        $this->assertSame('Real Estate', $parsed['industry']);
        $this->assertSame('Colorado Springs, CO', $parsed['geography']);
        $this->assertSame('2004', $parsed['founded']);
        $this->assertStringContainsString('master-planned communities', $parsed['summary']);
        $this->assertStringNotContainsString('Website', $parsed['summary'], 'labels excluded from overview');
    }

    public function test_org_fill_action_updates_organization_profile(): void
    {
        $org = Entity::create(['entity_type' => 'organization', 'display_name' => 'Acme Development LLC']);

        Livewire::test(EditEntity::class, ['record' => $org->getKey()])
            ->callAction('fillFromOrgProfile', data: [
                'source_text' => self::ORG_SAMPLE,
                'dba_name' => 'Acme Development',
                'website' => 'https://www.acmedev.com',
                'primary_geography' => 'Colorado Springs, CO',
                'public_summary' => 'Builds master-planned communities in El Paso County.',
            ])
            ->assertHasNoActionErrors();

        $org->refresh();
        $this->assertSame('Colorado Springs, CO', $org->primary_geography);
        $this->assertSame('Acme Development', $org->organizationProfile->dba_name);
        $this->assertSame('https://www.acmedev.com', $org->organizationProfile->website);
        $this->assertDatabaseHas('links', [
            'entity_id' => $org->id,
            'kind' => 'website',
            'url' => 'https://www.acmedev.com',
        ]);
    }

    public function test_fill_action_updates_person_profile_and_stores_link(): void
    {
        $person = Entity::create(['entity_type' => 'person', 'display_name' => 'Jane Q. Official']);

        Livewire::test(EditEntity::class, ['record' => $person->getKey()])
            ->callAction('fillFromProfile', data: [
                'source_text' => self::SAMPLE,
                'linkedin_url' => 'https://www.linkedin.com/in/jane-official',
                'full_name' => 'Jane Q. Official',
                'professional_role' => 'Director of Planning',
                'current_company' => 'Town of Monument',
                'geography_detail' => 'Monument, CO',
                'dossier_summary' => 'Land use and water policy.',
            ])
            ->assertHasNoActionErrors();

        $profile = $person->fresh()->personProfile;
        $this->assertSame('Director of Planning', $profile->professional_role);
        $this->assertSame('Town of Monument', $profile->current_company);
        $this->assertSame('Monument, CO', $profile->geography_detail);
        $this->assertSame('https://www.linkedin.com/in/jane-official', $profile->linkedin_url);

        // LinkedIn URL captured as a provenance link.
        $this->assertDatabaseHas('links', [
            'entity_id' => $person->id,
            'platform' => 'linkedin',
            'url' => 'https://www.linkedin.com/in/jane-official',
        ]);
    }
}
