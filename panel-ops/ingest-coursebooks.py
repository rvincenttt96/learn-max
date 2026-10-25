#!/usr/bin/env python3
"""Download coursebook PDFs from DirectAdmin, OCR text, upload .pages.json sidecars.

Does not print DirectAdmin secrets.
"""
from __future__ import annotations

import importlib.util
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.parse
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

os.environ.setdefault("PYTHONUNBUFFERED", "1")
os.environ.setdefault("OMP_THREAD_LIMIT", "1")
try:
    sys.stdout.reconfigure(line_buffering=True)
    sys.stderr.reconfigure(line_buffering=True)
except Exception:
    pass

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
DEPLOY_PATH = HERE / "deploy.py"

BOOK_DIR = "domains/panel.ghazalify.com/public_html/uploads/quiz-books"
WORK_ROOT = Path("/tmp/ww-ocr")
PAGE_TIMEOUT = 12
OCR_WORKERS = 2
TARGET_DPI = 130
MAX_WIDTH = 1400

FALLBACK_PDFS = [
    "qb_ww2_20261018181457_3bba4abb.pdf",
    "qb_ww2_2026101818457_3bba4abb.pdf",
    "Wider-World-American-Level-2-SB-WB.pdf",
    "Wider-World-American-Level-3-SB-WB.pdf",
    "Wider-World-American-Level-4-SB-WB.pdf",
]


def load_deploy():
    spec = importlib.util.spec_from_file_location("panel_deploy", DEPLOY_PATH)
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def log(msg: str) -> None:
    print(msg, flush=True)


def clean_text(text: str) -> str:
    text = text.replace("\r\n", "\n").replace("\x00", "")
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def guess_course(name: str) -> str:
    n = re.sub(r"[_\-.–—\[\]]+", " ", name.lower())
    n = re.sub(r"\s+", " ", n).strip()
    if re.search(r"\bww2\b", n) or "qb ww2" in n:
        return "WW2"
    if re.search(r"\bww3\b", n) or "qb ww3" in n:
        return "WW3"
    if re.search(r"\bww4\b", n) or "qb ww4" in n:
        return "WW4"
    if "wider" in n or "وایدر" in n:
        for num, key in ((4, "WW4"), (3, "WW3"), (2, "WW2")):
            if re.search(rf"\b(?:level|lvl|book)?\s*{num}\b", n):
                return key
    if re.search(r"connect|کانکت|کنکت", n):
        if re.search(r"foundat|فاندیشن|بنیاد", n):
            return "CNF"
        for num, key in ((5, "CN5"), (4, "CN4"), (3, "CN3"), (2, "CN2"), (1, "CN1")):
            if re.search(rf"\b(?:level|lvl|book|جلد)?\s*{num}\b", n):
                return key
    return ""


def original_for(stored: str) -> str:
    low = stored.lower()
    if "connect" in low or "کانکت" in low or "کنکت" in low:
        return stored
    if stored.startswith("qb_ww2_") or "level-2" in low or "level_2" in low:
        return "Wider-World-American-Level-2-SB-WB.pdf"
    if stored.startswith("qb_ww3_") or "level-3" in low:
        return "Wider-World-American-Level-3-SB-WB.pdf"
    if stored.startswith("qb_ww4_") or "level-4" in low:
        return "Wider-World-American-Level-4-SB-WB.pdf"
    return stored


def extract_pages_pdftotext(pdf_path: Path) -> dict[str, str]:
    proc = subprocess.run(
        ["pdftotext", "-layout", "-enc", "UTF-8", str(pdf_path), "-"],
        capture_output=True,
        timeout=240,
        check=False,
    )
    parts = proc.stdout.decode("utf-8", "replace").split("\x0c")
    pages: dict[str, str] = {}
    for i, body in enumerate(parts, 1):
        pages[str(i)] = clean_text(body)
    if pages and pages.get(str(len(pages)), "") == "":
        pages.pop(str(len(pages)), None)
    return pages


