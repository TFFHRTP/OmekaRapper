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
    if (suggestions.language) {
      rows.push(`<div style="margin-top:.35rem;"><strong>Language:</strong> ${escapeHtml(suggestions.language)}</div>`);
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
        const current = select.value;
        select.innerHTML = "";
        json.providers.forEach((providerName) => {
          const option = document.createElement("option");
          option.value = providerName;
          option.textContent = providerName;
          select.appendChild(option);
        });
        if ([...select.options].some((option) => option.value === current)) {
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

  function applySuggestion(panel, key, terms, results) {
    const suggestions = getState(panel);
    const value = suggestions && suggestions[key] ? suggestions[key] : "";
    if (!value) {
      results.innerHTML = `<span style="color:#b00;">No ${escapeHtml(key)} suggestion to apply.</span>`;
      return;
    }

    const ok = applyToProperty(terms, value);
    results.innerHTML = ok
      ? `<span style="color:#067d17;">Applied ${escapeHtml(key)} to item fields.</span>`
      : `<span style="color:#b00;">Could not find target property field (${escapeHtml(terms.join(", "))}).</span>`;
  }

  document.addEventListener("click", async (e) => {
    const btn = e.target.closest(".omeka-rapper-generate");
    if (!btn) return;

    const panel = btn.closest(".omeka-rapper-panel");
    const endpoint = panel.getAttribute("data-suggest-endpoint");
    const text = panel.querySelector(".omeka-rapper-input").value;
    const provider = panel.querySelector(".omeka-rapper-provider").value;
    const results = panel.querySelector(".omeka-rapper-results");

    results.innerHTML = "<em>Thinking…</em>";

    const body = new URLSearchParams({ text, provider });

    try {
      const resp = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body
      });

      const json = await resp.json();
      if (!json.ok) {
        setState(panel, null);
        results.innerHTML = `<span style="color:#b00;">${escapeHtml(json.error || "Error")}</span>`;
        return;
      }

      setState(panel, json.suggestions || null);
      render(results, json.suggestions);
    } catch (err) {
      setState(panel, null);
      results.innerHTML = `<span style="color:#b00;">Request failed</span>`;
    }
  });

  document.addEventListener("click", (e) => {
    const titleBtn = e.target.closest(".omeka-rapper-apply-title");
    if (titleBtn) {
      const panel = titleBtn.closest(".omeka-rapper-panel");
      const results = panel.querySelector(".omeka-rapper-results");
      applySuggestion(panel, "title", ["dcterms:title"], results);
      return;
    }

    const abstractBtn = e.target.closest(".omeka-rapper-apply-abstract");
    if (!abstractBtn) return;
    const panel = abstractBtn.closest(".omeka-rapper-panel");
    const results = panel.querySelector(".omeka-rapper-results");
    applySuggestion(panel, "abstract", ["dcterms:abstract", "dcterms:description"], results);
  });

  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".omeka-rapper-panel").forEach(populateProviders);
  });
})();
