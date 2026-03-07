# 🎤 OmekaRapper

**AI‑Assisted Cataloging for Omeka S**

[![Omeka S](https://img.shields.io/badge/Omeka%20S-4.x-blue)]()
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)]()
[![License](https://img.shields.io/badge/license-MIT-green)]()
[![Status](https://img.shields.io/badge/status-early%20development-orange)]()

OmekaRapper is an **AI-powered cataloging assistant for Omeka S** that helps curators, archivists, and researchers generate metadata automatically from article text, PDFs, OCR output, or web content.

The module integrates modern AI systems to analyze uploaded materials and generate suggested metadata such as:

- Title
- Abstract / Description
- Subjects / Keywords
- Creators
- Publication information
- Identifiers
- Language

These suggestions can then be reviewed and applied to Omeka items by a curator.

---

# ✨ Key Features

## AI Metadata Extraction

Automatically extract structured metadata from:

- journal articles
- reports
- scanned documents
- OCR text
- web content

The AI analyzes text and produces metadata suggestions suitable for:

- **Dublin Core**
- **Custom vocabularies**
- **Resource templates**

---

## Omeka Admin Integration

OmekaRapper adds an **AI Assistant sidebar panel** directly to:

Admin → Items → Add Item  
Admin → Items → Edit Item

From there curators can:

1. Paste article or OCR text
2. Upload a PDF
2. Select an AI provider
3. Generate metadata suggestions
4. Apply suggestions to item fields

Current built-in apply buttons support:

- `Apply title` → `dcterms:title`
- `Apply abstract` → `dcterms:abstract` (fallback `dcterms:description`)
- `Apply creators` → `dcterms:creator`
- `Apply subjects` → `dcterms:subject`
- `Apply date` → `dcterms:date`
- `Apply publisher` → `dcterms:publisher`
- `Apply language` → `dcterms:language`
- `Apply identifiers` → `dcterms:identifier`

Current limitations:

- values are applied as literal text entries only
- publication/container title is displayed but not yet mapped to an Omeka property automatically
- PDF import requires the `pdftotext` command-line tool (for example from Poppler)
- OCR fallback for scanned PDFs requires `pdftoppm` and `tesseract`
- OCR currently uses English (`eng`) and processes up to 10 PDF pages per request
- OpenAI integration depends on PHP cURL being available server-side

---

## Multi‑Provider AI Architecture

OmekaRapper supports multiple AI providers through a pluggable provider system.

Supported or planned providers:

| Provider | Status |
|--------|--------|
| DummyProvider | Included |
| ChatGPT | Included |
| Codex | Included |
| Claude | Included |
| Ollama / OpenAI-compatible local LLM | Included |
| Claude Code | Not yet implemented as a direct provider |

Developers can add additional providers easily.

---

# 🧠 Architecture

The module uses a provider abstraction layer so AI systems can be swapped without affecting the rest of the module.

```
Omeka Admin UI
      │
      ▼
OmekaRapper Panel
      │
      ▼
AssistController
      │
      ▼
AiClientManager
      │
      ▼
ProviderInterface
      │
 ┌────┴──────────────┐
 ▼                   ▼
OpenAIProvider   ClaudeProvider
```

---

# 📦 Installation

### 1. Download the module

Download or clone the repository.

### 2. Copy to Omeka modules directory

```
/modules/OmekaRapper
```

### 3. Install in Omeka

```
Admin → Modules → Install → OmekaRapper
```

### 4. Configure providers

```
Admin → Modules → OmekaRapper → Configure
```

### 5. Enable PDF import

Install `pdftotext` so OmekaRapper can extract text from uploaded PDFs before sending that text to the selected provider. For scanned PDFs, install `tesseract` too so OmekaRapper can fall back to OCR.

On macOS with Homebrew:

```sh
brew install poppler tesseract
```

Available settings:

- default provider for the item editor
- enable or disable the ChatGPT provider
- OpenAI API key
- ChatGPT model dropdown
- ChatGPT endpoint
- enable or disable the Codex provider
- Codex model dropdown
- Codex endpoint
- enable or disable the Claude provider
- Anthropic API key
- Claude model dropdown
- Claude endpoint
- enable or disable the Ollama provider
- Ollama model dropdown
- Ollama endpoint
- optional Ollama API key

### Local LLMs with Ollama

OmekaRapper can talk to local models through an OpenAI-compatible endpoint.

For Ollama:

1. Install and run Ollama.
2. Pull a model, for example:

```
ollama pull llama3.2
```

3. In `Admin → Modules → OmekaRapper → Configure`, enable `Ollama`.
4. Use:

```
Model: llama3.2
Endpoint: http://localhost:11434/v1/chat/completions
API key: ollama
```

Notes:

- the API key is usually ignored by Ollama, but a placeholder value is acceptable
- other OpenAI-compatible local servers such as LM Studio can be used by changing the endpoint and model name
- Claude Code is not exposed as a direct OmekaRapper provider yet; the module currently integrates Claude through the Anthropic Messages API
- model lists are loaded dynamically from the configured provider endpoints

---

# 🚀 Usage

1. Go to:

```
Admin → Items → Add Item
```

2. Locate the **OmekaRapper AI Assistant** panel.

3. Paste article or OCR text, upload a PDF, or do both.

4. Select a provider.

5. Click:

```
Suggest Metadata
```

AI-generated metadata suggestions will appear in the panel and can be applied to item fields.

---

# 🔌 API Endpoints

OmekaRapper exposes internal endpoints used by the admin UI.

```
GET  /admin/omeka-rapper/providers
POST /admin/omeka-rapper/suggest
```

Example request:

```
text=Example article text
provider=dummy
```

Example response:

```json
{
  "ok": true,
  "provider": "dummy",
  "suggestions": {
    "title": "Example Article Title",
    "abstract": "First portion of article text"
  }
}
```

---

# 🗂 Project Structure

```
OmekaRapper
├── Module.php
├── config
│   ├── module.config.php
│   └── module.ini
├── src
│   ├── Controller
│   │   └── AssistController.php
│   ├── Factory
│   │   ├── AiClientManagerFactory.php
│   │   └── AssistControllerFactory.php
│   └── Service
│       ├── AiClientManager.php
│       └── Provider
│           ├── ProviderInterface.php
│           └── DummyProvider.php
├── view
│   └── omeka-rapper
│       └── admin
│           └── assist
│               └── panel.phtml
└── asset
    └── js
        └── omeka-rapper.js
```

---

# 🔒 Security Considerations

When integrating external AI providers:

- Avoid sending restricted archival data without approval
- Store API keys securely
- Avoid exposing keys in JavaScript
- Implement request throttling and input limits
- Consider local models for sensitive collections

---

# 🛣 Roadmap

Planned features for future versions:

- OpenAI provider
- Claude provider
- PDF ingestion
- OCR processing
- Auto‑mapping to Dublin Core fields
- Resource template awareness
- Batch cataloging
- Dataset‑specific prompt profiles
- Linked open data enrichment

---

# 🧑‍💻 Contributing

Contributions are welcome.

Suggested areas for development:

- new AI providers
- metadata extraction improvements
- vocabulary integrations
- UI enhancements
- automated ingestion pipelines

---

# 📜 License

MIT License

---

# 🌍 Vision

OmekaRapper aims to become a **full AI‑assisted archival cataloging platform** for Omeka S that helps institutions:

- reduce manual metadata entry
- improve metadata consistency
- accelerate digitization workflows
- enhance discoverability of cultural collections
