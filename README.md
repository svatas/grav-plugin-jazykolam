
# Jazykolam

> Advanced i18n filters for Grav Twig – pluralization & months – without touching Grav core.

**Jazykolam** ("tongue twister" in Czech) brings configurable plural rules and localized month names
as easy-to-use Twig filters. It is locale-agnostic and ships defaults for English and Czech, with
room to add more (e.g., Polish, French, Russian).

- `|jazykolam_plural` — Pick the right plural form for any language.
- `|jazykolam_month` — Get localized month names in long/short (and genitive where applicable).

## Installation

1. Unzip to `user/plugins/jazykolam` or install via Admin → Tools → Direct Install.
2. Ensure the plugin is **enabled**.

## Configuration

`user/config/plugins/jazykolam.yaml` (defaults shown):

```yaml
enabled: true
default_locale: ''           # empty = use active Grav language
prefer_languages_yaml: true  # if true, values from site languages.yaml win
locales:
  en:
    plural: { order: [one, other] }
    months:
      long:   { 1: January, 2: February, 3: March, 4: April, 5: May, 6: June, 7: July, 8: August, 9: September, 10: October, 11: November, 12: December }
      short:  { 1: Jan, 2: Feb, 3: Mar, 4: Apr, 5: May, 6: Jun, 7: Jul, 8: Aug, 9: Sep, 10: Oct, 11: Nov, 12: Dec }
  cs:
    plural: { order: [one, few, other] }
    months:
      long:   { 1: leden, 2: únor, 3: březen, 4: duben, 5: květen, 6: červen, 7: červenec, 8: srpen, 9: září, 10: říjen, 11: listopad, 12: prosinec }
      short:  { 1: led, 2: úno, 3: bře, 4: dub, 5: kvě, 6: čvn, 7: čvc, 8: srp, 9: zář, 10: říj, 11: lis, 12: pro }
      genitive: { 1: ledna, 2: února, 3: března, 4: dubna, 5: května, 6: června, 7: července, 8: srpna, 9: září, 10: října, 11: listopadu, 12: prosince }
```

> You can override month names or provide plural strings in your **site `user/languages.yaml`**. See examples below.

## Usage

### 1) Pluralization — `|jazykolam_plural`

**By array (locale order)**

```twig
{# Czech: order is [one, few, other] #}
{{ 1|jazykolam_plural(['soubor','soubory','souborů']) }}   {# soubor #}
{{ 3|jazykolam_plural(['soubor','soubory','souborů']) }}   {# soubory #}
{{ 5|jazykolam_plural(['soubor','soubory','souborů']) }}   {# souborů #}

{# English: order is [one, other] #}
{{ 1|jazykolam_plural(['file','files'],'en') }}             {# file #}
{{ 2|jazykolam_plural(['file','files'],'en') }}             {# files #}
```

**By map (named categories)**

```twig
{{ total|jazykolam_plural({'one':'%d item','other':'%d items'}) }}
{{ n|jazykolam_plural({'one':'{{count}} soubor','few':'{{count}} soubory','other':'{{count}} souborů'}, 'cs') }}
```

**By translation key (site languages.yaml)**

```yaml
# user/languages.yaml
cs:
  JAZYKOLAM:
    FILE:
      one: soubor
      few: soubory
      other: souborů
```

```twig
{{ count|jazykolam_plural('JAZYKOLAM.FILE') }}
```

### 2) Months — `|jazykolam_month`

```twig
{{ 3|jazykolam_month }}                       {# March (active locale) #}
{{ 11|jazykolam_month('short', 'cs') }}       {# lis #}
{{ 11|jazykolam_month('genitive','cs') }}     {# listopadu #}

{# languages.yaml overrides: #}
{# user/languages.yaml #}
cs:
  JAZYKOLAM:
    MONTH:
      LONG:
        1: Leden (kapitálky)
```

```twig
{{ 1|jazykolam_month('long','cs') }}  {# Leden (kapitálky) #}
```

### Placeholders

- In plural strings you may use `{{count}}` or `%d` to insert the numeric value.

## Tested locales

- **English (en):** `one/other`
- **Czech (cs) & Slovak (sk):** `one/few/other`
- **Polish (pl):** `one/few/many/other` (rules simplified but CLDR-compatible in spirit)
- **French (fr) & others:** fall back to common two-form model unless configured

> You can extend `locales:` in the plugin config and the plural filter will honor the provided order.

## Why another i18n plugin?

Grav already supports translations via `languages.yaml`, but grammar-driven selections (plural categories, genitive months)
usually require custom logic in Twig. **Jazykolam** centralizes that logic behind clean filters and lets you keep content in translations.

## License

MIT
