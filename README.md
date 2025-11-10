# Jazykolam Plugin for Grav + Gantry 5

> Překládá s rozumem, ne silou.

## 🧩 O projektu

**Jazykolam** je rozšiřující plugin pro **Grav CMS** a **Gantry 5**, který umožňuje pokročilé překlady textů, časových údajů a pluralit **bez jakéhokoli zásahu do jádra Gravu nebo témat**.

Plugin se vkládá mezi Grav a Twig engine a:
- přidává vlastní filtry (`jazykolam_*`),
- umí přebít výchozí překladové filtry (`t`, `trans`, `nicetime`),
- integruje se s **Gantry 5 rendererem**, pokud je k dispozici,
- nabízí **debug panel** a vizuální zvýraznění přeložených řetězců,
- používá jednoduchou **ICU-lite syntaxi** pro plurály v `languages.yaml`.

## 📦 Instalace

1. Rozbalte ZIP balíček `jazykolam-1.0.0.zip`.
2. Nahrajte složku `jazykolam/` do `/user/plugins/`.
3. Aktivujte v Admin → Pluginy → Jazykolam.

Nebo ručně přes FTP do `/user/plugins/jazykolam`.

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

## 🎨 Integrace s Gantry 5

Pokud je nainstalován framework **Gantry 5**, Jazykolam jej automaticky detekuje a registruje své filtry i do jeho Twig rendereru. Díky tomu fungují překlady přímo v **particlech** a **outlines**.

## 🧰 Kompatibilita bez Gantry 5

Jazykolam funguje plnohodnotně i bez Gantry 5. Pokud Gantry není přítomné:
- integrační kód se přeskočí,
- nedochází k žádným chybám ani varováním,
- všechny Grav/Twig funkce zůstávají dostupné.

## 🧠 Použití – příklady

Základní překlad:
```twig
{{ 'HELLO_WORLD'|t }}
```

Pluralita:
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

## 🧪 Debug režim

```yaml
debug:
  enabled: true
  inject: smart
```

- zvýraznění přeložených řetězců,
- HTML panel dole na stránce,
- výpis do JavaScript konzole,
- nikdy se nevkládá do JSON/RSS/XHR odpovědí.

## 📄 Licence

MIT License © 2025 Svatopluk Vít

Více informací: viz [DOKUMENTACE.md](./DOKUMENTACE.md).
