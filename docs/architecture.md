# Architecture

## Runtime flow

1. Omeka admin item add/edit view renders the OmekaRapper panel via module event listeners.
2. Sidebar UI sends requests to admin routes in `AssistController`.
3. `AssistController` delegates provider calls to `AiClientManager`.
4. `AiClientManager` resolves a provider implementing `ProviderInterface`.
5. Provider returns normalized suggestion payload to UI.
6. UI optionally applies selected suggestions into Omeka property fields.

## Key files

- `module.php`: event hooks for panel injection.
- `config/module.config.php`: routes, services, factories, view paths.
- `src/Controller/AssistController.php`: provider list + suggest endpoints.
- `src/Service/AiClientManager.php`: provider registry/lookup.
- `src/Service/Provider/*`: provider interface + implementations.
- `view/omeka-rapper/admin/assist/panel.phtml`: sidebar UI shell.
- `asset/js/omeka-rapper.js`: fetch/render/apply behaviors.

## Extension points

- New provider classes implementing `ProviderInterface`.
- Provider registration in `AiClientManagerFactory`.
- Richer apply mappings in frontend JS and optional server-side mapping layer.
- Background jobs for large input/OCR/PDF pipelines.
