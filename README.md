
# Jazykolam 1.4.0

**New**
- `debug.inject: true` — auto-inject **panel + console export** via `onOutputGenerated` (no Twig calls required).
- Minimal **Admin Tools panel** (Plugins → Jazykolam) with quick buttons (Enable/Disable runtime, Clear Log, Copy JSON).

How to use
- Turn on `debug.enabled` and `debug.inject`, optionally `debug.console`.
- Add `?jazykolam_debug=1` to the URL to enable debug without changing config.
