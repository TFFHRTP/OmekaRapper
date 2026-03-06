# Contributing to OmekaRapper

Thanks for contributing.

## Scope

OmekaRapper is an Omeka S module for AI-assisted metadata suggestions in item add/edit workflows.

## Development setup

1. Place this module in your Omeka S `modules/` directory as `OmekaRapper`.
2. Enable Developer mode in Omeka S if needed.
3. Install and enable the module from `Admin -> Modules`.
4. Open `Admin -> Items -> Add Item` and verify the OmekaRapper panel appears.

## Coding expectations

- Follow existing PHP style in this module (`declare(strict_types=1)`, typed properties/returns).
- Keep provider outputs normalized to a stable suggestions schema.
- Keep UI changes progressive: suggestions first, then explicit user apply actions.
- Avoid breaking Omeka 4.x/5.x compatibility.

## Pull request checklist

- Explain behavior changes and user-facing impact.
- Include before/after screenshots for UI changes.
- Add or update docs in `README.md` and `docs/`.
- Add tests where practical (unit/integration/manual verification notes).
- Keep secrets out of commits and logs.

## Commit conventions

Recommended prefix style:

- `feat:` new feature
- `fix:` bug fix
- `docs:` documentation updates
- `refactor:` non-behavioral code change
- `test:` tests only

## Reporting issues

Open an issue with:

- Omeka S version
- Module version
- PHP version
- Reproduction steps
- Expected vs actual behavior
- Relevant logs/screenshot snippets
