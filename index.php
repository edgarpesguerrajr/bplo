<?php
session_start();

$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
];
$success = $_SESSION['register_success'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login';

session_unset(); // Clear messages after they're displayed

function showMessage($message, $type = 'error') {
    return !empty($message) ? "<p class='{$type}-message'>$message</p>" : '';
}

function isActiveForm($formName, $activeForm) {
    return $formName === $activeForm ? 'active' : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static\index.css">
    <link rel="stylesheet" href="static\global.css">
    <title>BPLO Monitoring System</title>
</head>
<body>
    <div class="container">
        <div class="form-box <?= isActiveForm('login', $activeForm);?>" id="login-form">
            <form action="login_register.php" method="post">
                <h2>Login</h2>
                <?= showMessage($errors['login']) ?>
                <?= showMessage($success, 'success') ?>
                <input placeholder="email" name="email" type="email" required="required">
                <input placeholder="password" name="password" type="password" required="required">
                <button type="submit" name="login">Login</button>
                <p>Don't have an account? <a href="#" onclick="showForm('register-form')">Register</a></p>
            </form>
        </div>

        <div class="form-box <?= isActiveForm('register', $activeForm);?>" id="register-form">
            <form action="login_register.php" method="post">
                <h2>Register</h2>
                <?= showMessage($errors['register']) ?>
                <input placeholder="name" name="name" type="text" required="required">
                <input placeholder="email" name="email" type="email" required="required">
                <input placeholder="password" name="password" type="password" required="required">
                <select name="role" required>
                    <option value="">--Select Role--</option>
                    <option value="employee">Employee</option>
                </select>
                <button type="submit" name="register">Register</button>
                <p>Already have an account? <a href="#" onclick="showForm('login-form')">Login</a></p>
            </form>
        </div>
    </div>

    <script>
        function showForm(formId) {
            document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
            document.getElementById(formId).classList.add("active");
        }
    </script>
</body>
</html>