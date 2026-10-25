(function () {
    const root = document.querySelector('[data-qb-root]');
    if (!root) {
        return;
    }
    const ajax = root.getAttribute('data-ajax') || 'ajax-quiz-books.php';
    const form = document.getElementById('qbUploadForm');
    const alertBox = document.getElementById('qbAlert');
    const progress = document.getElementById('qbProgress');
    const progressText = document.getElementById('qbProgressText');
    const btn = document.getElementById('qbUploadBtn');
    const courseSel = document.getElementById('qbCourse');
    const hint = document.getElementById('qbCourseHint');
    let courses = {};
    try {
        courses = JSON.parse(root.getAttribute('data-courses') || '{}');
    } catch (err) {
        courses = {};
    }
    let busy = false;

    function toast(msg) {
        let el = document.querySelector('.qb-toast');
        if (!el) {
            el = document.createElement('div');
            el.className = 'qb-toast';
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(el._t);
        el._t = setTimeout(function () {
            el.classList.remove('show');
        }, 2800);
    }

    function showAlert(msg, ok) {
        if (!alertBox) {
            return;
        }
        alertBox.hidden = !msg;
        alertBox.className = 'qb-alert ' + (ok ? 'ok' : 'err');
        alertBox.textContent = msg || '';
    }

    function setProgress(on, text) {
        busy = !!on;
        if (progress) {
            progress.hidden = !on;
        }
        if (progressText && text) {
            progressText.textContent = text;
        }
        if (btn) {
            btn.disabled = !!on;
        }
    }

    function humanError(err, r, text) {
        const status = r ? r.status : 0;
        const raw = String(text || '').slice(0, 180);
        if (err && err.name === 'AbortError') {
            return 'درخواست طول کشید. صفحه را رفرش کنید و همان دکمه را دوباره بزنید.';
        }
        if (status === 413) {
            return 'حجم فایل از حد سرور بیشتر است. از ثبت روی سرور استفاده کنید، نه آپلود فرم.';
        }
        if (status === 401 || status === 403) {
            return 'نشست قطع شده. یک‌بار از پنل خارج شوید، دوباره وارد شوید و ثبت را بزنید.';
        }
        if (status >= 500) {
            return 'سرور وسط کار قطع شد. صفحه را رفرش کنید؛ اگر فایل در کتابخانه آمده «ادامه استخراج» را بزنید.';
        }
        if (raw.charAt(0) === '<' || /timeout|timed out|503|504/i.test(raw)) {
            return 'سرور پاسخ کامل نداد. صفحه را رفرش کنید و دوباره ثبت یا ادامه استخراج را بزنید.';
        }
        return (err && err.message) || 'خطا در ذخیره.';
    }

    function post(payload, isFile, timeoutMs) {
        const body = isFile ? payload : new FormData();
        if (!isFile) {
            Object.keys(payload).forEach(function (k) {
                body.append(k, payload[k]);
            });
        }
        const ctrl = window.AbortController ? new AbortController() : null;
        const ms = timeoutMs || (isFile ? 180000 : 55000);
        let timer = null;
        if (ctrl) {
            timer = setTimeout(function () {
                ctrl.abort();
            }, ms);
        }
        return fetch(ajax, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            signal: ctrl ? ctrl.signal : undefined,
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    const trimmed = String(text || '').trim();
                    if (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[') {
                        try {
                            return { r: r, data: JSON.parse(trimmed) };
                        } catch (e) {
                            throw new Error(humanError(e, r, trimmed));
                        }
                    }
                    throw new Error(humanError(null, r, trimmed));
                });
            })
            .then(function (pack) {
                const data = pack.data;
                if (!data || !data.success) {
                    throw new Error((data && data.message) || 'خطا در ذخیره.');
                }
                return data;
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    throw new Error(humanError(err));
                }
                throw err;
            })
            .then(function (data) {
                if (timer) {
                    clearTimeout(timer);
                }
                return data;
            }, function (err) {
                if (timer) {
                    clearTimeout(timer);
                }
                throw err;
            });
    }

    function bookProgress(book) {
        const total = Math.max(0, parseInt(book.page_count || '0', 10));
        const cur = parseInt(book.extract_cursor || '0', 10);
        if (total < 1) {
            return 'شمارش صفحه‌های PDF…';
        }
        return 'استخراج متن: صفحه ' + cur + ' از ' + total;
    }

    function sleep(ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    }

    function extractBook(id, restart) {
        const startPayload = { action: 'extract_init', book_id: id };
        if (restart) {
            startPayload.restart = '1';
        }
        return post(startPayload, false, 35000).then(function (data) {
            let book = data.book || {};
            setProgress(true, bookProgress(book));
            let fails = 0;
            function next() {
                return post({ action: 'extract_chunk', book_id: id }, false, 50000)
                    .then(function (chunk) {
                        fails = 0;
                        book = chunk.book || book;
                        setProgress(true, bookProgress(book));
                        if (chunk.done) {
                            return chunk;
                        }
                        return next();
                    })
                    .catch(function (err) {
                        const msg = String(err && err.message || '');
                        const retryable = /طول کشید|پاسخ کامل نداد|قطع شد|JSON|fetch/i.test(msg);
                        fails += 1;
                        if (!retryable || fails > 12) {
                            throw new Error(msg || 'سرور وسط استخراج قطع شد. «ادامه استخراج» را بزنید؛ از همان صفحه ادامه می‌دهد.');
                        }
                        setProgress(true, 'وقفه کوتاه؛ ادامه از همان صفحه (' + fails + ')…');
                        return sleep(1400).then(next);
                    });
            }
            if (data.done) {
                return data;
            }
            return next();
        });
    }

    if (courseSel && hint) {
        courseSel.addEventListener('change', function () {
            const c = courses[courseSel.value];
            hint.textContent = c && c.note
                ? c.note
                : 'هر دو یونیت یک ترم است. معلم همان PDF را برای سطح کلاسش می‌بیند.';
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (busy) {
                return;
            }
            const fileInput = document.getElementById('qbFile');
            const file = fileInput && fileInput.files && fileInput.files[0];
            const maxMb = parseInt(root.getAttribute('data-max-mb') || '80', 10);
            if (file && maxMb > 0 && file.size > maxMb * 1048576) {
                showAlert('حجم این فایل از حد سرور (' + maxMb + ' مگابایت) بیشتر است.', false);
                return;
            }
            const fd = new FormData(form);
            fd.append('action', 'upload');
            setProgress(true, 'در حال آپلود فایل… استخراج بعد از ذخیره، تکه‌تکه شروع می‌شود.');
            showAlert('', true);
            post(fd, true, 180000)
                .then(function (data) {
                    const id = data.book && data.book.id;
                    if (!id) {
                        throw new Error('فایل ذخیره شد ولی شناسه کتاب نیامد.');
                    }
                    toast('فایل ذخیره شد. استخراج صفحه‌ها شروع شد.');
                    return extractBook(String(id), false);
                })
                .then(function (data) {
                    toast((data && data.message) || 'آماده‌ است.');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 400);
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    setProgress(false);
                });
        });
    }

    const pending = Array.prototype.slice.call(
        root.querySelectorAll('[data-status="extracting"],[data-status="pending"]')
    );
    function resumeNext(i) {
        if (i >= pending.length) {
            if (pending.length) {
                window.location.reload();
            }
            return Promise.resolve();
        }
        const id = pending[i].getAttribute('data-book-id');
        if (!id) {
            return resumeNext(i + 1);
        }
        setProgress(true, 'ادامه استخراج کتاب ناتمام…');
        return extractBook(id, false).then(function () {
            return resumeNext(i + 1);
        });
    }
    if (pending.length) {
        resumeNext(0).catch(function (err) {
            showAlert(err.message, false);
            setProgress(false);
        });
    }

    root.addEventListener('click', function (e) {
        const btnEl = e.target.closest('[data-act]');
        if (!btnEl) {
            return;
        }
        const act = btnEl.getAttribute('data-act');
        if (act === 'register') {
            const row = btnEl.closest('[data-filename]');
            const filename = row && row.getAttribute('data-filename');
            const guess = row && row.getAttribute('data-guess');
            const course = (guess || '') || (courseSel ? courseSel.value : '');
            if (!filename) {
                return;
            }
            if (!course) {
                showAlert('اول کتاب دوره را از لیست بالا انتخاب کنید؛ مثلاً Wider World 2.', false);
                return;
            }
            if (busy) {
                return;
            }
            btnEl.disabled = true;
            setProgress(true, 'ثبت فایل در کتابخانه…');
            post({ action: 'register', filename: filename, course_key: course }, false, 25000)
                .then(function (data) {
                    const id = data.book && data.book.id;
                    if (!id) {
                        throw new Error('ثبت شد ولی شناسه کتاب نیامد.');
                    }
                    toast('ثبت شد. استخراج بعد از رفرش شروع می‌شود.');
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 350);
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    btnEl.disabled = false;
                    setProgress(false);
                });
            return;
        }
        const card = btnEl.closest('[data-book-id]');
        if (!card) {
            return;
        }
        const id = card.getAttribute('data-book-id');
        if (act === 'delete') {
            if (!window.confirm('این کتاب از کتابخانه حذف شود؟')) {
                return;
            }
            btnEl.disabled = true;
            post({ action: 'delete', book_id: id })
                .then(function (data) {
                    toast(data.message);
                    card.remove();
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    btnEl.disabled = false;
                });
            return;
        }
        if (act === 'extract') {
            if (busy) {
                return;
            }
            btnEl.disabled = true;
            extractBook(id, true)
                .then(function (data) {
                    toast((data && data.message) || 'استخراج تمام شد.');
                    window.location.reload();
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                    btnEl.disabled = false;
                    setProgress(false);
                });
            return;
        }
        if (act === 'preview') {
            const box = card.querySelector('.qb-preview');
            if (!box) {
                return;
            }
            if (!box.hidden && box.textContent !== '') {
                box.hidden = true;
                return;
            }
            btnEl.disabled = true;
            post({ action: 'preview', book_id: id })
                .then(function (data) {
                    const pages = data.pages || [];
                    if (!pages.length) {
                        box.textContent = 'متنی برای پیش‌نمایش نیست.';
                    } else {
                        box.textContent = pages.map(function (p) {
                            return 'Page ' + p.page_no + '\n' + p.snippet;
                        }).join('\n\n');
                    }
                    box.hidden = false;
                })
                .catch(function (err) {
                    showAlert(err.message, false);
                })
                .then(function () {
                    btnEl.disabled = false;
                });
        }
    });
})();
