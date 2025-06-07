<?php
session_start();
require '../db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    $query = "SELECT * FROM tb_userLogin WHERE email = '$email'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: ../home.php");
            exit;
        } else {
            $error = "Password salah.";
        }
    } else {
        $error = "Email tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login Akun</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
        }

        .form-container {
            max-width: 400px;
            margin: 80px auto;
            padding: 30px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .form-group {
            position: relative;
            margin-bottom: 2rem;
        }

        .form-control-custom {
            border: none;
            border-bottom: 2px solid #ced4da;
            background: transparent;
            width: 100%;
            padding: 10px 0 5px 0;
            font-size: 1rem;
        }

        .form-control-custom:focus {
            border-color: #0d6efd;
            outline: none;
        }

        label {
            position: absolute;
            top: 12px;
            left: 0;
            font-size: 1rem;
            color: #6c757d;
            pointer-events: none;
            transition: 0.2s ease all;
        }

        .form-control-custom:focus + label,
        .form-control-custom:not(:placeholder-shown) + label {
            top: -10px;
            font-size: 0.8rem;
            color: #0d6efd;
        }

        .form-control-custom::placeholder {
            color: transparent;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #6c757d;
            cursor: pointer;
        }

        .back-button {
            position: absolute;
            top: 10px;
            left: 15px;
            font-size: 1.2rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="form-container">
    <a href="javascript:history.back()" class="back-button">
        <i class="bi bi-x-lg"></i>
    </a>

    <h4 class="mb-4 text-center">Login Akun</h4>

    <?php if (!empty($error)) : ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <!-- Email -->
        <div class="form-group">
            <input type="email" class="form-control-custom" name="email" id="email" required placeholder="Email">
            <label for="email">Email</label>
        </div>

        <!-- Password -->
        <div class="form-group">
            <input type="password" class="form-control-custom" name="password" id="password" required placeholder="Password">
            <label for="password">Password</label>
            <button type="button" class="toggle-password" onclick="togglePassword(this)">
                <i class="bi bi-eye"></i>
            </button>
        </div>

        <!-- Tombol Login -->
        <button type="submit" class="btn btn-primary w-100">Login</button>

        <!-- Link ke registrasi -->
        <div class="text-center mt-3">
            <small>Belum punya akun? <a href="./register.php" class="text-primary text-decoration-none">Daftar</a></small>
        </div>
    </form>
</div>

<script>
    function togglePassword(button) {
        const passwordInput = document.getElementById("password");
        const icon = button.querySelector("i");

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            icon.classList.remove("bi-eye");
            icon.classList.add("bi-eye-slash");
        } else {
            passwordInput.type = "password";
            icon.classList.remove("bi-eye-slash");
            icon.classList.add("bi-eye");
        }
    }
</script>

</body>
</html>