def ocr_png(png_path: str) -> str:
    try:
        proc = subprocess.run(
            [
                "tesseract",
                png_path,
                "stdout",
                "-l",
                "eng",
                "--psm",
                "6",
                "--oem",
                "1",
            ],
            capture_output=True,
            timeout=PAGE_TIMEOUT,
            env={**os.environ, "OMP_THREAD_LIMIT": "1"},
            check=False,
        )
        return clean_text(proc.stdout.decode("utf-8", "replace"))
    except subprocess.TimeoutExpired:
        return ""
    except Exception:
        return ""


def checkpoint_path(pdf_path: Path) -> Path:
    return pdf_path.with_suffix(".pages.partial.json")


def load_checkpoint(pdf_path: Path) -> dict[str, str]:
    path = checkpoint_path(pdf_path)
    if not path.is_file():
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        pages = data.get("pages") if isinstance(data, dict) else data
        if not isinstance(pages, dict):
            return {}
        return {str(k): str(v) for k, v in pages.items()}
    except Exception:
        return {}


def save_checkpoint(pdf_path: Path, pages: dict[str, str]) -> None:
    path = checkpoint_path(pdf_path)
    tmp = path.with_suffix(".tmp")
    tmp.write_text(json.dumps({"pages": pages}, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
    tmp.replace(path)


def extract_pages_ocr(pdf_path: Path) -> dict[str, str]:
    import pymupdf

    doc = pymupdf.open(str(pdf_path))
    total = doc.page_count
    pages = load_checkpoint(pdf_path)
    done = {int(k) for k in pages}
    pending = [i for i in range(total) if (i + 1) not in done]
    log(f"  OCR {pdf_path.name} ({total} scanned pages, {len(pages)} cached)…")
    if not pending:
        doc.close()
        return pages

    tmpdir = Path(tempfile.mkdtemp(prefix="ww-ocr-p-"))
    timed_out = 0
    t0 = time.time()
    try:
        for start in range(0, len(pending), OCR_WORKERS):
            chunk_idx = pending[start : start + OCR_WORKERS]
            jobs = []
            for i in chunk_idx:
                page = doc[i]
                zoom = TARGET_DPI / 72.0
                width = page.rect.width * zoom
                if width > MAX_WIDTH:
                    zoom = MAX_WIDTH / max(page.rect.width, 1)
                mat = pymupdf.Matrix(zoom, zoom)
                pix = page.get_pixmap(matrix=mat, colorspace=pymupdf.csGRAY, alpha=False)
                png = tmpdir / f"p{i + 1:04d}.png"
                pix.save(str(png))
                pix = None
                jobs.append((i + 1, str(png)))
            with ThreadPoolExecutor(max_workers=OCR_WORKERS) as pool:
                futs = {pool.submit(ocr_png, png): n for n, png in jobs}
                for fut in as_completed(futs):
                    n = futs[fut]
                    text = fut.result()
                    pages[str(n)] = text
                    if text == "":
                        timed_out += 1
            for _, png in jobs:
                try:
                    os.remove(png)
                except OSError:
                    pass
            finished = min(start + OCR_WORKERS, len(pending))
            if finished % 2 == 0 or finished == len(pending):
                save_checkpoint(pdf_path, pages)
            seen = len(done) + finished
            elapsed = max(time.time() - t0, 0.1)
            rate = finished / elapsed
            eta = (len(pending) - finished) / rate if rate else 0
            log(f"    {seen}/{total}  empty/timeout={timed_out}  eta={int(eta)}s")
    finally:
        doc.close()
        shutil.rmtree(tmpdir, ignore_errors=True)
        save_checkpoint(pdf_path, pages)
    return pages


def extract_pages(pdf_path: Path) -> dict[str, str]:
    pages = extract_pages_pdftotext(pdf_path)
    nonempty = sum(1 for v in pages.values() if v)
    log(f"  pdftotext {pdf_path.name}: {len(pages)} pages, {nonempty} with text")
    if nonempty < 10:
        pages = extract_pages_ocr(pdf_path)
        nonempty = sum(1 for v in pages.values() if v)
        log(f"  OCR {pdf_path.name}: {len(pages)} pages, {nonempty} with text")
    if nonempty < 10:
        raise SystemExit(f"too little text from {pdf_path.name}")
    return pages


def collect_names(data) -> list[str]:
    names: list[str] = []
    items = data
    if isinstance(data, dict):
        for key in ("files", "items", "data", "entries"):
            if key in data:
                items = data[key]
                break
        if isinstance(items, dict) and "files" in items:
            items = items["files"]
    if not isinstance(items, list):
        return names
    for item in items:
        if isinstance(item, str):
            name = item
        elif isinstance(item, dict):
            name = str(item.get("name") or item.get("file") or item.get("filename") or "")
        else:
            name = ""
        if name:
            names.append(name)
    return names


def list_files(da, directory: str) -> list[str]:
    queries = [
        ("GET", "/api/filemanager?" + urllib.parse.urlencode({"path": directory})),
        ("GET", "/api/filemanager/list?" + urllib.parse.urlencode({"path": directory})),
        ("GET", "/api/filemanager-actions/list?" + urllib.parse.urlencode({"path": directory})),
    ]
    for method, q in queries:
        st, _, body = da.call(method, q, timeout=60)
        log(f"list {q.split('?')[0]} HTTP {st} bytes={len(body)}")
        if st != 200:
            continue
        text = body.decode("utf-8", "replace")
        try:
            data = json.loads(text)
        except json.JSONDecodeError:
            continue
        names = collect_names(data)
        if names:
            return sorted(set(names))
    return []


def download(da, remote: str, dest: Path) -> None:
    if dest.is_file() and dest.stat().st_size > 1024 * 1024:
        log(f"  reuse {dest.name} {dest.stat().st_size} bytes")
        return
    cached = [
        Path("/tmp/ww-books-2sz4l3m4") / dest.name,
        Path("/tmp/ww-books-7mvnm2s4") / dest.name,
    ]
    for c in cached:
        if c.is_file() and c.stat().st_size > 1024 * 1024:
            shutil.copy2(c, dest)
            log(f"  copied cache {dest.name} {dest.stat().st_size} bytes")
            return
    q = "/api/filemanager/download?" + urllib.parse.urlencode({"path": remote})
    log(f"download {Path(remote).name} …")
    st, _, body = da.call("GET", q, timeout=600)
    if st != 200:
        raise SystemExit(f"download HTTP {st} for {remote}: {body[:160]!r}")
    if not body.startswith(b"%PDF-"):
        raise SystemExit(f"not a PDF ({len(body)} bytes) for {remote}")
    dest.write_bytes(body)
    log(f"  saved {dest.name} {len(body)} bytes")


def is_connectivity_pdf(name: str) -> bool:
    low = name.lower()
    if not low.endswith(".pdf"):
        return False
    return any(token in low for token in ("connect", "کانکت", "کنکت", "cnf", "cn1", "cn2", "cn3", "cn4", "cn5"))


def is_target_pdf(name: str) -> bool:
    low = name.lower()
    if not low.endswith(".pdf"):
        return False
    if is_connectivity_pdf(name):
        return True
    return any(
        token in low
        for token in (
            "ww2",
            "ww3",
            "ww4",
            "level-2",
            "level_2",
            "level-3",
            "level-4",
            "wider-world",
            "wider_world",
        )
    )


def write_ingest_url(da) -> None:
    st, _, cfg = da.call(
        "GET",
        "/api/filemanager/download?"
        + urllib.parse.urlencode({"path": "domains/panel.ghazalify.com/public_html/includes/rollcall-config.php"}),
        timeout=60,
    )
    if st != 200 or not cfg:
        log("INGEST_READY=0")
        log("No cron/identity key; Coursebooks page load will ingest.")
        return
    text = cfg.decode("utf-8", "replace")
    cron = ""
    m = re.search(r"'cron_key'\s*=>\s*'([^']*)'", text)
    if m:
        cron = m.group(1)
    if not cron:
        m = re.search(
            r"'identity'\s*=>\s*\[[^\]]*?'secret'\s*=>\s*'([^']*)'",
            text,
            re.S,
        )
        if m:
            cron = m.group(1)
    if not cron:
        log("INGEST_READY=0")
        log("No cron/identity key; Coursebooks page load will ingest.")
        return
    ingest_url = "https://panel.ghazalify.com/dashboard/supervisor/cron_quiz_books_ingest.php?k=" + urllib.parse.quote(cron)
    Path("/tmp/ghazal-ingest.url").write_text(ingest_url, encoding="utf-8")
    log("INGEST_READY=1")


def main() -> None:
    deploy = load_deploy()
    secrets = deploy.load_secrets()
    da = deploy.DA(secrets["url"], secrets["username"], secrets["password"])
    da.login()
    names = list_files(da, BOOK_DIR)
    if names:
        log("remote files: " + ", ".join(names))
    else:
        names = list(FALLBACK_PDFS)
        log("directory list empty; trying fallback names")

    remote_json = {n for n in names if n.lower().endswith(".pages.json")}
    argv = [a.lower() for a in sys.argv[1:]]
    connect_pdfs = [n for n in names if is_connectivity_pdf(n)]
    if connect_pdfs and "--all" not in argv:
        wanted = connect_pdfs
    else:
        wanted = [n for n in names if is_target_pdf(n)]
        if not wanted:
            wanted = [n for n in FALLBACK_PDFS if is_target_pdf(n)]
    uniq = []
    seen_course = set()
    for name in wanted:
        course = guess_course(name) or guess_course(original_for(name))
        key = course or name.lower()
        if key in seen_course:
            continue
        seen_course.add(key)
        uniq.append(name)
    wanted = uniq
    if not wanted:
        raise SystemExit("no coursebook PDFs found in quiz-books")
    log("wanted: " + ", ".join(wanted))

    WORK_ROOT.mkdir(parents=True, exist_ok=True)
    uploaded = []
    for name in wanted:
        json_name = Path(name).stem + ".pages.json"
        local_json = WORK_ROOT / json_name
        if json_name in remote_json:
            log(f"skip OCR {name}; sidecar already on server")
            uploaded.append(json_name)
            continue
        if not (local_json.is_file() and local_json.stat().st_size > 20000):
            local_pdf = WORK_ROOT / name
            try:
                download(da, BOOK_DIR + "/" + name, local_pdf)
            except SystemExit as e:
                log(f"skip {name}: {e}")
                continue
            pages = extract_pages(local_pdf)
            course = guess_course(name) or guess_course(original_for(name))
            payload = {
                "course_key": course,
                "stored_name": name,
                "original_name": original_for(name),
                "page_count": len(pages),
                "pages": pages,
            }
            local_json.write_text(json.dumps(payload, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
            log(f"  json {json_name} {local_json.stat().st_size} bytes course={course}")
        else:
            log(f"reuse local sidecar {json_name} {local_json.stat().st_size} bytes")
        st = da.upload(BOOK_DIR, json_name, local_json.read_bytes())
        if st not in (200, 204):
            raise SystemExit(f"upload {json_name} HTTP {st}")
        log(f"  uploaded {json_name} HTTP {st}")
        uploaded.append(json_name)

    if not uploaded:
        raise SystemExit("no sidecars uploaded")
    log("UPLOADED_JSON=" + ",".join(uploaded))
    write_ingest_url(da)


if __name__ == "__main__":
    sys.exit(main() or 0)
