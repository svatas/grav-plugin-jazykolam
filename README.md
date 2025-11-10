
# Jazykolam 1.3.2 — DEBUG helpers

**New:**
- `jazykolam_debug(value, meta={})` — wrap any output with inline DEBUG marker (with data attributes: key, locale, source).
- `jazykolam_debug_panel()` — floating table listing last translations resolved by Jazykolam.
- Markers automatically added when `debug.enabled: true` **or** URL has `?jazykolam_debug=1`.

## Examples
```twig
{{ jazykolam_set_locale('cs') }}
{{ 'JAZYKOLAM.FILE'|t({count: 3}) }}
{{ jazykolam_debug_panel() }}
```

**Inline mode** adds visible markers like `‹JL:…›` and a small `JL` badge. Elements have attributes:
`data-jl-source`, `data-jl-key`, `data-jl-locale` — handy for DOM search or QA.

## Config (snippet)
```yaml
debug:
  enabled: false
  mode: inline     # inline | wrap
  marker_prefix: '‹JL:'
  marker_suffix: '›'
  badge: 'JL'
  max_entries: 200
```

## Panel
Place `{{ jazykolam_debug_panel() }}` somewhere in your base template (or enable via a debug-only partial). Toggle is built in.

## Gantry 5
Both functions and filters are registered into Gantry Twig, so particles can use them directly. No template edits needed when auto overrides are enabled.
