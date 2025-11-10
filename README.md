# Jazykolam

Jazykolam je plugin pro Grav CMS, který rozšiřuje práci s překlady:
- umožňuje přepisovat a doplňovat překlady bez zásahu do jádra,
- přidává Admin Translation Manager,
- nabízí volitelný inline editor přímo na frontendu (pro adminy),
- funguje samostatně i s Gantry 5.

## Instalace

1. Nahrajte složku `jazykolam` do `user/plugins/` nebo použijte ZIP jako plugin balíček.
2. V Adminu plugin povolte.
3. Volitelně nastavte:
   - `auto_override` pro přesměrování `|t`, `|trans`, `|nicetime`,
   - `inline_edit` pro povolení inline editoru.

## Admin – Translation Manager

Od verzí 1.6.1–1.6.3:

- V levém menu Adminu se objeví položka **Jazykolam**.
- Zobrazí tabulku klíčů × jazyků.
- Uloží změny do `user/languages.jazykolam.yaml`.
- 1.6.3:
  - přidává filtrování, zobrazení pouze chybějících, detekci klíčů z Twig šablon,
  - přidává řádek pro vytvoření nového klíče,
  - před uložením vytváří `.bak` zálohu.

## Inline editor (1.6.2+)

Volitelný, **defaultně vypnutý**.

### Aktivace

```yaml
inline_edit:
  enabled: true
  allowed_roles:
    - admin
```

- Pro editaci: otevřete stránku jako přihlášený admin s `?jazykolam_inline=1`.
- Pro inspekci: použijte `?jazykolam_inline=inspect`.

### Chování (1.6.4)

- Režim `1`:
  - kliknutí na přeložený text (`.jazykolam-inline`) otevře popup:
    - vidíte klíč a jazyk,
    - v textarea upravíte text,
    - Save → uloží do `user/languages.jazykolam.yaml`.
- Režim `inspect`:
  - po najetí kurzoru se zobrazí tooltip s klíčem a jazykem,
  - žádné ukládání, pouze náhled.

V obou režimech:
- pouze pro přihlášené uživatele s povolenými rolemi,
- běžní návštěvníci nic nevidí.

## Kompatibilita

- Funguje bez Gantry 5.
- Při povolení `auto_override.gantry` se filtry zaregistrují i v Gantry rendereru.
