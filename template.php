<?php
session_start();

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard'; // Default to dashboard
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';

// Redirect if user is not logged in
if ($user_role === 'guest') {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPLO | <?= ucfirst($page) ?></title>
    <link rel="stylesheet" href="static/template.css">
    <link rel="stylesheet" href="static/global.css">
    <link rel="stylesheet" href="static/pages.css">
</head>
<body>
    <div class="layout">
        <?php include 'sidebar.php'; ?>
        <div class="main-content-container">
            <?php
            $accessible_pages = ['dashboard', 'documents', 'account', 'report', 'add_document', 'view_document', 'edit_document'];
            $admin_only_pages = ['dashboard', 'documents', 'account', 'report', 'add_document', 'view_document', 'edit_document', 'users'];

            if (in_array($page, $accessible_pages)) {
                include "pages/$page.php";
            } elseif ($user_role === 'admin' && in_array($page, $admin_only_pages)) {
                include "pages/$page.php";
            } else {
                include 'pages/404.php'; // Load 404 for unauthorized or unknown pages
            }
            ?>
        </div>
    </div>
    <script src="static/script.js"></script>
</body>
</html>