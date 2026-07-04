# Dossier Enrichment Architecture

**Goal:** auto-populate donor/candidate dossiers at Colorado scale (hundreds of thousands of TRACER
donors) with as little manual entry as possible — while staying compliant, defensible, and
evidence-first.

**Status:** Tier 1 building now (2026-07-04). Tiers 2–3 designed, not built.

---

## Principles (non-negotiable)

1. **No industrial scraping.** Systematic automated retrieval of copyrighted sites (Ballotpedia,
   LinkedIn, etc.) violates their ToS and is the single biggest legal risk to the platform. The
   scale that makes it tempting is exactly what makes it actionable against us. Human-initiated,
   paste-assisted capture of a single page is fine; a fleet crawler is not.
2. **Derived-vs-asserted provenance.** Facts derived from data we already own (TRACER) are safe to
   trust and store. Anything discovered externally (web/article search) is a **suggestion with a
   confidence score**, never auto-treated as verified, never auto-published.
3. **Prioritize, don't blanket.** Most donors are private citizens with no public profile. Deeply
   enrich a ranked shortlist (by total given, officeholder status, ties to prominent committees);
   let the long tail stay as bare finance records until they matter.
4. **Public-surface safety.** Auto-derived/inferred content stays internal until reviewed. Only
   `public` + verified facts can ever reach the eventual public product. See [[two-product-vision]].

## Why not a scraping skill for the whole state
- ToS/legal risk scales *with* the crawl (LinkedIn v. hiQ, Ballotpedia terms).
- Cost: an LLM/browser pass per donor across 300k+ records is slow and prohibitively expensive.
- Coverage: ~95% of donors aren't in Ballotpedia/Wikipedia/Wikidata at all (verified: "Ryan Graham
  (Colorado)" has no Wikidata/Wikipedia entry). The clean sources lack the locals; the source that
  has them can't be legally mass-pulled.
- Accuracy: name collisions (many "Ryan Graham"s) poison dossiers if auto-attached without review.

## The three tiers

### Tier 1 — Derive from data we already own  ✅ building now
Zero external calls, zero legal risk, covers *every* linked donor. From the TRACER rows already
linked to an entity, fill **blank** dossier fields (non-destructive):
- Person: `professional_role` ← most-common occupation; `current_company` ← most-common employer;
  `primary_geography` ← most-common city/state; `internal_summary` ← generated giving summary
  (total, count, year range, top recipients).
- Organization/committee: `internal_summary` ← generated received/made summary + top donors.
Delivered as: a `FinanceEnricher` service, a batch console command (idempotent, tenant-aware), and a
per-dossier "Fill blanks from finance data" button. Fills blanks by default; `--overwrite` optional.
Runs great right after the network build, which creates + links the donor entities.

### Tier 2 — Structured open datasets, matched by ID  (designed)
Automatic + clean for the *notable* subset only:
- **Wikidata** (CC0, SPARQL/REST): party, offices held w/ dates, education, occupation, birth date,
  portrait, and external IDs — including the **Ballotpedia ID** (so we get the Ballotpedia *link*
  legitimately, no scraping), FEC, VoteSmart, OpenSecrets.
- **OpenStates** (open API): Colorado state legislators — office, district, committees, contacts.
- **FEC** (open): federal candidates/committees.
Match by name + state + corroborating identifiers; attach with provenance; never overwrite curated
fields.

### Tier 3 — Assistive web/article discovery, human-in-the-loop  (designed)
Only for a prioritized shortlist. An agent (a Claude skill) runs a web search per subject and
**proposes** web/article links with a confidence score + provenance, into a **review queue** for
one-click human confirm/reject. Suggest → human approves → then it's a cited fact. Never auto-attach
(name-collision risk). This is the only place a browsing "skill" belongs — targeted and reviewed,
not a blanket crawler.

## Prioritization signal (for Tiers 2–3)
Rank entities by: total contributed/received, whether they hold/seek office, recipient prominence,
and appearance across multiple committees. Enrich top-N deeply; refresh over time.

## Roadmap
- [x] Tier 1: FinanceEnricher + batch command + dossier button
- [ ] Tier 1: optionally schedule enrichment after each import/network build
- [ ] Tier 2: Wikidata matcher (start here — best coverage/compliance ratio)
- [ ] Tier 2: OpenStates + FEC matchers
- [ ] Tier 3: enrichment review queue + web-search suggest agent
- [ ] Significance ranking to drive Tier 2/3 targeting
