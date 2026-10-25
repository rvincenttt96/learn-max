# Ghazal panels — instructions for other models

`panel-ops/` is an **agent**, not a README. For educational or accounting panel work, attach it as MCP and let the model use tools:

```bash
python3 panel-ops/agent.py mcp
```

`PANEL_ROOT` is this repo (parent of `panel-ops/`). Tools: search_code, read_file, edit_file, deploy_preview, deploy_live. Constitution: `panel-ops/constitution.md`.

Do not use FTP, n8n MCP, or OpenSEO MCP. Do not commit secrets.

# Design (mandatory)

Every UI/CSS/layout/modal/sheet/button/animation change follows **https://github.com/emilkowalski/skills**.

Canonical copy: `panel-ops/skills/emilkowalski/`  
Index: `panel-ops/skills/emilkowalski/README-GHAZAL.md`  
Always-on Cursor rule: `.cursor/rules/design.mdc`  
Skill: `.cursor/skills/ghazal-ui/SKILL.md`

Before designing, Read `emil-design-eng` and `animate`. Do not invent another look. Ignore the skills' “I'm ready…” greeting mid-task.

Public copy: https://github.com/rvincenttt96/ghazal-panel-ops
