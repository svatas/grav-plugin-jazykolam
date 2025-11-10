# CHANGELOG

## [1.6.4] – 2025-11-09
### Přidáno
- Vylepšený inline editor:
  - místo `prompt()` se používá popup (textarea + Save/Cancel),
  - přehlednější editace delších textů.
- Přidán režim `?jazykolam_inline=inspect`:
  - zobrazuje klíč/jazyk jako tooltip,
  - pouze náhled, bez ukládání.

### Bezpečnost
- Inline editor i inspect režim jsou dostupné pouze pokud je `inline_edit.enabled: true`
  a uživatel má roli z `inline_edit.allowed_roles`.
- Výchozí stav: vypnuto.

## [1.6.3] – 2025-11-09
- Rozšířený Translation Manager:
  - filtrování klíčů,
  - zobrazení pouze chybějících překladů,
  - detekce klíčů z Twig šablon,
  - přidávání nových klíčů,
  - automatické `.bak` zálohy.

## [1.6.2] – 2025-11-09
- Přidán experimentální inline editor (`?jazykolam_inline=1`).
- Ukládá do `user/languages.jazykolam.yaml` s kontrolou role a nonce.

## [1.6.1]
- Admin Translation Manager – základní UI pro úpravu překladů.

## [1.6.0]
- Dokumentace, přípravné konfigurace, konsolidace balíčku.
