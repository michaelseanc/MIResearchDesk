# Source Vault — Design (design-now, build-later)

**Status:** Designed, not built. Execution deliberately deferred (decision 2026-07-04).
**Owner:** Michael Christensen.
**Audience:** whoever picks up the build later — start here before writing code.

---

## 1. Purpose

A newsroom-only, maximum-sensitivity compartment for **human sources** and the material tied to
them. It answers *"who told us this, and how do we protect them?"* — distinct from the evidence
layer, which answers *"what's the evidence?"*

Protecting a confidential source is a legal and ethical obligation, not a nice-to-have. The vault is
the sharpest test of the platform's sensitivity boundary: a leak here is a source-protection
failure, not just an embarrassment.

## 2. What already exists (do NOT rebuild)

The evidence/document layer is already in place and the vault builds on it:

- `documents` — first-class research objects on a **private disk** (never a public URL);
  `source_type` already includes `interview | email | source_doc | foia`; has `ocr_text`,
  `retention_status` (`active | archived | superseded | destroyed`), `file_hash`.
- `document_citations` — page/paragraph/quote, the atomic unit of evidence.
- `document_versions`, `document_entity_links`, `document_story_links`.
- `public_records_requests` — FOIA tracking.
- Sensitivity scale on most records: `public | internal | confidential | sealed`.
- Per-record **access grants** (morph-based; see `EntityMerger` access-grant handling).
- `audit_logs` — already anticipates `viewed | downloaded | exported | unsealed` actions with a
  `sensitivity_touched` column.

The vault reuses all of this. Its new surface is the **source identity** and its **compartmentalized
linkage** to that existing material.

## 3. Threat model — THE decision that shapes everything

Pick before building; the model dictates storage design.

- **Level A — accidental internal exposure.** Protect against a colleague casually browsing.
  Achievable with sealing + access grants + audit on top of the current schema. This is the MVP.
- **Level B — device seizure / legal compulsion.** Protect against a subpoena or a seized laptop.
  Pushes toward encryption-at-rest for identity fields, aggressive/secure purge, legal-hold flags,
  and possibly *not storing true identity at all* (codename + an out-of-band identity the app never
  holds). This is the hardened version — a separate, larger effort.

Everything below is written for Level A (MVP), with Level B noted where it changes the design.

## 4. Data model (proposed)

### `sources`
| column | notes |
|---|---|
| id, uuid, organization_id | standard + tenant scope (`BelongsToOrganization`) |
| codename | **required**, unique per org. The ONLY identifier shown on downstream objects. |
| real_identity | Level A: encrypted-cast string. Level B: consider omitting entirely. |
| contact_method | how they're reached; sealed. |
| handling | promise made: `on_record \| background \| off_record \| anonymous`. |
| reliability | track-record note / rating. |
| status | `active \| dormant \| burned \| closed`. |
| sensitivity | defaults to `sealed`. |
| intake_note | how first contact / material was received. |
| legal_hold | bool — blocks purge (Level B). |
| created_by, timestamps, softDeletes | + audit. |

### `source_material` (link table)
Links a source to the documents / claims / relationships they provided **without exposing identity
downstream**. The downstream object renders the codename (or "confidential source"), never the name.
| column | notes |
|---|---|
| source_id | → sources |
| linkable_type / linkable_id | morph → document, claim, relationship, contact_interaction |
| corroboration | `sole_source \| corroborated \| verified` |
| note | handling context |

### Access
Reuse the existing per-record **access grants** rather than a new mechanism. A source is visible only
to explicitly granted users (assigned reporter + editor), even inside the newsroom. Default = no one
but creator until granted.

## 5. Behavior

- **Sealing.** Sources default to `sealed`. Sealed identity is hidden from list/search/graph for
  users without a grant — same enforcement path as the existing `notSealed()` entity scope and the
  graph's sealed-exclusion.
- **Codename everywhere.** Any citation, relationship, or claim sourced from a vault source shows the
  codename. Resolving codename → identity requires an unseal, which is logged.
- **Unseal = audited event.** Every view/unseal/download of identity writes an `audit_logs` row
  (`action = unsealed`, `sensitivity_touched = sealed`). This is the chain-of-custody record.
- **No leakage downstream.** Linking a source to a document does not stamp the source on the document;
  the link lives only in `source_material`, itself sealed.

## 6. The non-negotiable boundary

Per the two-product vision, sources and sealed material must be **structurally incapable** of reaching
the public civic-research product or any export:

- The public surface exposes only `public` + verified, evidence-backed facts. Sources are `sealed`.
- Exports must hard-exclude `sealed`/vault records and refuse to resolve codenames.
- This should be enforced by a shared guard, tested, not left to convention.

## 7. Scope split

**MVP (Level A) — a few days:**
- `sources` + `source_material` tables/models, sealed by default.
- Codename-first display; identity behind an access grant.
- Filament resource (own nav group, permission-gated), reusing sensitivity + access-grant UI.
- Audit-on-unseal.
- Export/public-surface guard + test proving sealed sources never render or export.

**Hardened (Level B) — separate, larger, only after threat model is settled:**
- Encryption-at-rest for identity fields (or store no true identity).
- Legal hold + secure purge (`retention_status = destroyed`) workflow.
- Duress/lockdown ("seal everything") and stricter session handling.

## 8. Open decisions for Michael

1. Threat model: Level A now, or design straight for Level B?
2. Who can unseal — role-based (editors) or per-source assignment lists?
3. How much identity to store in-app vs. deliberately kept out of the system?

## 9. Reuse checklist (for the builder)

- `BelongsToOrganization`, `HasUuid`, `TracksAuthor` traits.
- Sensitivity scale + `notSealed()`-style scoping.
- Existing access-grant morph + `audit_logs`.
- Private disk for any attached files (as documents already do).
- Follow the evidence-first + sensitivity-boundary principles already in the codebase.
