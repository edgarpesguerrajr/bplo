<?php
session_start();
require_once 'config.php'; // Assumes $conn is initialized in config.php

// Helper function to sanitize input
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// REGISTRATION LOGIC
if (isset($_POST['register'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = sanitize($_POST['role']);

    // Check if email is already registered
    $checkEmail = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        // Email already exists
        $_SESSION['register_error'] = 'Email is already registered.';
        $_SESSION['active_form'] = 'register';
    } else {
        // Insert new user (employees require verification)
        $verified = $role === 'admin' ? 1 : 0;
        $insertUser = $conn->prepare("INSERT INTO users (name, email, password, role, verified) VALUES (?, ?, ?, ?, ?)");
        $insertUser->bind_param("ssssi", $name, $email, $password, $role, $verified);
        
        if ($insertUser->execute()) {
            $_SESSION['register_success'] = 'Registration successful! Please log in.';
        } else {
            $_SESSION['register_error'] = 'Registration failed. Please try again.';
        }
        $insertUser->close();
    }

    // Redirect back to the registration form
    $checkEmail->close();
    header("Location: index.php");
    exit();
}

// LOGIN LOGIC
if (isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password']; // Don't sanitize passwords because they're hashed and verified

    // Check if email exists in the database
    $getUser = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $getUser->bind_param("s", $email);
    $getUser->execute();
    $result = $getUser->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc(); // Fetch user data
        // Verify password
        if (password_verify($password, $user['password'])) {
            if ($user['role'] !== 'admin' && (int)$user['verified'] !== 1) {
                $_SESSION['login_error'] = 'Account not verified. Please contact the administrator.';
                $_SESSION['active_form'] = 'login';
                $getUser->close();
                header("Location: index.php");
                exit();
            }
            // Set session variables
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on user role
            if ($user['role'] === 'admin') {
                header("Location: template.php"); // Admin's dashboard
            } else {
                header("Location: template.php"); // Employee's dashboard
            }
            exit();
        } else {
            $_SESSION['login_error'] = 'Incorrect email or password.';
        }
    } else {
        $_SESSION['login_error'] = 'Incorrect email or password.';
    }

    // Redirect back to login on failure
    $_SESSION['active_form'] = 'login';
    $getUser->close();
    header("Location: index.php");
    exit();
}

?>