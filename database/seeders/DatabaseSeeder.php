<?php

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\Organization;
use App\Models\RelationshipType;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /** Capability catalog — one permission per sensitive action. */
    private const PERMISSIONS = [
        'view_public', 'view_internal', 'view_confidential_metadata', 'view_confidential_identity',
        'manage_entities', 'manage_relationships', 'manage_claims', 'upload_documents',
        'view_restricted_documents', 'publish_records', 'export_data', 'manage_taxonomies',
        'manage_users', 'view_audit', 'delete_archive',
    ];

    /** Default capability set per role. Owner gets everything; the rest are sensible starting points. */
    private const ROLE_PERMISSIONS = [
        'Managing Editor' => [
            'view_public', 'view_internal', 'view_confidential_metadata', 'manage_entities',
            'manage_relationships', 'manage_claims', 'upload_documents', 'view_restricted_documents',
            'publish_records', 'export_data', 'manage_taxonomies', 'view_audit',
        ],
        'Editor' => [
            'view_public', 'view_internal', 'view_confidential_metadata', 'manage_entities',
            'manage_relationships', 'manage_claims', 'upload_documents', 'view_restricted_documents',
            'publish_records', 'export_data',
        ],
        'Reporter' => [
            'view_public', 'view_internal', 'manage_entities', 'manage_relationships',
            'manage_claims', 'upload_documents',
        ],
        'Contributor' => ['view_public', 'view_internal', 'upload_documents'],
        'Researcher' => ['view_public', 'view_internal', 'manage_entities', 'upload_documents'],
        'Read-only Observer' => ['view_public', 'view_internal'],
    ];

    private const RELATIONSHIP_TYPES = [
        ['name' => 'employed_by',            'label' => 'Employed by',              'inverse_name' => 'Employer of',       'category' => 'employment'],
        ['name' => 'donated_to',             'label' => 'Donated to',               'inverse_name' => 'Received from',     'category' => 'donation'],
        ['name' => 'board_member_of',        'label' => 'Board member of',          'inverse_name' => 'Board includes',    'category' => 'board'],
        ['name' => 'consultant_to',          'label' => 'Consultant to',            'inverse_name' => 'Consultant',        'category' => 'consultant'],
        ['name' => 'family_of',              'label' => 'Family of',                'inverse_name' => 'Family of',         'category' => 'family',      'is_directional' => false],
        ['name' => 'opposed_on_issue',       'label' => 'Opposed on issue',         'inverse_name' => 'Opposed by',        'category' => 'opposition'],
        ['name' => 'aligned_on_issue',       'label' => 'Publicly aligned on issue','inverse_name' => 'Aligned with',      'category' => 'alignment',   'is_directional' => false],
        ['name' => 'shared_vendor',          'label' => 'Shared vendor with',       'inverse_name' => 'Shared vendor with','category' => 'business',    'is_directional' => false],
        ['name' => 'financial_interest_in',  'label' => 'Financial interest in',    'inverse_name' => 'Interest held by',  'category' => 'financial'],
        ['name' => 'candidate_committee',    'label' => 'Campaign committee',       'inverse_name' => 'Candidate',         'category' => 'campaign'],
    ];

    public function run(): void
    {
        // Permissions are global (no team column); create once.
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Tenant #1 — Monument Independent. Pinned to id=1: the tenancy convention and the whole test
        // suite treat organization 1 as the primary tenant. Pinning it keeps behavior deterministic
        // across databases (MySQL doesn't reset AUTO_INCREMENT on rolled-back test transactions the
        // way SQLite :memory: effectively does).
        $org = Organization::query()->firstWhere('slug', 'monument-independent');
        if (! $org) {
            $org = new Organization(['name' => 'Monument Independent', 'slug' => 'monument-independent', 'status' => 'active']);
            $org->id = 1;
            $org->save();
        }

        // Operate as this tenant for the remainder of the seed (stamps organization_id, scopes roles).
        Organization::useOrganization($org->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        // Roles are team-scoped to this organization.
        $owner = Role::findOrCreate('Owner', 'web');
        $owner->syncPermissions(self::PERMISSIONS); // Owner: everything

        foreach (self::ROLE_PERMISSIONS as $roleName => $perms) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($perms);
        }

        // First Owner user. CHANGE THIS PASSWORD IMMEDIATELY and enable app MFA on first login.
        $ownerUser = User::firstOrCreate(
            ['email' => 'owner@monumentindependent.com'],
            [
                'organization_id' => $org->id,
                'name' => 'Newsroom Owner',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $ownerUser->assignRole($owner);

        // Starter jurisdictions (data, not hardcoded — editable per newsroom).
        $county = Jurisdiction::firstOrCreate(['name' => 'El Paso County', 'type' => 'county']);
        Jurisdiction::firstOrCreate(['name' => 'Monument', 'type' => 'town', 'parent_id' => $county->id]);
        Jurisdiction::firstOrCreate(['name' => 'Colorado', 'type' => 'state']);

        // Starter issue tags.
        foreach (['Buc-ee\'s', 'Water', 'School expansion', 'Growth', 'Public safety'] as $issue) {
            Tag::firstOrCreate(['kind' => 'issue', 'name' => $issue]);
        }

        // Relationship-type starter list (approved).
        foreach (self::RELATIONSHIP_TYPES as $type) {
            RelationshipType::firstOrCreate(
                ['name' => $type['name']],
                [
                    'label' => $type['label'],
                    'inverse_name' => $type['inverse_name'],
                    'category' => $type['category'],
                    'is_directional' => $type['is_directional'] ?? true,
                ]
            );
        }

        $this->command?->info("Seeded Monument Independent (org #{$org->id}), 7 roles, 15 permissions, Owner user, 9 relationship types.");
        $this->command?->warn('Owner login: owner@monumentindependent.com / password  — CHANGE THIS IMMEDIATELY.');
    }
}
