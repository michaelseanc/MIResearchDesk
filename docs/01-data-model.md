# Monument Independent Research Desk — MVP Data Model

**Status:** Draft for review • **Scope:** Lean MVP (multi-tenant-ready) • **DB:** SQLite in dev, MySQL in prod

This is the schema I intend to generate as Laravel migrations. Review the modeling decisions
before I write code — changing a design doc is free; migrating 25 live tables is not.

---

## 1. Guiding schema principles

1. **Multi-tenant-ready from day one.** Every application table carries `organization_id`
   (FK → `organizations`). A Laravel global scope auto-filters all queries to the current
   tenant. Monument is tenant #1. No cross-tenant query is possible without explicitly
   bypassing the scope (owner/superadmin only).
2. **Entity-first.** People, orgs, committees, government bodies, projects, properties are all
   rows in one `entities` table (base record + type). Type-specific fields live in extension
   tables (`person_profiles`, `organization_profiles`) joined 1:1 by `entity_id`. This keeps
   relationships, search, and dossiers uniform.
3. **Evidence is a first-class link, not a note.** Relationships, claims, and positions cannot
   be marked "verified" without at least one row in their `*_evidence` join table pointing to a
   document citation. Enforced in application logic + a DB-level check where feasible.
4. **Fact vs. interpretation is structural, not a text field.** No `motivation`/`ally`/`enemy`
   columns. Instead `verification_state` + `record_type` enums carry the epistemic status.
5. **Append-only audit.** `audit_events` is insert-only, hash-chained, stored separately.
6. **Soft deletes + `created_by`/`updated_by`** on every substantive table for accountability.
7. **Sensitivity on every record.** A shared `sensitivity` enum
   (`public|internal|confidential|sealed`) gates visibility; `sealed` is reserved for the future
   source vault and is filtered out of ordinary search/exports even in the MVP.

---

## 2. Tenancy & access tables

| Table | Key columns | Notes |
|---|---|---|
| `organizations` | id (uuid), name, slug, status, settings(json) | The tenant. Monument = row 1. |
| `users` | id, organization_id, name, email, password, two_factor_secret, two_factor_recovery_codes, last_login_at, is_active | Invite-only; 2FA columns present from day 1. |
| `roles` | id, organization_id, name | Spatie laravel-permission, tenant-scoped (team mode). |
| `permissions` | id, name, guard | Global permission catalog (see §8). |
| `model_has_roles` / `role_has_permissions` / `model_has_permissions` | — | Spatie pivot tables, team_id = organization_id. |
| `user_access_grants` | id, organization_id, user_id, grantable_type, grantable_id, ability, granted_by, expires_at | Per-record narrowing beyond role (e.g. this story, this entity). |
| `invitations` | id, organization_id, email, role, token, invited_by, accepted_at, expires_at | Invite-only onboarding. |

---

## 3. Entity & profile tables

| Table | Key columns |
|---|---|
| `entities` | id (uuid), organization_id, entity_type (person/organization/committee/government_body/business/project/property/public_office/media_outlet), display_name, legal_name, status (active/former/dissolved/historical/deceased/unknown), primary_geography, primary_jurisdiction_id, public_summary, internal_summary, sensitivity, why_it_matters, last_reviewed_at, last_reviewed_by, created_by, updated_by, timestamps, soft_deletes |
| `entity_aliases` | id, organization_id, entity_id, alias, alias_type (maiden/dba/abbreviation/spelling_variant/committee_name/campaign_name) |
| `person_profiles` | entity_id (PK/FK), full_name, known_names, professional_role, geography_detail, source_status, confidentiality_status (on_record/background/nfa/off_record/confidential), dossier_summary, reliability_notes (internal-only) |
| `organization_profiles` | entity_id (PK/FK), dba_name, org_subtype, website, registration_number, registered_agent, jurisdiction_id |
| `contact_methods` | id, organization_id, entity_id, method (phone/email/signal/social/in_person), value, is_preferred, restrictions (do_not_call/text_only/source_safe/etc.), sensitivity |
| `jurisdictions` | id, organization_id, name, type (town/county/school_district/state/special_district), parent_id | Configurable — NOT hardcoded to Monument/El Paso, so it ports to other newsrooms. |
| `tags` | id, organization_id, name, kind (issue/geography/campaign/project) |
| `taggables` | tag_id, taggable_type, taggable_id | Polymorphic tagging for entities/documents/stories. |

> Committee / government-body / project / property extension tables are deferred past MVP;
> those entity_types still work via the base `entities` row + tags until their profiles are built.

---

## 4. Relationship tables (the core)

| Table | Key columns |
|---|---|
| `relationship_types` | id, organization_id, name, is_directional, inverse_name, category (employment/donation/board/consultant/legal/family/project/opposition/etc.) |
| `relationships` | id (uuid), organization_id, from_entity_id, to_entity_id, relationship_type_id, is_directional, start_date, end_date, status (active/former/historical/disputed/unknown), verification_state (verified/corroborated/reported/lead/disputed/disproven), confidence (1–5), issue_tag_id (nullable), notes, sensitivity, last_reviewed_at, last_reviewed_by, created_by, updated_by, soft_deletes |
| `relationship_evidence` | id, organization_id, relationship_id, document_citation_id, note | **Required** ≥1 row before `verification_state = verified`. |

