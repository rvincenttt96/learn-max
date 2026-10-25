        </div> <!-- .main-content -->
        </div> <!-- .main-content-wrapper -->
    </div> <!-- .dashboard-layout -->

<div class="g-toast" id="gToast" hidden></div>
<div class="g-sheet-root" id="credsSheet" hidden>
    <div class="g-sheet-backdrop" data-close-creds></div>
    <div class="g-sheet g-sheet-sm" role="dialog" aria-modal="true" aria-labelledby="credsSheetTitle">
        <div class="g-sheet-head">
            <h2 id="credsSheetTitle">حساب ساخته شد</h2>
            <button type="button" class="g-icon-btn" data-close-creds aria-label="بستن">×</button>
        </div>
        <div class="g-sheet-body">
            <p class="g-hint" id="credsLead">یوزر و رمز را همین حالا یادداشت کنید. بعد از بستن این پنجره رمز دیگر دیده نمی‌شود.</p>
            <div class="g-creds">
                <div class="g-cred">
                    <span>نام</span>
                    <strong id="credName"></strong>
                </div>
                <div class="g-cred">
                    <span>نام کاربری</span>
                    <strong id="credUser" class="student-file"></strong>
                    <button type="button" class="g-btn g-btn-ghost g-btn-copy" data-copy="credUser">کپی</button>
                </div>
                <div class="g-cred">
                    <span>رمز عبور</span>
                    <strong id="credPass" class="student-file"></strong>
                    <button type="button" class="g-btn g-btn-ghost g-btn-copy" data-copy="credPass">کپی</button>
                </div>
            </div>
            <div class="g-sheet-actions">
                <button type="button" class="g-btn g-btn-ghost" data-copy="both">کپی هر دو</button>
                <button type="button" class="g-btn g-btn-primary" data-close-creds>متوجه شدم</button>
            </div>
        </div>
    </div>
</div>
<link href="/assets/css/liquid-glass.css?v=lg27" rel="stylesheet">
<?php if (!empty($wall) && !empty($wall['css'])): ?>
<link href="<?= htmlspecialchars($wall['css']) ?>" rel="stylesheet">
<?php endif; ?>
<link href="/assets/css/german.css?v=g8" rel="stylesheet">
<link href="/assets/css/german-theme.css?v=t2" rel="stylesheet">
<script src="/assets/js/liquid-glass.js?v=lg22"></script>
<script src="/assets/js/german.js?v=g5"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileSidebar = document.getElementById('mobileSidebar');
    if (mobileMenuToggle && mobileSidebar) {
        const sidebarBackdrop = document.createElement('div');
        sidebarBackdrop.className = 'sidebar-backdrop';
        document.body.appendChild(sidebarBackdrop);
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            mobileSidebar.classList.add('show');
            sidebarBackdrop.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
        sidebarBackdrop.addEventListener('click', function() {
            mobileSidebar.classList.remove('show');
            sidebarBackdrop.classList.remove('show');
            document.body.style.overflow = '';
        });
        mobileSidebar.querySelectorAll('.nav-item').forEach(function(item) {
            item.addEventListener('click', function() {
                mobileSidebar.classList.remove('show');
                sidebarBackdrop.classList.remove('show');
                document.body.style.overflow = '';
            });
        });
    }
});
</script>
</body>
</html>
