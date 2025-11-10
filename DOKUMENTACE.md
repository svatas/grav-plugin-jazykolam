
# 📘 Technická dokumentace pluginu Jazykolam

## 🔍 Přehled

**Jazykolam** je plugin pro Grav CMS, který poskytuje překladovou vrstvu založenou na override principu. Umožňuje:

- překlady textů, pluralit, měsíců a relativních časových výrazů,
- přepisy výchozích Twig filtrů (`t`, `trans`, `nicetime`),
- překlady URL segmentů pomocí `jazykolam_url` filtru,
- integraci s Gantry 5 rendererem,
- frontendové inline editace překladů (experimentální),
- administrační Translation Manager pro pohodlnou správu překladů.

## 🧱 Architektura

Plugin se skládá z několika hlavních komponent:

- `JazykolamPlugin` (hlavní třída pluginu)
- `JazykolamTwigExtension` (Twig rozšíření)
- Admin rozhraní (`admin/pages/jazykolam.md`, `admin/templates/jazykolam.html.twig`)
- Konfigurace (`blueprints.yaml`, `jazykolam.yaml`)

## 🔁 Události a hooky

### onPluginsInitialized
Rozhoduje, zda se plugin nachází v admin nebo frontend kontextu a aktivuje příslušné hooky.

### onTwigExtensions
Registruje Twig filtry:
- `jazykolam_t`
- `jazykolam_plural`
- `jazykolam_month`
- `jazykolam_time`
- `jazykolam_set_locale`
- `jazykolam_url`

Volitelně přepisuje filtry `t`, `trans`, `nicetime`.

### onOutputGenerated
Vkládá debug panel a inline editor JS do HTML odpovědi.

### onThemeInitialized
Integrace s Gantry 5 rendererem (pokud je aktivní a dostupný).

### onAdminMenu, onAdminPagesInitialized, onAdminTwigTemplatePaths, onAdminControllerInit
Zajišťují administrační rozhraní pluginu.

## 🗂 Jazykové soubory

Překlady se ukládají do:
```yaml
user/languages.jazykolam.yaml
```
Tento soubor má prioritu před všemi ostatními zdroji překladů.

## ✍️ Inline editor

### Aktivace
- V konfiguraci: `inline_edit.enabled: true`
- V URL: `?jazykolam_inline=1` nebo `=inspect`
- Uživatel musí mít roli z `inline_edit.allowed_roles`

### Funkce
- Obaluje přeložené řetězce do `<span class="jazykolam-inline">`
- Kliknutím se otevře popup s textarea
- Uložení probíhá přes `POST /task/jazykolam.inlineSave` s nonce

## 🧑‍💻 Translation Manager (admin)

- Položka v levém menu admin rozhraní
- Zobrazuje tabulku klíčů a jejich překladů
- Možnost editace, přidání nových klíčů, filtrování
- Automatické zálohování před uložením (`.bak` soubory)

## 🧪 Použití funkcí a příklady

### jazykolam_t
Překlad klíče s volitelnými parametry.
```twig
{{ 'HELLO_WORLD'|jazykolam_t }}
{{ 'WELCOME_USER'|jazykolam_t({ name: 'Sváťa' }) }}
```

### jazykolam_plural
Pluralizace podle locale.
```twig
{{ 'APPLE_COUNT'|jazykolam_plural({ count: 3 }) }}
```

### jazykolam_month
Získání názvu měsíce podle čísla.
```twig
{{ 3|jazykolam_month }}
```

### jazykolam_time
Relativní časový výraz.
```twig
{{ page.date|jazykolam_time }}
```

### jazykolam_set_locale
Dočasné přepnutí jazyka v šabloně.
```twig
{% do jazykolam_set_locale('en') %}
{{ 'HELLO'|t }}
{% do jazykolam_set_locale('cs') %}
```

### jazykolam_url
Překlad segmentu URL.
```twig
{{ 'about'|jazykolam_url }}
```

## 🚀 Jak začít s překladem

1. **Aktivujte plugin** v `user/config/plugins/jazykolam.yaml`:
```yaml
enabled: true
```
2. **Vytvořte soubor** `user/languages.jazykolam.yaml` a přidejte překlady:
```yaml
HELLO_WORLD:
  cs: "Ahoj světe"
  en: "Hello world"
  pl: "Witaj świecie"

APPLE_COUNT:
  cs: "{count, plural, one {máš jedno jablko} few {máš # jablka} other {máš # jablek}}"
  en: "{count, plural, one {you have one apple} other {you have # apples}}"
  pl: "{count, plural, one {masz jedno jabłko} few {masz # jabłka} other {masz # jabłek}}"
```
3. **Použijte klíče** v šablonách pomocí Twig filtrů.
4. **Překlady můžete upravovat** i přes Admin → Jazykolam (Translation Manager).

## 🔐 Bezpečnost

- Inline editor dostupný pouze pro autentizované uživatele s oprávněním
- Všechny změny se ukládají pouze do `languages.jazykolam.yaml`
- Žádné zásahy do jádra Gravu nebo jiných pluginů

## 🧩 Shrnutí funkcionalit

| Funkce               | Popis                                                  |
|----------------------|---------------------------------------------------------|
| `jazykolam_t`        | Překlad klíčů s podporou parametrů                     |
| `jazykolam_plural`   | Pluralizace podle locale (one/few/other)              |
| `jazykolam_month`    | Název měsíce dle čísla a formy                         |
| `jazykolam_time`     | Relativní časové výrazy (např. "před hodinou")        |
| `jazykolam_set_locale` | Dočasné přepnutí jazyka v šabloně                    |
| `jazykolam_url`      | Překlad segmentů URL (např. pro lokalizované routy)   |
| Debug panel          | HTML panel s logem překladů a výkonu                   |
| Inline editor        | Frontendová editace překladů (experimentální)         |
| Translation Manager  | Admin UI pro překlady                                  |

## 🧾 Autor

MIT License © 2025 Svatopluk Vít  
Email: [svatopluk.vit@ruzne.info](mailto:svatopluk.vit@ruzne.info)
