# Jazykolam

Jazykolam is a Grav CMS plugin that:

- extends translation handling without touching Grav core,
- adds an Admin Translation Manager,
- provides an optional frontend inline editor for administrators,
- integrates optionally with Gantry 5.

## Installation

1. Place the `jazykolam` folder into `user/plugins/` or upload the ZIP via Admin.
2. Enable the plugin.
3. Optionally configure:
   - `auto_override` to route `|t`, `|trans`, `|nicetime` through Jazykolam,
   - `inline_edit` to enable the inline editor.

## Admin – Translation Manager

Since 1.6.1–1.6.3:

- A **Jazykolam** entry appears in the Admin sidebar.
- Shows a matrix of keys vs languages.
- Saves into `user/languages.jazykolam.yaml`.
- 1.6.3 adds:
  - filtering,
  - missing-only view,
  - discovery of keys from Twig templates,
  - an add-new-key row,
  - automatic `.bak` backups.

## Inline editor (1.6.2+)

Optional, **disabled by default**.

### Enable

```yaml
inline_edit:
  enabled: true
  allowed_roles:
    - admin
```

- Editing mode: `?jazykolam_inline=1`
- Inspect mode: `?jazykolam_inline=inspect`

### Behaviour (1.6.4)

- Edit mode:
  - clicking a `.jazykolam-inline` span opens a small popup dialog
    (key + locale info, textarea, Save/Cancel),
  - saving writes into `user/languages.jazykolam.yaml` via a protected task.
- Inspect mode:
  - hovering shows tooltips with key/locale,
  - read-only, no saving.

Only authenticated users with allowed roles see these tools; public users are unaffected.
