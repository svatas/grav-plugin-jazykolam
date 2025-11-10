# Changelog – Jazykolam Plugin for Grav + Gantry 5

All notable changes to this project are documented here.

---

## [1.5.1] – 2025-11-09
### Fixed
- Corrected string concatenation for the debug panel (`.` instead of `+`).
- Updated metadata and inline comments.

### Improved
- Prepared documentation for ICU-lite examples.
- Added basic duplication-check for Gantry filters.

---

## [1.5.0] – 2025-10-??
### Added
- Full **Gantry 5** integration (dual Twig registration).
- New filters: `jazykolam_month`, `jazykolam_time`, `jazykolam_plural`.
- `jazykolam_set_locale()` for temporary locale switching.
- **Debug mode** (panel + console output).
- **ICU-lite syntax** for pluralization in `languages.yaml`.
- Demo outline with advanced language switch particle.

---

## [1.4.0] – 2025-09-??
- Initial Gantry integration (beta).
- Auto-override for `t`, `tu`, `tl`, `trans`, `nicetime`.
- Added `prefer_languages_yaml` option.

---

## [1.3.0] – 2025-08-??
- First implementation of plural categories (one/few/other).
- Added `default_locale` override.

---

## [1.2.0] – 2025-07-??
- Introduced `jazykolam_time`.
- Added translation fallback logging.

---

## [1.1.0] – 2025-06-??
- Added `jazykolam_month` and `jazykolam_debug`.
- Support for custom language files (`languages.jazykolam_*.yaml`).

---

## [1.0.0] – 2025-05-??
- First public release of the Jazykolam plugin.
- Basic translation filter.
- Supported languages: cs, en, pl, sk.

---

## Roadmap
- ✅ ICU-lite documentation.
- 🔜 Unit tests and GitHub release.
- 🔜 Additional language packs.
- 🔜 JSON/CSV dictionary imports.
- 🔜 Admin integration (locale switch and debug toggle).

## [1.6.0] – 2025-11-09
### Added
- Added `DOKUMENTACE.md` (Czech full documentation) to the package.
- Introduced preparatory configuration options for inline translations, automatic locale detection and localized date/time formats (no impact when disabled).
- Normalized package root folder to `jazykolam` for proper Grav plugin installation.

### Note
- Inline editing and extended tools are designed as a safe base for future 1.6.x releases without touching Grav core.

## [1.6.1] – 2025-11-09
### Added
- Basic **Translation Manager** in the Admin panel (left menu item "Jazykolam").
- Displays a matrix of keys vs. languages with inline editing.

### How it works
- Changes are stored in `user/languages.jazykolam.yaml`.
- These values take precedence over other translation sources.
- Only available to authenticated administrators.

### Note
- Frontend inline editing is not enabled yet – 1.6.1 focuses on a safe Admin-based workflow.

## [1.6.2] – 2025-11-09
### Added
- Experimental **frontend inline translation editor**.
- Enabled via `inline_edit.enabled: true` and the `?jazykolam_inline=1` URL flag.
- Clicking on a highlighted `span.jazykolam-inline` allows editing the translation.

### Security & Behaviour
- Only authenticated users with roles from `inline_edit.allowed_roles` (default `admin`) may edit.
- Saves via protected `/task/jazykolam.inlineSave` endpoint with a nonce.
- Writes into `user/languages.jazykolam.yaml`, consistent with 1.6.1.

### Note
- Inline editor is disabled by default and has no impact on normal site visitors.

