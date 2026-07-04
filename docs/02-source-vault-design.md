# Source Vault — Threat Model & Key-Management Design

**Status:** Design-only for this phase (build deferred, per decision) • Applies to `sensitivity = sealed`

The single most consequential design question in this platform is: *if the server is seized,
the operator is subpoenaed, or the database is exfiltrated, can a confidential source's identity
be recovered?* For real journalism the answer must be **no, not from the server alone.** This
document defines how we get there so the MVP reserves the right seams and the eventual build
doesn't require reworking the schema.

---

## 1. What we are protecting

| Asset | Sensitivity | Exposure consequence |
|---|---|---|
| Source real identity (name, contact) | **sealed** | Can endanger a person; destroys source trust |
| Source ↔ story/claim linkage | **sealed** | De-anonymizes even without the name |
| Source notes / meeting details | **sealed** | Corroborating detail can identify |
| Internal source code (`MI-SOURCE-014`) | internal | Safe — the whole point is it reveals nothing |

Everything above is **excluded from ordinary search, exports, and dossiers** even in the MVP,
because `sensitivity = sealed` is filtered out globally. The MVP simply stores no sealed
identities yet.

---

## 2. Threat model (who we defend against)

| Adversary | Capability | Our defense |
|---|---|---|
| Opportunistic attacker / leaked backup | Reads DB dump / file storage at rest | Identity fields encrypted; ciphertext useless without a key not in the DB |
| Compromised app server | Reads DB + app env + memory of running process | Key not derivable from server-held secrets alone; sealed decryption requires an operator-supplied passphrase per session |
| Legal compulsion (subpoena of the SaaS operator) | Can compel operator to hand over what operator *can* access | Operator (you, or a future SaaS host) **cannot** unilaterally decrypt — no server-only path to plaintext |
| Malicious/curious insider | Valid low-privilege login | `view_confidential_identity` permission + per-record `source_access_grants`; every access writes an immutable audit row |
| Coerced/compromised single admin | Owner credentials stolen | (Future) split-knowledge / M-of-N unwrap for the most sensitive records |

Explicitly **out of scope**: a nation-state with persistent kernel-level access to an unlocked
operator device while they are actively decrypting. No software design defeats that; operational
security (air-gapped identity storage) is the answer, which is why option B below exists.

---

## 3. Key-management design (the core decision)

The rule: **the data-encryption key for sealed identities is never recoverable from data the
server holds at rest.**

Recommended construction (envelope encryption):

1. Each sealed record's identity fields are encrypted with a random per-record **data key (DEK)**
   using authenticated encryption (AES-256-GCM / libsodium secretbox).
2. The DEK is wrapped (encrypted) by a **key-encryption key (KEK)** that is derived from an
   **operator passphrase** (Argon2id) at session unlock time — *not* stored in Laravel's `.env`
   or the DB. The app's normal `APP_KEY` protects ordinary `confidential` data but is explicitly
   **not** trusted for `sealed` identities.
3. Unlocking the vault requires an authorized user to enter the vault passphrase, which lives only
   in their head / password manager. The derived KEK is held in memory for a short, expiring
   session and never persisted.
4. A DB dump alone yields: ciphertext + wrapped DEKs + Argon2id salt. Without the passphrase this
   is not decryptable in any practical timeframe.

Future hardening (post-MVP, if warranted): move KEK custody to a hardware/KMS boundary
(YubiKey/HSM/cloud KMS) and support **M-of-N** unwrap so no single compromised admin can decrypt.

---

## 4. Two viable operating modes

**Mode A — Encrypted in-app vault (default target).** Identities live in the app, encrypted per
§3, unlocked by passphrase. Convenient; strong against seizure/exfiltration; acceptable for most
local-newsroom threat levels.

**Mode B — Off-platform identities.** For the highest-risk sources, the app stores *only* the
internal code (`MI-SOURCE-014`) and reporting metadata; the real identity never touches the
platform (kept in an air-gapped/offline record the reporter controls). Lowest risk, least
convenient. The schema supports both simultaneously — sensitivity of the source dictates which.

Recommendation: build Mode A as the vault, always allow Mode B for a reporter who chooses it.

---

## 5. Schema seams reserved in the MVP (so no later migration to core tables)

Deferred tables (built with the vault, not now):

| Table | Purpose |
|---|---|
| `source_identity_vault` | entity_id (nullable), source_code (unique, e.g. MI-SOURCE-014), enc_name, enc_contact, enc_dek, kdf_salt, kdf_params, created_by |
| `source_access_grants` | which users may unwrap which source records; expiry |
| `source_notes` | encrypted notes tied to a source_code |

Already present in the MVP core (no rework needed later):
- `sensitivity = sealed` enum value on entities, relationships, claims, documents, interactions.
- Global scope that strips `sealed` from search, dossiers, exports.
- `view_confidential_identity` permission (grants nothing until the vault ships).
- `audit_events` hash-chained log ready to record every vault view/edit/export.
- Ability to write `MI-SOURCE-xxx` codes in ordinary notes today with zero identity stored.

---

## 6. Operational safeguards that pair with the crypto

- Sealed material never in routine CSV/PDF exports (enforced by the global scope).
- Every open/edit/download/export of a sealed record → immutable `audit_events` row.
- Short vault-unlock session with auto-relock; re-enter passphrase after timeout.
- Sealed PDFs exported for outside counsel get watermarking + a redacted copy path that preserves
  the untouched original.
- Reconsider hosting for the vault specifically: managed shared hosting (Cloudways) is fine for the
  app tier, but the highest-sensitivity material may warrant a more controlled boundary. Decide
  before the vault is built, not after.

---

## 7. Monetization note

If this becomes multi-tenant SaaS, **you (the operator) holding decryptable source identities for
client newsrooms is both a liability and a trust-killer.** The §3 design already prevents
server-only decryption, which is exactly what lets you tell a prospective newsroom "we cannot read
your sources even if compelled." That is a selling point — but it must be true by construction,
which is why the passphrase-derived KEK matters and why this is designed now rather than bolted on.
