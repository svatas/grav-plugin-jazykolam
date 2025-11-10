# CHANGELOG (EN)

## [1.6.4] – 2025-11-09
### Added
- Improved inline editor:
  - uses a popup dialog (textarea + Save/Cancel) instead of `prompt()`,
  - better for longer texts.
- Added `?jazykolam_inline=inspect`:
  - shows key/locale as tooltip,
  - read-only inspection.

### Security
- Inline/inspect only active if `inline_edit.enabled: true`
  and user has one of `inline_edit.allowed_roles`.
- Disabled by default.

## [1.6.3] – 2025-11-09
- Extended Translation Manager (filtering, missing-only, template key discovery, add-new-key row, backups).

## [1.6.2]
- Experimental inline editor (`?jazykolam_inline=1`), saving to `user/languages.jazykolam.yaml`.

## [1.6.1]
- Basic Admin Translation Manager.

## [1.6.0]
- Documentation and configuration groundwork.
