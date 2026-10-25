# ایجنت پنل غزال

این ریپو یک **ایجنت** است، نه یک راهنمای دیپلوی.

کارش همان کاری است که ایجنت کرسر روی پنل غزال می‌کند: تسک فارسی می‌گیری، در کد پنل می‌گردد، ادیت می‌کند، و با DirectAdmin روی سرور زنده می‌گذارد.

مدل (جمنای ۴، GPT، کلود، …) مغز است. این ریپو دست‌هاست: جستجو، خواندن، ادیت، آپلود. مدل را با متن «لطفاً از FTP استفاده نکن» تربیت نکن؛ **ابزار را بهش وصل کن**.

ریپو: [github.com/rvincenttt96/ghazal-panel-ops](https://github.com/rvincenttt96/ghazal-panel-ops)

---

## سه روش اجرا

| روش | مغز | برای کی |
|---|---|---|
| **MCP** (پیشنهاد) | جمینای ۴ / مدل Cursor / کلود همان جلسه‌ای که باز است | می‌خواهی مدل دیگر مثل همین ایجنت کار کند |
| **Playground** | `GEMINI_API_KEY` | چت در مرورگر، رد ابزارها را می‌بینی |
| **CLI** | همان کلید | یک تسک در ترمینال: `python3 agent.py run "…"` |

کد PHP پنل داخل این ریپو نیست. `PANEL_ROOT` باید ریشهٔ ریپوی خصوصی پنل باشد (`dashboard/` و `includes/` آنجاست).

---

## ۱) جمینای ۴ یا هر مدل در Cursor — MCP

مدل خودش لوپ ایجنتیک را اجرا می‌کند. کلید جمینای لازم نیست.

```bash
git clone https://github.com/rvincenttt96/ghazal-panel-ops.git
cp ghazal-panel-ops/config.example.json ghazal-panel-ops/config.json
# فقط password دایرکت‌ادمین را در config.json بگذار
```

در Cursor: Settings → MCP → همین JSON (مسیرها را عوض کن):

```json
{
  "mcpServers": {
    "ghazal-panel": {
      "command": "python3",
      "args": ["/ABS/ghazal-panel-ops/agent.py", "mcp"],
      "env": { "PANEL_ROOT": "/ABS/private-panel-repo" }
    }
  }
}
```

نمونه کامل: [`examples/cursor-mcp.json`](examples/cursor-mcp.json)

بعد به مدل بگو:

> ابزار `ghazal-panel` را داری. constitution را رعایت کن. تسک: …

Gemini CLI:

```bash
gemini mcp add ghazal-panel python3 /ABS/ghazal-panel-ops/agent.py mcp
# PANEL_ROOT را در env همان MCP بگذار
```

ابزارهایی که مدل می‌بیند:

`search_code` · `read_file` · `list_files` · `edit_file` · `write_file` · `deploy_preview` · `deploy_live` · `panel_status`

لوپ اجباری ایجنت: جستجو → خواندن → ادیت → `deploy_preview` → `deploy_live`. FTP ندارد.

---

## ۲) Playground در مرورگر

```bash
cd ghazal-panel-ops
cp config.example.json config.json
cp .env.example .env
# GEMINI_API_KEY و PANEL_ROOT را در .env پر کن

python3 agent.py doctor
cd playground && npm install && npm run dev
```

UI روی پورت `4548` است و API روی `4547`. یا بعد از `npm run build`:

```bash
python3 agent.py serve --port 4547
```

بدون کلید مدل، چت کار نمی‌کند ولی وضعیت پنل و ابزارها هنوز زنده است؛ MCP همان مسیر بدون کلید است.

---

## ۳) یک تسک از ترمینال

```bash
export PANEL_ROOT=/ABS/private-panel-repo
python3 agent.py run "ریمارک سوپروایزر ترم‌پلن برای معلم روی کارت جلسه دیده شود"
```

خروجی ابزارها را خط به خط چاپ می‌کند، بعد گزارش نهایی.

ابزار تکی، بدون مدل:

```bash
python3 agent.py call panel_status
python3 agent.py call search_code --pattern term_plan_session_remarks --glob '*.php'
python3 agent.py call deploy_preview --files includes/term-plan.php
```

---

## قانون‌هایی که داخل ایجنت است

در [`constitution.md`](constitution.md) قفل شده، نه در چت:

- Session Plan را به Term Plan تبدیل نکن
- لول‌های T / B / E / PI / I / UI / ADV را حذف نکن
- ترم‌پلن Connectivity / Wider World فقط برای WT و CB / CE / CPI / CI / CUI / CADV
- فایل رمز آپلود نشود
- حسابداری سه c دارد: `acccounting.ghazalify.com`
- سئو فقط وردپرس است

دیپلوی همان `deploy.py` است (DirectAdmin Evolution، overwrite، بعد دانلود برای چک). ایجنت آن را صدا می‌زند؛ مدل مسیر سرور را حدس نمی‌زند.

---

## نصب کنار پنل

```text
private-panel-repo/
├── dashboard/
├── includes/
├── accounting/
└── panel-ops/     ← کلون این ریپو
```

آن وقت `PANEL_ROOT` همان والد است و لازم نیست دستی باشد:

```bash
python3 panel-ops/agent.py doctor
python3 panel-ops/agent.py mcp
```

رمز دایرکت‌ادمین: `config.json` کنار ایجنت، یا `includes/directadmin-config.php` داخل پنل. هیچ‌کدام را commit نکن.

```bash
python3 agent.py doctor
```

اگر ریشهٔ پنل یا رمز DA یا کلید مدل غلط باشد، همان‌جا می‌گوید.

وابستگی: پایتون ۳.۸+ با کتابخانهٔ استاندارد. برای playground فقط `npm`. پکیج `requests` لازم نیست.
