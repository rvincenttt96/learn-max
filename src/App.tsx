/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */
import React, { useState, useEffect, useRef } from 'react';
import { 
  LayoutDashboard, 
  Users, 
  Wallet, 
  Receipt, 
  Plus, 
  Search, 
  Clock, 
  Bell, 
  Settings,
  ChevronRight,
  TrendingDown,
  TrendingUp,
  DollarSign,
  Loader2,
  Trash2,
  BookOpen,
  ChevronDown,
  PlusCircle,
  Phone,
  Menu,
  FileSpreadsheet,
  Download,
  CheckCircle2,
  Coins,
  X,
  Pencil,
  LogOut,
  Printer,
  Eye,
  EyeOff
} from 'lucide-react';
import { motion, AnimatePresence } from 'motion/react';
import * as XLSX from 'xlsx';
import { Term, Student, TeacherSalary, Expense, Level } from './types';
import { fetchJSON } from './api';

type Section = 'overview' | 'registration' | 'payroll' | 'expenses';

interface User {
  id: string;
  username: string;
  role: 'manager' | 'reception';
}

function deriveStudentStatus(totalPayable: number, amountPaid: number) {
  const total = Number(totalPayable) || 0;
  const paid = Number(amountPaid) || 0;
  if (total <= 0 && paid <= 0) return 'pending';
  return total - paid <= 0 ? 'paid' : 'unpaid';
}

function studentPaymentBadge(status: string) {
  if (status === 'paid') {
    return { label: 'Settled', className: 'bg-emerald-950/60 text-emerald-400 border-emerald-500/40' };
  }
  if (status === 'pending') {
    return { label: 'Draft', className: 'bg-slate-800/70 text-slate-300 border-slate-500/40' };
  }
  return { label: 'Unpaid', className: 'bg-rose-950/60 text-rose-400 border-rose-500/40' };
}

const ACC_SECTION_KEY = 'gz-acc-section';
const ACC_TERM_KEY = 'gz-acc-term';

function readPersistedSection(role?: string | null): Section {
  try {
    const raw = sessionStorage.getItem(ACC_SECTION_KEY) || localStorage.getItem(ACC_SECTION_KEY) || '';
    const allowed: Section[] = role === 'reception'
      ? ['registration']
      : ['overview', 'registration', 'payroll', 'expenses'];
    if (allowed.includes(raw as Section)) return raw as Section;
  } catch {
    /* ignore */
  }
  return role === 'reception' ? 'registration' : 'overview';
}

function persistSection(id: Section) {
  try {
    sessionStorage.setItem(ACC_SECTION_KEY, id);
    localStorage.setItem(ACC_SECTION_KEY, id);
  } catch {
    /* ignore */
  }
}

function readPersistedTerm(): string | null {
  try {
    return sessionStorage.getItem(ACC_TERM_KEY) || localStorage.getItem(ACC_TERM_KEY);
  } catch {
    return null;
  }
}

function persistTermId(id: string | null | undefined) {
  if (!id) return;
  try {
    sessionStorage.setItem(ACC_TERM_KEY, String(id));
    localStorage.setItem(ACC_TERM_KEY, String(id));
  } catch {
    /* ignore */
  }
}

function pickDefaultTermId(terms: any[] | undefined | null): string | null {
  if (!terms || !terms.length) return null;
  const teaching = terms.find((t) => t.isTeaching);
  if (teaching) return String(teaching.id);
  const registration = terms.find((t) => t.isRegistration);
  if (registration) return String(registration.id);
  return String(terms[0].id);
}

function termPinned(): boolean {
  try {
    return sessionStorage.getItem('gz-acc-term-pinned') === '1';
  } catch {
    return false;
  }
}

function pinTermChoice() {
  try {
    sessionStorage.setItem('gz-acc-term-pinned', '1');
  } catch {
    /* ignore */
  }
}

function usePersistedTerm(terms: any[] | undefined | null, allowAll = false) {
  const [selectedTermId, setSelectedTermId] = useState<string | null>(() => readPersistedTerm());

  useEffect(() => {
    const list = terms || [];
    const saved = readPersistedTerm();
    setSelectedTermId((current) => {
      if (allowAll && current === 'all') return current;
      const pinned = termPinned();
      const savedRow = saved && saved !== 'all'
        ? list.find((t: any) => String(t.id) === String(saved))
        : null;
      const currentRow = current && current !== 'all'
        ? list.find((t: any) => String(t.id) === String(current))
        : null;
      const teaching = list.find((t: any) => t.isTeaching);
      const stale = (row: any) => row && String(row.status || '') === 'completed' && teaching;
      if (!pinned && stale(currentRow) && teaching) {
        persistTermId(String(teaching.id));
        return String(teaching.id);
      }
      if (current && current !== 'all' && list.some((t: any) => String(t.id) === String(current))) {
        persistTermId(current);
        return current;
      }
      if (allowAll && saved === 'all') return 'all';
      if (!pinned && stale(savedRow) && teaching) {
        persistTermId(String(teaching.id));
        return String(teaching.id);
      }
      if (saved && saved !== 'all' && list.some((t: any) => String(t.id) === String(saved))) {
        return saved;
      }
      const next = pickDefaultTermId(list);
      if (next) persistTermId(next);
      return next;
    });
  }, [terms, allowAll]);

  const setTerm = (id: string) => {
    pinTermChoice();
    persistTermId(id);
    setSelectedTermId(id);
  };

  return [selectedTermId, setTerm] as const;
}

function decorateTerms(list: any[] | undefined | null): any[] {
  if (!Array.isArray(list) || !list.length) return [];
  const terms = list.map((t) => ({ ...t, id: String(t?._id || t?.id || '') }));
  let teach = String(terms.find((t) => t.isTeaching)?.id || '');
  let reg = String(terms.find((t) => t.isRegistration)?.id || '');
  terms.forEach((t) => {
    const id = String(t.id);
    t.isTeaching = teach !== '' && id === teach;
    t.isRegistration = reg !== '' && id === reg && !t.isTeaching;
  });
  terms.sort((a, b) => {
    const rank = (t: any) => (t.isTeaching ? 0 : t.isRegistration ? 1 : 2);
    const d = rank(a) - rank(b);
    return d !== 0 ? d : Number(b.createdAt || 0) - Number(a.createdAt || 0);
  });
  return terms;
}

function termSelectLabel(term: any) {
  if (!term) return '';
  if (term.isTeaching) return `${term.name} — در حال برگزاری`;
  if (term.isRegistration) return `${term.name} — در حال ثبت نام`;
  return term.name || '';
}

function TermBadge({ term }: { term: any }) {
  if (!term) return null;
  if (term.isTeaching) {
    return (
      <span className="text-[9px] px-2 py-0.5 rounded-full bg-emerald-950/80 text-emerald-300 border border-emerald-500/30 whitespace-nowrap">
        در حال برگزاری
      </span>
    );
  }
  if (term.isRegistration) {
    return (
      <span className="text-[9px] px-2 py-0.5 rounded-full bg-sky-950/80 text-sky-300 border border-sky-500/30 whitespace-nowrap">
        در حال ثبت نام
      </span>
    );
  }
  return null;
}

