
# Jazykolam

> Advanced i18n filters for Grav Twig – pluralization, months & relative time – without touching Grav core.

**Jazykolam** ("tongue twister" in Czech) brings configurable plural rules, localized month names
and human-friendly relative time. Locale-agnostic defaults are provided for English and Czech.

- `|jazykolam_plural` — Pick the right plural form for any language.
- `|jazykolam_month` — Localized month names in long/short (and genitive where applicable).
- `|jazykolam_time` — Human-friendly relative time ("just now", "3 days ago", "in 2 hours").

## Installation

1. Unzip to `user/plugins/jazykolam` or install via Admin → Tools → Direct Install.
2. Ensure the plugin is **enabled**.

## Configuration

`user/config/plugins/jazykolam.yaml` (snippet):

```yaml
enabled: true
default_locale: ''
prefer_languages_yaml: true
locales:
  en:
    plural: { order: [one, other] }
    months: { ... }
    relative:
      now: just now
      past:
        minute: { one: a minute ago, other: '{{count}} minutes ago' }
      future:
        minute: { one: in a minute, other: 'in {{count}} minutes' }
  cs:
    plural: { order: [one, few, other] }
    months: { ... }
    relative:
      now: právě teď
      past:
        hour: { one: hodinou, few: '{{count}} hodinami', other: '{{count}} hodinami' }
      future:
        day: { one: zítra, few: '{{count}} dny', other: '{{count}} dní' }
```

> You can override any piece via **`user/languages.yaml`** under `JAZYKOLAM.RELATIVE.*` keys.

## Usage

### 1) Pluralization — `|jazykolam_plural`
(see below for details)

### 2) Months — `|jazykolam_month`
(see below for details)

### 3) Relative time — `|jazykolam_time`

```twig
{{ page.date|jazykolam_time }}                    {# based on active locale #}
{{ '2025-11-07 14:00'|jazykolam_time('en') }}     {# e.g., 1 hour ago #}
{{ (-90)|jazykolam_time('cs') }}                   {# před 1 minutou (≈) #}
{{ (7200)|jazykolam_time('cs') }}                  {# za 2 hodiny #}

{# Override reference "now" #}
{{ page.date|jazykolam_time('cs', '2025-11-07 12:00:00') }}
```

**How it formats**

- Under ~45 seconds → `now` string (e.g., `just now` / `právě teď`).
- Otherwise, picks the largest meaningful unit among `second, minute, hour, day, week, month, year`.
- Chooses plural category by locale (`one/few/many/other`).
- Builds phrase from either **`user/languages.yaml`** or plugin config.

### Override via `user/languages.yaml`

```yaml
cs:
  JAZYKOLAM:
    RELATIVE:
      NOW: právě teď (teď hned!)
      PAST:
        MINUTE:
          one: minutou
          few: {{count}} minutami
          other: {{count}} minutami
      FUTURE:
        DAY:
          one: zítra
          few: {{count}} dny
          other: {{count}} dní
```

## Tested locales

- **English (en):** relative time phrases + plural `one/other`
- **Czech (cs):** relative time with case-aware forms for past/future (`před X`, `za X`)

## License

MIT
