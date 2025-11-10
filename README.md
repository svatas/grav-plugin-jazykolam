# Jazykolam Plugin for Grav + Gantry 5
> Překládá s rozumem, ne silou.

## 🧩 O projektu

**Jazykolam** je rozšiřující plugin pro **Grav CMS (1.7.x)** a **Gantry 5**, který umožňuje
pokročilé překlady textů, časů a pluralit **bez jakéhokoli zásahu do jádra Gravu nebo témat**.
Vznikl jako praktická odpověď na omezení popsaná v [Grav issue #2947](https://github.com/getgrav/grav/issues/2947)
a související diskusi na [Discourse](https://discourse.getgrav.org/t/translation-possibilities-of-grav/12701).

Jazykolam se vkládá mezi Grav a Twig engine a:
- přidává vlastní filtry (`jazykolam_*`),
- umí **přebít výchozí překladové filtry** (`t`, `trans`, `nicetime`),
- integruje se s **Gantry 5 rendererem**, pokud je k dispozici,
- nabízí **debug panel** a vizuální zvýraznění přeložených řetězců,
- používá jednoduchou **ICU-lite syntaxi** pro plurály v `languages.yaml`.

---

## 📦 Instalace

1. Rozbalte `grav-plugin-jazykolam-1.5.1-intl.zip`.
2. Nahrajte složku `jazykolam/` do `/user/plugins/`.
3. Aktivujte v Admin → Pluginy → Jazykolam.

Nebo ručně přes FTP do `/user/plugins/jazykolam`.

(Oficiální GPM repozitář zatím není k dispozici.)

---

## ⚙️ Konfigurace

V `user/config/plugins/jazykolam.yaml`:

```yaml
enabled: true
default_locale: cs
prefer_languages_yaml: true

auto_override:
  t: true
  nicetime: true
  gantry: true

debug:
  enabled: false
  inject: smart
  ignore_bots: true
  ignore_json: true
  ignore_xhr: true
  max_entries: 250
```

---

## 🎨 Integrace s Gantry 5

Pokud je nainstalován framework **Gantry 5**, Jazykolam jej automaticky detekuje
(`\Gantry\Framework\Gantry`) a registruje své filtry i do jeho Twig rendereru.
Díky tomu fungují překlady přímo v **particlech** a **outlines**.
Součástí balíčku může být i demo outline `jazykolam_demo_outline_langswitch`.

---

## ⚙️ Kompatibilita bez Gantry 5

Jazykolam funguje plnohodnotně i bez Gantry 5. Pokud Gantry není přítomné:

- integrační kód se přeskočí,
- nedochází k žádným chybám ani varováním,
- všechny Grav/Twig funkce zůstávají dostupné.

| Funkce | Funguje bez Gantry? | Poznámka |
|---------|----------------------|-----------|
| Překlady a pluralita (`t`, `trans`) | ✅ | Plná funkčnost |
| Relativní čas (`jazykolam_time`) | ✅ | Plná funkčnost |
| Měsíce (`jazykolam_month`) | ✅ | Plná funkčnost |
| Debug panel | ✅ | Stejný výstup jako s Gantry |
| Gantry particles / outlines | ❌ | Aktivuje se pouze, pokud je Gantry přítomno |
| Demo outline `langswitch` | ❌ | Bez Gantry se nepoužije |

Jazykolam je tedy **samostatný Grav plugin**. Integrace s Gantry je volitelná nadstavba.

---

## 🧠 Použití – příklady

Základní překlad:
```twig
{{ 'HELLO_WORLD'|t }}
```

Plurál (mapa):
```yaml
APPLE_COUNT:
  one: "Máš jedno jablko"
  few: "Máš {count} jablka"
  other: "Máš {count} jablek"
```

```twig
{{ 'APPLE_COUNT'|t({ count: 3 }) }}
```

Relativní čas:
```twig
{{ page.date|jazykolam_time }}
```

Měsíce:
```twig
{{ 3|jazykolam_month('genitive') }}
```

Přepnutí locale:
```twig
{% do jazykolam_set_locale('en') %}
{{ 'HELLO'|t }}
{% do jazykolam_set_locale('cs') %}
```

---

## 🧰 Debug režim

```yaml
debug:
  enabled: true
  inject: smart
```

- zvýraznění přeložených řetězců,
- HTML panel dole na stránce,
- výpis do JavaScript konzole,
- nikdy se nevkládá do JSON/RSS/XHR odpovědí.

---

## 📜 Licence

MIT License © 2025 Svatopluk Vít  
Email: svatopluk.vit@ruzne.info

Více informací: viz [CHANGELOG.md](./CHANGELOG.md).


## 🛠 Admin – Translation Manager (od verze 1.6.1)

- V Admin rozhraní se zobrazí položka **Jazykolam**.
- Stránka zobrazuje tabulku všech detekovaných klíčů a jejich překladů.
- Úpravy se ukládají do `user/languages.jazykolam.yaml`, který má prioritu.
- Přístup pouze pro roli `admin`.


## ✏️ Inline editor překladů (od verze 1.6.2)

Experimentální funkce pro rychlou úpravu překladů přímo na frontendu.

**Jak zapnout:**
- v `user/config/plugins/jazykolam.yaml`:
  ```yaml
  inline_edit:
    enabled: true
    allowed_roles:
      - admin
  ```
- stránku otevři jako přihlášený admin s parametrem `?jazykolam_inline=1`.

**Jak to funguje:**
- přeložené řetězce obalené Jazykolamem se vykreslí jako `<span class="jazykolam-inline" ...>`.
- kliknutím na text se zobrazí dialog pro úpravu překladu.
- po potvrzení se změna uloží do `user/languages.jazykolam.yaml` (přes endpoint `/task/jazykolam.inlineSave`).

**Bezpečnost:**
- pouze přihlášený uživatel s povolenou rolí může cokoliv uložit,
- požadavek je chráněn nonce tokenem,
- pro běžné návštěvníky je funkce neaktivní a nevkládá žádný JavaScript.


## 🧩 Rozšířený Translation Manager (od verze 1.6.3)

- Filtrování podle klíče i textu překladu.
- Přepínač pro zobrazení pouze klíčů s chybějícími překlady.
- Automatické načtení klíčů z Twig šablon (themes/plugins), aby bylo vidět, co chybí.
- Možnost přidat nový klíč přímo z tabulky.
- Před uložením se provede záloha `user/languages.jazykolam.yaml` jako `.bak` soubor.