function TermPicker({
  terms,
  value,
  onChange,
  allowAll = false,
  allLabel = 'همه ترم‌ها',
  className = '',
}: {
  terms: any[];
  value: string | null;
  onChange: (id: string) => void;
  allowAll?: boolean;
  allLabel?: string;
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const boxRef = useRef<HTMLDivElement | null>(null);
  const selected = value && value !== 'all' ? terms.find((t) => String(t.id) === String(value)) : null;
  const buttonLabel = !value || value === 'all' ? allLabel : (selected?.name || 'Select term');

  useEffect(() => {
    if (!open) return;
    const onDoc = (e: Event) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('pointerdown', onDoc, true);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('pointerdown', onDoc, true);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  return (
    <div className={`relative ${className}`} ref={boxRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="w-full bg-[#12121C] border border-red-900/40 text-white px-5 py-2.5 rounded-full font-medium flex items-center justify-between gap-2 hover:border-red-500/50 transition-all text-sm min-w-[220px] cursor-pointer"
      >
        <span className="flex items-center gap-2 min-w-0">
          <ChevronDown size={16} className={`shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
          <span className="truncate">{buttonLabel}</span>
        </span>
        <TermBadge term={selected} />
      </button>
      {open && (
        <div className="absolute top-full right-0 mt-2 w-full min-w-[260px] sm:w-80 bg-[#0F0F18]/95 backdrop-blur-2xl border border-red-500/30 rounded-2xl shadow-[0_0_30px_rgba(220,38,38,0.3)] z-50 overflow-hidden">
          <div className="max-h-72 overflow-y-auto py-2 custom-scrollbar">
            {allowAll && (
              <button
                type="button"
                onClick={() => { onChange('all'); setOpen(false); }}
                className={`w-full text-right px-4 py-2.5 text-sm flex items-center justify-between gap-2 ${!value || value === 'all' ? 'text-white font-bold bg-red-950/40' : 'text-slate-400 hover:bg-red-950/40'}`}
              >
                <span>{allLabel}</span>
              </button>
            )}
            {terms.map((term) => (
              <button
                type="button"
                key={term.id}
                onClick={() => { onChange(String(term.id)); setOpen(false); }}
                className={`w-full text-right px-4 py-2.5 text-sm flex items-center justify-between gap-2 hover:bg-red-950/40 ${String(value) === String(term.id) ? 'text-white font-bold bg-red-950/30' : 'text-slate-300'}`}
              >
                <span className="flex items-center gap-2 min-w-0">
                  <span className="truncate">{term.name}</span>
                  <TermBadge term={term} />
                </span>
                {String(value) === String(term.id) && <div className="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] shrink-0" />}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

function TehranClock() {
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(id);
  }, []);
  const weekday = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { timeZone: 'Asia/Tehran', weekday: 'long' }).format(now);
  const date = new Intl.DateTimeFormat('fa-IR-u-nu-latn', { timeZone: 'Asia/Tehran', year: 'numeric', month: 'long', day: 'numeric' }).format(now);
  const time = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Tehran', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(now);
  return (
    <div className="flex items-center gap-2 text-right" dir="rtl" data-gz-clock="1">
      <Clock size={14} className="text-red-400/80 hidden sm:block shrink-0" />
      <div className="leading-tight">
        <div className="text-[10px] lg:text-xs text-slate-300">{weekday}، {date}</div>
        <div className="text-xs lg:text-sm font-mono text-white tracking-wider" dir="ltr">{time}</div>
      </div>
    </div>
  );
}

function readStoredUser(): User | null {
  try {
    const raw = sessionStorage.getItem('ghazal-user');
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed.username === 'string' && parsed.username) {
      return parsed as User;
    }
  } catch {
    /* ignore */
  }
  return null;
}

function showHtmlLoginShell() {
  document.documentElement.classList.remove('gz-app-ready', 'gz-auth-handoff');
  document.documentElement.classList.add('gz-login-pending');
  const shell = document.getElementById('gz-login');
  if (shell) shell.hidden = false;
}

function hideHtmlLoginShell() {
  document.documentElement.classList.remove('gz-login-pending', 'gz-auth-handoff');
  document.documentElement.classList.add('gz-app-ready');
  const shell = document.getElementById('gz-login');
  if (shell) shell.hidden = true;
}

export default function App() {
  const [user, setUser] = useState<User | null>(() => readStoredUser());
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loginError, setLoginError] = useState('');
  const [isLoggingIn, setIsLoggingIn] = useState(false);

  const [activeSection, setActiveSection] = useState<Section>(() => (
    readPersistedSection(readStoredUser()?.role)
  ));
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [terms, setTerms] = useState<Term[]>([]);
  const [students, setStudents] = useState<Student[]>([]);
  const [salaries, setSalaries] = useState<TeacherSalary[]>([]);
  const [expenses, setExpenses] = useState<Expense[]>([]);
  const [levels, setLevels] = useState<Level[]>([]);
  const [panelClasses, setPanelClasses] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [showMobileSearch, setShowMobileSearch] = useState(false);

  useEffect(() => {
    (window as any).__ghazalAccTookAuth = true;

    const applyUser = (next: User) => {
      sessionStorage.setItem('ghazal-user', JSON.stringify(next));
      setUser(next);
      const nextSection = readPersistedSection(next.role);
      persistSection(nextSection);
      setActiveSection(nextSection);
      hideHtmlLoginShell();
    };

    const onHtmlLogin = (event: Event) => {
      const next = (event as CustomEvent).detail;
      if (next && next.username) applyUser(next);
    };
    window.addEventListener('ghazal-acc-login', onHtmlLogin);

    const stored = readStoredUser();
    fetchJSON('/api/me')
      .then((data) => {
        if (data?.user) {
          applyUser(data.user);
          return;
        }
        sessionStorage.removeItem('ghazal-user');
        setUser(null);
        if (!stored) return;
        showHtmlLoginShell();
      })
      .catch(() => {
        if (!readStoredUser()) setUser(null);
      });

    return () => window.removeEventListener('ghazal-acc-login', onHtmlLogin);
  }, []);

  useEffect(() => {
    if (!user) return;

    const fetchData = async () => {
      try {
        setIsLoading(true);
        const [t, s, sal, exp, l, pc] = await Promise.all([
          fetchJSON('/api/terms'),
          fetchJSON('/api/students'),
          fetchJSON('/api/salaries'),
          fetchJSON('/api/expenses'),
          fetchJSON('/api/levels'),
          fetchJSON('/api/panel_classes').catch(() => [])
        ]);
        
        const mapId = (arr: any[]) => arr.map(i => ({ ...i, id: i._id || i.id }));

        setTerms(decorateTerms(mapId(t)));
        setStudents(mapId(s));
        setSalaries(mapId(sal));
        setExpenses(mapId(exp));
        setLevels(mapId(l));
        setPanelClasses(Array.isArray(pc) ? pc : []);
      } catch (err) {
        console.error("Fetch error details:", err);
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, [user]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoggingIn(true);
    setLoginError('');
    try {
      const data = await fetchJSON('/api/login', {
        method: 'POST',
        body: JSON.stringify({ username, password })
      });
      if (data.success && data.user) {
        sessionStorage.setItem('ghazal-user', JSON.stringify(data.user));
        setUser(data.user);
        const nextSection = readPersistedSection(data.user.role);
        persistSection(nextSection);
        setActiveSection(nextSection);
        hideHtmlLoginShell();
      } else {
        setLoginError(data.message || 'نام کاربری یا رمز عبور اشتباه است');
      }
    } catch (err: any) {
      setLoginError(err?.message || 'خطا در برقراری ارتباط با سرور');
    } finally {
      setIsLoggingIn(false);
    }
  };

  const handleLogout = () => {
    fetchJSON('/api/logout', { method: 'POST' }).catch(() => {});
    sessionStorage.removeItem('ghazal-user');
    setUser(null);
    setUsername('');
    setPassword('');
    setTerms([]);
    setStudents([]);
    setSalaries([]);
    setExpenses([]);
    setLevels([]);
    setPanelClasses([]);
    showHtmlLoginShell();
  };

  const handleAddTerm = async (name: string) => {
    const data = await fetchJSON('/api/terms', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, status: 'active', createdAt: Date.now() })
    });
    const newTerm = { ...data, id: data._id || data.id };
    setTerms(decorateTerms([newTerm, ...terms]));
  };

  const handleUpdateTerm = async (id: string, name: string) => {
    try {
      await fetchJSON(`/api/terms/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name })
      });
      setTerms(decorateTerms(terms.map((t: Term) => t.id === id ? { ...t, name } : t)));
    } catch (err) { console.error(err); }
  };

  const handleDeleteTerm = async (id: string) => {
    if (!window.confirm('آیا از حذف این ترم اطمینان دارید؟')) return;
    try {
      await fetchJSON(`/api/terms/${id}`, { method: 'DELETE' });
      setTerms(terms.filter((t: Term) => t.id !== id));
    } catch (err: any) {
      alert(err?.message || 'خطا در حذف ترم');
    }
  };

  const handleAddStudent = async (studentData: Omit<Student, 'id' | 'debt' | 'status'>) => {
    const debt = studentData.totalPayable - studentData.amountPaid;
    const body = { ...studentData, debt, status: deriveStudentStatus(studentData.totalPayable, studentData.amountPaid) };
    
    try {
      // ۱. ثبت دانشجو
      const data = await fetchJSON('/api/students', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const newStudent = { ...data, id: data._id || data.id };
      setStudents(prev => [newStudent, ...prev]);

      // ۲. صدور خودکار رسید در صورت وجود پرداختی اولیه
      if (Number(studentData.amountPaid) > 0) {
        await fetchJSON('/api/receipts', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            studentId: newStudent.id,
            termId: newStudent.termId,
            paidAmount: Number(studentData.amountPaid),
            date: new Date().toLocaleDateString('fa-IR-u-nu-latn')
          })
        });
      }
    } catch (err) {
      console.error("خطا در ثبت اطلاعات:", err);
      throw err;
    }
  };

  const handleUpdateStudent = async (id: string, amountPaid: number, receiptUrl?: string, extraData?: any) => {
    const data = await fetchJSON(`/api/students/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ amountPaid, receiptUrl, ...extraData })
    });
    if (data.success) {
      setStudents(students.map((s: Student) => s.id === id ? { 
        ...s, 
        amountPaid, 
        totalPayable: extraData?.totalPayable !== undefined ? extraData.totalPayable : s.totalPayable,
        hasBook: extraData?.hasBook !== undefined ? extraData.hasBook : s.hasBook,
        bookName: extraData?.bookName !== undefined ? extraData.bookName : s.bookName,
        bookPrice: extraData?.bookPrice !== undefined ? extraData.bookPrice : s.bookPrice,
        hasInterview: extraData?.hasInterview !== undefined ? extraData.hasInterview : s.hasInterview,
        hasDiscount: extraData?.hasDiscount !== undefined ? extraData.hasDiscount : s.hasDiscount,
        discountPercent: extraData?.discountPercent !== undefined ? extraData.discountPercent : s.discountPercent,
        discountAmount: extraData?.discountAmount !== undefined ? extraData.discountAmount : s.discountAmount,
        hasExtraCourse: extraData?.hasExtraCourse !== undefined ? extraData.hasExtraCourse : (s as any).hasExtraCourse,
        extraCourseName: extraData?.extraCourseName !== undefined ? extraData.extraCourseName : (s as any).extraCourseName,
        extraCoursePrice: extraData?.extraCoursePrice !== undefined ? extraData.extraCoursePrice : (s as any).extraCoursePrice,
        debt: data.debt, 
        status: data.status, 
        studentCode: extraData?.studentCode !== undefined ? extraData.studentCode : (data.studentCode !== undefined ? data.studentCode : s.studentCode),
        receiptUrl: receiptUrl || s.receiptUrl 
      } : s));
    }
  };

  const handleDeleteStudent = async (id: string) => {
    if (!window.confirm('این دانشجو از حسابداری و از پنل حذف می‌شود. ادامه می‌دهید؟')) return;
    try {
      await fetchJSON(`/api/students/${id}`, { method: 'DELETE' });
      setStudents((prev) => prev.filter((s: Student) => String(s.id) !== String(id)));
    } catch (err: any) {
      console.error(err);
      alert(err?.message || 'Delete student انجام نشد.');
    }
  };

  const handleBatchStudent = async (studentsList: any[]) => {
    try {
      setIsLoading(true);
      const data = await fetchJSON('/api/students/batch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(studentsList)
      });
      if (data.success) {
        const s = await fetchJSON('/api/students');
        setStudents(s.map((i: any) => ({ ...i, id: i._id || i.id })));
        alert(`${studentsList.length} دانشجو با موفقیت به سیستم اضافه شدند.`);
      }
    } catch (err: any) {
      console.error(err);
      alert('خطا در ذخیره‌سازی اطلاعات در سیستم. لطفا اتصال اینترنت و فرمت فایل را بررسی کنید.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleDeleteSalary = async (id: string) => {
    if (!window.confirm('آیا از حذف این فیش حقوقی اطمینان دارید؟')) return;
    try {
      await fetchJSON(`/api/salaries/${id}`, { method: 'DELETE' });
      setSalaries(salaries.filter((s: TeacherSalary) => s.id !== id));
    } catch (err) { console.error(err); }
  };

  const handleUpdateSalary = async (id: string, updatedData: any) => {
    try {
      await fetchJSON(`/api/salaries/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updatedData)
      });
      setSalaries(salaries.map((s: TeacherSalary) => s.id === id ? { ...s, ...updatedData } : s));
    } catch (err) { console.error(err); }
  };

  const handleAddSalary = async (s: any) => {
    try {
      const data = await fetchJSON('/api/salaries', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(s)
      });
      const newSalary = { ...data, id: data._id || data.id };
      setSalaries([newSalary, ...salaries]);
    } catch (err) { console.error(err); }
  };

  const handleDeleteExpense = async (id: string) => {
    if (!window.confirm('آیا از حذف این هزینه اطمینان دارید؟')) return;
    try {
      await fetchJSON(`/api/expenses/${id}`, { method: 'DELETE' });
      setExpenses(expenses.filter((e: Expense) => e.id !== id));
    } catch (err) { console.error(err); }
  };

  const handleUpdateExpense = async (id: string, updatedData: any) => {
    try {
      await fetchJSON(`/api/expenses/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updatedData)
      });
      setExpenses(expenses.map((e: Expense) => e.id === id ? { ...e, ...updatedData } : e));
    } catch (err) { console.error(err); }
  };

  const handleAddExpense = async (e: any) => {
    try {
      const data = await fetchJSON('/api/expenses', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(e)
      });
      const newExp = { ...data, id: data._id || data.id };
      setExpenses([newExp, ...expenses]);
    } catch (err) { console.error(err); }
  };

  const handleAddLevel = async (level: Omit<Level, 'id'>) => {
    try {
      const data = await fetchJSON('/api/levels', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(level)
      });
      const newLevel = { ...data, id: data._id || data.id };
      setLevels(prev => [...prev, newLevel]);
    } catch (err) { 
      console.error("Error in handleAddLevel:", err);
    }
  };

  const handleDeleteLevel = async (id: string) => {
    try {
      await fetchJSON(`/api/levels/${id}`, { method: 'DELETE' });
      setLevels(levels.filter(l => (l as any)._id !== id && l.id !== id));
    } catch (err) { console.error(err); }
  };

  const handleBatchLevels = async (levelsList: any[]) => {
    try {
      setIsLoading(true);
      const data = await fetchJSON('/api/levels/batch', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(levelsList)
      });
      if (data.success) {
        const l = await fetchJSON('/api/levels');
        setLevels(l.map((i: any) => ({ ...i, id: i._id || i.id })));
        alert(`${levelsList.length} سطح آموزشی با موفقیت به سیستم اضافه شدند.`);
      }
    } catch (err: any) {
      console.error(err);
      alert('خطا در ذخیره‌سازی سطوح آموزشی. لطفا فرمت فایل را بررسی کنید.');
    } finally {
      setIsLoading(false);
    }
  };

  const navItems = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard, roles: ['manager'] },
    { id: 'registration', label: 'Registration', icon: Users, roles: ['manager', 'reception'] },
    { id: 'payroll', label: 'Payroll', icon: Wallet, roles: ['manager'] },
    { id: 'expenses', label: 'Expenses', icon: Receipt, roles: ['manager'] },
  ];

  const allowedNavItems = navItems.filter(item => user && item.roles.includes(user.role));

  if (!user) {
    if (readStoredUser()) {
      return (
        <div className="min-h-screen bg-[#08080C] flex flex-col items-center justify-center gap-4">
          <div className="relative">
            <div className="w-16 h-16 rounded-full border-2 border-red-500/20 border-t-red-500 animate-spin" />
            <Loader2 className="text-red-500 animate-spin absolute inset-0 m-auto" size={24} />
          </div>
          <span className="text-xs text-red-400 tracking-wider">در حال ورود به پنل...</span>
        </div>
      );
    }
    return (
      <div className="relative min-h-[100dvh] w-full overflow-hidden bg-[#07060a] text-slate-100" dir="rtl">
        <div className="pointer-events-none absolute -top-24 -right-16 h-[28rem] w-[28rem] rounded-full bg-rose-600/25 blur-[140px]" />
        <div className="pointer-events-none absolute -bottom-28 -left-20 h-[32rem] w-[32rem] rounded-full bg-red-800/20 blur-[150px]" />
        <div className="pointer-events-none absolute top-1/3 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-fuchsia-700/10 blur-[100px]" />
        <div className="pointer-events-none absolute inset-x-0 bottom-[-18%] h-[46%] origin-bottom scale-x-150 bg-[linear-gradient(rgba(244,63,94,0.16)_1px,transparent_1px),linear-gradient(90deg,rgba(244,63,94,0.16)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:linear-gradient(to_top,rgba(0,0,0,0.45),transparent)] [transform:perspective(700px)_rotateX(62deg)]" />

        <div className="relative z-10 mx-auto grid min-h-[100dvh] w-full max-w-[1180px] items-center gap-8 overflow-x-clip px-5 py-8 sm:px-8 lg:grid-cols-[minmax(360px,440px)_minmax(0,1.1fr)] lg:[grid-template-areas:'card_brand'] lg:gap-16 lg:px-[6vw] lg:py-12">
          <section className="relative text-center lg:text-right lg:[grid-area:brand]">
            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-rose-500/30 bg-rose-950/40 px-3 py-1.5 text-[11px] tracking-[0.28em] text-rose-200" dir="ltr">
              <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-400 shadow-[0_0_12px_#fb7185]" />
              GHAZAL ACADEMY
            </div>
            <p className="pointer-events-none absolute -right-4 top-8 hidden select-none text-[11rem] font-black leading-none text-white/[0.04] lg:block">غ</p>
            <h1 className="bg-gradient-to-b from-white via-rose-50 to-rose-300 bg-clip-text text-[clamp(3rem,8vw,6.25rem)] font-black leading-[0.92] text-transparent drop-shadow-[0_0_28px_rgba(244,63,94,0.28)]">
              غزال
            </h1>
            <p className="mx-auto mt-5 max-w-md text-sm leading-8 text-rose-100/75 lg:mx-0 lg:text-[15px]">
              پنل حسابداری آموزشگاه
              <br />
              شهریه، ثبت‌نام و حقوق مدرسان در یک نگاه
            </p>
            <p className="mt-8 hidden text-xs text-rose-300/45 lg:block">«خاموشی زبان خداست، دیگر همه ترجمه ناقص است.»</p>
          </section>

          <motion.section
            initial={{ opacity: 0, y: 18 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45 }}
            className="relative w-full overflow-hidden rounded-[28px] lg:[grid-area:card]"
          >
            <div className="relative border border-rose-400/25 bg-[#0c0b12]/80 p-6 shadow-[0_24px_80px_rgba(0,0,0,0.55),0_0_70px_rgba(244,63,94,0.16)] backdrop-blur-2xl sm:p-8">
              <div className="mb-7 flex items-start gap-4">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-rose-500 to-red-800 text-white shadow-[0_0_24px_rgba(244,63,94,0.45)]">
                  <BookOpen size={22} />
                </div>
                <div className="min-w-0 text-right">
                  <h2 className="text-[1.65rem] font-semibold leading-tight text-white">ورود به حسابداری</h2>
                  <p className="mt-1 text-[13px] leading-6 text-slate-400">با حساب مدیریت یا پذیرش وارد شوید.</p>
                </div>
              </div>

              <form onSubmit={handleLogin} className="space-y-5">
                <div className="space-y-2">
                  <label htmlFor="acc-username" className="block text-[13px] font-medium text-rose-200/90">نام کاربری</label>
                  <input
                    id="acc-username"
                    type="text"
                    required
                    autoComplete="username"
                    value={username}
                    onChange={(e) => setUsername(e.target.value)}
                    placeholder="نام کاربری"
                    className="h-[52px] w-full rounded-2xl border border-white/10 bg-black/35 px-4 text-[16px] text-white outline-none transition placeholder:text-slate-500 focus:border-rose-400/70 focus:ring-2 focus:ring-rose-500/25"
                  />
                </div>

                <div className="space-y-2">
                  <label htmlFor="acc-password" className="block text-[13px] font-medium text-rose-200/90">رمز عبور</label>
                  <div className="relative">
                    <input
                      id="acc-password"
                      type={showPassword ? 'text' : 'password'}
                      required
                      autoComplete="current-password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="••••••••"
                      className="h-[52px] w-full rounded-2xl border border-white/10 bg-black/35 px-4 pl-12 text-[16px] text-white outline-none transition placeholder:text-slate-500 focus:border-rose-400/70 focus:ring-2 focus:ring-rose-500/25"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword((v) => !v)}
                      className="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-xl text-slate-400 transition hover:bg-white/5 hover:text-white"
                      aria-label={showPassword ? 'پنهان کردن رمز' : 'نمایش رمز'}
                    >
                      {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                    </button>
                  </div>
                </div>

                {loginError && (
                  <motion.div
                    initial={{ opacity: 0, y: 6 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="rounded-2xl border border-rose-500/40 bg-rose-950/60 px-4 py-3 text-center text-[13px] text-rose-200"
                    role="alert"
                  >
                    {loginError}
                  </motion.div>
                )}

                <button
                  type="submit"
                  disabled={isLoggingIn}
                  className="flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-l from-red-700 via-rose-600 to-red-500 text-[15px] font-semibold text-white shadow-[0_10px_30px_rgba(225,29,72,0.35)] transition hover:brightness-110 disabled:cursor-wait disabled:opacity-80"
                >
                  {isLoggingIn ? (
                    <>
                      <Loader2 className="animate-spin" size={18} />
                      در حال بررسی...
                    </>
                  ) : (
                    'ورود به پنل'
                  )}
                </button>
              </form>
            </div>
          </motion.section>
        </div>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className="min-h-screen bg-[#08080C] flex flex-col items-center justify-center gap-4">
        <div className="relative">
          <div className="w-16 h-16 rounded-full border-2 border-red-500/20 border-t-red-500 animate-spin" />
          <Loader2 className="text-red-500 animate-spin absolute inset-0 m-auto" size={24} />
        </div>
        <span className="text-xs text-red-400 tracking-wider">در حال بارگذاری داده‌های غزال...</span>
      </div>
    );
  }

  return (
    <div className="h-dvh bg-[#08080C] text-slate-100 flex overflow-hidden font-sans relative" dir="rtl">
      {/* Background Liquid Red Orbs */}
      <div className="fixed top-[-10%] right-[-5%] w-[600px] h-[600px] bg-red-600/10 rounded-full blur-[150px] pointer-events-none animate-float-slow" />
      <div className="fixed bottom-[-10%] left-[-5%] w-[600px] h-[600px] bg-rose-700/10 rounded-full blur-[150px] pointer-events-none animate-float-slow" />

      {/* Sidebar Overlay for Mobile */}
      <AnimatePresence>
        {isSidebarOpen && (
          <motion.div 
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={() => setIsSidebarOpen(false)}
            className="fixed inset-0 bg-black/70 backdrop-blur-md z-40 lg:hidden"
          />
        )}
      </AnimatePresence>

      {/* Sidebar - Liquid Glass Red & Obsidian */}
      <aside className={`fixed lg:static inset-y-0 right-0 w-72 bg-[#0B0B10]/90 backdrop-blur-2xl text-white border-l border-red-900/30 flex flex-col z-50 transition-transform duration-300 transform ${isSidebarOpen ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'}`}>
        <div className="p-6 flex items-center justify-between border-b border-red-900/20">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-red-600 to-rose-800 border border-red-400/30 flex items-center justify-center text-white shadow-[0_0_15px_rgba(220,38,38,0.4)]">
              <BookOpen size={20} />
            </div>
            <div>
              <h1 className="text-lg font-serif font-semibold tracking-wide text-white">آکادمی غزال</h1>
              <p className="text-[10px] text-red-400/80 text-right mt-0.5 font-sans">
                {user.role === 'manager' ? 'دسترسی: مدیر کل' : 'دسترسی: پذیرش'}
              </p>
            </div>
          </div>
          <button onClick={() => setIsSidebarOpen(false)} className="lg:hidden p-2 text-slate-400 hover:text-white">
            <X size={20} />
          </button>
        </div>

        <nav className="flex-1 px-4 py-6 space-y-2">
          {allowedNavItems.map((item) => {
            const Icon = item.icon;
            const isActive = activeSection === item.id;
            return (
              <button
                key={item.id}
                onClick={() => {
                  persistSection(item.id as Section);
                  setActiveSection(item.id as Section);
                  setIsSidebarOpen(false);
                }}
                className={`w-full flex items-center gap-4 px-5 py-3.5 rounded-2xl transition-all duration-200 cursor-pointer ${
                  isActive 
                    ? 'bg-gradient-to-r from-red-600 to-rose-700 text-white font-medium shadow-[0_0_20px_rgba(220,38,38,0.4)] border border-red-400/30' 
                    : 'text-slate-400 hover:text-red-300 hover:bg-red-950/20 border border-transparent'
                }`}
              >
                <Icon size={18} className={isActive ? 'text-white' : 'text-slate-400'} />
                <span className="text-sm text-right flex-1 font-medium">{item.label}</span>
                {isActive && <div className="w-2 h-2 rounded-full bg-white shadow-[0_0_8px_rgba(255,255,255,0.8)] animate-pulse" />}
              </button>
            );
          })}
        </nav>

        <div className="p-6 mt-auto space-y-4 border-t border-red-900/20">
          <button 
            onClick={handleLogout}
            className="w-full flex items-center justify-center gap-2 bg-red-950/40 hover:bg-red-900/60 text-red-300 border border-red-900/50 py-3 rounded-2xl transition-all text-xs font-medium cursor-pointer"
          >
            <LogOut size={14} />
            <span>Sign out</span>
          </button>
          
          <div className="p-2 text-center">
            <p className="text-[10px] text-slate-500 font-mono tracking-wider" dir="ltr">GHAZAL ACADEMY • LIQUID GLASS</p>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <div className="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden bg-[#08080C]/80">
        <header className="shrink-0 z-20 bg-[#0A0A0E]/80 backdrop-blur-2xl border-b border-red-900/20 px-4 lg:px-8 py-4 flex items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="relative w-40 lg:w-96 hidden sm:block">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-red-400/60" size={18} />
              <input 
                type="text" 
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="جستجو در اطلاعات..."
                className="w-full bg-[#12121C]/90 border border-red-900/30 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:border-red-500 transition-colors text-sm text-right text-white placeholder:text-slate-500"
                dir="rtl"
              />
            </div>
            <button 
              onClick={() => setShowMobileSearch(!showMobileSearch)} 
              className="sm:hidden p-2 hover:bg-red-950/30 rounded-full transition-colors text-slate-200 relative"
            >
              <Search size={20} />
            </button>
            {showMobileSearch && (
              <div className="absolute inset-x-4 top-1/2 -translate-y-1/2 bg-[#12121C] border border-red-500/40 rounded-full py-1.5 px-3 flex items-center sm:hidden z-25 shadow-2xl">
                <Search size={16} className="text-red-400 ml-2" />
                <input 
                  type="text" 
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="جستجو..."
                  className="w-full bg-transparent border-0 outline-none py-1 text-sm text-right text-white"
                  dir="rtl"
                  autoFocus
                />
              </div>
            )}
          </div>
          
          <div className="flex items-center gap-2 lg:gap-4">
            <TehranClock />
            <div className="text-right hidden xs:block">
              <p className="text-sm font-semibold truncate max-w-[80px] lg:max-w-none text-white">{user.username === 'admin' ? 'مدیریت غزال' : user.username}</p>
              <p className="text-[10px] lg:text-xs text-red-400/80">{user.role === 'manager' ? 'مدیر کل' : 'پذیرش آموزشگاه'}</p>
            </div>
            <button onClick={() => setIsSidebarOpen(true)} className="lg:hidden p-2.5 bg-red-950/60 border border-red-500/30 text-white rounded-xl">
              <Menu size={18} />
            </button>
          </div>
        </header>

        <main className="flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
        <div className="p-4 lg:p-8">
          <AnimatePresence mode="wait">
            <motion.div
              key={activeSection}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              transition={{ duration: 0.3 }}
            >
              <SectionRouter 
                section={activeSection} 
                data={{ terms, students, salaries, expenses, levels, panelClasses, searchQuery }}
                actions={{ 
                  handleAddTerm, 
                  handleUpdateTerm,
                  handleDeleteTerm,
                  handleAddStudent, 
                  handleUpdateStudent, 
                  handleDeleteStudent, 
                  handleBatchStudent, 
                  handleAddSalary,
                  handleUpdateSalary,
                  handleDeleteSalary,
                  handleAddExpense,
                  handleUpdateExpense,
                  handleDeleteExpense,
                  handleAddLevel, 
                  handleDeleteLevel,
                  handleBatchLevels
                }}
              />
            </motion.div>
          </AnimatePresence>
        </div>
        </main>
      </div>
    </div>
  );
}

function SectionRouter({ section, data, actions }: any) {
  switch (section) {
    case 'overview': return <OverviewSection data={data} />;
    case 'registration': return (
      <RegistrationSection 
        terms={data.terms} 
        students={data.students} 
        levels={data.levels}
        panelClasses={data.panelClasses || []}
        searchQuery={data.searchQuery}
        onAddTerm={actions.handleAddTerm} 
        onUpdateTerm={actions.handleUpdateTerm}
        onDeleteTerm={actions.handleDeleteTerm}
        onAddStudent={actions.handleAddStudent}
        onUpdateStudent={actions.handleUpdateStudent}
        onDeleteStudent={actions.handleDeleteStudent}
        onBatchStudent={actions.handleBatchStudent}
        onAddLevel={actions.handleAddLevel}
        onDeleteLevel={actions.handleDeleteLevel}
        onBatchLevels={actions.handleBatchLevels}
      />
    );
    case 'payroll': return (
      <PayrollSection 
        salaries={data.salaries} 
        terms={data.terms} 
        searchQuery={data.searchQuery}
        onAddSalary={actions.handleAddSalary}
        onUpdateSalary={actions.handleUpdateSalary}
        onDeleteSalary={actions.handleDeleteSalary}
      />
    );
    case 'expenses': return (
      <ExpensesSection 
        expenses={data.expenses} 
        terms={data.terms} 
        searchQuery={data.searchQuery}
        onAddExpense={actions.handleAddExpense}
        onUpdateExpense={actions.handleUpdateExpense}
        onDeleteExpense={actions.handleDeleteExpense}
      />
    );
    default: return null;
  }
}

function OverviewSection({ data }: { data: any }) {
  const [selectedTermId, setSelectedTermId] = usePersistedTerm(data.terms, true);

  const query = data.searchQuery ? data.searchQuery.trim().toLowerCase() : '';
  const filteredRawStudents = query
    ? data.students.filter((s: any) =>
        s.firstName?.toLowerCase().includes(query) ||
        s.lastName?.toLowerCase().includes(query) ||
        s.phone?.toLowerCase().includes(query) ||
        String(s.studentCode || '').toLowerCase().includes(query) ||
        s.level?.toLowerCase().includes(query)
      )
    : data.students;

  const filteredRawExpenses = query
    ? data.expenses.filter((e: any) =>
        e.title?.toLowerCase().includes(query) ||
        e.category?.toLowerCase().includes(query) ||
        e.date?.toLowerCase().includes(query)
      )
    : data.expenses;

  const filteredRawSalaries = query
    ? data.salaries.filter((s: any) =>
        s.teacherName?.toLowerCase().includes(query) ||
        s.role?.toLowerCase().includes(query) ||
        s.month?.toLowerCase().includes(query)
      )
    : data.salaries;

  const students = selectedTermId && selectedTermId !== 'all' ? filteredRawStudents.filter((s: any) => String(s.termId) === String(selectedTermId)) : filteredRawStudents;
  const expenses = selectedTermId && selectedTermId !== 'all' ? filteredRawExpenses.filter((e: any) => String(e.termId) === String(selectedTermId)) : filteredRawExpenses;
  const salaries = selectedTermId && selectedTermId !== 'all' ? filteredRawSalaries.filter((s: any) => String(s.termId) === String(selectedTermId)) : filteredRawSalaries;

  const totalExpected = Math.max(0, students.reduce((acc: number, s: any) => acc + (Number(s.totalPayable) || 0), 0));
  const totalReceived = Math.max(0, students.reduce((acc: number, s: any) => acc + (Number(s.amountPaid) || 0), 0));
  const totalExpenses = Math.max(0, expenses.reduce((acc: number, e: any) => acc + (Number(e.amount) || 0), 0));
  const totalPayroll = Math.max(0, salaries.reduce((acc: number, s: any) => acc + (Number(s.amount) || 0), 0));
  const totalDebt = Math.max(0, students.reduce((acc: number, s: any) => acc + (Number(s.debt) || 0), 0));
  const netIncome = totalExpected - totalExpenses - totalPayroll;

  const handleExportExcel = () => {
    const overviewData = [
      { 'عنوان': 'جمع دریافتی تا این لحظه', 'مبلغ (تومان)': totalReceived },
      { 'عنوان': 'جمع قابل دریافت', 'مبلغ (تومان)': totalExpected },
      { 'عنوان': 'درآمد کل', 'مبلغ (تومان)': netIncome },
      { 'عنوان': 'جمع مبالغ بدهی', 'مبلغ (تومان)': totalDebt },
      { 'عنوان': 'هزینه‌ها', 'مبلغ (تومان)': totalExpenses },
      { 'عنوان': 'حقوق پرداختنی', 'مبلغ (تومان)': totalPayroll },
    ];

    const salariesData = salaries.map((s: any) => ({
      'نام استاد': s.teacherName || '',
      'نقش': s.role || '',
      'مبلغ (تومان)': s.amount || 0,
      'بابت ماه': s.month || '',
      'وضعیت پرداخت': s.status === 'paid' ? 'پرداخت شده' : (s.status === 'pending' ? 'ثبت اولیه' : 'پرداخت نشده')
    }));

    const studentsData = students.map((s: any) => ({
      'شماره پرونده': s.studentCode || '',
      'شناسه': s.studentCode || '',
      'نام': s.firstName || '',
      'نام خانوادگی': s.lastName || '',
      'سطح/دوره': s.level || '',
      'شماره تماس': s.phone || '',
      'نوع کلاس': s.classType || '',
      'کل شهریه': s.totalPayable || 0,
      'مبلغ پرداختی': s.amountPaid || 0,
      'بدهی': s.debt || 0,
      'وضعیت تسویه': s.status === 'paid' ? 'تسویه شده' : (s.status === 'pending' ? 'ثبت اولیه' : 'بدهکار')
    }));

    const expensesData = expenses.map((e: any) => ({
      'عنوان': e.title || '',
      'دسته بندی': e.category || '',
      'مبلغ (تومان)': e.amount || 0,
      'تاریخ': e.date || ''
    }));

    const wb = XLSX.utils.book_new();
    const wsOverview = XLSX.utils.json_to_sheet(overviewData);
    const wsSalaries = XLSX.utils.json_to_sheet(salariesData);
    const wsStudents = XLSX.utils.json_to_sheet(studentsData);
    const wsExpenses = XLSX.utils.json_to_sheet(expensesData);

    XLSX.utils.book_append_sheet(wb, wsOverview, 'نمای کلی');
    XLSX.utils.book_append_sheet(wb, wsSalaries, 'حقوق دستمزد');
    XLSX.utils.book_append_sheet(wb, wsStudents, 'ثبت نام');
    XLSX.utils.book_append_sheet(wb, wsExpenses, 'هزینه ها');

    const termName = selectedTermId && selectedTermId !== 'all' 
      ? data.terms.find((t: any) => String(t.id) === String(selectedTermId))?.name || 'گزارش'
      : 'همه_ترم_ها';
      
    XLSX.writeFile(wb, `Ghazal_Report_${termName.replace(/\s+/g, '_')}.xlsx`);
  };

  const stats = [
    { label: 'جمع دریافتی تا این لحظه', value: totalReceived, icon: CheckCircle2, color: 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' },
    { label: 'جمع قابل دریافت', value: totalExpected, icon: TrendingUp, color: 'bg-sky-500/10 text-sky-400 border border-sky-500/30' },
    { label: 'درآمد کل', value: netIncome, icon: Coins, color: 'bg-purple-500/10 text-purple-400 border border-purple-500/30' },
    { label: 'جمع مبالغ بدهی', value: totalDebt, icon: DollarSign, color: 'bg-rose-500/10 text-rose-300 border border-rose-500/30' },
    { label: 'هزینه‌های جاری', value: totalExpenses, icon: TrendingDown, color: 'bg-red-500/10 text-red-400 border border-red-500/30' },
    { label: 'حقوق پرداختنی', value: totalPayroll, icon: Wallet, color: 'bg-amber-500/10 text-amber-400 border border-amber-500/30' },
  ];

  return (
    <div className="space-y-6 lg:space-y-10 p-4 lg:p-8" dir="rtl">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h2 className="text-2xl lg:text-3xl font-serif font-medium text-white">داشبورد مدیریتی</h2>
          <p className="text-slate-400 mt-1 text-sm lg:text-base">خلاصه وضعیت مالی و عملکرد آکادمی غزال</p>
        </div>
        <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full sm:w-auto">
          <button 
            onClick={handleExportExcel}
            className="flex justify-center items-center gap-2 bg-gradient-to-r from-red-950 to-red-900 text-red-200 hover:text-white hover:border-red-500 px-5 py-2.5 rounded-full transition-all border border-red-500/30 text-sm font-medium whitespace-nowrap cursor-pointer shadow-[0_0_15px_rgba(220,38,38,0.2)]"
          >
            <Download size={18} />
            <span>Excel export</span>
          </button>
          <TermPicker
            terms={data.terms || []}
            value={selectedTermId}
            onChange={setSelectedTermId}
            allowAll
          />
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 lg:gap-5">
        {stats.map((stat, i) => {
          const Icon = stat.icon;
          const formattedValue = Math.abs(stat.value).toLocaleString('en-US');
          const isNegative = stat.value < 0;
          return (
            <motion.div 
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: i * 0.08 }}
              key={i} 
              className="liquid-glass-interactive p-5 rounded-2xl border border-red-900/30 shadow-[0_8px_32px_rgba(0,0,0,0.5)] group flex flex-col justify-between"
            >
              <div>
                <div className="flex justify-between items-start mb-3">
                  <div className={`p-2.5 rounded-xl transition-transform group-hover:scale-105 ${stat.color}`}>
                    <Icon size={20} />
                  </div>
                </div>
                <p className="text-slate-400 text-xs sm:text-sm mb-2 font-medium truncate" title={stat.label}>{stat.label}</p>
              </div>
              <div>
                <h3 className="text-base sm:text-lg lg:text-xl font-bold tracking-tight text-white flex items-baseline gap-1.5 flex-wrap">
                  <span dir="ltr" className={`inline-block font-sans font-bold tracking-normal ${isNegative ? 'text-rose-400' : ''}`}>
                    {isNegative ? `-${formattedValue}` : formattedValue}
                  </span>
                  <span className="text-xs font-normal text-slate-400 font-sans">تومان</span>
                </h3>
              </div>
            </motion.div>
          );
        })}
      </div>

      <div className="liquid-glass p-6 lg:p-8 rounded-3xl border border-red-900/30 shadow-[0_8px_32px_rgba(0,0,0,0.5)]">
        <h4 className="text-xl font-serif font-medium text-white mb-8">نمای کلی ثبت‌نام</h4>
        <div className="w-full h-48 lg:h-64 flex items-end gap-4 lg:gap-12 px-4 justify-around mt-8">
           {[
             { label: 'کل دانش‌آموزان', value: students.length, barBg: 'bg-gradient-to-t from-red-900 to-rose-600', textColor: 'text-red-300' },
             { label: 'تسویه‌شده', value: students.filter((s:any) => s.status === 'paid').length, barBg: 'bg-gradient-to-t from-emerald-900 to-emerald-500', textColor: 'text-emerald-400' },
             { label: 'بدهکار', value: students.filter((s:any) => s.status === 'unpaid').length, barBg: 'bg-gradient-to-t from-red-950 to-red-600', textColor: 'text-red-400' },
             { label: 'ثبت اولیه', value: students.filter((s:any) => s.status === 'pending').length, barBg: 'bg-gradient-to-t from-slate-800 to-slate-500', textColor: 'text-slate-300' }
           ].map((item, i, arr) => {
             const maxVal = Math.max(arr[0].value, 1);
             const heightPct = (item.value / maxVal) * 100;
             return (
               <div key={i} className="flex-1 flex flex-col items-center gap-4 h-full justify-end">
                 <div className={`text-xl lg:text-3xl font-bold ${item.textColor}`}>{item.value} <span className="text-xs font-normal text-slate-400">نفر</span></div>
                 <motion.div 
                   initial={{ height: 0 }}
                   animate={{ height: `${heightPct}%` }}
                   transition={{ delay: i * 0.1, duration: 0.8 }}
                   className={`w-full max-w-[120px] ${item.barBg} rounded-t-xl shadow-[0_0_15px_rgba(220,38,38,0.2)]`}
                 />
                 <span className="text-xs lg:text-sm text-slate-400 font-medium whitespace-nowrap">{item.label}</span>
               </div>
             );
           })}
        </div>
      </div>
    </div>
  );
}

function EditPaymentModal({
  editingStudent,
  onClose,
  onUpdateStudent
}: {
  editingStudent: Student;
  onClose: () => void;
  onUpdateStudent: (id: string, amountPaid: number, receiptUrl?: string, extraData?: any) => Promise<void>;
}) {
  const [hasBook, setHasBook] = useState(!!editingStudent.hasBook);
  const [bookName, setBookName] = useState(editingStudent.bookName || '');
  const [bookPrice, setBookPrice] = useState<number | string>(editingStudent.bookPrice || '');
  const [hasInterview, setHasInterview] = useState(!!editingStudent.hasInterview);
  const [hasDiscount, setHasDiscount] = useState(!!editingStudent.hasDiscount);
  const [discountPercent, setDiscountPercent] = useState<number | string>(editingStudent.discountPercent || '');
  const [hasExtraCourse, setHasExtraCourse] = useState(!!(editingStudent as any).hasExtraCourse);
  const [extraCourseName, setExtraCourseName] = useState((editingStudent as any).extraCourseName || '');
  const [extraCoursePrice, setExtraCoursePrice] = useState<number | string>((editingStudent as any).extraCoursePrice || '');

  const prevDiscount = (editingStudent.hasDiscount && editingStudent.discountAmount) ? editingStudent.discountAmount : 0;
  const prevBook = editingStudent.hasBook ? (Number(editingStudent.bookPrice) || 0) : 0;
  const prevInterview = editingStudent.hasInterview ? 250000 : 0;
  const prevExtra = (editingStudent as any).hasExtraCourse ? (Number((editingStudent as any).extraCoursePrice) || 0) : 0;
  const rawBaseTuition = Math.max(0, editingStudent.totalPayable + prevDiscount - prevBook - prevInterview - prevExtra);

  const discountPctNum = hasDiscount ? (Number(discountPercent) || 0) : 0;
  const calculatedDiscountAmount = Math.round(rawBaseTuition * (discountPctNum / 100));
  const discountedBaseTuition = Math.max(0, rawBaseTuition - calculatedDiscountAmount);

  const calculatedBookPrice = hasBook ? (Number(bookPrice) || 0) : 0;
  const calculatedInterviewFee = hasInterview ? 250000 : 0;
  const calculatedExtraCourse = hasExtraCourse ? (Number(extraCoursePrice) || 0) : 0;
  const calculatedTotalPayable = discountedBaseTuition + calculatedBookPrice + calculatedInterviewFee + calculatedExtraCourse;

  const [amountPaid, setAmountPaid] = useState<number | string>(editingStudent.amountPaid);
  const [isSubmitting, setIsSubmitting] = useState(false);

  return (
    <Modal title={`Edit payment: ${editingStudent.firstName} ${editingStudent.lastName}`} onClose={onClose}>
      <form onSubmit={async (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
          const f = new FormData(e.currentTarget);
          let receiptUrl = '';
          const file = f.get('receipt') as File;
          if (file && file.size > 0) {
            receiptUrl = await new Promise((resolve) => {
              const reader = new FileReader();
              reader.onloadend = () => resolve(reader.result as string);
              reader.readAsDataURL(file);
            });
          }

          await onUpdateStudent(
            editingStudent.id,
            Number(amountPaid),
            receiptUrl || undefined,
            {
              totalPayable: calculatedTotalPayable,
              studentCode: (f.get('studentCode') as string || '').trim(),
              hasBook,
              bookName: hasBook ? bookName : '',
              bookPrice: hasBook ? (Number(bookPrice) || 0) : 0,
              hasInterview,
              hasDiscount,
              discountPercent: discountPctNum,
              discountAmount: calculatedDiscountAmount,
              hasExtraCourse,
              extraCourseName: hasExtraCourse ? extraCourseName : '',
              extraCoursePrice: hasExtraCourse ? (Number(extraCoursePrice) || 0) : 0
            }
          );
          onClose();
        } catch (err) {
          console.error(err);
        } finally {
          setIsSubmitting(false);
        }
      }} className="space-y-4">

        <div className="space-y-1">
          <label className="text-[10px] text-slate-400 px-1 font-medium">شماره پرونده (همان نام کاربری سایت و پنل)</label>
          <input name="studentCode" defaultValue={editingStudent.studentCode || ''} dir="ltr" className="w-full bg-[#12121C] border border-red-900/40 text-amber-200 rounded-xl py-2.5 px-3 font-mono text-sm" placeholder="1102" />
        </div>

        <div className="space-y-1">
          <label className="text-[10px] text-red-400 px-1 font-medium">شهریه پایه سطح (تومان)</label>
          <div className="w-full bg-[#12121C] border border-red-900/40 text-slate-200 rounded-xl py-2.5 px-3 font-semibold text-sm">
            {rawBaseTuition.toLocaleString('en-US')}
          </div>
        </div>

        {/* بخش گزینه‌ها: کتاب، interview و تخفیف */}
        <div className="bg-[#12121C]/90 border border-red-900/40 rounded-xl p-3.5 space-y-3">
          <div className="text-xs font-bold text-red-300 border-b border-red-900/30 pb-2">
            خدمات، تخفیف و افزوده مالی
          </div>

          {/* 1. تیک کتاب */}
          <div className="space-y-2">
            <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
              <input
                type="checkbox"
                checked={hasBook}
                onChange={(e) => setHasBook(e.target.checked)}
                className="w-4 h-4 accent-red-600 rounded cursor-pointer"
              />
              <span>کتاب</span>
            </label>

            {hasBook && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 bg-[#181826] p-3 rounded-lg border border-red-900/30">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 font-medium">اسم کتاب</label>
                  <input
                    type="text"
                    required={hasBook}
                    value={bookName}
                    onChange={(e) => setBookName(e.target.value)}
                    placeholder="مثال: Family & Friends"
                    className="w-full bg-[#0E0E16] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 font-medium">مبلغ کتاب (تومان)</label>
                  <input
                    type="number"
                    required={hasBook}
                    value={bookPrice}
                    onChange={(e) => setBookPrice(e.target.value)}
                    placeholder="تومان"
                    className="w-full bg-[#0E0E16] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Extra Course */}
          <div className="space-y-2 pt-2 border-t border-red-900/30">
            <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
              <input
                type="checkbox"
                checked={hasExtraCourse}
                onChange={(e) => setHasExtraCourse(e.target.checked)}
                className="w-4 h-4 accent-red-600 rounded cursor-pointer"
              />
              <span>Extra Course</span>
            </label>

            {hasExtraCourse && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 bg-[#181826] p-3 rounded-lg border border-fuchsia-500/20">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 font-medium">اسم دوره</label>
                  <input
                    type="text"
                    required={hasExtraCourse}
                    value={extraCourseName}
                    onChange={(e) => setExtraCourseName(e.target.value)}
                    placeholder="مثال: Conversation"
                    className="w-full bg-[#0E0E16] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                  />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 font-medium">هزینه دوره (تومان)</label>
                  <input
                    type="number"
                    required={hasExtraCourse}
                    value={extraCoursePrice}
                    onChange={(e) => setExtraCoursePrice(e.target.value)}
                    placeholder="تومان"
                    className="w-full bg-[#0E0E16] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                  />
                </div>
              </div>
            )}
          </div>

          {/* 2. تیک interview */}
          <div className="pt-2 border-t border-red-900/30 flex items-center justify-between">
            <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
              <input
                type="checkbox"
                checked={hasInterview}
                onChange={(e) => setHasInterview(e.target.checked)}
                className="w-4 h-4 accent-red-600 rounded cursor-pointer"
              />
              <span>interview</span>
            </label>
            <span className={`text-xs font-semibold ${hasInterview ? 'text-emerald-400' : 'text-slate-400'}`}>
              250,000 تومان {hasInterview ? '(افزوده شد)' : ''}
            </span>
          </div>

          {/* 3. تیک تخفیف */}
          <div className="pt-2 border-t border-red-900/30 space-y-2">
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={hasDiscount}
                  onChange={(e) => setHasDiscount(e.target.checked)}
                  className="w-4 h-4 accent-red-600 rounded cursor-pointer"
                />
                <span>تخفیف روی شهریه ترم</span>
              </label>
              {hasDiscount && (
                <span className="text-xs font-semibold text-rose-400 font-mono">
                  کم شده: {calculatedDiscountAmount.toLocaleString('en-US')} تومان
                </span>
              )}
            </div>

            {hasDiscount && (
              <div className="bg-[#181826] p-3 rounded-lg border border-red-900/30 space-y-1">
                <label className="text-[10px] text-slate-400 font-medium">درصد تخفیف (%)</label>
                <input
                  type="number"
                  min="0"
                  max="100"
                  required={hasDiscount}
                  value={discountPercent}
                  onChange={(e) => setDiscountPercent(e.target.value)}
                  placeholder="e.g. 10"
                  className="w-full bg-[#0E0E16] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none font-mono"
                />
              </div>
            )}
          </div>
        </div>

        {/* جمع شهریه جدید پس از احتساب تیک‌ها */}
        <div className="bg-gradient-to-r from-red-950 to-red-900 text-white p-3 rounded-xl flex justify-between items-center text-xs font-medium border border-red-500/30">
          <span>مجموع کل شهریه (قابل پرداخت):</span>
          <span className="text-sm font-bold text-emerald-400 font-mono">{calculatedTotalPayable.toLocaleString('en-US')} تومان</span>
        </div>

        {/* مبلغ پرداخت شده */}
        <div className="space-y-1">
          <div className="flex justify-between items-center">
            <label className="text-[10px] text-slate-400 px-1 font-medium">مبلغ پرداخت شده (تومان)</label>
            <button
              type="button"
              onClick={() => setAmountPaid(calculatedTotalPayable)}
              className="text-[10px] text-emerald-400 hover:underline font-medium cursor-pointer"
            >
              Pay in full ({calculatedTotalPayable.toLocaleString('en-US')})
            </button>
          </div>
          <input
            name="p"
            type="number"
            required
            value={amountPaid}
            onChange={(e) => setAmountPaid(e.target.value)}
            placeholder="تومان"
            className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500 outline-none text-sm font-semibold font-mono"
          />
        </div>

        {/* آپلود رسید */}
        <div className="space-y-1 mt-2">
          <label className="text-[10px] text-slate-400 px-1 font-medium">آپلود رسید جدید (اختیاری)</label>
          <input
            name="receipt"
            type="file"
            accept="image/*"
            className="w-full bg-[#12121C] border border-red-900/40 rounded-xl py-2 px-3 focus:border-red-500 outline-none text-xs text-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-red-600 file:text-white hover:file:bg-red-700 transition-all cursor-pointer"
          />
        </div>

        {editingStudent.receiptUrl && (
          <div className="pt-2">
            <label className="text-[10px] text-slate-400 px-1 block mb-2 font-medium">رسید فعلی</label>
            <img src={editingStudent.receiptUrl} alt="Receipt" className="h-24 rounded-lg object-contain bg-[#12121C] p-1 border border-red-900/40" />
          </div>
        )}

        <button
          type="submit"
          disabled={isSubmitting}
          className="w-full bg-gradient-to-r from-red-600 to-rose-700 text-white hover:brightness-110 py-3.5 rounded-2xl font-bold shadow-[0_0_20px_rgba(220,38,38,0.4)] transition-all text-xs uppercase tracking-wider cursor-pointer mt-4"
        >
          {isSubmitting ? 'Saving...' : 'Save changes'}
        </button>
      </form>
    </Modal>
  );
}

function matchLevelFromTitle(title: string, levels: Level[]) {
  const t = (title || '').trim();
  if (!t || !levels?.length) return null;
  const exact = levels.find((l: Level) => l.name === t);
  if (exact) return exact;
  const sorted = [...levels].sort((a: Level, b: Level) => (b.name || '').length - (a.name || '').length);
  for (const l of sorted) {
    const name = (l.name || '').trim();
    if (!name) continue;
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp('(^|[^A-Za-z0-9\\u0600-\\u06FF])' + escaped + '([^A-Za-z0-9\\u0600-\\u06FF]|$)', 'i');
    if (re.test(t)) return l;
  }
  return null;
}

function RegistrationSection({ terms, students: rawStudents, levels, panelClasses = [], searchQuery, onAddTerm, onUpdateTerm, onDeleteTerm, onAddStudent, onUpdateStudent, onDeleteStudent, onBatchStudent, onAddLevel, onDeleteLevel, onBatchLevels }: any) {
  const [showAddTerm, setShowAddTerm] = useState(false);
  const [editingTerm, setEditingTerm] = useState<Term | null>(null);
  const [showAddStudent, setShowAddStudent] = useState(false);
  const [editingStudent, setEditingStudent] = useState<Student | null>(null);
  const [showAddLevel, setShowAddLevel] = useState(false);
  const [view, setView] = useState<'students' | 'levels'>('students');
  const [selectedLevelInfo, setSelectedLevelInfo] = useState<{name: string, fee: number} | null>(null);
  const [ongoingClassId, setOngoingClassId] = useState('');
  const [addClassType, setAddClassType] = useState('حضوری');
  const [selectedTermId, setSelectedTermId] = usePersistedTerm(terms, false);
  const [showTermMenu, setShowTermMenu] = useState(false);
  const termMenuRef = useRef<HTMLDivElement | null>(null);
  const [visiblePhoneId, setVisiblePhoneId] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<'all' | 'paid' | 'unpaid' | 'pending'>('all');
  const [classTypeFilter, setClassTypeFilter] = useState<'all' | 'حضوری' | 'آنلاین' | 'منتورینگ' | 'آلمانی' | 'خصوصی'>('all');
  const [selectedReceipt, setSelectedReceipt] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [addHasBook, setAddHasBook] = useState(false);
  const [addBookName, setAddBookName] = useState('');
  const [addBookPrice, setAddBookPrice] = useState('');
  const [addHasInterview, setAddHasInterview] = useState(false);
  const [addHasDiscount, setAddHasDiscount] = useState(false);
  const [addDiscountPercent, setAddDiscountPercent] = useState('');
  const [addHasExtraCourse, setAddHasExtraCourse] = useState(false);
  const [addExtraCourseName, setAddExtraCourseName] = useState('');
  const [addExtraCoursePrice, setAddExtraCoursePrice] = useState('');
  
  // متغیرهای بخش رسیدها
  const [receiptModalStudent, setReceiptModalStudent] = useState<Student | null>(null);
  const [studentReceipts, setStudentReceipts] = useState<any[]>([]);
  const [receiptAmount, setReceiptAmount] = useState('');
  const [isReceiptLoading, setIsReceiptLoading] = useState(false);
  
  const fileInputRef = React.useRef<HTMLInputElement>(null);
  const levelFileInputRef = React.useRef<HTMLInputElement>(null);

  const query = searchQuery ? searchQuery.trim().toLowerCase() : '';
  const students = query
    ? rawStudents.filter((s: Student) =>
        s.firstName?.toLowerCase().includes(query) ||
        s.lastName?.toLowerCase().includes(query) ||
        s.phone?.toLowerCase().includes(query) ||
        String(s.studentCode || '').toLowerCase().includes(query) ||
        s.level?.toLowerCase().includes(query) ||
        s.classType?.toLowerCase().includes(query)
      )
    : rawStudents;

  const selectedTerm = terms.find((t: Term) => t.id === selectedTermId) || terms[0];

  useEffect(() => {
    if (!showTermMenu) return;
    const onDoc = (e: Event) => {
      if (termMenuRef.current && !termMenuRef.current.contains(e.target as Node)) {
        setShowTermMenu(false);
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setShowTermMenu(false);
    };
    document.addEventListener('pointerdown', onDoc, true);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('pointerdown', onDoc, true);
      document.removeEventListener('keydown', onKey);
    };
  }, [showTermMenu]);

  const handleImportExcel = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    
    if (!selectedTermId) {
      alert('لطفا ابتدا یک ترم را انتخاب کنید');
      if (fileInputRef.current) fileInputRef.current.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
      try {
        const data = new Uint8Array(event.target?.result as ArrayBuffer);
        const workbook = XLSX.read(data, { type: 'array' });
        const sheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[sheetName];
        const json = XLSX.utils.sheet_to_json(worksheet) as any[];

        const studentsToImport = json.map(row => {
          const firstName = row['نام'] || row['First Name'] || '';
          const lastName = row['نام خانوادگی'] || row['Last Name'] || '';
          const levelName = row['سطح'] || row['Level'] || '';
          const phone = row['شماره تماس'] || row['Phone'] || '';
          const studentCode = row['شماره پرونده'] || row['شناسه'] || row['Student Code'] || row['studentCode'] || '';
          const classType = row['نوع کلاس'] || row['Class Type'] || 'حضوری';
          const amountPaid = Number(row['مبلغ پرداختی'] || row['Paid Amount'] || 0);
          
          const level = levels.find((l: any) => l.name === levelName);
          const totalPayable = level ? level.fee : 0;
          const debt = totalPayable - amountPaid;
          const status = deriveStudentStatus(totalPayable, amountPaid);

          return {
            firstName,
            lastName,
            level: levelName,
            phone: String(phone),
            studentCode: String(studentCode || '').trim(),
            classType,
            totalPayable,
            amountPaid,
            debt,
            status,
            termId: selectedTermId
          };
        }).filter(s => s.firstName && s.lastName);

        if (studentsToImport.length > 0) {
          onBatchStudent(studentsToImport);
        } else {
          alert('هیچ دانشجوی معتبری در فایل یافت نشد. لطفا ستون‌های "نام"، "نام خانوادگی" و "سطح" را چک کنید.');
        }
      } catch (err) {
        console.error(err);
        alert('خطا در خواندن فایل اکسل');
      }
      if (fileInputRef.current) fileInputRef.current.value = '';
    };
    reader.readAsArrayBuffer(file);
  };

  const handleDownloadTemplate = () => {
    const templateData = [
      {
        'نام': 'مثال: علی',
        'نام خانوادگی': 'مثال: محمدی',
        'سطح': 'مثال: Elementary 1',
        'نوع کلاس': 'حضوری / آنلاین / منتورینگ / آلمانی',
        'شماره تماس': '09121234567',
        'مبلغ پرداختی': 500000
      }
    ];

    const worksheet = XLSX.utils.json_to_sheet(templateData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Students");
    XLSX.writeFile(workbook, "ghazal_import_template.xlsx");
  };

  const handleImportLevelExcel = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      try {
        const data = new Uint8Array(event.target?.result as ArrayBuffer);
        const workbook = XLSX.read(data, { type: 'array' });
        const sheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[sheetName];
        const json = XLSX.utils.sheet_to_json(worksheet) as any[];

        const levelsToImport = json.map(row => {
          const name = String(row['نام سطح'] || row['سطح'] || row['نام'] || row['Level Name'] || row['Level'] || row['Name'] || '').trim();
          let rawFee = row['شهریه (تومان)'] || row['شهریه'] || row['شهریه پایه'] || row['مبلغ شهریه'] || row['مبلغ'] || row['Fee'] || row['Tuition'] || row['Price'] || 0;
          
          if (typeof rawFee === 'string') {
            rawFee = rawFee.replace(/[^\d]/g, '');
          }
          const fee = Number(rawFee) || 0;

          return { name, fee };
        }).filter(l => l.name && l.fee > 0);

        if (levelsToImport.length > 0) {
          if (typeof onBatchLevels === 'function') {
            onBatchLevels(levelsToImport);
          }
        } else {
          alert('هیچ سطح معتبری در فایل یافت نشد. لطفا ستون‌های "نام سطح" و "شهریه" را چک کنید.');
        }
      } catch (err) {
        console.error(err);
        alert('خطا در خواندن فایل اکسل سطوح');
      }
      if (levelFileInputRef.current) levelFileInputRef.current.value = '';
    };
    reader.readAsArrayBuffer(file);
  };

  const handleDownloadLevelTemplate = () => {
    const templateData = [
      {
        'نام سطح': 'Elementary (A1)',
        'شهریه (تومان)': 1800000
      },
      {
        'نام سطح': 'Pre-Intermediate (A2)',
        'شهریه (تومان)': 2200000
      },
      {
        'نام سطح': 'Intermediate (B1)',
        'شهریه (تومان)': 2500000
      }
    ];

    const worksheet = XLSX.utils.json_to_sheet(templateData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "Levels");
    XLSX.writeFile(workbook, "ghazal_levels_template.xlsx");
  };

  // ------------------------------------
  // توابع مربوط به سیستم چاپ رسید
  // ------------------------------------
  const handleOpenReceipts = async (student: Student) => {
    setReceiptModalStudent(student);
    setIsReceiptLoading(true);
    try {
      const data = await fetchJSON(`/api/receipts/${student.id}`);
      setStudentReceipts(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error(err);
      setStudentReceipts([]);
    } finally {
      setIsReceiptLoading(false);
    }
  };

  const formatEnNum = (num: number | string) => {
    const n = Number(num);
    if (isNaN(n)) return String(num);
    return n.toLocaleString('en-US');
  };

  const toEnDigits = (str: string | number) => {
    return String(str)
      .replace(/[۰-۹]/g, (d) => "0123456789"["۰۱۲۳۴۵۶۷۸۹".indexOf(d)])
      .replace(/[٠-٩]/g, (d) => "0123456789"["٠١٢٣٤٥٦٧٨٩".indexOf(d)]);
  };

  const printHTML = (student: Student, amount: number, date: string) => {
    const termName = terms.find((t: any) => String(t.id) === String(student.termId))?.name || 'نامشخص';
    const printWindow = window.open('', '', 'width=900,height=1100');
    if (!printWindow) return alert('لطفا پاپ‌آپ (Pop-up) مرورگر را فعال کنید');

    const esc = (v: unknown) => String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');

    const payNow = Number(amount) || 0;
    const isPay = payNow > 0;
    const docTitle = isPay ? 'رسید پرداخت و صورتحساب' : 'صورتحساب';
    const docSub = isPay
      ? 'پرداخت ثبت‌شده به‌همراه ریز اقلام شهریه، خدمات و کتاب'
      : 'ریز اقلام شهریه، خدمات و کتاب آموزشی';
    const issueDate = toEnDigits(date);
    const fileNo = toEnDigits(student.studentCode || '—');
    const serialDate = issueDate
      .split(/[^\d]+/)
      .filter(Boolean)
      .map((p, i) => (i === 0 ? p : p.padStart(2, '0')))
      .join('');
    const serial = `${fileNo}-${serialDate || '00000000'}`;
    const paidToDate = Number(student.amountPaid) || 0;

    const bookCost = student.hasBook ? (Number(student.bookPrice) || 0) : 0;
    const interviewCost = student.hasInterview ? 250000 : 0;
    const extraCost = (student as any).hasExtraCourse ? (Number((student as any).extraCoursePrice) || 0) : 0;
    const discountCost = (student.hasDiscount && student.discountAmount) ? student.discountAmount : 0;
    const rawBaseTuition = Math.max(0, (Number(student.totalPayable) || 0) + discountCost - bookCost - interviewCost - extraCost);

    let rowCount = 1;
    const itemRows: string[] = [];
    const pushRow = (label: string, amountText: string, kind = '') => {
      const alt = rowCount % 2 === 0 ? ' alt' : '';
      itemRows.push(`<tr class="${kind}${alt}">
        <td class="n">${rowCount++}</td>
        <td class="d">${label}</td>
        <td class="a">${amountText}</td>
      </tr>`);
    };

    pushRow(`شهریه دوره آموزشی (${esc(student.level)})`, `${formatEnNum(rawBaseTuition)} <span class="u">تومان</span>`);
    if (student.hasDiscount && discountCost > 0) {
      pushRow(`تخفیف ویژه شهریه ترم (${esc(student.discountPercent || 0)}٪)`, `− ${formatEnNum(discountCost)} <span class="u">تومان</span>`, 'disc');
    }
    if (student.hasBook) {
      pushRow(`کتاب آموزشی: ${esc(student.bookName || 'کتاب')}`, `${formatEnNum(bookCost)} <span class="u">تومان</span>`);
    }
    if (student.hasInterview) {
      pushRow('مصاحبه / Interview', `${formatEnNum(interviewCost)} <span class="u">تومان</span>`);
    }
    if ((student as any).hasExtraCourse) {
      pushRow(`Extra Course: ${esc((student as any).extraCourseName || 'Extra Course')}`, `${formatEnNum(extraCost)} <span class="u">تومان</span>`);
    }

    const payRow = isPay ? `
      <tr class="sum">
        <td colspan="2">مبلغ دریافتی این رسید</td>
        <td class="a">${formatEnNum(payNow)} <span class="u">تومان</span></td>
      </tr>` : '';

    const html = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <title>${esc(docTitle)} — غزال</title>
  <style>
    @page { size: A4; margin: 12mm; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; width: 100%; background: #fff; color: #000; font-family: Tahoma, Arial, sans-serif; }
    .wrap { padding: 8px; }
    table.frame { width: 100%; border: 3px double #000; border-collapse: collapse; }
    table.frame > tbody > tr > td { padding: 0; vertical-align: top; }
    table.inner { width: 100%; border-collapse: collapse; }
    .logo { width: 78px; height: 78px; object-fit: contain; display: block; }
    .brand { padding: 12px 14px; }
    .brand h1 { margin: 0; font-size: 20px; }
    .brand .en { margin: 3px 0 0; font-size: 11px; }
    .brand .tag { margin: 4px 0 0; font-size: 11px; }
    .meta td { border: 1px solid #000; padding: 7px 10px; font-size: 12px; white-space: nowrap; }
    .meta .k { width: 88px; }
    .meta .v { font-weight: 700; text-align: left; font-family: Tahoma, Arial, sans-serif; }
    .docbar { text-align: center; padding: 10px 12px; border-top: 3px double #000; border-bottom: 1px solid #000; }
    .docbar h2 { margin: 0; font-size: 18px; font-weight: 900; }
    .docbar p { margin: 4px 0 0; font-size: 11px; }
    .info td { border: 1px solid #000; padding: 8px 10px; font-size: 13px; width: 50%; }
    .info b { display: inline-block; min-width: 84px; }
    .items th { background: #000; color: #fff; font-size: 12px; padding: 8px; font-weight: 700; }
    .items td { border: 1px solid #000; padding: 8px 10px; font-size: 13px; }
    .items .n { width: 54px; text-align: center; }
    .items .a, .items th.a { width: 28%; text-align: left; font-variant-numeric: tabular-nums; }
    .items tr.alt td { background: #f2f2f2; }
    .items tr.disc td { font-style: italic; }
    .u { font-size: 10px; padding-right: 4px; }
    .items tfoot td { font-weight: 700; }
    .items .grand td { background: #000; color: #fff; font-size: 14px; }
    .items .sum td { background: #ececec; }
    .items .debt td { font-size: 14px; }
    .signs td { text-align: center; padding: 28px 16px 12px; font-size: 12px; }
    .signs .line { border-top: 1px solid #000; margin: 46px auto 0; padding-top: 6px; width: 42%; }
    .note { font-size: 11px; padding: 4px 14px 12px; line-height: 1.7; border-top: 1px solid #000; }
    @media print {
      body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
  </style>
</head>
<body>
  <div class="wrap">
  <table class="frame" role="presentation">
    <tr><td>
      <table class="inner" role="presentation">
        <tr>
          <td class="brand">
            <table role="presentation"><tr>
              <td style="padding-left:12px;vertical-align:middle">
                <img class="logo" id="academy-logo" src="https://acccounting.ghazalify.com/assets/ghazal-logo.jpg" alt="لوگوی آموزشگاه غزال">
              </td>
              <td style="vertical-align:middle">
                <h1>آموزشگاه زبان غزال</h1>
                <p class="en">Ghazal Language Academy</p>
                <p class="tag">شهریه، خدمات و کتاب آموزشی</p>
              </td>
            </tr></table>
          </td>
          <td style="width:240px;vertical-align:top">
            <table class="meta inner" role="presentation">
              <tr><td class="k">شماره سند</td><td class="v">${esc(serial)}</td></tr>
              <tr><td class="k">تاریخ صدور</td><td class="v">${esc(issueDate)}</td></tr>
              <tr><td class="k">شماره پرونده</td><td class="v">${esc(fileNo)}</td></tr>
            </table>
          </td>
        </tr>
      </table>
      <div class="docbar">
        <h2>${esc(docTitle)}</h2>
        <p>${esc(docSub)}</p>
      </div>
      <table class="info inner" role="presentation">
        <tr>
          <td><b>نام زبان‌آموز:</b> ${esc(student.firstName)} ${esc(student.lastName)}</td>
          <td><b>شماره تماس:</b> ${esc(toEnDigits(student.phone || '—'))}</td>
        </tr>
        <tr>
          <td><b>ترم تحصیلی:</b> ${esc(termName)}</td>
          <td><b>سطح / کلاس:</b> ${esc(student.level)} (${esc(student.classType || 'نامشخص')})</td>
        </tr>
      </table>
      <table class="items inner">
        <thead>
          <tr>
            <th class="n">ردیف</th>
            <th>شرح اقلام صورتحساب</th>
            <th class="a">مبلغ</th>
          </tr>
        </thead>
        <tbody>${itemRows.join('')}</tbody>
        <tfoot>
          <tr class="grand">
            <td colspan="2">جمع کل صورتحساب</td>
            <td class="a">${formatEnNum(student.totalPayable)} <span class="u">تومان</span></td>
          </tr>
          ${payRow}
          <tr class="sum">
            <td colspan="2">جمع پرداخت‌ها تا این لحظه</td>
            <td class="a">${formatEnNum(paidToDate)} <span class="u">تومان</span></td>
          </tr>
          <tr class="debt">
            <td colspan="2">مانده بدهی</td>
            <td class="a">${formatEnNum(student.debt)} <span class="u">تومان</span></td>
          </tr>
        </tfoot>
      </table>
      <table class="signs inner" role="presentation">
        <tr>
          <td><div class="line">مهر و امضای آموزشگاه</div></td>
        </tr>
      </table>
      <p class="note">این سند بدون مهر و امضای آموزشگاه اعتبار ندارد. اقلام بالا شامل شهریه دوره و تمام خدمات ثبت‌شده در پرونده است؛ خرید کتاب فاکتور جداگانه ندارد.</p>
    </td></tr>
  </table>
  </div>
  <script>
    const goPrint = () => setTimeout(() => { window.print(); window.close(); }, 250);
    window.onload = () => {
      const img = document.getElementById('academy-logo');
      if (!img || img.complete) goPrint();
      else { img.onload = goPrint; img.onerror = goPrint; }
    };
  </script>
</body>
</html>`;
    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
  };

  const handleIssueNewReceipt = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!receiptModalStudent || !receiptAmount) return;
    
    const date = new Date().toLocaleDateString('fa-IR-u-nu-latn');
    const amount = Number(receiptAmount);
    
    try {
      await fetchJSON('/api/receipts', {
        method: 'POST',
        body: JSON.stringify({
          studentId: receiptModalStudent.id,
          termId: receiptModalStudent.termId,
          paidAmount: amount,
          date: date
        })
      });

      const newPaid = (Number(receiptModalStudent.amountPaid) || 0) + amount;
      const newDebt = Math.max(0, (Number(receiptModalStudent.totalPayable) || 0) - newPaid);
      const updatedStudentForPrint = {
        ...receiptModalStudent,
        amountPaid: newPaid,
        debt: newDebt,
        status: deriveStudentStatus(Number(receiptModalStudent.totalPayable) || 0, newPaid)
      };
      
      printHTML(updatedStudentForPrint, amount, date);
      
      const data = await fetchJSON(`/api/receipts/${receiptModalStudent.id}`);
      setStudentReceipts(Array.isArray(data) ? data : []);
      setReceiptAmount('');

      if (typeof onUpdateStudent === 'function') {
        await onUpdateStudent(receiptModalStudent.id, newPaid);
      }
      setReceiptModalStudent(updatedStudentForPrint);
      
    } catch (err) {
      console.error(err);
      alert('خطا در صدور رسید');
    }
  };

  return (
    <div className="space-y-6 lg:space-y-8" dir="rtl">
      <div className="flex flex-col lg:flex-row lg:justify-between lg:items-end gap-6">
        <div>
          <h2 className="text-2xl lg:text-3xl font-serif font-medium text-white">مدیریت ثبت‌نام</h2>
          <p className="text-slate-400 mt-1 text-sm lg:text-base">تعریف ترم‌ها، سطوح آموزشی و پرونده دانشجویان</p>
        </div>
        <div className="flex flex-wrap gap-2 lg:gap-3">
          <input 
            type="file" 
            ref={fileInputRef} 
            onChange={handleImportExcel} 
            accept=".xlsx, .xls, .csv" 
            className="hidden" 
          />
          <input 
            type="file" 
            ref={levelFileInputRef} 
            onChange={handleImportLevelExcel} 
            accept=".xlsx, .xls, .csv" 
            className="hidden" 
          />
          <button 
            onClick={() => setView(view === 'students' ? 'levels' : 'students')} 
            className={`flex-1 lg:flex-none px-5 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 transition-all text-sm cursor-pointer ${view === 'levels' ? 'bg-gradient-to-r from-red-600 to-rose-700 text-white font-bold shadow-[0_0_15px_rgba(220,38,38,0.3)]' : 'bg-[#12121C] border border-red-900/40 text-slate-200 hover:bg-red-950/40 hover:border-red-500/40'}`}
          >
            {view === 'levels' ? <Users size={16} /> : <BookOpen size={16} />}
            {view === 'levels' ? 'Students' : 'Levels & tuition'}
          </button>
          
          {view === 'students' ? (
            <>
              <button 
                onClick={handleDownloadTemplate} 
                className="flex-1 lg:flex-none bg-[#12121C] border border-red-900/40 text-slate-200 px-4 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 hover:bg-red-950/40 hover:border-red-500/40 transition-all text-sm cursor-pointer"
                title="Download Excel template"
              >
                <Download size={16} className="text-sky-400" /> Template
              </button>
              <button 
                onClick={() => fileInputRef.current?.click()} 
                className="flex-1 lg:flex-none bg-[#12121C] border border-red-900/40 text-slate-200 px-4 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 hover:bg-red-950/40 hover:border-red-500/40 transition-all text-sm cursor-pointer"
              >
                <FileSpreadsheet size={16} className="text-emerald-400" /> Upload Excel
              </button>
              <div className="relative flex-1 lg:flex-none" ref={termMenuRef}>
                <button 
                  onClick={() => setShowTermMenu(!showTermMenu)} 
                  className="w-full bg-[#12121C] border border-red-900/40 text-slate-200 px-5 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 hover:bg-red-950/40 hover:border-red-500/40 transition-all text-sm min-w-[130px] cursor-pointer"
                >
                  <ChevronDown size={16} className={`transition-transform ${showTermMenu ? 'rotate-180' : ''}`} />
                  <span className="truncate">{selectedTerm ? selectedTerm.name : 'Select term'}</span>
                  <TermBadge term={selectedTerm} />
                </button>
                
                <AnimatePresence>
                  {showTermMenu && (
                    <motion.div 
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      exit={{ opacity: 0, y: 10 }}
                      className="absolute top-full right-0 mt-2 w-full sm:w-80 bg-[#0F0F18]/95 backdrop-blur-2xl border border-red-500/30 rounded-2xl shadow-[0_0_30px_rgba(220,38,38,0.3)] z-50 overflow-hidden"
                    >
                      <div className="max-h-60 overflow-y-auto py-2 custom-scrollbar">
                        {terms.map((term: Term) => (
                          <div key={term.id} className="group/item flex items-center justify-between hover:bg-red-950/40 transition-colors">
                            <button 
                              onClick={() => {
                                setSelectedTermId(term.id);
                                setShowTermMenu(false);
                              }}
                              className={`flex-1 text-right px-4 py-2.5 text-sm flex items-center justify-between gap-2 ${selectedTermId === term.id ? 'text-white font-bold' : 'text-slate-400'}`}
                            >
                              <span className="flex items-center gap-2 min-w-0">
                                <span className="truncate">{term.name}</span>
                                <TermBadge term={term} />
                              </span>
                              {selectedTermId === term.id && <div className="w-1.5 h-1.5 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] shrink-0" />}
                            </button>
                            <div className="flex items-center gap-1 px-2 opacity-0 group-hover/item:opacity-100 transition-opacity">
                              <button 
                                onClick={(e) => { e.stopPropagation(); setEditingTerm(term); setShowTermMenu(false); }} 
                                className="p-1 hover:bg-red-900/40 rounded-lg text-sky-400"
                                title="Edit"
                              >
                                <Pencil size={12} />
                              </button>
                              <button 
                                onClick={(e) => { e.stopPropagation(); onDeleteTerm(term.id); }} 
                                className="p-1 hover:bg-red-900/40 rounded-lg text-rose-400"
                                title="Delete"
                              >
                                <Trash2 size={12} />
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                      <button 
                        onClick={() => {
                          setShowAddTerm(true);
                          setShowTermMenu(false);
                        }}
                        className="w-full text-right px-4 py-3 text-xs bg-[#0A0A10] text-red-300 font-medium border-t border-red-900/30 hover:bg-red-950/60 transition-all flex items-center gap-2 cursor-pointer"
                      >
                        <PlusCircle size={14} />
                        <span>Add new term...</span>
                      </button>
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
              <button onClick={() => setShowAddStudent(true)} className="flex-1 lg:flex-none bg-gradient-to-r from-red-600 to-rose-700 text-white hover:brightness-110 px-5 py-2.5 rounded-full font-bold flex items-center justify-center gap-2 transition-all text-sm cursor-pointer shadow-[0_0_20px_rgba(220,38,38,0.4)]">
                <Plus size={16} /> Add student
              </button>
            </>
          ) : (
            <>
              <button 
                onClick={handleDownloadLevelTemplate} 
                className="flex-1 lg:flex-none bg-[#12121C] border border-red-900/40 text-slate-200 px-4 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 hover:bg-red-950/40 hover:border-red-500/40 transition-all text-sm cursor-pointer"
                title="Download levels Excel template"
              >
                <Download size={16} className="text-sky-400" /> Excel template
              </button>
              <button 
                onClick={() => levelFileInputRef.current?.click()} 
                className="flex-1 lg:flex-none bg-[#12121C] border border-red-900/40 text-slate-200 px-4 py-2.5 rounded-full font-medium flex items-center justify-center gap-2 hover:bg-red-950/40 hover:border-red-500/40 transition-all text-sm cursor-pointer"
              >
                <FileSpreadsheet size={16} className="text-emerald-400" /> Upload Excel
              </button>
              <button onClick={() => setShowAddLevel(true)} className="w-full lg:w-auto bg-gradient-to-r from-red-600 to-rose-700 text-white hover:brightness-110 px-6 py-2.5 rounded-full font-bold flex items-center justify-center gap-2 transition-all text-sm cursor-pointer shadow-[0_0_20px_rgba(220,38,38,0.4)]">
                <Plus size={16} /> Add level
              </button>
            </>
          )}
        </div>
      </div>

      {view === 'levels' ? (
        levels.length === 0 ? (
          <div className="bg-[#0F0F18]/90 backdrop-blur-xl p-12 rounded-3xl border border-red-900/30 text-center space-y-4 shadow-[0_0_30px_rgba(220,38,38,0.1)]">
            <BookOpen size={48} className="mx-auto text-red-500/50" />
            <h3 className="text-xl font-serif text-white font-medium">هیچ سطح آموزشی تعریف نشده است</h3>
            <p className="text-sm text-slate-400 max-w-md mx-auto">می‌توانید به راحتی سطوح آموزشی را به‌صورت گروهی از طریق فایل اکسل وارد کنید یا به صورت دستی ثبت نمایید.</p>
            <div className="flex flex-wrap justify-center gap-3 pt-2">
              <button 
                onClick={handleDownloadLevelTemplate} 
                className="bg-[#12121C] border border-red-900/40 hover:border-red-500/50 text-slate-200 px-4 py-2.5 rounded-full text-sm font-medium flex items-center gap-2 transition-all cursor-pointer"
              >
                <Download size={16} className="text-sky-400" /> Download template
              </button>
              <button 
                onClick={() => levelFileInputRef.current?.click()} 
                className="bg-[#12121C] border border-red-900/40 hover:border-red-500/50 text-slate-200 px-5 py-2.5 rounded-full text-sm font-medium flex items-center gap-2 transition-all cursor-pointer"
              >
                <FileSpreadsheet size={16} className="text-emerald-400" /> Import Excel
              </button>
              <button 
                onClick={() => setShowAddLevel(true)} 
                className="bg-gradient-to-r from-red-600 to-rose-700 text-white px-5 py-2.5 rounded-full text-sm font-bold flex items-center gap-2 cursor-pointer hover:brightness-110 transition-all shadow-[0_0_15px_rgba(220,38,38,0.3)]"
              >
                <Plus size={16} /> Add level manually
              </button>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
            <AnimatePresence>
              {levels.map((level: Level) => (
                <motion.div 
                  layout
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, scale: 0.95 }}
                  key={level.id || (level as any)._id} 
                  className="bg-[#0F0F18]/90 backdrop-blur-xl p-6 rounded-3xl relative group border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)] hover:border-red-500/40 hover:shadow-[0_0_40px_rgba(220,38,38,0.2)] transition-all"
                >
                  <div className="flex justify-between items-start mb-6">
                    <div className="w-11 h-11 rounded-2xl bg-red-950/50 border border-red-500/30 text-red-400 flex items-center justify-center">
                      <BookOpen size={20} />
                    </div>
                    <button 
                      onClick={() => onDeleteLevel((level as any)._id || level.id)}
                      className="p-2 lg:opacity-0 lg:group-hover:opacity-100 hover:bg-rose-950/60 text-rose-400 rounded-xl transition-all cursor-pointer"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>
                  <h3 className="text-xl font-serif font-medium text-white">{level.name}</h3>
                  <p className="text-2xl font-bold mt-2 text-red-400 font-mono">{level.fee.toLocaleString('en-US')} <span className="text-[10px] font-normal text-slate-400">تومان</span></p>
                  <p className="text-[10px] text-slate-400 mt-6 border-t border-red-900/30 pt-4">شهریه مصوب این سطح در آکادمی غزال</p>
                </motion.div>
              ))}
            </AnimatePresence>
          </div>
        )
      ) : (
        <div className="space-y-8">
          <div className="bg-[#0F0F18]/90 backdrop-blur-xl p-4 lg:p-8 rounded-3xl overflow-hidden border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)]">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
              <h4 className="text-xl font-serif font-medium text-white">لیست پرونده دانشجویان</h4>
              <div className="flex flex-wrap gap-3">
                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-slate-400 font-medium uppercase">وضعیت:</span>
                  <select 
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as any)}
                    className="bg-[#12121C] border border-red-900/40 rounded-full py-1.5 px-3 text-xs outline-none focus:border-red-500 transition-colors text-slate-200 cursor-pointer"
                  >
                    <option value="all">همه وضعیت‌ها</option>
                    <option value="paid">تسویه شده</option>
                    <option value="unpaid">بدهکار</option>
                    <option value="pending">ثبت اولیه</option>
                  </select>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-[10px] text-slate-400 font-medium uppercase">نوع کلاس:</span>
                  <select 
                    value={classTypeFilter}
                    onChange={(e) => setClassTypeFilter(e.target.value as any)}
                    className="bg-[#12121C] border border-red-900/40 rounded-full py-1.5 px-3 text-xs outline-none focus:border-red-500 transition-colors text-slate-200 cursor-pointer"
                  >
                    <option value="all">همه کلاس‌ها</option>
                    <option value="حضوری">حضوری</option>
                    <option value="آنلاین">آنلاین</option>
                    <option value="منتورینگ">منتورینگ</option>
                    <option value="آلمانی">آلمانی</option>
                    <option value="خصوصی">خصوصی</option>
                  </select>
                </div>
              </div>
            </div>
            <div className="overflow-x-auto -mx-4 px-4 lg:mx-0 lg:px-0">
              <table className="w-full text-right border-collapse min-w-[780px]">
                <thead>
                  <tr className="text-slate-400 font-serif text-xs uppercase border-b border-red-900/30">
                    <th className="py-3 pr-4 text-right">شماره پرونده</th>
                    <th className="py-3 text-right">نام دانشجو</th>
                    <th className="py-3 text-right">سطح</th>
                    <th className="py-3 text-center">نوع کلاس</th>
                    <th className="py-3 text-center">شهریه کل</th>
                    <th className="py-3 text-center">پرداخت شده</th>
                    <th className="py-3 text-center">مانده بدهی</th>
                    <th className="py-3 text-center">وضعیت</th>
                    <th className="py-3 text-center">عملیات</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-red-900/20">
                  {students
                    .filter((s: Student) => !selectedTermId || String(s.termId) === String(selectedTermId))
                    .filter((s: Student) => statusFilter === 'all' || s.status === statusFilter)
                    .filter((s: Student) => classTypeFilter === 'all' || s.classType === classTypeFilter)
                    .map((s: Student) => (
                    <tr key={s.id} className="hover:bg-red-950/20 transition-colors group">
                      <td className="py-4 pr-4 font-mono text-xs text-amber-300 tracking-wider">{s.studentCode || '—'}</td>
                      <td className="py-4 font-medium text-white">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-sm">{s.firstName} {s.lastName}</span>
                          
                          {s.hasDiscount && s.discountPercent ? (
                            <span className="bg-rose-950/60 text-rose-300 border border-rose-500/30 text-[10px] px-2 py-0.5 rounded-full font-medium" title={`تخفیف: ${s.discountPercent}% (${(s.discountAmount || 0).toLocaleString('en-US')} تومان)`}>
                              🏷️ تخفیف: {s.discountPercent}%
                            </span>
                          ) : null}

                          {s.hasBook ? (
                            <span className="bg-amber-950/60 text-amber-300 border border-amber-500/30 text-[10px] px-2 py-0.5 rounded-full font-medium" title={s.bookName ? `کتاب: ${s.bookName}` : 'کتاب'}>
                              کتاب: {s.bookName || 'کتاب'}
                            </span>
                          ) : null}
                          {s.hasInterview ? (
                            <span className="bg-sky-950/60 text-sky-300 border border-sky-500/30 text-[10px] px-2 py-0.5 rounded-full font-medium" title="مصاحبه (250,000 تومان)">
                              Interview
                            </span>
                          ) : null}
                          {(s as any).hasExtraCourse ? (
                            <span data-gz-course-tag="1" className="gz-course-tag bg-fuchsia-950/70 text-fuchsia-300 border border-fuchsia-500/40 text-[10px] px-2 py-0.5 rounded-full font-medium" title={(s as any).extraCourseName ? `Extra Course: ${(s as any).extraCourseName}` : 'Extra Course'}>
                              {(s as any).extraCourseName ? `Course: ${(s as any).extraCourseName}` : 'Course'}
                            </span>
                          ) : null}
                          
                        </div>
                      </td>
                      <td className="py-4 text-slate-400 text-xs">{s.level}</td>
                      <td className="py-4 text-center">
                        <span className={`px-2.5 py-1 rounded-full text-[10px] font-medium border ${
                          s.classType === 'حضوری' ? 'bg-sky-950/50 text-sky-400 border-sky-500/30' : 
                          s.classType === 'آنلاین' ? 'bg-purple-950/50 text-purple-400 border-purple-500/30' : 
                          s.classType === 'منتورینگ' ? 'bg-amber-950/50 text-amber-400 border-amber-500/30' : 
                          s.classType === 'خصوصی' ? 'bg-emerald-950/50 text-emerald-400 border-emerald-500/30' : 
                          'bg-rose-950/50 text-rose-400 border-rose-500/30'
                        }`}>
                          {s.classType || 'نامشخص'}
                        </span>
                      </td>
                      <td className="py-4 text-center font-mono text-xs text-slate-200">{s.totalPayable.toLocaleString('en-US')}</td>
                      <td className="py-4 text-center font-mono text-xs text-emerald-400 font-medium">{s.amountPaid.toLocaleString('en-US')}</td>
                      <td className="py-4 text-center font-mono text-xs text-rose-400 font-medium">{s.debt.toLocaleString('en-US')}</td>
                      <td className="py-4 text-center">
                        <span className={`px-3 py-1 rounded-full text-[10px] font-medium border ${studentPaymentBadge(s.status).className}`}>
                          {studentPaymentBadge(s.status).label}
                        </span>
                      </td>
                      <td className="py-4 text-center">
                        <div className="flex items-center justify-center gap-1">
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              handleOpenReceipts(s);
                            }}
                            className="p-1.5 hover:bg-red-900/40 rounded-lg text-slate-300 hover:text-sky-400 transition-all cursor-pointer"
                            title="Receipts"
                          >
                            <Printer size={15} />
                          </button>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              setVisiblePhoneId(visiblePhoneId === s.id ? null : s.id);
                            }}
                            className="p-1.5 hover:bg-red-900/40 rounded-lg text-slate-300 hover:text-white transition-all relative cursor-pointer"
                            title="Phone"
                          >
                            <Phone size={15} />
                            <AnimatePresence>
                              {visiblePhoneId === s.id && (
                                <motion.div
                                  initial={{ opacity: 0, scale: 0.9, y: 8 }}
                                  animate={{ opacity: 1, scale: 1, y: 0 }}
                                  exit={{ opacity: 0, scale: 0.9, y: 8 }}
                                  className="absolute left-1/2 -translate-x-1/2 top-full mt-1 bg-[#12121C] text-white border border-red-500/30 px-3 py-1 rounded-xl text-xs whitespace-nowrap shadow-xl z-40 font-mono"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  {s.phone || 'ثبت نشده'}
                                </motion.div>
                              )}
                            </AnimatePresence>
                          </button>
                          {(s.hasReceipt || s.receiptUrl) && (
                            <button
                              type="button"
                              onClick={async (e) => {
                                e.stopPropagation();
                                try {
                                  if (s.receiptUrl) {
                                    setSelectedReceipt(s.receiptUrl);
                                    return;
                                  }
                                  const data = await fetchJSON(`/api/students/${s.id}/receipt`);
                                  setSelectedReceipt(data.receiptUrl || null);
                                } catch (err: any) {
                                  alert(err?.message || 'رسید در دسترس نیست');
                                }
                              }}
                              className="p-1.5 hover:bg-red-900/40 rounded-lg text-slate-300 hover:text-emerald-400 transition-all cursor-pointer"
                              title="View receipt"
                            >
                              <Receipt size={15} />
                            </button>
                          )}
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              setEditingStudent(s);
                            }}
                            className="p-1.5 hover:bg-red-900/40 rounded-lg text-slate-300 hover:text-sky-400 transition-all cursor-pointer"
                            title="Edit payment"
                          >
                            <Pencil size={15} />
                          </button>
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              onDeleteStudent(s.id);
                            }}
                            className="p-1.5 hover:bg-red-900/40 rounded-lg text-rose-300 hover:text-rose-400 hover:bg-rose-950/60 transition-all cursor-pointer"
                            title="Delete student"
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {students
                .filter((s: Student) => !selectedTermId || String(s.termId) === String(selectedTermId))
                .filter((s: Student) => statusFilter === 'all' || s.status === statusFilter)
                .filter((s: Student) => classTypeFilter === 'all' || s.classType === classTypeFilter)
                .length === 0 && (
                <div className="py-12 text-center text-slate-400 italic text-sm">هیچ دانشجویی با این فیلترها یافت نشد.</div>
              )}
            </div>
          </div>
        </div>
      )}

      <AnimatePresence>
        {editingStudent && (
          <EditPaymentModal
            editingStudent={editingStudent}
            onClose={() => setEditingStudent(null)}
            onUpdateStudent={onUpdateStudent}
          />
        )}
        {selectedReceipt && (
          <Modal title="View receipt" onClose={() => setSelectedReceipt(null)}>
            <div className="flex justify-center p-4">
              <img src={selectedReceipt} alt="Receipt" className="max-w-full max-h-[60vh] rounded-xl object-contain" />
            </div>
          </Modal>
        )}
        {showAddLevel && (
          <Modal title="افزودن سطح و شهریه" onClose={() => setShowAddLevel(false)}>
            <form onSubmit={async (e) => {
              e.preventDefault();
              const f = new FormData(e.currentTarget);
              await onAddLevel({
                name: f.get('name') as string,
                fee: Number(f.get('fee'))
              });
              setShowAddLevel(false);
            }} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs text-gray-500 px-1">نام سطح آموزشی</label>
                <input name="name" required placeholder="Pre-Intermediate 1" className="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 focus:border-red-500/50 outline-none" />
              </div>
              <div className="space-y-1">
                <label className="text-xs text-gray-500 px-1">مبلغ شهریه پایه (تومان)</label>
                <input name="fee" type="number" required placeholder="1,500,000" className="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 focus:border-red-500/50 outline-none" />
              </div>
              <button type="submit" className="w-full bg-red-600 py-3 rounded-xl font-bold red-glow mt-2 shadow-lg">Save level</button>
            </form>
          </Modal>
        )}
        {showAddTerm && (
          <Modal title="افزودن ترم جدید" onClose={() => setShowAddTerm(false)}>
            <form onSubmit={async (e) => {
              e.preventDefault();
              await onAddTerm((e.currentTarget.elements.namedItem('name') as HTMLInputElement).value);
              setShowAddTerm(false);
            }} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs text-[#8B7E74] px-1 font-medium">نام ترم</label>
                <input name="name" required placeholder="Spring 1403" className="w-full bg-white border border-[#E5E1DA] text-[#2D2424] rounded-xl py-3 px-4 focus:border-[#2D2424] outline-none text-sm" />
              </div>
              <button type="submit" className="w-full bg-[#2D2424] text-[#FCF9F6] py-3.5 rounded-full font-medium hover:bg-[#4A3D3D] mt-2 shadow-sm text-xs uppercase tracking-wider cursor-pointer">Save term</button>
            </form>
          </Modal>
        )}
        {editingTerm && (
          <Modal title="ویرایش ترم" onClose={() => setEditingTerm(null)}>
            <form onSubmit={async (e) => {
              e.preventDefault();
              await onUpdateTerm(editingTerm.id, (e.currentTarget.elements.namedItem('name') as HTMLInputElement).value);
              setEditingTerm(null);
            }} className="space-y-4">
              <div className="space-y-1">
                <label className="text-xs text-[#8B7E74] px-1 font-medium">نام ترم</label>
                <input name="name" required defaultValue={editingTerm.name} placeholder="Spring 1403" className="w-full bg-white border border-[#E5E1DA] text-[#2D2424] rounded-xl py-3 px-4 focus:border-[#2D2424] outline-none text-sm" />
              </div>
              <button type="submit" className="w-full bg-[#2D2424] text-[#FCF9F6] py-3.5 rounded-full font-medium hover:bg-[#4A3D3D] mt-2 shadow-sm text-xs uppercase tracking-wider cursor-pointer">Update term</button>
            </form>
          </Modal>
        )}
        {showAddStudent && (
          <Modal title="ثبت‌نام دانشجو" onClose={() => {
            setShowAddStudent(false);
            setAddHasBook(false);
            setAddBookName('');
            setAddBookPrice('');
            setAddHasInterview(false);
            setAddHasDiscount(false);
            setAddDiscountPercent('');
            setAddHasExtraCourse(false);
            setAddExtraCourseName('');
            setAddExtraCoursePrice('');
            setOngoingClassId('');
            setAddClassType('حضوری');
            setSelectedLevelInfo(null);
          }}>
            <form onSubmit={async (e) => {
                  e.preventDefault();
                  if (isSubmitting) return;
                  
                  const f = new FormData(e.currentTarget);
                  const tid = f.get('tid') as string;
                  const lv = (f.get('lv') as string) || '';
                  const panelClassId = (f.get('panelClassId') as string) || '';

                  if (!tid) {
                    alert('لطفا ابتدا یک ترم انتخاب کنید. اگر ترمی وجود ندارد، ابتدا آن را بسازید.');
                    return;
                  }
                  if (!panelClassId && !lv) {
                    alert('یک کلاس جاری پنل انتخاب کنید، یا اگر کلاس باز متناسب نبود از همه سطوح یک سطح بگیرید.');
                    return;
                  }

                  setIsSubmitting(true);
                  try {
                    let receiptUrl = '';
                    const file = f.get('receipt') as File;
                    if (file && file.size > 0) {
                      receiptUrl = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result as string);
                        reader.onerror = reject;
                        reader.readAsDataURL(file);
                      });
                    }

                    const baseFee = selectedLevelInfo?.fee || 0;
                    const discountPctNum = addHasDiscount ? (Number(addDiscountPercent) || 0) : 0;
                    const discountAmt = Math.round(baseFee * (discountPctNum / 100));

                    const panelCls = (panelClasses || []).find((c: any) => String(c.id) === String(panelClassId));
                    const matched = panelCls ? matchLevelFromTitle(panelCls.title, levels) : null;
                    const levelName = panelCls
                      ? (matched?.name || panelCls.title)
                      : lv;

                    await onAddStudent({
                      firstName: f.get('f') as string,
                      lastName: f.get('l') as string,
                      level: levelName,
                      classType: addClassType as any,
                      phone: f.get('ph') as string,
                      studentCode: (f.get('studentCode') as string || '').trim(),
                      totalPayable: Number(f.get('t')),
                      amountPaid: Number(f.get('p')),
                      termId: tid,
                      receiptUrl,
                      hasBook: addHasBook,
                      bookName: addHasBook ? addBookName : '',
                      bookPrice: addHasBook ? (Number(addBookPrice) || 0) : 0,
                      hasInterview: addHasInterview,
                      hasDiscount: addHasDiscount && discountPctNum > 0,
                      discountPercent: addHasDiscount ? discountPctNum : 0,
                      discountAmount: addHasDiscount ? discountAmt : 0,
                      hasExtraCourse: addHasExtraCourse,
                      extraCourseName: addHasExtraCourse ? addExtraCourseName : '',
                      extraCoursePrice: addHasExtraCourse ? (Number(addExtraCoursePrice) || 0) : 0,
                      panelClassId: panelClassId ? Number(panelClassId) : 0
                    } as any);
                    setShowAddStudent(false);
                    setAddHasBook(false);
                    setAddBookName('');
                    setAddBookPrice('');
                    setAddHasInterview(false);
                    setAddHasDiscount(false);
                    setAddDiscountPercent('');
                    setAddHasExtraCourse(false);
                    setAddExtraCourseName('');
                    setAddExtraCoursePrice('');
                    setOngoingClassId('');
                    setAddClassType('حضوری');
                    setSelectedLevelInfo(null);
                  } catch (err: any) {
                    console.error('Registration Error:', err);
                    alert('خطا در ثبت دانشجو: ' + (err.message || 'خطای نامشخص'));
                  } finally {
                    setIsSubmitting(false);
                  }
                }} className="space-y-4 max-h-[60vh] overflow-y-auto px-1 custom-scrollbar">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">نام</label>
                      <input name="f" required placeholder="علی" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm" />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">نام خانوادگی</label>
                      <input name="l" required placeholder="محمدی" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm" />
                    </div>
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 px-1 font-medium">شماره تماس (اختیاری)</label>
                    <input name="ph" placeholder="0912..." className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm text-left font-mono" dir="ltr" />
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 px-1 font-medium">شماره پرونده (خالی بماند تا از پنل ساخته شود)</label>
                    <input name="studentCode" placeholder="مثلاً 1692" dir="ltr" className="w-full bg-[#12121C] border border-red-900/40 text-amber-200 rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm text-left font-mono" />
                    <p className="text-[10px] text-slate-500 px-1">فقط اطلاعات فردی را وارد کنید. شماره پرونده، نام کاربری و رمز سایت و پنل همان عدد است.</p>
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-slate-400 px-1 font-medium">نوع کلاس</label>
                    <select name="ct" required value={addClassType} onChange={(e) => setAddClassType(e.target.value)} className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm cursor-pointer">
                      <option value="حضوری">حضوری</option>
                      <option value="آنلاین">آنلاین</option>
                      <option value="منتورینگ">منتورینگ</option>
                      <option value="آلمانی">آلمانی</option>
                      <option value="خصوصی">خصوصی</option>
                    </select>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">انتخاب ترم</label>
                      <select name="tid" required defaultValue={selectedTermId || ''} className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm cursor-pointer">
                        <option value="" disabled className="text-slate-500">انتخاب ترم...</option>
                        {terms.map((t: Term, idx: number) => <option key={`add-std-term-${t.id || idx}`} value={t.id}>{termSelectLabel(t)}</option>)}
                      </select>
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">همه سطوح</label>
                      <select 
                        name="lv" 
                        value={ongoingClassId ? '' : (selectedLevelInfo?.name || '')}
                        disabled={!!ongoingClassId}
                        onChange={(e) => {
                          setOngoingClassId('');
                          const level = levels.find((l: Level) => l.name === e.target.value);
                          if (level) setSelectedLevelInfo({ name: level.name, fee: level.fee });
                          else setSelectedLevelInfo(null);
                        }}
                        className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-2.5 px-3 focus:border-red-500/50 outline-none text-sm cursor-pointer disabled:opacity-50"
                      >
                        <option value="" className="text-slate-500">انتخاب سطح...</option>
                        {levels.map((l: Level, idx: number) => <option key={`add-std-lvl-${l.id || (l as any)._id || idx}`} value={l.name}>{l.name}</option>)}
                      </select>
                      <p className="text-[10px] text-slate-500 px-1">اگر کلاس باز متناسب نبود، از اینجا سطح را بگیرید؛ فقط به لیست دانش‌آموزان پنل اضافه می‌شود.</p>
                    </div>
                  </div>
                  <div className="space-y-1">
                    <label className="text-[10px] text-amber-300/90 px-1 font-medium">کلاس‌های باز پنل (ongoing)</label>
                    <select
                      name="panelClassId"
                      value={ongoingClassId}
                      onChange={(e) => {
                        const id = e.target.value;
                        setOngoingClassId(id);
                        if (!id) return;
                        const cls = (panelClasses || []).find((c: any) => String(c.id) === String(id));
                        if (!cls) return;
                        if (cls.classType) setAddClassType(cls.classType);
                        const matched = matchLevelFromTitle(cls.title, levels);
                        if (matched) setSelectedLevelInfo({ name: matched.name, fee: matched.fee });
                        else setSelectedLevelInfo({ name: cls.title, fee: 0 });
                      }}
                      className="w-full bg-[#12121C] border border-amber-700/40 text-white rounded-xl py-2.5 px-3 focus:border-amber-500/50 outline-none text-sm cursor-pointer"
                    >
                      <option value="" className="text-slate-500">اگر کلاس باز دارید از اینجا انتخاب کنید...</option>
                      {(panelClasses || []).map((c: any) => (
                        <option key={`pc-${c.id}`} value={c.id}>
                          {(c.term_name ? c.term_name + ' · ' : '') + c.title + (c.classType ? ' · ' + c.classType : '')}
                        </option>
                      ))}
                    </select>
                    <p className="text-[10px] text-amber-500/80 px-1">با انتخاب کلاس باز، دانش‌آموز همان کلاس در پنل ثبت می‌شود.</p>
                  </div>

                  {/* بخش افزوده: تخفیف، کتاب و Interview */}
                  <div className="bg-[#12121C]/80 border border-red-900/40 rounded-xl p-3.5 space-y-3">
                    <div className="text-xs font-bold text-slate-200 border-b border-red-900/30 pb-2">
                      خدمات، تخفیف و افزوده مالی
                    </div>

                    {/* 1. تیک تخفیف */}
                    <div className="space-y-2">
                      <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
                        <input
                          type="checkbox"
                          checked={addHasDiscount}
                          onChange={(e) => setAddHasDiscount(e.target.checked)}
                          className="w-4 h-4 accent-red-600 rounded cursor-pointer"
                        />
                        <span>تخفیف روی شهریه ترم</span>
                      </label>

                      {addHasDiscount && (
                        <div className="pt-1 bg-[#0A0A10] p-3 rounded-lg border border-red-900/30 space-y-1">
                          <label className="text-[10px] text-rose-300 font-medium">درصد تخفیف (فقط از شهریه ترم کسر می‌شود)</label>
                          <div className="flex items-center gap-2">
                            <input
                              type="number"
                              min="0"
                              max="100"
                              required={addHasDiscount}
                              value={addDiscountPercent}
                              onChange={(e) => setAddDiscountPercent(e.target.value)}
                              placeholder="e.g. 10"
                              className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none font-mono"
                            />
                            <span className="text-xs font-bold text-rose-400">%</span>
                          </div>
                          {selectedLevelInfo && (
                            <div className="text-[10px] text-rose-400 mt-1">
                              مبلغ تخفیف: {Math.round((selectedLevelInfo.fee || 0) * ((Number(addDiscountPercent) || 0) / 100)).toLocaleString('en-US')} تومان
                            </div>
                          )}
                        </div>
                      )}
                    </div>

                    {/* 2. تیک کتاب */}
                    <div className="space-y-2 pt-2 border-t border-red-900/30">
                      <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
                        <input
                          type="checkbox"
                          checked={addHasBook}
                          onChange={(e) => setAddHasBook(e.target.checked)}
                          className="w-4 h-4 accent-red-600 rounded cursor-pointer"
                        />
                        <span>کتاب</span>
                      </label>

                      {addHasBook && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 bg-[#0A0A10] p-3 rounded-lg border border-red-900/30">
                          <div className="space-y-1">
                            <label className="text-[10px] text-slate-400 font-medium">اسم کتاب</label>
                            <input
                              type="text"
                              required={addHasBook}
                              value={addBookName}
                              onChange={(e) => setAddBookName(e.target.value)}
                              placeholder="مثال: Family & Friends"
                              className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                            />
                          </div>
                          <div className="space-y-1">
                            <label className="text-[10px] text-slate-400 font-medium">مبلغ کتاب (تومان)</label>
                            <input
                              type="number"
                              required={addHasBook}
                              value={addBookPrice}
                              onChange={(e) => setAddBookPrice(e.target.value)}
                              placeholder="تومان"
                              className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none font-mono"
                            />
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Extra Course */}
                    <div className="space-y-2 pt-2 border-t border-red-900/30">
                      <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
                        <input
                          type="checkbox"
                          checked={addHasExtraCourse}
                          onChange={(e) => setAddHasExtraCourse(e.target.checked)}
                          className="w-4 h-4 accent-red-600 rounded cursor-pointer"
                        />
                        <span>Extra Course</span>
                      </label>

                      {addHasExtraCourse && (
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 bg-[#0A0A10] p-3 rounded-lg border border-fuchsia-500/20">
                          <div className="space-y-1">
                            <label className="text-[10px] text-slate-400 font-medium">اسم دوره</label>
                            <input
                              type="text"
                              required={addHasExtraCourse}
                              value={addExtraCourseName}
                              onChange={(e) => setAddExtraCourseName(e.target.value)}
                              placeholder="مثال: Conversation"
                              className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none"
                            />
                          </div>
                          <div className="space-y-1">
                            <label className="text-[10px] text-slate-400 font-medium">هزینه دوره (تومان)</label>
                            <input
                              type="number"
                              required={addHasExtraCourse}
                              value={addExtraCoursePrice}
                              onChange={(e) => setAddExtraCoursePrice(e.target.value)}
                              placeholder="تومان"
                              className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-lg py-2 px-3 text-xs focus:border-red-500 outline-none font-mono"
                            />
                          </div>
                        </div>
                      )}
                    </div>

                    {/* 3. تیک interview */}
                    <div className="pt-2 border-t border-red-900/30 flex items-center justify-between">
                      <label className="flex items-center gap-2.5 text-sm text-slate-200 font-medium cursor-pointer select-none">
                        <input
                          type="checkbox"
                          checked={addHasInterview}
                          onChange={(e) => setAddHasInterview(e.target.checked)}
                          className="w-4 h-4 accent-red-600 rounded cursor-pointer"
                        />
                        <span>interview</span>
                      </label>
                      <span className={`text-xs font-semibold ${addHasInterview ? 'text-emerald-400' : 'text-slate-400'}`}>
                        250,000 تومان {addHasInterview ? '(افزوده شد)' : ''}
                      </span>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">شهریه کل (محاسبه شده)</label>
                      <input 
                        name="t" 
                        type="number" 
                        required 
                        placeholder="تومان" 
                        key={`payable-${selectedLevelInfo?.fee}-${addHasDiscount}-${addDiscountPercent}-${addHasBook}-${addBookPrice}-${addHasInterview}-${addHasExtraCourse}-${addExtraCoursePrice}`}
                        defaultValue={(() => {
                          const base = selectedLevelInfo?.fee || 0;
                          const discPct = addHasDiscount ? (Number(addDiscountPercent) || 0) : 0;
                          const discAmt = Math.round(base * (discPct / 100));
                          const discBase = Math.max(0, base - discAmt);
                          const bookP = addHasBook ? (Number(addBookPrice) || 0) : 0;
                          const interviewP = addHasInterview ? 250000 : 0;
                          const extraP = addHasExtraCourse ? (Number(addExtraCoursePrice) || 0) : 0;
                          return discBase + bookP + interviewP + extraP;
                        })()}
                        className="w-full bg-[#0A0A10] border border-red-500/40 text-red-400 font-mono font-bold rounded-xl py-2.5 px-3 focus:border-red-500 outline-none text-sm" 
                      />
                    </div>
                    <div className="space-y-1">
                      <label className="text-[10px] text-slate-400 px-1 font-medium">مبلغ پرداختی اولیه</label>
                      <input name="p" type="number" required placeholder="تومان" className="w-full bg-[#12121C] border border-red-900/40 text-white font-mono rounded-xl py-2.5 px-3 focus:border-red-500 outline-none text-sm" />
                    </div>
                  </div>
                  <div className="space-y-1 mt-2">
                    <label className="text-[10px] text-slate-400 px-1 font-medium">آپلود رسید (اختیاری)</label>
                    <input name="receipt" type="file" accept="image/*" className="w-full bg-[#12121C] border border-red-900/40 rounded-xl py-2 px-3 focus:border-red-500 outline-none text-xs text-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-red-600 file:text-white hover:file:bg-red-700 transition-all cursor-pointer" />
                  </div>
                  <button 
                    type="submit" 
                    disabled={isSubmitting}
                    className={`w-full py-3.5 rounded-full font-bold mt-4 shadow-[0_0_20px_rgba(220,38,38,0.4)] transition-all text-xs uppercase tracking-wider cursor-pointer ${isSubmitting ? 'bg-slate-700 text-slate-400 cursor-not-allowed' : 'bg-gradient-to-r from-red-600 to-rose-700 text-white hover:brightness-110'}`}
                  >
                    {isSubmitting ? 'Saving...' : 'Save student'}
                  </button>
                </form>
          </Modal>
        )}
        {receiptModalStudent && (
          <Modal title={`مدیریت رسیدها: ${receiptModalStudent.firstName} ${receiptModalStudent.lastName}`} onClose={() => { setReceiptModalStudent(null); setReceiptAmount(''); }}>
            <div className="space-y-5">

              {/* خلاصه پرونده مالی و خدمات افزوده */}
              <div className="bg-[#12121C] border border-red-900/40 rounded-2xl p-4 space-y-3">
                <div className="flex flex-wrap justify-between items-center text-xs text-slate-400 gap-2">
                  <span>سطح: <strong className="text-white">{receiptModalStudent.level} ({receiptModalStudent.classType || 'نامشخص'})</strong></span>
                  <span>شماره تماس: <strong className="text-slate-200 font-mono">{receiptModalStudent.phone || '---'}</strong></span>
                </div>

                {/* اقلام فاکتور (تخفیف، کتاب و مصاحبه) */}
                <div className="space-y-1.5 pt-1 border-t border-red-900/30">
                  <div className="text-[11px] font-bold text-slate-200">خدمات و افزوده مالی بر روی فیش:</div>
                  <div className="flex flex-wrap gap-2">
                    {receiptModalStudent.hasDiscount && receiptModalStudent.discountPercent ? (
                      <span className="bg-rose-950/60 text-rose-300 border border-rose-500/30 text-[11px] px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
                        🏷️ تخفیف شهریه: {receiptModalStudent.discountPercent}% ({(receiptModalStudent.discountAmount || 0).toLocaleString('en-US')} تومان)
                      </span>
                    ) : null}

                    {receiptModalStudent.hasBook ? (
                      <span className="bg-amber-950/60 text-amber-300 border border-amber-500/30 text-[11px] px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
                        📖 کتاب: {receiptModalStudent.bookName || 'کتاب'} ({Number(receiptModalStudent.bookPrice || 0).toLocaleString('en-US')} تومان)
                      </span>
                    ) : (
                      <span className="bg-[#0A0A10] text-slate-500 border border-red-900/20 text-[10px] px-2.5 py-0.5 rounded-full">
                        بدون کتاب
                      </span>
                    )}

                    {receiptModalStudent.hasInterview ? (
                      <span className="bg-sky-950/60 text-sky-300 border border-sky-500/30 text-[11px] px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
                        🎙️ مصاحبه / Interview (250,000 تومان)
                      </span>
                    ) : (
                      <span className="bg-[#0A0A10] text-slate-500 border border-red-900/20 text-[10px] px-2.5 py-0.5 rounded-full">
                        بدون مصاحبه
                      </span>
                    )}

                    {(receiptModalStudent as any).hasExtraCourse ? (
                      <span className="bg-fuchsia-950/70 text-fuchsia-300 border border-fuchsia-500/40 text-[11px] px-2.5 py-1 rounded-full font-medium flex items-center gap-1">
                        Course: {(receiptModalStudent as any).extraCourseName || 'Extra Course'} ({Number((receiptModalStudent as any).extraCoursePrice || 0).toLocaleString('en-US')} تومان)
                      </span>
                    ) : (
                      <span className="bg-[#0A0A10] text-slate-500 border border-red-900/20 text-[10px] px-2.5 py-0.5 rounded-full">
                        بدون Extra Course
                      </span>
                    )}
                  </div>
                </div>

                {/* خلاصه مالی */}
                <div className="grid grid-cols-3 gap-2 pt-2 border-t border-red-900/30 text-center">
                  <div className="bg-[#0A0A10] p-2 rounded-xl border border-red-900/30">
                    <div className="text-[10px] text-slate-400">شهریه کل (با خدمات)</div>
                    <div className="text-xs font-bold text-white font-mono mt-0.5">{Number(receiptModalStudent.totalPayable).toLocaleString('en-US')} <span className="text-[9px] font-normal text-slate-400">تومان</span></div>
                  </div>
                  <div className="bg-[#0A0A10] p-2 rounded-xl border border-red-900/30">
                    <div className="text-[10px] text-slate-400">پرداختی تا کنون</div>
                    <div className="text-xs font-bold text-emerald-400 font-mono mt-0.5">{Number(receiptModalStudent.amountPaid).toLocaleString('en-US')} <span className="text-[9px] font-normal text-slate-400">تومان</span></div>
                  </div>
                  <div className="bg-[#0A0A10] p-2 rounded-xl border border-red-900/30">
                    <div className="text-[10px] text-slate-400">مانده بدهی</div>
                    <div className="text-xs font-bold text-rose-400 font-mono mt-0.5">{Number(receiptModalStudent.debt).toLocaleString('en-US')} <span className="text-[9px] font-normal text-slate-400">تومان</span></div>
                  </div>
                </div>
              </div>

              {/* فرم صدور رسید جدید */}
              <form onSubmit={handleIssueNewReceipt} className="bg-[#12121C] p-4 rounded-2xl border border-red-900/40 space-y-3">
                <h4 className="text-sm font-serif font-medium text-white flex items-center gap-2"><Printer size={16}/> صدور رسید جدید</h4>
                <div className="space-y-2">
                  <div className="flex justify-between items-center">
                    <label className="text-[10px] text-slate-400 px-1 font-medium">مبلغ این رسید (تومان)</label>
                    {receiptModalStudent.debt > 0 && (
                      <button
                        type="button"
                        onClick={() => setReceiptAmount(String(receiptModalStudent.debt))}
                        className="text-[10px] text-emerald-400 hover:underline font-medium cursor-pointer"
                      >
                        Pay in full ({Number(receiptModalStudent.debt).toLocaleString('en-US')})
                      </button>
                    )}
                  </div>
                  <div className="flex gap-2">
                    <input 
                      type="number" 
                      required 
                      value={receiptAmount} 
                      onChange={(e) => setReceiptAmount(e.target.value)} 
                      placeholder="تومان" 
                      className="flex-1 bg-[#0A0A10] border border-red-900/40 text-white rounded-xl py-2 px-3 focus:border-red-500 outline-none text-sm font-bold font-mono" 
                    />
                    <button type="button" onClick={() => {
                      const date = new Date().toLocaleDateString('fa-IR-u-nu-latn');
                      printHTML(receiptModalStudent, 0, date);
                    }} className="bg-amber-950/50 border border-amber-500/30 text-amber-200 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0">
                      چاپ فاکتور
                    </button>
                    <button type="submit" className="bg-gradient-to-r from-red-600 to-rose-700 hover:brightness-110 text-white px-5 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 shadow-[0_0_15px_rgba(220,38,38,0.3)]">
                      Save & print
                    </button>
                  </div>
                </div>
              </form>

              <div>
                <h4 className="text-sm font-serif font-medium mb-3 text-white">تاریخچه رسیدهای صادر شده</h4>
                {isReceiptLoading ? (
                   <p className="text-center text-xs text-slate-400 py-4">در حال بارگذاری...</p>
                ) : studentReceipts.length === 0 ? (
                   <p className="text-center text-xs text-slate-400 py-4 bg-[#12121C] rounded-xl border border-red-900/30">تا کنون رسیدی برای این دانشجو صادر نشده است.</p>
                ) : (
                   <div className="space-y-2 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                     {studentReceipts.map((r, idx) => (
                       <div key={idx} className="flex justify-between items-center bg-[#12121C] p-3 rounded-xl border border-red-900/30 hover:border-red-500/40 transition-colors">
                         <div>
                           <div className="text-sm font-bold text-emerald-400 font-mono">{Number(r.paidAmount).toLocaleString('en-US')} <span className="text-[10px] text-slate-400 font-sans">تومان</span></div>
                           <div className="text-[10px] text-slate-400 mt-1">{r.date}</div>
                         </div>
                         <button 
                           onClick={(e) => { e.preventDefault(); printHTML(receiptModalStudent, r.paidAmount, r.date); }}
                           className="p-2 bg-red-950/40 hover:bg-red-600 text-red-300 hover:text-white rounded-full transition-colors cursor-pointer"
                           title="Reprint"
                           type="button"
                         >
                           <Printer size={16} />
                         </button>
                       </div>
                     ))}
                   </div>
                )}
              </div>
            </div>
          </Modal>
        )}
      </AnimatePresence>
    </div>
  );
}

function PayrollSection({ salaries: rawSalaries, terms, searchQuery, onAddSalary, onUpdateSalary, onDeleteSalary }: any) {
  const [showAddSalary, setShowAddSalary] = useState(false);
  const [editingSalary, setEditingSalary] = useState<TeacherSalary | null>(null);
  const [selectedTermId, setSelectedTermId] = usePersistedTerm(terms, true);
  const [selectedRole, setSelectedRole] = useState<string>('all');
  const [selectedReceipt, setSelectedReceipt] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const availableRoles = Array.from(
    new Set(
      rawSalaries
        .map((s: TeacherSalary) => s.role?.trim())
        .filter((r: string | undefined): r is string => Boolean(r))
    )
  ) as string[];

  const query = searchQuery ? searchQuery.trim().toLowerCase() : '';
  const allSalaries = query
    ? rawSalaries.filter((s: TeacherSalary) =>
        s.teacherName?.toLowerCase().includes(query) ||
        s.role?.toLowerCase().includes(query) ||
        s.month?.toLowerCase().includes(query) ||
        s.status?.toLowerCase().includes(query)
      )
    : rawSalaries;

  let salaries = selectedTermId && selectedTermId !== 'all' ? allSalaries.filter((s: any) => String(s.termId) === String(selectedTermId)) : allSalaries;

  if (selectedRole && selectedRole !== 'all') {
    salaries = salaries.filter((s: any) => s.role?.trim() === selectedRole);
  }

  const handleProcessSalary = async (e: React.FormEvent<HTMLFormElement>, isEdit: boolean) => {
    e.preventDefault();
    if (isSubmitting) return;
    setIsSubmitting(true);

    try {
      const f = new FormData(e.currentTarget);
      const tId = f.get('tId') as string;
      
      let receiptUrl = (isEdit && editingSalary?.receiptUrl) || '';
      const file = f.get('receipt') as File;
      if (file && file.size > 0) {
        receiptUrl = await new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result as string);
          reader.readAsDataURL(file);
        });
      }

      const salaryData = {
        teacherName: f.get('n') as string,
        role: f.get('r') as string,
        amount: Number(f.get('a')),
        month: f.get('m') as string,
        status: f.get('s') as string || 'unpaid',
        termId: tId,
        receiptUrl
      };

      if (isEdit && editingSalary) {
        await onUpdateSalary(editingSalary.id, salaryData);
        setEditingSalary(null);
      } else {
        await onAddSalary(salaryData);
        setShowAddSalary(false);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-6 lg:space-y-8" dir="rtl">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-red-900/30 pb-6">
        <div>
          <h2 className="text-2xl lg:text-3xl font-serif font-medium text-white">حقوق و دستمزد</h2>
          <p className="text-slate-400 mt-1 text-sm">مدیریت فیش‌های قانونی اساتید آکادمی غزال</p>
        </div>
        <div className="flex flex-wrap items-center gap-3 w-full sm:w-auto">
          <TermPicker
            terms={terms || []}
            value={selectedTermId}
            onChange={setSelectedTermId}
            allowAll
            className="flex-1 sm:flex-none"
          />
          <select 
            value={selectedRole}
            onChange={(e) => setSelectedRole(e.target.value)}
            className="flex-1 sm:flex-none bg-[#12121C] border border-red-900/40 rounded-full py-2.5 px-4 focus:border-red-500 outline-none text-slate-200 cursor-pointer text-sm font-medium min-w-[140px]"
          >
            <option value="all">همه نقش‌ها / سمت‌ها</option>
            {availableRoles.map((role: string) => (
              <option key={role} value={role}>{role}</option>
            ))}
          </select>
          <button onClick={() => setShowAddSalary(true)} className="flex-1 sm:flex-none bg-gradient-to-r from-red-600 to-rose-700 text-white px-5 py-2.5 rounded-full font-bold flex items-center justify-center gap-2 hover:brightness-110 transition-all text-sm cursor-pointer shadow-[0_0_20px_rgba(220,38,38,0.4)]">
            <Plus size={16} /> New payslip
          </button>
        </div>
      </div>
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
        <div className="lg:col-span-3 bg-[#0F0F18]/90 backdrop-blur-xl p-6 lg:p-8 rounded-3xl border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)] order-2 lg:order-1">
          <h4 className="text-xl font-serif font-medium text-white mb-6">لیست فیش‌های صادره</h4>
          <div className="space-y-4 max-h-[500px] overflow-y-auto custom-scrollbar pl-2">
            {salaries.length === 0 ? (
               <div className="text-center py-20 bg-[#12121C]/60 rounded-3xl border border-dashed border-red-900/30">
                 <Wallet className="mx-auto text-slate-500 mb-4" size={40} />
                 <p className="text-slate-400 text-sm italic">هنوز هیچ فیش حقوقی صادر نشده است.</p>
               </div>
            ) : (
              salaries.map((s: TeacherSalary) => (
                <div key={s.id} className="flex flex-col sm:flex-row items-start sm:items-center justify-between p-5 bg-[#12121C] rounded-2xl border border-red-900/30 hover:border-red-500/40 transition-all group">
                  <div className="flex items-center gap-4 mb-3 sm:mb-0">
                    <div className="w-10 h-10 rounded-2xl bg-red-950/50 border border-red-500/30 text-red-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                      <Users size={18} />
                    </div>
                    <div>
                      <p className="font-medium text-base text-white">{s.teacherName}</p>
                      <p className="text-[10px] text-slate-400 uppercase tracking-wider">{s.role || s.month} • {terms.find((t:any) => String(t.id) === String(s.termId))?.name || 'نامشخص'}</p>
                    </div>
                  </div>
                  <div className="w-full sm:w-auto flex justify-between sm:items-center gap-4">
                    <div className="sm:text-left">
                      <p className="font-mono font-bold text-sm lg:text-base text-white">{s.amount.toLocaleString('en-US')} <span className="text-[10px] font-normal text-slate-400">تومان</span></p>
                      <span className={`text-[10px] font-medium px-2.5 py-0.5 rounded-full inline-block mt-1 border ${s.status === 'paid' ? 'bg-emerald-950/60 text-emerald-400 border-emerald-500/40' : 'bg-amber-950/60 text-amber-400 border-amber-500/40'}`}>
                        {s.status === 'paid' ? 'Paid' : 'Pending'}
                      </span>
                    </div>
                    <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      {s.receiptUrl && (
                        <button onClick={() => setSelectedReceipt(s.receiptUrl || null)} className="p-2 hover:bg-red-900/40 rounded-lg text-emerald-400" title="View receipt">
                          <Receipt size={16} />
                        </button>
                      )}
                      <button onClick={() => setEditingSalary(s)} className="p-2 hover:bg-red-900/40 rounded-lg text-sky-400" title="Edit">
                        <Pencil size={16} />
                      </button>
                      <button onClick={() => onDeleteSalary(s.id)} className="p-2 hover:bg-red-900/40 rounded-lg text-rose-400" title="Delete">
                        <Trash2 size={16} />
                      </button>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>
        <div className="lg:col-span-2 space-y-6 order-1 lg:order-2">
          <div className="bg-[#0F0F18]/90 backdrop-blur-xl p-8 rounded-3xl flex flex-col items-center justify-center text-center border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)] min-h-[250px] relative overflow-hidden group">
             <div className="absolute top-0 right-0 p-8 opacity-10 text-red-500 -mr-4 -mt-4 group-hover:scale-110 transition-transform duration-700">
               <Wallet size={120} />
             </div>
             <div className="w-14 h-14 rounded-2xl bg-red-950/50 border border-red-500/30 text-red-400 flex items-center justify-center mb-5 shadow-[0_0_15px_rgba(220,38,38,0.3)]">
               <TrendingUp size={28} />
             </div>
             <h4 className="text-slate-400 text-sm font-medium">مجموع هزینه‌های دفتری اساتید</h4>
             <h3 className="text-3xl lg:text-4xl font-bold mt-3 text-red-400 font-mono tracking-tight">{salaries.reduce((a: number, b: any) => a + b.amount, 0).toLocaleString('en-US')} <span className="text-xs font-normal text-slate-400">تومان</span></h3>
             <div className="mt-6 flex items-center gap-2">
               <span className="w-2 h-2 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
               <p className="text-[10px] text-slate-500 font-mono tracking-wider">GHAZAL FINANCIAL REPORT</p>
             </div>
          </div>
          <div className="bg-[#0F0F18]/90 backdrop-blur-xl p-6 rounded-3xl border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)] text-center">
            <p className="text-xs text-slate-400 mb-1">تعداد فیش‌های ثبت شده این دوره</p>
            <p className="text-2xl font-bold text-white font-mono">{salaries.length} <span className="text-xs font-normal text-slate-400">عدد</span></p>
          </div>
        </div>
      </div>

      <AnimatePresence>
        {(showAddSalary || editingSalary) && (
          <Modal title={editingSalary ? "ویرایش فیش حقوقی" : "صدور فیش حقوقی"} onClose={() => { setShowAddSalary(false); setEditingSalary(null); }}>
             <form onSubmit={(e) => handleProcessSalary(e, !!editingSalary)} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">نام و نام خانوادگی استاد</label>
                  <input name="n" required defaultValue={editingSalary?.teacherName} placeholder="استاد علوی" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 text-sm" />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">سمت / دپارتمان</label>
                  <input name="r" defaultValue={editingSalary?.role} placeholder="مدیر گروه زبان" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 text-sm" />
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">انتخاب ترم</label>
                  <select name="tId" required defaultValue={editingSalary?.termId || (selectedTermId && selectedTermId !== 'all' ? selectedTermId : '')} className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 focus:border-red-500 outline-none text-sm cursor-pointer">
                    <option value="" disabled className="text-slate-500">انتخاب ترم...</option>
                    {terms.map((t: any) => <option key={t.id} value={t.id}>{termSelectLabel(t)}</option>)}
                  </select>
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">وضعیت پرداخت</label>
                  <select name="s" required defaultValue={editingSalary?.status || 'unpaid'} className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 focus:border-red-500 outline-none text-sm cursor-pointer">
                    <option value="unpaid">Pending</option>
                    <option value="paid">Paid</option>
                  </select>
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">مبلغ خالص دریافتی (تومان)</label>
                  <input name="a" type="number" required defaultValue={editingSalary?.amount} placeholder="12,000,000" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 font-mono text-sm" />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">توضیحات / ماه</label>
                  <input name="m" defaultValue={editingSalary?.month} placeholder="Ordibehesht 1403" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 text-sm" />
                </div>
              </div>
              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 px-1 font-medium">آپلود رسید پرداخت (اختیاری)</label>
                <input name="receipt" type="file" accept="image/*" className="w-full bg-[#12121C] border border-red-900/40 rounded-xl py-2 px-3 focus:border-red-500 outline-none text-xs text-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-red-600 file:text-white hover:file:bg-red-700 transition-all cursor-pointer" />
              </div>
              <button type="submit" disabled={isSubmitting} className="w-full bg-gradient-to-r from-red-600 to-rose-700 text-white py-3.5 rounded-full font-bold hover:brightness-110 mt-2 shadow-[0_0_20px_rgba(220,38,38,0.4)] transition-all text-xs uppercase tracking-wider cursor-pointer disabled:opacity-50">
                {isSubmitting ? "Saving..." : (editingSalary ? "Save changes" : "Issue payslip")}
              </button>
            </form>
          </Modal>
        )}
        {selectedReceipt && (
          <Modal title="View receipt" onClose={() => setSelectedReceipt(null)}>
            <div className="flex justify-center p-4">
              <img src={selectedReceipt} alt="Receipt" className="max-w-full max-h-[60vh] rounded-2xl object-contain shadow-xl" />
            </div>
          </Modal>
        )}
      </AnimatePresence>
    </div>
  );
}

function ExpensesSection({ expenses: rawExpenses, terms, searchQuery, onAddExpense, onUpdateExpense, onDeleteExpense }: any) {
  const [showAdd, setShowAdd] = useState(false);
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
  const [selectedTermId, setSelectedTermId] = usePersistedTerm(terms, true);
  const [selectedReceipt, setSelectedReceipt] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const query = searchQuery ? searchQuery.trim().toLowerCase() : '';
  const allExpenses = query
    ? rawExpenses.filter((e: Expense) =>
        e.title?.toLowerCase().includes(query) ||
        e.category?.toLowerCase().includes(query) ||
        e.date?.toLowerCase().includes(query)
      )
    : rawExpenses;

  const expenses = selectedTermId && selectedTermId !== 'all' ? allExpenses.filter((e: any) => String(e.termId) === String(selectedTermId)) : allExpenses;

  const handleProcessExpense = async (e: React.FormEvent<HTMLFormElement>, isEdit: boolean) => {
    e.preventDefault();
    if (isSubmitting) return;
    setIsSubmitting(true);

    try {
      const f = new FormData(e.currentTarget);
      const tId = f.get('tId') as string;
      
      let receiptUrl = (isEdit && editingExpense?.receiptUrl) || '';
      const file = f.get('receipt') as File;
      if (file && file.size > 0) {
        receiptUrl = await new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result as string);
          reader.readAsDataURL(file);
        });
      }

      const expenseData = {
        title: f.get('t') as string,
        category: f.get('c') as string,
        amount: Number(f.get('a')),
        date: editingExpense?.date || new Date().toLocaleDateString('fa-IR-u-nu-latn'),
        termId: tId,
        receiptUrl
      };

      if (isEdit && editingExpense) {
        await onUpdateExpense(editingExpense.id, expenseData);
        setEditingExpense(null);
      } else {
        await onAddExpense(expenseData);
        setShowAdd(false);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="space-y-6 lg:space-y-8" dir="rtl">
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 border-b border-red-900/30 pb-6">
        <div>
          <h2 className="text-2xl lg:text-3xl font-serif font-medium text-white">مدیریت مخارج</h2>
          <p className="text-slate-400 mt-1 text-sm">ثبت و بایگانی هزینه‌های جاری آموزشگاه</p>
        </div>
        <div className="flex items-center gap-3 w-full sm:w-auto">
          <TermPicker
            terms={terms || []}
            value={selectedTermId}
            onChange={setSelectedTermId}
            allowAll
            className="flex-1 sm:flex-none"
          />
          <button onClick={() => setShowAdd(true)} className="flex-1 sm:flex-none bg-gradient-to-r from-red-600 to-rose-700 text-white px-5 py-2.5 rounded-full font-bold flex items-center justify-center gap-2 hover:brightness-110 transition-all text-sm cursor-pointer shadow-[0_0_20px_rgba(220,38,38,0.4)]">
            <Plus size={16} /> Add expense
          </button>
        </div>
      </div>
      <div className="bg-[#0F0F18]/90 backdrop-blur-xl rounded-3xl overflow-hidden border border-red-900/30 shadow-[0_0_30px_rgba(220,38,38,0.1)]">
        <div className="overflow-x-auto">
          <table className="w-full text-right min-w-[600px] border-collapse">
            <thead>
              <tr className="bg-[#12121C] text-slate-400 font-serif text-xs uppercase border-b border-red-900/30">
                <th className="px-8 py-4 font-medium">عنوان هزینه</th>
                <th className="px-8 py-4 font-medium">دسته‌بندی</th>
                <th className="px-8 py-4 font-medium text-center">مبلغ پرداختی</th>
                <th className="px-8 py-4 font-medium text-center">ترم / تاریخ</th>
                <th className="px-8 py-4 font-medium text-center">عملیات</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-red-900/20 text-sm">
              {expenses.length === 0 ? (
                 <tr><td colSpan={5} className="px-8 py-20 text-center text-slate-500 italic">هنوز هزینه‌ای در این دوره ثبت نشده است.</td></tr>
              ) : (
                expenses.map((e: Expense) => (
                  <tr key={e.id} className="hover:bg-[#12121C]/60 transition-colors group">
                    <td className="px-8 py-4">
                      <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-xl bg-red-950/50 border border-red-500/30 text-red-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                          <Receipt size={14} />
                        </div>
                        <span className="font-medium text-white">{e.title}</span>
                      </div>
                    </td>
                    <td className="px-8 py-4"><span className="bg-[#12121C] px-3 py-1 rounded-full text-[10px] border border-red-900/30 font-medium text-slate-300">{e.category}</span></td>
                    <td className="px-8 py-4 text-center font-mono font-bold text-rose-400 text-base">{e.amount.toLocaleString('en-US')} <span className="text-[10px] font-normal text-slate-400">تومان</span></td>
                    <td className="px-8 py-4 text-slate-400 text-xs font-mono text-center">
                      <div>{e.date}</div>
                      <div className="text-[10px] text-slate-500 mt-1 font-sans">{terms.find((t:any) => String(t.id) === String(e.termId))?.name || 'نامشخص'}</div>
                    </td>
                    <td className="px-8 py-4 text-center">
                      <div className="flex items-center justify-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                         {e.receiptUrl && (
                           <button onClick={() => setSelectedReceipt(e.receiptUrl || null)} className="p-2 hover:bg-red-900/40 rounded-lg text-emerald-400" title="View receipt">
                             <Receipt size={16} />
                           </button>
                         )}
                         <button onClick={() => setEditingExpense(e)} className="p-2 hover:bg-red-900/40 rounded-lg text-sky-400" title="Edit">
                           <Pencil size={16} />
                         </button>
                         <button onClick={() => onDeleteExpense(e.id)} className="p-2 hover:bg-red-900/40 rounded-lg text-rose-400" title="Delete">
                           <Trash2 size={16} />
                         </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      <AnimatePresence>
        {(showAdd || editingExpense) && (
          <Modal title={editingExpense ? "ویرایش هزینه" : "ثبت هزینه جدید"} onClose={() => { setShowAdd(false); setEditingExpense(null); }}>
             <form onSubmit={(e) => handleProcessExpense(e, !!editingExpense)} className="space-y-4">
              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 px-1 font-medium">شرح هزینه</label>
                <input name="t" required defaultValue={editingExpense?.title} placeholder="خرید ملزومات دفتری" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 text-sm" />
              </div>
              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 px-1 font-medium">انتخاب ترم</label>
                <select name="tId" required defaultValue={editingExpense?.termId || (selectedTermId && selectedTermId !== 'all' ? selectedTermId : '')} className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 focus:border-red-500 outline-none text-sm cursor-pointer">
                  <option value="" disabled className="text-slate-500">انتخاب ترم...</option>
                  {terms.map((t: any) => <option key={t.id} value={t.id}>{termSelectLabel(t)}</option>)}
                </select>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">دسته‌بندی</label>
                  <input name="c" required defaultValue={editingExpense?.category} placeholder="ملزومات / قبوض / تعمیرات" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 text-sm" />
                </div>
                <div className="space-y-1">
                  <label className="text-[10px] text-slate-400 px-1 font-medium">مبلغ فاکتور (تومان)</label>
                  <input name="a" type="number" required defaultValue={editingExpense?.amount} placeholder="تومان" className="w-full bg-[#12121C] border border-red-900/40 text-white rounded-xl py-3 px-4 outline-none focus:border-red-500 font-mono text-sm" />
                </div>
              </div>
              <div className="space-y-1">
                <label className="text-[10px] text-slate-400 px-1 font-medium">آپلود رسید / فاکتور (اختیاری)</label>
                <input name="receipt" type="file" accept="image/*" className="w-full bg-[#12121C] border border-red-900/40 rounded-xl py-2 px-3 focus:border-red-500 outline-none text-xs text-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-semibold file:bg-red-600 file:text-white hover:file:bg-red-700 transition-all cursor-pointer" />
              </div>
              <button type="submit" disabled={isSubmitting} className="w-full bg-gradient-to-r from-red-600 to-rose-700 text-white py-3.5 rounded-full font-bold hover:brightness-110 mt-4 shadow-[0_0_20px_rgba(220,38,38,0.4)] transition-all text-xs uppercase tracking-wider cursor-pointer disabled:opacity-50">
                {isSubmitting ? "Saving..." : (editingExpense ? "Save changes" : "Save expense")}
              </button>
            </form>
          </Modal>
        )}
        {selectedReceipt && (
          <Modal title="View receipt" onClose={() => setSelectedReceipt(null)}>
            <div className="flex justify-center p-4">
              <img src={selectedReceipt} alt="Receipt" className="max-w-full max-h-[60vh] rounded-2xl object-contain shadow-xl" />
            </div>
          </Modal>
        )}
      </AnimatePresence>
    </div>
  );
}

function Modal({ title, children, onClose }: { title: string, children: React.ReactNode, onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <motion.div 
        initial={{ opacity: 0 }} 
        animate={{ opacity: 1 }} 
        exit={{ opacity: 0 }} 
        onClick={(e) => {
          e.preventDefault();
          e.stopPropagation();
          onClose();
        }} 
        className="absolute inset-0 bg-black/75 backdrop-blur-md" 
      />
      <motion.div 
        initial={{ opacity: 0, scale: 0.95, y: 15 }}
        animate={{ opacity: 1, scale: 1, y: 0 }}
        exit={{ opacity: 0, scale: 0.95, y: 15 }}
        className="bg-[#0F0F18]/95 backdrop-blur-2xl text-slate-100 w-full max-w-lg p-6 lg:p-8 rounded-3xl relative z-10 border border-red-500/30 shadow-[0_0_50px_rgba(220,38,38,0.25)] max-h-[90vh] flex flex-col overflow-hidden"
        dir="rtl"
      >
        <div className="flex justify-between items-center mb-6 pb-4 border-b border-red-900/30 shrink-0">
          <div>
            <h3 className="text-xl lg:text-2xl font-serif font-medium text-white">{title}</h3>
          </div>
          <button 
            type="button"
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              onClose();
            }} 
            className="p-2.5 bg-red-950/40 border border-red-500/30 hover:bg-red-600 text-red-300 hover:text-white rounded-full transition-all cursor-pointer shrink-0 z-20 flex items-center justify-center shadow-[0_0_10px_rgba(220,38,38,0.2)]"
            aria-label="Close"
          >
            <X size={18} />
          </button>
        </div>
        
        <div className="relative overflow-y-auto custom-scrollbar pr-1">
          {children}
        </div>
      </motion.div>
    </div>
  );
}

