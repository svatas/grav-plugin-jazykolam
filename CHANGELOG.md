# Changelog – Jazykolam Plugin for Grav + Gantry 5

Všechny významné změny v tomto projektu jsou popsány v tomto souboru.

---

## [1.5.1] – 2025-11-09
### Opraveno
- Opraveno spojování debug panelu a console snippetu v metodě `onOutputGenerated()` (`.` namísto `+`).
- Aktualizována metadata a komentáře.

### Vylepšeno
- Příprava dokumentace pro ICU-lite příklady.
- Základní interní kontrola duplicitních filtrů pro Gantry 5.

---

## [1.5.0] – 2025-10-??
### Přidáno
- Kompletní podpora **Gantry 5** – registrace filtrů i v prostředí rendereru.
- Nové filtry: `jazykolam_month`, `jazykolam_time`, `jazykolam_plural`.
- Funkce `jazykolam_set_locale()` pro dočasné přepnutí locale v šabloně.
- **Debug režim**: inline značkování, HTML panel, console výpis.
- Podpora **ICU-lite syntaxe** pro plurály v `languages.yaml`.
- Demo outline s pokročilým přepínačem jazyků.

---

## [1.4.0] – 2025-09-??
- Počáteční integrace s Gantry (beta).
- Automatické přepisy filtrů `t`, `tu`, `tl`, `trans`, `nicetime`.
- Volba `prefer_languages_yaml`.

---

## [1.3.0] – 2025-08-??
- První implementace pluralitních kategorií (one/few/other).
- Přidáno `default_locale` pro přepsání výchozího jazyka.

---

## [1.2.0] – 2025-07-??
- Zaveden filtr `jazykolam_time`.
- Logování fallbacků při chybějících klíčích.

---

## [1.1.0] – 2025-06-??
- Přidán `jazykolam_month` a `jazykolam_debug`.
- Podpora vlastních jazykových souborů `languages.jazykolam_*.yaml`.

---

## [1.0.0] – 2025-05-??
- První veřejná verze pluginu Jazykolam.
- Základní překladový filtr.
- Podpora jazyků: cs, en, pl, sk.

---

## Roadmap / plány
- ✅ Dokumentace ICU-lite formátu.
- 🔜 Unit testy a veřejný repozitář.
- 🔜 Rozšířené jazykové sady.
- 🔜 Import slovníků z JSON/CSV.
- 🔜 Integrace s Admin pluginem.

## [1.6.0] – 2025-11-09
### Přidáno
- Přidán soubor `DOKUMENTACE.md` s podrobným popisem architektury a použití.
- Přidány přípravné konfigurační volby pro inline překlady, automatickou detekci jazyka a lokalizované formáty data/času (bez dopadu na výkon, pokud jsou vypnuté).
- Upravena struktura balíčku tak, aby měl kořenovou složku `jazykolam` kompatibilní s Grav plugin instalací.

### Poznámka
- Funkce inline překládání a rozšířené nástroje jsou navrženy tak, aby byly bezpečně rozšiřitelné v dalších verzích (1.6.x) bez zásahu do jádra Gravu.

## [1.6.1] – 2025-11-09
### Přidáno
- Základní **Translation Manager** v Admin rozhraní (položka „Jazykolam“ v levém menu).
- Zobrazení matice klíčů a jazyků a možnost je upravit.

### Jak to funguje
- Úpravy se ukládají do souboru `user/languages.jazykolam.yaml`.
- Hodnoty z tohoto souboru mají prioritu před ostatními zdroji překladů.
- Funkce je dostupná pouze pro přihlášené administrátory.

### Poznámka
- Inline editace na frontendu zatím není aktivní – 1.6.1 přidává bezpečný základ v Admin UI.

## [1.6.2] – 2025-11-09
### Přidáno
- Experimentální **inline editor překladů** na frontendu.
- Aktivace přes konfiguraci `inline_edit.enabled: true` a parametr `?jazykolam_inline=1`.
- Kliknutím na zvýrazněný text (obalený `span.jazykolam-inline`) lze upravit překlad.

### Bezpečnost a omezení
- Upravovat mohou pouze přihlášení uživatelé s rolí z `inline_edit.allowed_roles` (výchozí `admin`).
- Uložení probíhá přes chráněný endpoint `/task/jazykolam.inlineSave` s nonce.
- Změny se ukládají do `user/languages.jazykolam.yaml` stejně jako v 1.6.1.

### Poznámka
- Inline editor je defaultně vypnutý a nemá vliv na výkon běžného webu.

