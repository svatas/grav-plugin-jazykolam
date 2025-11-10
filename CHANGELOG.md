# Changelog – Jazykolam Plugin for Grav + Gantry 5

## [1.0.0] – První veřejná verze

### Přidáno

- Překladové filtry `jazykolam_plural`, `jazykolam_month`, `jazykolam_time`, `jazykolam_set_locale`
- Podpora ICU-lite syntaxe pro plurály v `languages.yaml`
- Automatické přepisy filtrů `t`, `trans`, `nicetime` (volitelně)
- Podpora jazyků: cs, en, pl, sk
- Debug režim: inline značkování, HTML panel, výpis do konzole
- Admin rozhraní „Translation Manager“:
  - zobrazení matice klíčů a jazyků
  - úprava překladů přímo v UI
  - přidání nového klíče z UI
  - filtrování podle textu a chybějících překladů
- Inline editor překladů na frontendu (experimentální)
- Automatická detekce klíčů použitých v šablonách
- Zálohování jazykového souboru před uložením (`.bak`)
- Integrace s Gantry 5 rendererem (pokud je přítomen)
- Demo outline s přepínačem jazyků
- Konfigurační volby:
  - `inline_edit.enabled`, `inline_edit.allowed_roles`
  - `auto_override.t`, `auto_override.nicetime`, `auto_override.gantry`
  - `debug.enabled`, `debug.inject`
- Dokumentace (`DOKUMENTACE.md`, `DOCUMENTATION.md`)
- Struktura balíčku kompatibilní s Grav plugin instalací

### Změněno

- Datový model Admin UI zahrnuje i nepřeložené klíče z šablon
- Všechny změny se ukládají pouze do `user/languages.jazykolam.yaml`
- Plugin nezasahuje do jádra Gravu ani jiných pluginů

### Opraveno

- Spojování debug panelu a console snippetu (`.` místo `+`)
- Interní kontrola duplicitních filtrů pro Gantry 5
- Aktualizace metadat a komentářů

### Poznámky

- Inline editor je výchozím nastavením vypnutý a nemá vliv na výkon
- Funkce jsou navrženy tak, aby byly bezpečně rozšiřitelné v dalších verzích