Graph traversal (1st/2nd/3rd degree) will run as a recursive CTE over `relationships` with
hard depth + node-count caps. Cytoscape rendering is deferred; the adjacency structure here is
graph-ready.

---

## 5. Positions / interests & claims (fact vs. interpretation)

| Table | Key columns |
|---|---|
| `positions_interests` | id, organization_id, entity_id, topic_tag_id, record_type (public_position/financial_interest/stated_motivation/reported_motivation/editorial_analysis/vote/endorsement/opposition), summary, date_start, date_end, verification_status (verified/attributed/reported/disputed/unresolved), visibility, review_flag (needs_right_to_respond/legal_review/source_corroboration/none), created_by, updated_by |
| `position_evidence` | id, organization_id, position_id, document_citation_id, note |
| `claims` | id, organization_id, subject_entity_id (nullable), statement, verification_state, sensitivity, review_due_at, created_by, updated_by |
| `claim_evidence` | id, organization_id, claim_id, document_citation_id, note |

This is what lets "publicly opposed the development," "owns adjacent property," "a source alleges
a financial motive," and "the team believes they benefit indirectly" be stored as **different**
record types with different verification states — never flattened into one "fact."

---

## 6. Documents & citations

| Table | Key columns |
|---|---|
| `documents` | id (uuid), organization_id, title, source_type (public_record/campaign_filing/meeting_packet/interview/email/court_filing/web_capture/foia/source_doc), origin, original_url, file_path (private disk), file_hash (sha256, dedupe), mime, page_count, capture_date, document_date, sensitivity, ocr_text (fulltext idx), retention_status, created_by, updated_by, soft_deletes |
| `document_versions` | id, organization_id, document_id, version_relationship (original/amended/replacement/corrected), file_path, file_hash, note, created_by |
| `document_citations` | id, organization_id, document_id, page, paragraph, quote, image_ref, note | The atomic unit evidence links point to. |
| `document_entity_links` | id, organization_id, document_id, entity_id, note |
| `document_story_links` | id, organization_id, document_id, story_id |

Files live on a **private** disk (local in dev, private object storage in prod) — never a public
URL. `file_hash` prevents duplicate/confused versions.

---

## 7. Stories, issues, contacts, tasks

| Table | Key columns |
|---|---|
| `stories` | id (uuid), organization_id, title, type (story/investigation/ongoing_issue/beat/project), status (lead/reporting/records_pending/draft/edit/legal_review/published/follow_up/archived), priority, central_question, why_it_matters, open_questions, known_facts, counterarguments, next_action, created_by, updated_by, soft_deletes |
| `story_entities` | story_id, entity_id, role_note |
| `story_contacts` | story_id, entity_id, attribution_terms |
| `story_claims` | story_id, claim_id |
| `story_tasks` | id, organization_id, story_id, title, assigned_to, due_at, status |
| `contact_interactions` | id, organization_id, entity_id, story_id (nullable), interaction_type (call/email/meeting/tip/interview/public_comment/records_request), occurred_at, summary, attribution_terms, follow_up_at, visibility (internal/sealed), created_by |
| `public_records_requests` | id, organization_id, story_id, agency, subject, submitted_at, due_at, status, response_note |

---

## 8. Audit & saved state

| Table | Key columns |
|---|---|
| `audit_events` | id, organization_id, user_id, action, auditable_type, auditable_id, sensitivity_touched, ip, user_agent, payload_hash, prev_hash, created_at | **Insert-only, hash-chained.** No update/delete. Every sealed/confidential view, edit, download, export writes a row. |
| `saved_searches` | id, organization_id, user_id, name, params(json) |
| `activity_log` | (spatie/laravel-activitylog) general change tracking, distinct from the tamper-evident `audit_events` |

### Permission catalog (Spatie, tenant-scoped)
`view_public`, `view_internal`, `view_confidential_metadata`, `view_confidential_identity`,
`manage_entities`, `manage_relationships`, `manage_claims`, `upload_documents`,
`view_restricted_documents`, `publish_records`, `export_data`, `manage_taxonomies`,
`manage_users`, `view_audit`, `delete_archive`.
(Finance/import permissions added when those modules land.)

Roles ship pre-seeded: **Owner, Managing Editor, Editor, Reporter, Contributor, Researcher,
Read-only Observer.** Source-vault permissions (`view_confidential_identity`) exist but grant
access to nothing until the vault is built.

---

## 9. Deferred to later phases (seams reserved)

- `source_identity_vault`, `source_access_grants`, `source_notes` — vault (see `02-source-vault-design.md`).
- `finance_*` tables — campaign-finance module (pluggable per-jurisdiction adapter, not TRACER-hardcoded).
- `articles`, `article_entity_links`, `article_sync_logs`, `wordpress_webhook_events` — Newspack sync.
- `saved_graph_views`, alert rules.

The `sensitivity=sealed` value and `view_confidential_identity` permission already exist so the
vault bolts on without a migration to existing tables.

---

## Open questions for your review
1. **Entity types in MVP:** ship all 9 types selectable, or restrict the MVP UI to person + organization (data model supports all regardless)?
2. **Relationship types:** I'll seed a starter list (employed_by, donated_to, board_member_of, consultant_to, family_of, opposed_on_issue, aligned_on_issue, shared_vendor, financial_interest_in). Add/remove any?
3. **Do you want soft-delete + restore, or hard-delete-with-approval** for records? (Spec §4 mentions "deletion approval.")
