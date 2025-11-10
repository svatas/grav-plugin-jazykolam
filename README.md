
# Jazykolam 1.3.1

**What’s new**
- Twig **function**: `jazykolam_set_locale(locale)` — set per-render locale override for all Jazykolam filters and auto `|nicetime`.
- Registered into **Gantry 5** Twig renderer (filters + function + overrides), so it works inside **particles** without template edits.

## Usage
```twig
{{ jazykolam_set_locale('cs') }}
{{ -3600|nicetime }}         {# => před 1 hodinou (with auto override) #}
{{ 11|jazykolam_month('genitive') }}  {# => listopadu #}
{{ n|jazykolam_plural({'one':'soubor','few':'soubory','other':'souborů'}) }}
```

## Configuration
```yaml
auto_override:
  t: true
  nicetime: true
  gantry: true
# default_locale: cs  # force locale globally
```

## Gantry 5
The plugin detects Gantry and registers all filters and the `jazykolam_set_locale` function into its Twig renderer.
No template edits needed — particles can call the function and filters directly.
