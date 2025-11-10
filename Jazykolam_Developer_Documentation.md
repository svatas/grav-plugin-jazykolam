# Jazykolam – Developer Documentation

## 🇨🇿 ČESKÁ SEKCE

### Přehled

Jazykolam je rozšiřující plugin pro Grav CMS, který přidává pokročilé funkce překladu bez zásahu do jádra Gravu a bez nutnosti upravovat šablony třetích stran. Funguje i s Gantry 5 a umožňuje nadstavbové přepsání překladů pomocí vlastního souboru `user/languages.jazykolam.yaml`.

### Architektura

- Hlavní třída: `JazykolamPlugin` (`jazykolam.php`)
- Twig rozšíření: `JazykolamTwigExtension` (`classes/JazykolamTwigExtension.php`)
- Admin UI: `admin/templates/jazykolam.html.twig`
- Překlady uložené v:
  - `user/languages.yaml`
  - `user/languages.<lang>.yaml`
  - `user/languages.jazykolam.yaml` (nejvyšší priorita)

Plugin využívá eventy Gravu (`onPluginsInitialized`, `onTwigExtensions`, `onOutputGenerated`, `onAdminMenu`, `onAdminControllerInit`) a nepřepisuje core.

### Klíčové vlastnosti (1.6.0–1.6.3)

- Přepis a rozšíření překladů bez úprav jádra.
- Volitelné přesměrování standardních filtrů (`t`, `trans`, `nicetime`) přes Jazykolam logiku.
- Podpora pluralit, názvů měsíců, přirozeného času.
- Admin Translation Manager:
  - zobrazení matice klíčů × jazyků,
  - ukládání do `languages.jazykolam.yaml`,
  - od 1.6.3:
    - filtrování,
    - zvýraznění chybějících překladů,
    - zobrazení klíčů z Twig šablon,
    - přidávání nových klíčů,
    - automatické `.bak` zálohy.
- Inline editor (1.6.2+):
  - volitelný, pouze pro přihlášené adminy,
  - aktivuje se `inline_edit.enabled: true` + `?jazykolam_inline=1`,
  - ukládá přes `/task/jazykolam.inlineSave` do `languages.jazykolam.yaml`.

### Bezpečnost

- Všechny zápisy jsou omezeny na `user/languages.jazykolam.yaml`.
- Inline editor:
  - jen autentizovaní uživatelé s povolenou rolí,
  - ochrana pomocí nonce (`jazykolam-inline`),
  - žádný vliv na anonymní návštěvníky.
- Admin nástroje jsou dostupné pouze v rámci Grav Admin.

### Výkon

- Běžný frontend:
  - bez zapnutého inline režimu přidává pouze lehkou překladovou logiku.
  - žádné velké skeny souborů na každém requestu pro návštěvníky.
- Sken šablon na klíče se provádí v rámci Admin UI (1.6.3) – není součástí běžného frontendu.

---

## 🇬🇧 ENGLISH SECTION

### Overview

Jazykolam is a Grav CMS plugin providing an advanced, non-intrusive translation layer. It lets you override and extend translations via `user/languages.jazykolam.yaml`, integrate with Twig filters, and manage translations from the Admin UI and (optionally) via a safe inline editor on the frontend.

### Architecture

- Main plugin: `JazykolamPlugin` (`jazykolam.php`)
- Twig extension: `JazykolamTwigExtension`
- Admin UI template: `admin/templates/jazykolam.html.twig`
- Primary override file: `user/languages.jazykolam.yaml` (highest priority)

### Key features (1.6.0–1.6.3)

- Extended translation handling without touching Grav core.
- Optional override of `t`, `trans`, `nicetime` through Jazykolam.
- Pluralization helpers, month names, human-friendly time.
- Admin Translation Manager:
  - matrix of keys vs languages,
  - writes to `languages.jazykolam.yaml`,
  - since 1.6.3:
    - filtering,
    - missing-only view,
    - keys discovered from Twig templates,
    - add-new-key row,
    - automatic `.bak` backups.
- Frontend inline editor (since 1.6.2, experimental, opt-in).

### Security

- Only authenticated users with allowed roles can change translations.
- All writes go to `user/languages.jazykolam.yaml`.
- Inline editor is nonce-protected and disabled by default.

### Performance

- No impact on public users unless explicitly enabled features are active.
- Template scanning is scoped to Admin usage, not to normal page rendering.

