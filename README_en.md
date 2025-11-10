# Jazykolam Plugin for Grav + Gantry 5
> Translates with logic, not brute force.

## 🧩 About the Project

**Jazykolam** is an extension plugin for **Grav CMS (1.7.x)** and **Gantry 5** that provides
advanced translation, pluralization, and date/time localization **without modifying Grav core or themes**.
It was created as a practical response to [Grav issue #2947](https://github.com/getgrav/grav/issues/2947)
and the related [Discourse topic](https://discourse.getgrav.org/t/translation-possibilities-of-grav/12701).

Jazykolam acts as a middleware layer between Grav and Twig:
- introduces custom filters (`jazykolam_*`);
- can **override default Grav filters** (`t`, `trans`, `nicetime`);
- integrates with the **Gantry 5 renderer**, when available;
- offers a **debug panel** and visual highlighting of translated strings;
- supports a lightweight **ICU-lite plural syntax** in `languages.yaml`.

---

## 📦 Installation

1. Extract `grav-plugin-jazykolam-1.5.1-intl.zip`.
2. Upload the `jazykolam/` directory into `/user/plugins/`.
3. Enable the plugin in Admin → Plugins → Jazykolam.

Or install manually via FTP.  
*(Official GPM package not yet published.)*

---

## ⚙️ Configuration

Example `user/config/plugins/jazykolam.yaml`:

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

## 🎨 Gantry 5 Integration

If **Gantry 5** is installed, Jazykolam automatically detects it
(`\Gantry\Framework\Gantry`) and registers its filters in the Gantry Twig environment.
This enables translations inside **particles** and **layout structures**.
An example outline `jazykolam_demo_outline_langswitch` can be used as a reference.

---

## ⚙️ Compatibility without Gantry 5

Jazykolam works perfectly **without Gantry 5**. If Gantry is not available:

- the Gantry integration code is skipped,
- no errors or warnings are thrown,
- all Grav/Twig features remain fully operational.

| Feature | Works without Gantry? | Notes |
|---------|----------------------|-------|
| Translations & pluralization (`t`, `trans`) | ✅ | Fully functional |
| Relative time (`jazykolam_time`) | ✅ | Fully functional |
| Month names (`jazykolam_month`) | ✅ | Fully functional |
| Debug panel | ✅ | Same behavior as with Gantry |
| Gantry particles / outlines | ❌ | Enabled only if Gantry is present |
| Demo outline `langswitch` | ❌ | Ignored when Gantry is not installed |

Jazykolam is a **standalone Grav plugin**.  
Gantry integration is an optional enhancement that activates automatically when present.

---

## 🧠 Usage Examples

Basic translation:
```twig
{{ 'HELLO_WORLD'|t }}
```

Pluralization (map style):
```yaml
APPLE_COUNT:
  one: "You have one apple"
  few: "You have {count} apples"
  other: "You have {count} apples"
```

```twig
{{ 'APPLE_COUNT'|t({ count: 3 }) }}
```

Relative time:
```twig
{{ page.date|jazykolam_time }}
```

Month names:
```twig
{{ 3|jazykolam_month('genitive') }}
```

Locale switching:
```twig
{% do jazykolam_set_locale('en') %}
{{ 'HELLO'|t }}
{% do jazykolam_set_locale('cs') %}
```

---

## 🧰 Debug Mode

```yaml
debug:
  enabled: true
  inject: smart
```

- highlights translated strings,
- adds an HTML debug panel at the bottom of the page,
- logs details to the browser console,
- never injects into JSON/RSS/XHR responses.

---

## 📜 License

MIT License © 2025 Svatopluk Vít  
Email: svatopluk.vit@ruzne.info

See [CHANGELOG.md](./CHANGELOG.md) and [CHANGELOG_en.md](./CHANGELOG_en.md) for details.


## 🛠 Admin – Translation Manager (since 1.6.1)

- Adds a **Jazykolam** entry to the Admin navigation.
- Displays a table of discovered translation keys and their language values.
- Edits are saved into `user/languages.jazykolam.yaml` which overrides other sources.
- Restricted to `admin` role.
