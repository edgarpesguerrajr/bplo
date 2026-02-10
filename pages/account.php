<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php'; // For database connection

// Get the logged-in user's information
$user_email = $_SESSION['email'];
$query = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
$query->bind_param("s", $user_email);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'] ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if ($name && $email) {
        if ($password) {
            $update = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $update->bind_param("sssi", $name, $email, $password, $user['id']);
        } else {
            $update = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $update->bind_param("ssi", $name, $email, $user['id']);
        }

        if ($update->execute()) {
            $_SESSION['name'] = $name;   // keep dashboard greeting fresh
            $_SESSION['email'] = $email; // keep session email in sync
            $_SESSION['success'] = "Account updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update account. Please try again.";
        }
    }

    $redirect = "template.php?page=account";
    if (!headers_sent()) {
        header("Location: " . $redirect);
    } else {
        echo "<script>window.location.href='" . $redirect . "';</script>";
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Account</title>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Manage Account</h1>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <p style="color: green;"><?= $_SESSION['success'] ?></p>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <p style="color: red;"><?= $_SESSION['error'] ?></p>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <form action="" method="POST" class="card">
            <div class="form">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="form">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="form">
                <label for="password">New Password <span style="font-weight: normal;">(leave blank to keep current password)</span></label>
                <input type="password" id="password" name="password" placeholder="Enter new password">
            </div>
            <button type="submit">Update Account</button>
        </form>
    </div>
</body>
</html>