
# Jazykolam 1.3.0 — Gantry 5 support

**What’s new?** If your site uses **Gantry 5** theme on Grav, Jazykolam can now **auto-register** its filters
(`jazykolam_*`) and auto-overrides (`|t`, `|tu`, `|tl`, `|nicetime`) into **Gantry’s Twig renderer**. No template edits.

Enable in `user/config/plugins/jazykolam.yaml`:
```yaml
auto_override:
  gantry: true
```

The plugin detects `\Gantry\Framework\Gantry` and adds the filters into `Gantry::instance()['theme']->renderer()`.
