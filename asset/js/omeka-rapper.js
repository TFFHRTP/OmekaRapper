(function () {
  const STATE_KEY = "omekaRapperSuggestions";

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[c]));
  }

  function getState(panel) {
    return panel[STATE_KEY] || null;
  }

  function setState(panel, suggestions) {
    panel[STATE_KEY] = suggestions || null;
  }

  function getStatus(panel) {
    return panel.querySelector(".omeka-rapper-status");
  }

  function setStatus(panel, html) {
    const status = getStatus(panel);
    if (status) {
      status.innerHTML = html || "";
    }
  }

  function getPdfStatus(panel) {
    return panel.querySelector(".omeka-rapper-pdf-status");
  }

  function setPdfStatus(panel, text, color) {
    const status = getPdfStatus(panel);
    if (!status) return;
    status.textContent = text || "";
    status.style.color = color || "#666";
  }

  function normalizeList(value) {
    if (!Array.isArray(value)) return [];
    return value
      .map((entry) => {
        if (typeof entry === "string") return entry.trim();
        if (entry && typeof entry === "object") {
          return String(entry.name || entry.label || entry.value || "").trim();
        }
        return "";
      })
      .filter(Boolean);
  }

  function renderList(label, values) {
    if (!values.length) return "";
    return `<div style="margin-top:.35rem;"><strong>${escapeHtml(label)}:</strong> ${escapeHtml(values.join(", "))}</div>`;
  }

  function render(container, suggestions) {
    const rows = [];
    const subjects = normalizeList(suggestions.subjects);
    const creators = normalizeList(suggestions.creators);
    const identifiers = normalizeList(suggestions.identifiers);
    const propertyRows = Array.isArray(suggestions.properties) ? suggestions.properties : [];

    if (suggestions.title) {
      rows.push(`<div><strong>Title:</strong> ${escapeHtml(suggestions.title)}</div>`);
    }
    if (suggestions.abstract) {
      rows.push(`<div style="margin-top:.5rem;"><strong>Abstract:</strong><div>${escapeHtml(suggestions.abstract)}</div></div>`);
    }
    rows.push(renderList("Creators", creators));
    rows.push(renderList("Subjects", subjects));
    if (suggestions.date) {
      rows.push(`<div style="margin-top:.35rem;"><strong>Date:</strong> ${escapeHtml(suggestions.date)}</div>`);
    }
    if (suggestions.publisher) {
      rows.push(`<div style="margin-top:.35rem;"><strong>Publisher:</strong> ${escapeHtml(suggestions.publisher)}</div>`);
    }
    if (suggestions.publication) {
      rows.push(`<div style="margin-top:.35rem;"><strong>Publication:</strong> ${escapeHtml(suggestions.publication)}</div>`);
    }
    if (suggestions.language) {
      rows.push(`<div style="margin-top:.35rem;"><strong>Language:</strong> ${escapeHtml(suggestions.language)}</div>`);
    }
    rows.push(renderList("Identifiers", identifiers));
    if (propertyRows.length) {
      const lines = propertyRows
        .filter((property) => property && property.term && Array.isArray(property.values) && property.values.length)
        .map((property) => `<div style="margin-top:.35rem;"><strong>${escapeHtml(property.term)}:</strong> ${escapeHtml(property.values.join(", "))}</div>`);
      if (lines.length) {
        rows.push(`<div style="margin-top:.75rem;"><strong>Detected properties</strong>${lines.join("")}</div>`);
      }
    }

    const visibleRows = rows.filter(Boolean);
    if (!visibleRows.length) {
      container.innerHTML = "<em>No suggestions returned.</em>";
      return;
    }

    container.innerHTML = visibleRows.join("");
  }

  function populateProviders(panel) {
    const endpoint = panel.getAttribute("data-providers-endpoint");
    const select = panel.querySelector(".omeka-rapper-provider");
    if (!endpoint || !select) return;

    fetch(endpoint, { credentials: "same-origin" })
      .then((resp) => resp.json())
      .then((json) => {
        if (!json.ok || !Array.isArray(json.providers) || !json.providers.length) {
          return;
        }
        const configuredDefault = typeof json.default_provider === "string" ? json.default_provider : "";
        const current = select.value;
        select.innerHTML = "";
        json.providers.forEach((providerName) => {
          const option = document.createElement("option");
          option.value = providerName;
          option.textContent = providerName;
          select.appendChild(option);
        });
        if (configuredDefault && [...select.options].some((option) => option.value === configuredDefault)) {
          select.value = configuredDefault;
        } else if ([...select.options].some((option) => option.value === current)) {
          select.value = current;
        }
      })
      .catch(() => {});
  }

  function findPropertySelectorItem(term) {
    const candidates = document.querySelectorAll("#property-selector li.selector-child[data-property-term]");
    return [...candidates].find((node) => node.getAttribute("data-property-term") === term) || null;
  }

  function findPropertyField(term) {
    const candidates = document.querySelectorAll("#properties .resource-property[data-property-term]");
    return [...candidates].find((node) => node.getAttribute("data-property-term") === term) || null;
  }

  function ensurePropertyField(term) {
    const selectorItem = findPropertySelectorItem(term);
    if (!selectorItem) return null;

    let field = findPropertyField(term);
    if (!field) {
      selectorItem.click();
      field = findPropertyField(term);
    }
    return field;
  }

  function ensureLiteralInput(field) {
    if (!field) return null;

    let literalInput = field.querySelector('.values .value[data-data-type="literal"] textarea.input-value');
    if (literalInput) return literalInput;

    const addLiteralButton = field.querySelector('.add-value[data-type="literal"]');
    if (addLiteralButton) {
      addLiteralButton.click();
      literalInput = field.querySelector('.values .value[data-data-type="literal"] textarea.input-value');
    }

    return literalInput;
  }

  function ensureLiteralInputs(field, count) {
    if (!field || count < 1) return [];

    const selector = '.values .value[data-data-type="literal"] textarea.input-value';
    let inputs = [...field.querySelectorAll(selector)];
    const addLiteralButton = field.querySelector('.add-value[data-type="literal"]')
      || field.querySelector('.add-value.button');

    while (inputs.length < count && addLiteralButton) {
      addLiteralButton.click();
      inputs = [...field.querySelectorAll(selector)];
    }

    return inputs;
  }

  function setFieldValue(input, value) {
    if (!input) return false;
    input.value = value;
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));
    return true;
  }

  function applyToProperty(preferredTerms, value) {
    if (!value || !String(value).trim()) return false;

    for (const term of preferredTerms) {
      const field = ensurePropertyField(term);
      const input = ensureLiteralInput(field);
      if (setFieldValue(input, String(value).trim())) {
        return true;
      }
    }
    return false;
  }

  function applyListToProperty(preferredTerms, values) {
    const normalizedValues = normalizeList(values);
    if (!normalizedValues.length) return false;

    for (const term of preferredTerms) {
      const field = ensurePropertyField(term);
      const inputs = ensureLiteralInputs(field, normalizedValues.length);
      if (inputs.length < normalizedValues.length) {
        continue;
      }

      let appliedCount = 0;
      normalizedValues.forEach((value, index) => {
        if (setFieldValue(inputs[index], value)) {
          appliedCount += 1;
        }
      });
      if (appliedCount === normalizedValues.length) {
        return true;
      }
    }

    return false;
  }

  function collectAvailableTerms() {
    const terms = new Set();
    document.querySelectorAll("#property-selector li.selector-child[data-property-term]").forEach((node) => {
      const term = (node.getAttribute("data-property-term") || "").trim();
      if (term) terms.add(term);
    });
    document.querySelectorAll("#properties .resource-property[data-property-term]").forEach((node) => {
      const term = (node.getAttribute("data-property-term") || "").trim();
      if (term) terms.add(term);
    });
    return [...terms];
  }

  function applyPropertySuggestions(panel) {
    const suggestions = getState(panel);
    const properties = Array.isArray(suggestions && suggestions.properties) ? suggestions.properties : [];
    if (!properties.length) {
      setStatus(panel, `<span style="color:#b00;">No property suggestions to apply.</span>`);
      return;
    }

    let applied = 0;
    let skipped = 0;
    properties.forEach((property) => {
      const term = property && property.term ? String(property.term).trim() : "";
      const values = normalizeList(property && property.values ? property.values : []);
      if (!term || !values.length) {
        return;
      }
      const ok = applyListToProperty([term], values);
      if (ok) {
        applied += 1;
      } else {
        skipped += 1;
      }
    });

    if (applied) {
      const skippedText = skipped ? ` ${skipped} properties could not be applied.` : "";
      setStatus(panel, `<span style="color:#067d17;">Applied ${escapeHtml(String(applied))} metadata properties.${escapeHtml(skippedText)}</span>`);
      return;
    }

    setStatus(panel, `<span style="color:#b00;">Could not apply any suggested properties to the current item form.</span>`);
  }

  function applySuggestion(panel, key, terms) {
    const suggestions = getState(panel);
    const value = suggestions && suggestions[key] ? suggestions[key] : "";
    if (!value) {
      setStatus(panel, `<span style="color:#b00;">No ${escapeHtml(key)} suggestion to apply.</span>`);
      return;
    }

    const ok = applyToProperty(terms, value);
    setStatus(panel, ok
      ? `<span style="color:#067d17;">Applied ${escapeHtml(key)} to item fields.</span>`
      : `<span style="color:#b00;">Could not find target property field (${escapeHtml(terms.join(", "))}).</span>`);
  }

  function applyListSuggestion(panel, key, terms) {
    const suggestions = getState(panel);
    const values = suggestions ? suggestions[key] : [];

    if (!normalizeList(values).length) {
      setStatus(panel, `<span style="color:#b00;">No ${escapeHtml(key)} suggestion to apply.</span>`);
      return;
    }

    const ok = applyListToProperty(terms, values);
    setStatus(panel, ok
      ? `<span style="color:#067d17;">Applied ${escapeHtml(key)} to item fields.</span>`
      : `<span style="color:#b00;">Could not find target property field (${escapeHtml(terms.join(", "))}).</span>`);
  }

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".omeka-rapper-generate");
    if (!btn) return;

    const panel = btn.closest(".omeka-rapper-panel");
    const endpoint = panel.getAttribute("data-suggest-endpoint");
    const text = panel.querySelector(".omeka-rapper-input").value;
    const provider = panel.querySelector(".omeka-rapper-provider").value;
    const pdfInput = panel.querySelector(".omeka-rapper-pdf");
    const pdfFile = pdfInput && pdfInput.files ? pdfInput.files[0] : null;
    const results = panel.querySelector(".omeka-rapper-results");

    setStatus(panel, "");
    results.innerHTML = "<em>Thinking…</em>";

    const body = new FormData();
    body.append("text", text);
    body.append("provider", provider);
    body.append("available_terms", JSON.stringify(collectAvailableTerms()));
    if (pdfFile) {
      body.append("pdf", pdfFile);
      setPdfStatus(panel, `Uploading ${pdfFile.name}...`, "#666");
    } else {
      setPdfStatus(panel, "", "#666");
    }

    try {
      const resp = await fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        body
      });

      const raw = await resp.text();
      if (!raw.trim()) {
        throw new Error("The server returned an empty response. The request likely timed out or hit a PHP fatal error.");
      }

      let json;
      try {
        json = JSON.parse(raw);
      } catch (error) {
        throw new Error(raw.trim() || "The server returned invalid JSON.");
      }
      if (!resp.ok || !json.ok) {
        setState(panel, null);
        setStatus(panel, `<span style="color:#b00;">${escapeHtml(json.error || "Error")}</span>`);
        results.innerHTML = `<span style="color:#b00;">${escapeHtml(json.error || "Error")}</span>`;
        return;
      }

      setState(panel, json.suggestions || null);
      let sourceMessage = "";
      if (json.source && json.source.has_pdf) {
        sourceMessage = json.source.ocr_used
          ? " Suggestions include OCR text extracted from the PDF."
          : " Suggestions include extracted PDF text.";
        setPdfStatus(panel, `Loaded ${pdfFile ? pdfFile.name : "PDF"} successfully.`, "#067d17");
      }
      const warningMessage = json.warning
        ? ` Provider warning: ${json.warning}. Heuristic fallback was used.`
        : "";
      setStatus(panel, `<span style="color:#067d17;">Suggestions generated.${escapeHtml(sourceMessage + warningMessage)}</span>`);
      render(results, json.suggestions);
    } catch (err) {
      setState(panel, null);
      const message = err && err.message ? err.message : "Request failed.";
      if (pdfFile) {
        setPdfStatus(panel, message, "#b00");
      }
      setStatus(panel, `<span style="color:#b00;">${escapeHtml(message)}</span>`);
      results.innerHTML = `<span style="color:#b00;">${escapeHtml(message)}</span>`;
    }
  });

  document.addEventListener("change", (e) => {
    const input = e.target.closest(".omeka-rapper-pdf");
    if (!input) return;

    const panel = input.closest(".omeka-rapper-panel");
    if (!panel) return;

    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) {
      setPdfStatus(panel, "", "#666");
      return;
    }

    setPdfStatus(panel, `Selected PDF: ${file.name}`, "#666");
  });

  document.addEventListener("click", (e) => {
    const applyAllBtn = e.target.closest(".omeka-rapper-apply-all");
    if (applyAllBtn) {
      const panel = applyAllBtn.closest(".omeka-rapper-panel");
      applyPropertySuggestions(panel);
      return;
    }

    const titleBtn = e.target.closest(".omeka-rapper-apply-title");
    if (titleBtn) {
      const panel = titleBtn.closest(".omeka-rapper-panel");
      applySuggestion(panel, "title", ["dcterms:title"]);
      return;
    }

    const abstractBtn = e.target.closest(".omeka-rapper-apply-abstract");
    if (abstractBtn) {
      const panel = abstractBtn.closest(".omeka-rapper-panel");
      applySuggestion(panel, "abstract", ["dcterms:abstract", "dcterms:description"]);
      return;
    }

    const creatorsBtn = e.target.closest(".omeka-rapper-apply-creators");
    if (creatorsBtn) {
      const panel = creatorsBtn.closest(".omeka-rapper-panel");
      applyListSuggestion(panel, "creators", ["dcterms:creator"]);
      return;
    }

    const subjectsBtn = e.target.closest(".omeka-rapper-apply-subjects");
    if (subjectsBtn) {
      const panel = subjectsBtn.closest(".omeka-rapper-panel");
      applyListSuggestion(panel, "subjects", ["dcterms:subject"]);
      return;
    }

    const dateBtn = e.target.closest(".omeka-rapper-apply-date");
    if (dateBtn) {
      const panel = dateBtn.closest(".omeka-rapper-panel");
      applySuggestion(panel, "date", ["dcterms:date"]);
      return;
    }

    const publisherBtn = e.target.closest(".omeka-rapper-apply-publisher");
    if (publisherBtn) {
      const panel = publisherBtn.closest(".omeka-rapper-panel");
      applySuggestion(panel, "publisher", ["dcterms:publisher"]);
      return;
    }

    const languageBtn = e.target.closest(".omeka-rapper-apply-language");
    if (languageBtn) {
      const panel = languageBtn.closest(".omeka-rapper-panel");
      applySuggestion(panel, "language", ["dcterms:language"]);
      return;
    }

    const identifiersBtn = e.target.closest(".omeka-rapper-apply-identifiers");
    if (identifiersBtn) {
      const panel = identifiersBtn.closest(".omeka-rapper-panel");
      applyListSuggestion(panel, "identifiers", ["dcterms:identifier"]);
    }
  });

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".omeka-rapper-panel").forEach(populateProviders);
  });
})();
