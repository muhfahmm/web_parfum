<?php
session_start();
$conn = mysqli_connect("localhost", "root", "", "db_makaroni");
if (!$conn) die("Connection failed: " . mysqli_connect_error());

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek apakah username sudah ada
    $check = mysqli_query($conn, "SELECT * FROM tb_admin WHERE username = '$username'");
    if (mysqli_num_rows($check) > 0) {
        $message = "Username sudah digunakan.";
    } elseif ($password !== $confirm_password) {
        $message = "Konfirmasi password tidak cocok.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = mysqli_query($conn, "INSERT INTO tb_admin (username, password) VALUES ('$username', '$hashed_password')");
        if ($query) {
            $message = "Registrasi berhasil. Silakan login.";
        } else {
            $message = "Gagal mendaftar: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container mt-5">
        <h2>Register Admin</h2>
        <?php if ($message) echo "<div class='alert alert-info'>$message</div>"; ?>
        <form method="post">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>Konfirmasi Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button class="btn btn-primary">Register</button>
            <a href="login.php" class="btn btn-link text-white">Login</a>
        </form>
    </div>
</body>
</html>
