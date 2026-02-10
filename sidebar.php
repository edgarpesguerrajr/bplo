<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/sidebar.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,100..700,0,-50..200" />
    <title>BPLO Monitoring System</title>
</head>
<body>
    <nav class="site-nav">
        <button class="sidebar-toggle">
            <span class="material-symbols-rounded">menu</span>
        </button>
    </nav>
    <div class="container">
        <aside class="sidebar">
            <header class="sidebar-header">
                <img src="static\tanay_logo.png" alt="tanay_logo" class="header-logo">
                <button class="sidebar-toggle">
                    <span class="material-symbols-rounded">chevron_left</span>
                </button>
            </header>
            <div class="sidebar-content">
            <ul class="menu-list">
                <li class="menu-item">
                    <a href="template.php?page=dashboard" class="menu-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                        <span class="material-symbols-rounded">dashboard</span>
                        <span class="menu-label">Dashboard</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="template.php?page=documents" class="menu-link <?= in_array($page, ['documents', 'add_document', 'edit_document', 'view_document']) ? 'active' : '' ?>">
                        <span class="material-symbols-rounded">description</span>
                        <span class="menu-label">Documents</span>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="template.php?page=report" class="menu-link <?= $page === 'report' ? 'active' : '' ?>">
                        <span class="material-symbols-rounded">insert_chart</span>
                        <span class="menu-label">Reports</span>
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li class="menu-item">
                        <a href="template.php?page=users" class="menu-link <?= $page === 'users' ? 'active' : '' ?>">
                            <span class="material-symbols-rounded">group</span>
                            <span class="menu-label">Users</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="menu-item">
                    <a href="template.php?page=account" class="menu-link <?= $page === 'account' ? 'active' : '' ?>">
                        <span class="material-symbols-rounded">settings</span>
                        <span class="menu-label">Manage Account</span>
                    </a>
                </li>
            </ul>
            </div>

            <div class="sidebar-footer">
                <button class="theme-toggle">
                    <div class="theme-label">
                        <span class="theme-icon material-symbols-rounded">dark_mode</span>
                        <span class="theme-text">Dark Mode</span>
                    </div>
                    <div class="theme-toggle-track">
                        <div class="theme-toggle-indicator"></div>
                    </div>
                </button>
                <button class="theme-toggle" onclick="window.location.href='logout.php'">
                    <div class="theme-label">
                        <span class="material-symbols-rounded">logout</span>
                        <span class="theme-text">Logout</span>
                    </div>
                </button>
            </div>
        </aside>
    <script>
        const sidebar = document.querySelector(".sidebar");
        const sidebarToggleBtn = document.querySelectorAll(".sidebar-toggle");
        const themeToggleBtn = document.querySelector(".theme-toggle");
        const themeIcon = themeToggleBtn.querySelector(".theme-icon");

        const updateThemeIcon = () => {
            const isDark = document.body.classList.contains("dark-theme");
            themeIcon.textContent = sidebar.classList.contains("collapsed") ?
            (isDark ? "light_mode" : "dark_mode") : "dark_mode";
        }


        const savedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
        const shouldUseDarkTheme = savedTheme === "dark" || (!savedTheme && systemPrefersDark);


        document.body.classList.toggle("dark-theme", shouldUseDarkTheme);
        updateThemeIcon();

        sidebarToggleBtn.forEach((btn) => {
            btn.addEventListener("click", () => {
                sidebar.classList.toggle("collapsed");
                updateThemeIcon();
            })
        });

        themeToggleBtn.addEventListener("click", () => {
            const isDark = document.body.classList.toggle("dark-theme");
            localStorage.setItem('theme', isDark ? "dark" : "light");
            updateThemeIcon();
        });

        if(window.innerWidth <= 768) sidebar.classList.add("collapsed");
    </script>
</body>
</html>