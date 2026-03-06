# Plan: Split OmekaRapper into Its Own Repository

## Goal

Create a standalone `omeka-rapper` repo while keeping compatibility with Omeka S module installation (`modules/OmekaRapper`).

## Phase 1: Prepare inside current monorepo

1. Stabilize module boundaries.
   - Keep all module code self-contained under `modules/OmekaRapper`.
   - Remove implicit references to sibling project paths.
2. Add governance/docs files.
   - `LICENSE`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, `docs/`.
3. Define release metadata.
   - Confirm versioning strategy (SemVer).
   - Keep `config/module.ini` version aligned with tagged releases.
4. Add CI checks in future standalone repo.
   - PHP lint
   - Static analysis (optional)
   - Minimal smoke test notes

## Phase 2: Extract Git history

Use one of the following:

- `git subtree split --prefix=modules/OmekaRapper -b codex/omekarapper-split`
- `git filter-repo --path modules/OmekaRapper --path-rename modules/OmekaRapper/:`

Recommended: `git subtree split` for simplicity.

## Phase 3: Create standalone repo

1. Create new repository (e.g., `omekarapper` or `omeka-rapper`).
2. Push extracted branch history into new repo default branch.
3. Ensure root layout is module root (not nested under `modules/`).
4. Add README install note for Omeka users:
   - clone/copy repo contents to `modules/OmekaRapper`.

## Phase 4: Wire release and distribution

1. Add tags and changelog.
2. Configure GitHub Releases (zip artifacts optional).
3. Verify Omeka module install from zip and from git clone.
4. Add issue templates and PR templates.

## Phase 5: Sync strategy with source monorepo

Choose one:

- One-way cutover (recommended): module lives only in standalone repo; monorepo vendors release snapshots.
- Mirror mode: periodic sync from standalone into monorepo via subtree.

Recommended: one-way cutover to avoid dual-source drift.

## Phase 6: Post-split hardening backlog

1. Implement first real provider (OpenAI or Anthropic).
2. Move provider config to module settings UI.
3. Add request timeout/retry/rate-limit controls.
4. Add structured logging and redaction defaults.
5. Add tests for controller response contracts and provider normalization.

## Acceptance checklist

- Standalone repo has full module history.
- Module installs cleanly in Omeka S 4.x/5.x.
- Docs and governance files are present.
- Release process documented and repeatable.
