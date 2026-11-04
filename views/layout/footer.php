            </main>
        </div> <!-- /.app-main-container -->
    </div> <!-- /.app-layout -->

    <!-- Driver & Utility Scripts -->
    <script src="assets/js/scale.js"></script>
    <script src="assets/js/scanner.js"></script>
    <script src="assets/js/xlsx.full.min.js"></script>
    <script src="assets/js/chart.umd.min.js"></script>

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

        // Tutup drawer jika tombol ESC ditekan
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                toggleSidebar(false);
            }
        });

        // Global Export to Excel (.xlsx) Handler using SheetJS
        function exportTableToExcel(tableId, baseFilename) {
            const table = document.getElementById(tableId);
            if (!table) {
                if (window.showAppAlert) {
                    window.showAppAlert({
                        title: 'Tabel Tidak Ditemukan',
                        text: 'Tabel data tidak dapat ditemukan untuk diekspor.',
                        icon: 'warning'
                    });
                } else {
                    alert('Tabel tidak ditemukan');
                }
                return;
            }
            if (typeof XLSX === 'undefined') {
                if (window.showAppAlert) {
                    window.showAppAlert({
                        title: 'Pustaka Excel Belum Siap',
                        text: 'Modul SheetJS sedang dimuat, silakan coba sesaat lagi.',
                        icon: 'info'
                    });
                } else {
                    alert('Pustaka Excel belum siap.');
                }
                return;
            }
            const d = new Date().toISOString().slice(0, 10);
            const wb = XLSX.utils.table_to_book(table, { raw: false, cellDates: true });
            XLSX.writeFile(wb, `${baseFilename}_${d}.xlsx`);
            if (window.showToast) {
                window.showToast(`Berhasil mengekspor data ke ${baseFilename}_${d}.xlsx!`, 'success');
            }
        }

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

        // Initialize Lucide Icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    </script>
</body>
</html>
