            </main>
        </div> <!-- /.app-main-container -->
    </div> <!-- /.app-layout -->

    <!-- Driver & Utility Scripts -->
    <script src="assets/js/scale.js"></script>
    <script src="assets/js/scanner.js"></script>
    <script src="assets/js/xlsx.full.min.js"></script>
    <script src="assets/js/chart.umd.min.js"></script>
    <script src="assets/js/table-tools.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/table-tools.js') ?>"></script>
    <script src="assets/js/tabel-rapi.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/tabel-rapi.js') ?>"></script>
    <script src="assets/js/select-ui.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/select-ui.js') ?>"></script>
    <script src="assets/js/ekspor.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/ekspor.js') ?>"></script>
    <script src="assets/js/batal.js?v=<?= @filemtime(__DIR__ . '/../../assets/js/batal.js') ?>"></script>

    <script>
        // Toggle Sidebar Drawer (Mobile & Tablet)
        function toggleSidebar(forceState) {
            const sidebar = document.getElementById('appSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            const body = document.body;
            
            if (!sidebar) return;
            
            const isOpen = typeof forceState === 'boolean' 
                ? forceState 
                : !sidebar.classList.contains('open');
                
            if (isOpen) {
                sidebar.classList.add('open');
                if (backdrop) backdrop.classList.add('open');
                body.classList.add('sidebar-active');
            } else {
                sidebar.classList.remove('open');
                if (backdrop) backdrop.classList.remove('open');
                body.classList.remove('sidebar-active');
            }
        }

        // Tombol MENU: di desktop melipat / membuka sidebar (diingat di browser), di layar kecil membuka laci
        function toggleSidebarMenu() {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                const lipat = document.body.classList.toggle('sidebar-collapsed');
                try { localStorage.setItem('sidebarCollapsed', lipat ? '1' : '0'); } catch (e) {}
                const btn = document.getElementById('btnSidebarToggle');
                if (btn) btn.setAttribute('aria-expanded', lipat ? 'false' : 'true');
            } else {
                toggleSidebar();
            }
        }
        (function () {
            const btn = document.getElementById('btnSidebarToggle');
            if (btn) btn.setAttribute('aria-expanded', document.body.classList.contains('sidebar-collapsed') ? 'false' : 'true');
        })();

        // Tutup drawer jika tombol ESC ditekan
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                toggleSidebar(false);
            }
        });

        // Tema Gelap/Terang Toggle (Shadcn Theme Controller)
        const themeBtn = document.getElementById('themeToggle');
        const sunIcon = document.getElementById('sunIcon');
        const moonIcon = document.getElementById('moonIcon');
        const themeBadge = document.getElementById('themeBadge');

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            if (theme === 'dark') {
                if (sunIcon) sunIcon.style.display = 'none';
                if (moonIcon) moonIcon.style.display = 'inline-block';
                if (themeBadge) themeBadge.textContent = 'Gelap';
            } else {
                if (sunIcon) sunIcon.style.display = 'inline-block';
                if (moonIcon) moonIcon.style.display = 'none';
                if (themeBadge) themeBadge.textContent = 'Terang';
            }
        }

        const initialTheme = localStorage.getItem('theme') || 'light';
        applyTheme(initialTheme);

        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                applyTheme(next);
            });
        }

        // Dropdown Profil User & Role Switcher
        function toggleUserDropdown(event) {
            if (event) event.stopPropagation();
            const menu = document.getElementById('userDropdownMenu');
            const btn = document.getElementById('btnUserMenu');
            if (menu) {
                const isShown = menu.classList.contains('show');
                menu.classList.toggle('show');
                if (btn) btn.classList.toggle('active', !isShown);
            }
        }

        document.addEventListener('click', function(e) {
            const menu = document.getElementById('userDropdownMenu');
            const wrapper = document.getElementById('userMenuWrapper');
            if (menu && wrapper && !wrapper.contains(e.target)) {
                menu.classList.remove('show');
                const btn = document.getElementById('btnUserMenu');
                if (btn) btn.classList.remove('active');
            }
        });

        // Initialize Lucide Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
