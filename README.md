# 🎤 OmekaRapper

**AI‑Assisted Cataloging for Omeka S**

[![Omeka S](https://img.shields.io/badge/Omeka%20S-4.x%20%7C%205.x-blue)]()
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
2. Select an AI provider
3. Generate metadata suggestions
4. Apply suggestions to item fields

Current built-in apply buttons support:

- `Apply title` → `dcterms:title`
- `Apply abstract` → `dcterms:abstract` (fallback `dcterms:description`)

---

## Multi‑Provider AI Architecture

OmekaRapper supports multiple AI providers through a pluggable provider system.

Supported or planned providers:

| Provider | Status |
|--------|--------|
| DummyProvider | Included |
| OpenAI (ChatGPT) | Planned |
| OpenAI Codex | Planned |
| Anthropic Claude | Planned |
| Claude Code | Planned |
| Local LLM (Ollama / LM Studio) | Planned |

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

---

# 🚀 Usage

1. Go to:

```
Admin → Items → Add Item
```

2. Locate the **OmekaRapper AI Assistant** panel.

3. Paste article or OCR text.

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
