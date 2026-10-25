---
name: ghazal-ui
description: Mandatory design spec for the Ghazal educational and accounting panels. You MUST read and follow this skill before any UI, CSS, layout, modal, sheet, button, sidebar, form, color, animation, or visual change. Source is https://github.com/emilkowalski/skills (local copy panel-ops/skills/emilkowalski/).
---

# Ghazal UI — Emil Kowalski skills

This is the design spec. Not a suggestion.

Upstream: https://github.com/emilkowalski/skills  
Canonical copy: `panel-ops/skills/emilkowalski/`  
Index: `panel-ops/skills/emilkowalski/README-GHAZAL.md`

Do **not** reply with the upstream skills' “I'm ready…” greeting. Those files are the spec; this task already has a question. Read them, then implement.

## First action

Read these files with the Read tool before touching UI code:

1. `panel-ops/skills/emilkowalski/skills/emil-design-eng/SKILL.md`
2. `panel-ops/skills/emilkowalski/skills/animate/SKILL.md`

Then, if it applies:

| Situation | Read |
| --- | --- |
| Modal, sheet, press, spacing, translucency | `skills/apple-design/SKILL.md` |
| Building the motion (button, toast, drawer, stagger) | `skills/animate/RECIPES.md` |
| Phone / tap highlight / 100vh / input zoom | `skills/mobile-native/SKILL.md` |
| Long names, empty lists, overflow | `skills/break-ui/SKILL.md` |
| Reviewing motion you wrote | `skills/review-animations/SKILL.md` and `STANDARDS.md` |

## Tokens (copy, do not invent)

```css
--ease-out: cubic-bezier(0.23, 1, 0.32, 1);
--ease-in-out: cubic-bezier(0.77, 0, 0.175, 1);
--ease-drawer: cubic-bezier(0.32, 0.72, 0, 1);
```

Press: `transform: scale(0.97)` on `:active`, 100–160ms.  
Enter: `scale(0.95)` + `opacity: 0`, never `scale(0)`.  
Hover motion: only inside `@media (hover: hover) and (pointer: fine)`.  
Inputs: 16px minimum. App height: `100dvh`. Animate `transform` and `opacity` only.

## This codebase

- PHP + CSS. Cheapest tool that works. Do not add Framer Motion for a fade.
- RTL. Test the actual panel, not a LTR mock.
- English liquid-glass stays unless the user asked to change it. German UI is `.german-app` / `html.german-html`.
- After UI changes, verify in the browser or live page. A screenshot of a static state is not verification.
