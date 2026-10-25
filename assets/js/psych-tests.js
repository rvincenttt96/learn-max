(function () {
    const root = document.querySelector("[data-pt-take]");
    if (!root) return;

    let boot = {};
    try {
        boot = JSON.parse(root.getAttribute("data-boot") || "{}");
    } catch (e) {
        boot = {};
    }

    const answers = Object.assign({}, boot.answers || {});
    const total = Number(boot.item_count || 0);
    const key = String(boot.test_key || "");
    const ajax = String(boot.ajax || "ajax-psych.php");
    const form = document.getElementById("ptForm");
    const okBox = document.getElementById("ptOk");
    const alertBox = document.getElementById("ptAlert");
    const submitBtn = document.getElementById("ptSubmit");
    const saveBtn = document.getElementById("ptSave");
    const countEl = document.getElementById("ptCount");
    const barEl = document.getElementById("ptBar");
    const storageKey = "ghazal-psych-" + key;
    let saveTimer = null;
    let saving = false;

    function showAlert(msg) {
        if (!alertBox) return;
        alertBox.hidden = !msg;
        alertBox.textContent = msg || "";
    }

    function answeredCount() {
        let n = 0;
        for (let i = 1; i <= total; i += 1) {
            if (answers[i] >= 1 && answers[i] <= 5) n += 1;
        }
        return n;
    }

    function paintProgress() {
        const n = answeredCount();
        if (countEl) countEl.textContent = n + " از " + total;
        if (barEl) barEl.style.width = (total ? Math.round((n / total) * 100) : 0) + "%";
        if (submitBtn) submitBtn.disabled = n !== total || saving;
    }

    function persistLocal() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(answers));
        } catch (e) {}
    }

    function restoreLocal() {
        if (Object.keys(answers).length) return;
        try {
            const raw = JSON.parse(localStorage.getItem(storageKey) || "{}");
            Object.keys(raw).forEach(function (k) {
                const n = parseInt(k, 10);
                const v = parseInt(raw[k], 10);
                if (n > 0 && v >= 1 && v <= 5) answers[n] = v;
            });
        } catch (e) {}
    }

    function markChoice(n, v) {
        const q = form && form.querySelector('.pt-q[data-n="' + n + '"]');
        if (!q) return;
        q.classList.toggle("is-open", true);
        q.querySelectorAll("label").forEach(function (lab) {
            lab.classList.toggle("is-on", lab.getAttribute("data-v") === String(v));
        });
        const input = q.querySelector('input[value="' + v + '"]');
        if (input) input.checked = true;
    }

    function post(action) {
        return fetch(ajax, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                action: action,
                test_key: key,
                answers: answers,
            }),
        }).then(function (r) {
            return r.json();
        });
    }

    function queueDraft() {
        persistLocal();
        paintProgress();
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            post("draft").catch(function () {});
        }, 400);
    }

    restoreLocal();
    if (form) {
        form.querySelectorAll(".pt-q").forEach(function (q) {
            const n = parseInt(q.getAttribute("data-n"), 10);
            if (answers[n]) markChoice(n, answers[n]);
            q.querySelectorAll("label").forEach(function (lab) {
                lab.addEventListener("click", function () {
                    const v = parseInt(lab.getAttribute("data-v"), 10);
                    if (v >= 1 && v <= 5) {
                        answers[n] = v;
                        markChoice(n, v);
                        showAlert("");
                        queueDraft();
                    }
                });
            });
        });
    }

    paintProgress();

    if (saveBtn) {
        saveBtn.addEventListener("click", function () {
            showAlert("");
            saveBtn.disabled = true;
            post("draft")
                .then(function (data) {
                    if (!data || !data.success) {
                        showAlert((data && data.message) || "پیش‌نویس ذخیره نشد.");
                        return;
                    }
                    saveBtn.textContent = "ذخیره شد";
                    setTimeout(function () {
                        saveBtn.textContent = "ذخیره پیش‌نویس";
                    }, 1200);
                })
                .catch(function () {
                    showAlert("خطا در ارتباط با سرور.");
                })
                .then(function () {
                    saveBtn.disabled = false;
                });
        });
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            showAlert("");
            if (answeredCount() !== total) {
                showAlert("همه سوال‌ها را پاسخ دهید.");
                const first = Array.prototype.find.call(form.querySelectorAll(".pt-q"), function (q) {
                    return !q.querySelector("label.is-on");
                });
                if (first) first.scrollIntoView({ block: "center" });
                return;
            }
            saving = true;
            paintProgress();
            post("submit")
                .then(function (data) {
                    if (!data || !data.success) {
                        showAlert((data && data.message) || "ثبت نشد.");
                        saving = false;
                        paintProgress();
                        return;
                    }
                    try {
                        localStorage.removeItem(storageKey);
                    } catch (err) {}
                    if (form) form.hidden = true;
                    const prog = document.querySelector(".pt-progress");
                    if (prog) prog.hidden = true;
                    if (okBox) okBox.hidden = false;
                })
                .catch(function () {
                    showAlert("خطا در ارتباط با سرور.");
                    saving = false;
                    paintProgress();
                });
        });
    }
})();
