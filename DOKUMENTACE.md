# 📘 Podrobná dokumentace – Jazykolam plugin

## 🔍 Co je Jazykolam

**Jazykolam** je jazykový překladový nadstavbový plugin pro **Grav CMS**, který umožňuje překládat texty, časové výrazy, názvy měsíců a plurály, a to **bez nutnosti upravovat jádro Gravu** nebo přepisovat šablony.

Je určen pro uživatele, kteří chtějí:
- přeložit Grav do více jazyků nebo opravit existující překlady,
- mít kontrolu nad plurály, časem a lokalizací,
- používat překlady i v **Gantry 5**, nebo jen v čistém Gravu,
- a zachovat čistotu systému bez zásahu do jádra.

---

## 🧠 Základní princip
Grav při vykreslování stránky používá Twig engine. Jazykolam se napojí do jeho procesu, aniž by cokoli měnil v jádru.
Zachytí filtry jako `t`, `trans` a `nicetime` a obohatí je o logiku pluralit, měsíců a času.

---

## ⚙️ Co plugin dělá
1. **Zaregistruje se** do událostí Gravu (`onTwigExtensions`, `onOutputGenerated`).
2. **Přidá vlastní filtry** (`jazykolam_*`) do Twigu.
3. **Volitelně přepíše** výchozí Grav filtry, pokud je `auto_override` aktivní.
4. **Integruje se s Gantry 5**, pokud je k dispozici, jinak se chová čistě v Grav prostředí.
5. **Debug panel** zobrazuje ladicí informace o překladech.

---

## 🧩 Použitelné filtry
| Filtr | Funkce | Příklad |
|-------|---------|----------|
| `jazykolam_plural` | Pluralita podle locale | `{{ 'APPLE_COUNT'|jazykolam_plural({count:3}) }}` |
| `jazykolam_month` | Název měsíce | `{{ 3|jazykolam_month('genitive') }}` |
| `jazykolam_time` | Relativní čas | `{{ page.date|jazykolam_time }}` |
| `jazykolam_set_locale` | Přepnutí jazyka | `{% do jazykolam_set_locale('pl') %}` |

---

## 🔤 Jak fungují překlady
Vše vychází z `user/languages.yaml`. Příklad:
```yaml
HELLO_WORLD:
  cs: "Ahoj světe"
  en: "Hello world"
```
Použití v šabloně:
```twig
{{ 'HELLO_WORLD'|t }}
```

### Plurality
Mapový zápis:
```yaml
APPLE_COUNT:
  one: "Máš jedno jablko"
  few: "Máš {count} jablka"
  other: "Máš {count} jablek"
```
ICU-lite zápis:
```yaml
APPLE_COUNT: "{count, plural, one {jedno jablko} few {# jablka} other {# jablek}}"
```

---

## 🕒 Relativní čas
```twig
{{ (date() - 3600)|jazykolam_time }}
```
→ „před hodinou“

---

## 📅 Měsíce
```twig
{{ 3|jazykolam_month('genitive') }}
```
→ „března“

---

## 🌍 Přepnutí jazyka
```twig
{% do jazykolam_set_locale('en') %}
{{ 'HELLO'|t }}
{% do jazykolam_set_locale('cs') %}
```

---

## 🧰 Debug panel
V `user/config/plugins/jazykolam.yaml` zapni:
```yaml
debug:
  enabled: true
  inject: smart
```
Zobrazí přehled přeložených klíčů a výkonu překladu.

---

## 🧱 Technické shrnutí
- Grav plugin využívá eventy, nezasahuje do jádra.
- Přidává filtry při `onTwigExtensions`.
- Přidává debug panel při `onOutputGenerated`.
- Integruje se s Gantry, ale funguje i bez něj.

---

## ✅ Shrnutí výhod
| Cíl | Řešení |
|-----|---------|
| Překlad bez úprav jádra | Event-based systém |
| Plurality a pádové tvary | ICU-lite syntaxe |
| Funkční s Gantry i bez něj | Dvojitá registrace |
| Debugging a vizualizace | Debug panel a značky |
| Jednoduché rozšíření | Jasně oddělené filtry |

---

## 📜 Autor
MIT License © 2025 Svatopluk Vít  
Email: svatopluk.vit@ruzne.info
