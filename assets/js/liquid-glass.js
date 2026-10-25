(function () {
    'use strict';

    function lgEsc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function lgRenderParticipants(targetId, data, options) {
        options = options || {};
        var el = document.getElementById(targetId);
        if (!el) {
            return;
        }
        if (!data || !data.success) {
            el.innerHTML = '<div class="lg-student-status is-error">خطا در بارگذاری اطلاعات کلاس</div>';
            return;
        }
        var students = data.students || [];
        var info = data.class_info || {};
        var heading = options.title || info.title || info.teacher_name || '';
        var label = options.label || 'Class';
        var html = '<div class="lg-participants">';
        html += '<div class="lg-participants-head">';
        html += '<div class="lg-participants-class">' + lgEsc(label) + ': ' + lgEsc(heading) + '</div>';
        html += '<span class="lg-student-meta">' + students.length + ' learners</span>';
        html += '</div>';
        if (options.showTeacherPhone) {
            html += '<p class="lg-participants-sub">' + (info.teacher_phone ? lgEsc(info.teacher_phone) : 'No phone number') + '</p>';
        }
        if (!students.length) {
            html += '<div class="lg-student-status">No learners available</div>';
        } else {
            html += '<div class="lg-participants-grid">';
            students.forEach(function (student, index) {
                html += '<div class="lg-participant-card" style="animation-delay:' + Math.min(index * 20, 280) + 'ms">';
                html += '<span class="lg-participant-name">' + lgEsc(student.full_name) + '</span>';
                html += '<span class="lg-participant-code">' + lgEsc(student.student_code || 'No code') + '</span>';
                if (options.showPhone && student.phone) {
                    html += '<a class="lg-participant-phone" href="tel:' + lgEsc(student.phone) + '">' + lgEsc(student.phone) + '</a>';
                }
                html += '</div>';
            });
            html += '</div>';
        }
        html += '</div>';
        el.innerHTML = html;
    }

    window.lgEsc = lgEsc;
    window.lgRenderParticipants = lgRenderParticipants;

    var NAV_ALIASES = {
        'attendance-students.php': 'attendance.php',
        'save-attendance.php': 'attendance.php',
        'homework.php': 'attendance.php',
        'homework-students.php': 'attendance.php',
        'save-homework.php': 'attendance.php',
        'session-plan-details.php': 'session-plan.php',
        'join-online.php': 'classes.php',
        'bookstore-order.php': 'bookstore.php',
        'bookstore.php': 'bookstore.php',
        'quiz.php': 'quizzes.php',
        'assignment.php': 'assignments.php',
        'students-info-view.php': 'students-info.php',
        'students-info-import.php': 'students-info.php',
        'students-info-export.php': 'students-info.php',
        'teachers-info-view.php': 'teachers-info.php',
        'teachers-info-save.php': 'teachers-info.php'
    };

    function ensureOrbs() {
        var wrap = document.querySelector('.lg-orbs');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'lg-orbs';
            wrap.setAttribute('aria-hidden', 'true');
            wrap.innerHTML = '<span class="lg-orb lg-orb-a"></span><span class="lg-orb lg-orb-b"></span><span class="lg-orb lg-orb-c"></span><span class="lg-orb lg-orb-d"></span><span class="lg-orb lg-orb-e"></span><div class="lg-grid-floor"></div>';
            document.body.insertBefore(wrap, document.body.firstChild);
            return wrap;
        }
        if (!wrap.querySelector('.lg-grid-floor')) {
            wrap.insertAdjacentHTML('beforeend', '<span class="lg-orb lg-orb-d"></span><span class="lg-orb lg-orb-e"></span><div class="lg-grid-floor"></div>');
        }
        return wrap;
    }

    function hoistToBody(el) {
        if (el && el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
    }

    function hoistOverlays() {
        document.querySelectorAll('.modal').forEach(hoistToBody);
        hoistToBody(document.getElementById('mobileSidebar'));
    }

    function pageFile() {
        var path = (window.location.pathname || '').replace(/\/+$/, '');
        var file = path.split('/').pop() || 'index.php';
        if (file.indexOf('.') === -1) {
            file = 'index.php';
        }
        return NAV_ALIASES[file] || file;
    }

    function hrefFile(href) {
        if (!href) {
            return '';
        }
        var clean = href.split('#')[0].split('?')[0];
        var name = clean.split('/').pop() || '';
        return name;
    }

    function markActiveNav() {
        var target = pageFile();
        var items = document.querySelectorAll('.sidebar-nav .nav-item, .mobile-sidebar .nav-item');
        items.forEach(function (a) {
            var href = a.getAttribute('href') || '';
            var name = hrefFile(href);
            if (!name || name === 'logout.php' || href === '' || href === '#') {
                return;
            }
            if (name === target) {
                a.classList.add('active');
            }
        });
    }

    function enterPage() {
        var main = document.querySelector('.main-content');
        if (!main) {
            return;
        }
        main.classList.add('lg-enter');
        var cards = main.querySelectorAll(
            '.card, .dashboard-card, .eas-card, .plc-card, .si-card, .edu-cal-card, .term-management-card, .term-dates-card, .classes-card, .supervisor-class-card, .user-management-card'
        );
        cards.forEach(function (el, i) {
            el.classList.add('lg-rise');
            el.style.animationDelay = Math.min(i * 35, 420) + 'ms';
        });
    }

    function parallax(wrap) {
        if (!wrap || window.matchMedia('(pointer: coarse)').matches) {
            return;
        }
        window.addEventListener('pointermove', function (e) {
            var x = (e.clientX / window.innerWidth - 0.5) * 24;
            var y = (e.clientY / window.innerHeight - 0.5) * 18;
            wrap.style.transform = 'translate(' + x + 'px,' + y + 'px)';
        }, { passive: true });
    }

    function replayDrawerItems(sidebar) {
        var items = sidebar.querySelectorAll('.nav-item');
        items.forEach(function (el, i) {
            el.style.animation = 'none';
            void el.offsetWidth;
            el.style.animation = '';
            el.style.animationDelay = (30 + i * 32) + 'ms';
        });
    }

    function watchDrawer() {
        var sidebar = document.getElementById('mobileSidebar');
        if (!sidebar || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        var observer = new MutationObserver(function () {
            if (sidebar.classList.contains('show')) {
                replayDrawerItems(sidebar);
            }
        });
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
    }

    function refreshLiveClassDots() {
        var now = Math.floor(Date.now() / 1000);
        document.querySelectorAll('.class-live-dot').forEach(function (dot) {
            var start = parseInt(dot.getAttribute('data-session-start') || '0', 10);
            var end = parseInt(dot.getAttribute('data-session-end') || '0', 10);
            var on = start > 0 && end > start && now >= start && now < end;
            dot.classList.toggle('is-on', on);
            if (on) {
                dot.setAttribute('title', 'در حال برگزاری');
                dot.setAttribute('aria-label', 'در حال برگزاری');
                dot.removeAttribute('aria-hidden');
            } else {
                dot.removeAttribute('title');
                dot.removeAttribute('aria-label');
                dot.setAttribute('aria-hidden', 'true');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('lg-app');
        refreshLiveClassDots();
        setInterval(refreshLiveClassDots, 30000);
        hoistOverlays();
        markActiveNav();
        var wrap = ensureOrbs();
        document.addEventListener('show.bs.modal', function (e) {
            hoistToBody(e.target);
        });
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }
        enterPage();
        parallax(wrap);
        watchDrawer();
    });
})();
