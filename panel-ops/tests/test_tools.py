#!/usr/bin/env python3
from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from lib.paths import is_blocked, resolve_rel  # noqa: E402
from lib.tools import PanelContext, ToolError, run_tool  # noqa: E402


class PathTests(unittest.TestCase):
    def test_blocks_secrets(self) -> None:
        self.assertTrue(is_blocked("includes/directadmin-config.php", []))
        self.assertTrue(is_blocked("panel-ops/config.json", []))
        self.assertFalse(is_blocked("includes/term-plan.php", []))

    def test_resolve_rejects_escape(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / "includes").mkdir()
            with self.assertRaises(ValueError):
                resolve_rel(root, "../outside.php")


class ToolTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        (self.root / "includes").mkdir()
        (self.root / "includes" / "term-plan.php").write_text("<?php\nfunction term_plan() {}\n", encoding="utf-8")
        (self.root / "dashboard").mkdir()
        ops = ROOT
        self.ctx = PanelContext.load(root=self.root, ops=ops)

    def tearDown(self) -> None:
        self.tmp.cleanup()

    def test_search_and_edit(self) -> None:
        found = run_tool(self.ctx, "search_code", {"pattern": "term_plan", "glob": "*.php"})
        self.assertIn("includes/term-plan.php", found)
        run_tool(
            self.ctx,
            "edit_file",
            {
                "path": "includes/term-plan.php",
                "old_string": "function term_plan() {}",
                "new_string": "function term_plan() { return 1; }",
            },
        )
        text = run_tool(self.ctx, "read_file", {"path": "includes/term-plan.php"})
        self.assertIn("return 1", text)

    def test_refuses_secret_write(self) -> None:
        with self.assertRaises(ToolError):
            run_tool(
                self.ctx,
                "write_file",
                {"path": "includes/directadmin-config.php", "contents": "nope"},
            )

    def test_dry_run_maps_panel_file(self) -> None:
        out = run_tool(self.ctx, "deploy_preview", {"files": ["includes/term-plan.php"]})
        self.assertIn("panel.ghazalify.com", out)
        self.assertIn("includes/term-plan.php", out)


if __name__ == "__main__":
    unittest.main()
