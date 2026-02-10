<?php
require_once __DIR__ . '/../config.php';

$users = [];
$perPage = 10;
$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($page - 1) * $perPage;
$totalUsers = 0;

if (isset($_GET['verify'])) {
    $userId = (int)$_GET['verify'];
    if ($userId > 0) {
        $verifyStmt = $conn->prepare("UPDATE users SET verified = 1 WHERE id = ? AND role = 'employee'");
        if ($verifyStmt) {
            $verifyStmt->bind_param('i', $userId);
            $verifyStmt->execute();
            $verifyStmt->close();
        }
    }
    $redirectUrl = 'template.php?page=users&success=verified';
    if (!headers_sent()) {
        header("Location: {$redirectUrl}");
    } else {
        echo "<script>window.location.replace('{$redirectUrl}');</script>";
    }
    exit;
}

if (isset($_GET['delete'])) {
    $userId = (int)$_GET['delete'];
    if ($userId > 0) {
        $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'employee'");
        if ($deleteStmt) {
            $deleteStmt->bind_param('i', $userId);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
    }
    $redirectUrl = 'template.php?page=users&success=deleted';
    if (!headers_sent()) {
        header("Location: {$redirectUrl}");
    } else {
        echo "<script>window.location.replace('{$redirectUrl}');</script>";
    }
    exit;
}

$totalResult = $conn->query("SELECT COUNT(*) AS total FROM users");
if ($totalResult) {
    $row = $totalResult->fetch_assoc();
    $totalUsers = (int)($row['total'] ?? 0);
    $totalResult->free();
}
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$perPage = (int)$perPage;
$offset = (int)$offset;
$result = $conn->query("SELECT id, name, email, role, verified FROM users ORDER BY name ASC LIMIT $perPage OFFSET $offset");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,-50..200" />
    <title>Users</title>
    <style>
        .success-popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #e8f7ed;
            color: #1b5e20;
            border: 1px solid #c8e6c9;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            font-size: 14px;
        }
        .action-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid var(--color-border-hr);
            background: var(--color-bg-secondary);
            color: var(--color-text-primary);
            margin-right: 6px;
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
            text-decoration: none;
        }
        .action-icon:last-child {
            margin-right: 0;
        }
        .action-icon:hover {
            background: var(--color-hover-secondary);
            border-color: var(--color-hover-primary);
        }
        .action-icon:active {
            transform: scale(0.98);
        }
        .action-icon.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            pointer-events: none;
        }
        .action-icon .material-symbols-outlined {
            font-size: 18px;
            line-height: 1;
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'verified'): ?>
        <div class="success-popup" id="successPopup">User verified successfully.</div>
        <script>
            setTimeout(() => {
                const popup = document.getElementById('successPopup');
                if (popup) popup.remove();
            }, 3000);
        </script>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div class="success-popup" id="successPopup">User deleted successfully.</div>
        <script>
            setTimeout(() => {
                const popup = document.getElementById('successPopup');
                if (popup) popup.remove();
            }, 3000);
        </script>
    <?php endif; ?>
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Users</h1>
            <button class="add-btn">+ Add User</button>
        </div>
        <div class="search-filter">
            <input type="text" placeholder="Search users..." class="search-bar">
        </div>

        <table class="documents-table">
            <thead>
                <tr>
                    <th>NAME</th>
                    <th>EMAIL</th>
                    <th>ROLE</th>
                    <th>VERIFIED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge <?= $user['role'] === 'admin' ? 'success' : 'danger' ?>">
                                    <?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= (int)$user['verified'] === 1 ? 'success' : 'danger' ?>">
                                    <?= (int)$user['verified'] === 1 ? 'Yes' : 'No' ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'employee' && (int)$user['verified'] !== 1): ?>
                                    <a href="template.php?page=users&verify=<?= (int)$user['id'] ?>" class="action-icon" title="Verify">
                                        <span class="material-symbols-outlined">verified</span>
                                    </a>
                                <?php else: ?>
                                    <span class="action-icon disabled" title="Verified">
                                        <span class="material-symbols-outlined">verified</span>
                                    </span>
                                <?php endif; ?>

                                <?php if ($user['role'] === 'employee'): ?>
                                    <a href="template.php?page=users&delete=<?= (int)$user['id'] ?>" class="action-icon" title="Delete" onclick="return confirm('Delete this user?');">
                                        <span class="material-symbols-outlined">delete</span>
                                    </a>
                                <?php else: ?>
                                    <span class="action-icon disabled" title="Delete disabled">
                                        <span class="material-symbols-outlined">delete</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <span>
                Showing <?= count($users) ?> of <?= $totalUsers ?> user<?= $totalUsers === 1 ? '' : 's' ?>
            </span>
            <div class="pagination-controls">
                <?php if ($page > 1): ?>
                    <a class="pagination-btn" href="template.php?page=users&p=<?= $page - 1 ?>">Previous</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>Previous</button>
                <?php endif; ?>

                <?php if ($totalPages <= 5): ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="pagination-btn <?= $i === $page ? 'active' : '' ?>" href="template.php?page=users&p=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                <?php else: ?>
                    <a class="pagination-btn <?= 1 === $page ? 'active' : '' ?>" href="template.php?page=users&p=1">1</a>
                    <?php if ($page > 3): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>

                    <?php $start = max(2, $page - 1); ?>
                    <?php $end = min($totalPages - 1, $page + 1); ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a class="pagination-btn <?= $i === $page ? 'active' : '' ?>" href="template.php?page=users&p=<?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages - 2): ?>
                        <span class="pagination-ellipsis">...</span>
                    <?php endif; ?>
                    <a class="pagination-btn <?= $totalPages === $page ? 'active' : '' ?>" href="template.php?page=users&p=<?= $totalPages ?>">
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="pagination-btn" href="template.php?page=users&p=<?= $page + 1 ?>">Next</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>Next</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>