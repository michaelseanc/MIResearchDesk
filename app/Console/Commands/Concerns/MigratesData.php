<?php

namespace App\Console\Commands\Concerns;

/**
 * Shared config for the data:export / data:import commands — a one-time full copy of DOMAIN data
 * between environments (e.g. local SQLite → production MySQL). Deliberately EXCLUDES auth/system
 * tables (users, roles/permissions, sessions, jobs, cache, migrations, audit, invitations,
 * user_access_grants) so the target environment keeps its own logins, 2FA, and roles.
 */
trait MigratesData
{
    /** Domain tables to copy, ordered parents-first (order is cosmetic — imports run with FK checks off). */
    protected function migratableTables(): array
    {
        return [
            'organizations',            // special-cased on import (upsert, never deleted — users depend on it)
            'jurisdictions',
            'tags',
            'relationship_types',
            'entities',
            'entity_aliases',
            'person_profiles',
            'organization_profiles',
            'relationships',
            'contact_methods',
            'addresses',
            'links',
            'contact_interactions',
            'positions_interests',
            'claims',
            'documents',
            'document_versions',
            'document_citations',
            'document_entity_links',
            'document_story_links',
            'relationship_evidence',
            'position_evidence',
            'claim_evidence',
            'stories',
            'story_entities',
            'story_contacts',
            'story_claims',
            'story_tasks',
            'public_records_requests',
            'taggables',
            'finance_import_batches',
            'finance_transactions',
            'saved_graph_views',
            'saved_searches',
        ];
    }

    /** Columns that reference users.id — remapped to the target environment's owner on import. */
    protected function userColumns(): array
    {
        return ['created_by', 'updated_by', 'user_id'];
    }
}
