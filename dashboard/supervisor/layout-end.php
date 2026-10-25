        </div> <!-- .main-content -->
        </div> <!-- .main-content-wrapper -->
    </div> <!-- .dashboard-layout -->

<link href="/assets/css/liquid-glass.css?v=lg25" rel="stylesheet">
<link href="/assets/css/supervisor/supervisor-landscape.css?v=s3" rel="stylesheet">
<?php if (!empty($wall) && !empty($wall['css'])): ?>
<link href="<?= htmlspecialchars($wall['css']) ?>" rel="stylesheet">
<?php endif; ?>
<link href="/assets/css/supervisor/supervisor-ai.css?v=a6" rel="stylesheet">
<script src="/assets/js/liquid-glass.js?v=lg21"></script>
<?php if (($current_page ?? '') !== 'ai-assistance.php'): ?>
<?php
$sai_mode = 'dock';
require __DIR__ . '/supervisor-ai-chat.php';
?>
<?php endif; ?>
<script src="/assets/js/supervisor-ai.js?v=a6"></script>
<script>
// منوی موبایل
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
        
        const navItems = mobileSidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
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
