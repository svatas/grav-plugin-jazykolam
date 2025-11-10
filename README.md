
# Jazykolam

> Advanced i18n filters for Grav Twig – pluralization, months & relative time – without touching Grav core.
> **v1.2.0** adds optional *automatic overrides* of Grav's `|t` and `|nicetime` so you don't have to edit templates.

## Features
- `|jazykolam_plural` — locale-aware plural selection
- `|jazykolam_month` — month names (long/short/genitive)
- `|jazykolam_time` — human-friendly relative time
- **Auto overrides (optional)**:
  - Replace `|t` / `|tu` / `|tl` to support ICU-like patterns and plural categories from translations
  - Replace `|nicetime` to use `jazykolam_time`

## Zero-template-change mode
Enable in `user/config/plugins/jazykolam.yaml`:
```yaml
auto_override:
  t: true
  nicetime: true
```
Then:
- Keep using `{{ 'KEY'|t }}` in themes. If the translation for `KEY` is
  - an **ICU-like pattern** (`{count, plural, one {...} other {...}}`) *and* you pass `count` in params, Jazykolam picks the right form.
  - a **map of plural categories** (`one/few/many/other`), Jazykolam picks a form when you pass `count`.
- Any `|nicetime` calls in themes will use Jazykolam's relative time logic.

> If a template never provides a `count`, there is nothing to pluralize — Jazykolam will fallback to the original string.

## ICU-lite example
```yaml
cs:
  BLOG:
    COMMENTS:
      _: "{count, plural, one {Komentář} few {Komentáře} other {Komentářů}}"
```
```twig
{{ 'BLOG.COMMENTS._'|t({count: comments|length}) }}
```

## License
MIT
