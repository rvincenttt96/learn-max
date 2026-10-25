import { useEffect, useMemo, useState } from 'react'
import { Badge } from './components/ui/badge'
import { Button } from './components/ui/button'
import { Input } from './components/ui/input'
import { Textarea } from './components/ui/textarea'

type Health = {
  ok: boolean
  llm: string
  needs_key: boolean
  status: string
  tools: string[]
  help: string
}

type ToolEvent = {
  type: string
  name?: string
  args?: unknown
  ok?: boolean
  result?: string
}

type ChatMessage = {
  role: 'user' | 'assistant' | 'error'
  text: string
  events?: ToolEvent[]
}

const EXAMPLES = [
  'ریمارک سوپروایزر ترم‌پلن برای معلم روی کارت جلسه دیده شود',
  'ترم‌پلن Connectivity فقط روی لول‌های CB/CE/CPI اعمال شود نه روی PI',
  'کارت Term Plan معلم درصد سپری‌شدهٔ ترم را نشان بدهد',
]

async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(path, {
    ...init,
    headers: { 'Content-Type': 'application/json', ...(init?.headers || {}) },
  })
  const data = await res.json()
  if (!res.ok) {
    const err = new Error(data.error || data.help || res.statusText)
    ;(err as Error & { payload?: unknown }).payload = data
    throw err
  }
  return data as T
}

function statusLine(status: string, key: string) {
  const line = status.split('\n').find((l) => l.startsWith(key + '='))
  return line ? line.slice(key.length + 1) : ''
}

