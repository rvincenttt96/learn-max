# Gemini 4

Add this repo as an MCP server so you can actually edit the live Ghazal panels:

```bash
gemini mcp add ghazal-panel python3 /ABS/ghazal-panel-ops/agent.py mcp
```

Set `PANEL_ROOT` to the private panel repository (the one with `dashboard/` and `includes/`). DirectAdmin password stays in `config.json` next to `agent.py`.

Read `constitution.md`. Use tools. Do not explain how you would use FTP. Search, edit, `deploy_preview`, `deploy_live`.
