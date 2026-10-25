        </div> <!-- .main-content -->
        </div> <!-- .main-content-wrapper -->
    </div> <!-- .dashboard-layout --> 

<link href="/assets/css/liquid-glass.css?v=lg27" rel="stylesheet">
<script src="/assets/js/liquid-glass.js?v=lg22"></script>
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