export default function App() {
  const [health, setHealth] = useState<Health | null>(null)
  const [healthError, setHealthError] = useState('')
  const [loadingHealth, setLoadingHealth] = useState(true)
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [draft, setDraft] = useState('')
  const [busy, setBusy] = useState(false)
  const [search, setSearch] = useState('term_plan')
  const [searchOut, setSearchOut] = useState('')
  const [searching, setSearching] = useState(false)

  async function refresh() {
    setLoadingHealth(true)
    try {
      const data = await api<Health>('/api/health')
      setHealth(data)
      setHealthError('')
    } catch (e) {
      setHealthError(e instanceof Error ? e.message : 'API در دسترس نیست')
    } finally {
      setLoadingHealth(false)
    }
  }

  useEffect(() => {
    void refresh()
  }, [])

  const meta = useMemo(() => {
    const status = health?.status || ''
    return {
      root: statusLine(status, 'panel_root'),
      llm: health?.llm || '—',
      da: statusLine(status, 'da_password_present'),
      dashboard: statusLine(status, 'has_dashboard'),
    }
  }, [health])

  async function send(text: string) {
    const trimmed = text.trim()
    if (!trimmed || busy) return
    setDraft('')
    setMessages((m) => [...m, { role: 'user', text: trimmed }])
    setBusy(true)
    try {
      const data = await api<{ text: string; events: ToolEvent[] }>('/api/chat', {
        method: 'POST',
        body: JSON.stringify({ message: trimmed }),
      })
      setMessages((m) => [...m, { role: 'assistant', text: data.text, events: data.events }])
    } catch (e) {
      const err = e as Error & { payload?: { help?: string } }
      const help = err.payload?.help
      setMessages((m) => [
        ...m,
        { role: 'error', text: help ? `${err.message}\n\n${help}` : err.message },
      ])
    } finally {
      setBusy(false)
    }
  }

  async function runSearch() {
    setSearching(true)
    setSearchOut('')
    try {
      const data = await api<{ result: string }>('/api/tool', {
        method: 'POST',
        body: JSON.stringify({ name: 'search_code', args: { pattern: search, max_results: 20 } }),
      })
      setSearchOut(data.result)
    } catch (e) {
      setSearchOut(e instanceof Error ? e.message : 'خطا')
    } finally {
      setSearching(false)
    }
  }

  return (
    <div className="min-h-svh bg-moss-950 text-mist">
      <div className="mx-auto grid min-h-svh max-w-6xl lg:grid-cols-[minmax(0,1fr)_320px]">
        <main className="flex min-h-svh flex-col border-moss-800 lg:border-l">
          <header className="flex flex-wrap items-center justify-between gap-3 border-b border-moss-800 px-4 py-4 sm:px-6">
            <div>
              <p className="text-[11px] tracking-[0.2em] text-gold-400">GHAZAL PANEL AGENT</p>
              <h1 className="text-xl font-semibold text-white">ایجنت پنل غزال</h1>
              <p className="mt-1 max-w-xl text-sm text-mist/70">
                تسک را به فارسی بگو. ایجنت در ریپوی پنل می‌گردد، ادیت می‌کند، با DirectAdmin روی سرور
                می‌گذارد. MCP نیست؛ خود ایجنت است.
              </p>
            </div>
            <Button variant="outline" size="sm" onClick={() => void refresh()}>
              تازه‌سازی وضعیت
            </Button>
          </header>

          <div className="flex flex-wrap gap-2 border-b border-moss-800 px-4 py-3 sm:px-6">
            <Badge>مدل: {meta.llm}</Badge>
            <Badge>رمز DA: {meta.da === 'True' ? 'حاضر' : 'نیست'}</Badge>
            <Badge>dashboard: {meta.dashboard || '—'}</Badge>
          </div>
          {meta.root ? (
            <p className="border-b border-moss-800 px-4 py-2 font-mono text-[11px] text-mist/50 sm:px-6" dir="ltr">
              PANEL_ROOT {meta.root}
            </p>
          ) : null}

          {health?.needs_key ? (
            <div className="mx-4 mt-4 rounded-2xl border border-gold-400/40 bg-moss-900 px-4 py-3 text-sm sm:mx-6">
              <p className="font-medium text-gold-300">مغز مدل هنوز وصل نیست</p>
              <p className="mt-1 text-mist/80">
                برای چت همین‌جا <span className="font-mono" dir="ltr">GEMINI_API_KEY</span> را در{' '}
                <span className="font-mono" dir="ltr">.env</span> بگذار. برای جمینای ۴ بهتر است این ریپو را
                به‌صورت MCP به Cursor یا Gemini CLI وصل کنی تا خود مدل ابزارها را صدا بزند.
              </p>
            </div>
          ) : null}

          {healthError ? (
            <div className="mx-4 mt-4 rounded-2xl border border-red-500/40 bg-red-950/40 px-4 py-3 text-sm sm:mx-6">
              API ایجنت بالا نیست. در ریشهٔ ریپو بزن:{' '}
              <span className="font-mono" dir="ltr">
                python3 agent.py serve --port 4547
              </span>
              <div className="mt-1 text-mist/70">{healthError}</div>
            </div>
          ) : null}

          <section className="flex-1 space-y-4 overflow-y-auto px-4 py-5 sm:px-6">
            {loadingHealth && !health ? (
              <p className="text-sm text-mist/60">در حال خواندن وضعیت ایجنت…</p>
            ) : null}

            {messages.length === 0 && !busy ? (
              <div className="rounded-2xl border border-dashed border-moss-700 bg-moss-900/40 p-5">
                <p className="text-sm text-mist/80">هنوز تسکی ندادی. مثل ایجنت کرسر، یک کار واقعی بگو:</p>
                <div className="mt-3 flex flex-col gap-2">
                  {EXAMPLES.map((ex) => (
                    <button
                      key={ex}
                      type="button"
                      className="rounded-xl border border-moss-700 bg-moss-950 px-3 py-2 text-right text-sm hover:border-gold-400/60"
                      onClick={() => void send(ex)}
                    >
                      {ex}
                    </button>
                  ))}
                </div>
              </div>
            ) : null}

            {messages.map((msg, i) => (
              <article
                key={i}
                className={
                  msg.role === 'user'
                    ? 'mr-0 ml-8 rounded-2xl bg-moss-800 px-4 py-3'
                    : msg.role === 'error'
                      ? 'rounded-2xl border border-red-500/40 bg-red-950/30 px-4 py-3'
                      : 'rounded-2xl border border-moss-700 bg-moss-900 px-4 py-3'
                }
              >
                <p className="text-[11px] text-gold-400">
                  {msg.role === 'user' ? 'تو' : msg.role === 'error' ? 'خطا' : 'ایجنت'}
                </p>
                <pre className="mt-2 whitespace-pre-wrap font-sans text-sm leading-7">{msg.text}</pre>
                {msg.events && msg.events.length > 0 ? (
                  <ol className="mt-3 space-y-2">
                    {msg.events.map((ev, j) => (
                      <li key={j} className="rounded-lg bg-moss-950 px-3 py-2 font-mono text-[11px]" dir="ltr">
                        <div className="text-gold-300">
                          {ev.name} {ev.ok === false ? '✗' : ev.type === 'tool_end' ? '✓' : '…'}
                        </div>
                        {ev.result ? (
                          <div className="mt-1 max-h-32 overflow-auto whitespace-pre-wrap text-mist/70">
                            {ev.result.slice(0, 800)}
                          </div>
                        ) : null}
                      </li>
                    ))}
                  </ol>
                ) : null}
              </article>
            ))}

            {busy ? (
              <p className="text-sm text-gold-300">در حال کار ایجنتیک: جستجو / ادیت / دیپلوی…</p>
            ) : null}
          </section>

          <form
            className="border-t border-moss-800 p-4 sm:p-6"
            onSubmit={(e) => {
              e.preventDefault()
              void send(draft)
            }}
          >
            <Textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              placeholder="مثلاً: معلم ریمارک سوپروایزر را در Term Plan ببیند"
              disabled={busy}
            />
            <div className="mt-3 flex items-center justify-between gap-3">
              <p className="text-[11px] text-mist/50">Enter برای ارسال نیست؛ دکمه را بزن تا تسک ناقص نرود.</p>
              <Button type="submit" disabled={busy || !draft.trim()}>
                اجرای ایجنت
              </Button>
            </div>
          </form>
        </main>

        <aside className="border-t border-moss-800 bg-moss-900/50 p-4 lg:border-t-0 lg:border-r">
          <h2 className="text-sm font-semibold text-white">ابزار بدون مدل</h2>
          <p className="mt-1 text-xs leading-6 text-mist/70">
            اگر کلید جمینای نداری، همین‌جا در کد پنل جستجو کن. دیپلوی زنده هنوز از چت / MCP می‌آید.
          </p>
          <div className="mt-3 flex gap-2">
            <Input
              dir="ltr"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="regex"
            />
            <Button variant="outline" onClick={() => void runSearch()} disabled={searching || !search.trim()}>
              {searching ? '…' : 'جستجو'}
            </Button>
          </div>
          <pre className="mt-3 max-h-72 overflow-auto whitespace-pre-wrap rounded-xl bg-moss-950 p-3 font-mono text-[11px] text-mist/80" dir="ltr">
            {searchOut || (health?.tools || []).join('\n') || 'ابزاری گزارش نشده'}
          </pre>
          <div className="mt-6 space-y-2 text-xs leading-6 text-mist/70">
            <p className="font-medium text-mist">وصل کردن جمینای ۴</p>
            <p dir="ltr" className="font-mono text-[11px] text-gold-300/90">
              python3 agent.py mcp
            </p>
            <p>
              این دستور را به‌عنوان MCP به Gemini CLI یا Cursor بده. مدل، ابزارهای همین ایجنت را صدا می‌زند؛
              لازم نیست README را حفظ کند.
            </p>
          </div>
        </aside>
      </div>
    </div>
  )
}
