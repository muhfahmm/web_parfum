<?php
session_start();
require '../db.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validasi sederhana
    if (!empty($username) && !empty($phone) && !empty($email) && !empty($password)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Cek apakah username sudah digunakan
        $checkStmt = $conn->prepare("SELECT id FROM tb_userLogin WHERE username = ?");
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "Username sudah digunakan.";
        } else {
            // Simpan ke database
            $stmt = $conn->prepare("INSERT INTO tb_userLogin (username, nomor_hp, email, password) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $phone, $email, $hashedPassword);

            if ($stmt->execute()) {
                $success = "Registrasi berhasil. Silakan login.";
            } else {
                $error = "Gagal mendaftar. Silakan coba lagi.";
            }

            $stmt->close();
        }

        $checkStmt->close();
    } else {
        $error = "Semua field harus diisi.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Halaman Register</title>
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

        .form-floating .form-control {
            border: none;
            border-bottom: 2px solid #ced4da;
            border-radius: 0;
            background-color: transparent;
            padding-left: 0;
            transition: border-color 0.3s ease;
        }

        .form-floating .form-control:focus {
            border-color: #0d6efd;
            box-shadow: none;
        }

        .form-floating>label {
            padding-left: 0;
            color: #6c757d;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            border: none;
            background: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: #6c757d;
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

        <h4 class="mb-4 text-center">Registrasi Akun</h4>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-floating mb-4">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required />
                <label for="username">Username</label>
            </div>

            <div class="form-floating mb-4">
                <input type="tel" class="form-control" id="phone" name="phone" placeholder="+62 123 - 4567 - 8901" required />
                <label for="phone">Nomor HP</label>
            </div>

            <div class="form-floating mb-4">
                <input type="email" class="form-control" id="email" name="email" placeholder="email@example.com" required />
                <label for="email">Email</label>
            </div>

            <div class="form-floating mb-4 position-relative">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required />
                <label for="password">Password</label>
                <button type="button" class="toggle-password" onclick="togglePassword(this)">
                    <i class="bi bi-eye"></i>
                </button>
            </div>

            <button type="submit" class="btn btn-primary w-100">Daftar</button>

            <div class="text-center mt-3">
                <small>Sudah punya akun? <a href="./login.php" class="text-primary text-decoration-none">Login</a></small>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(button) {
            const password = document.getElementById("password");
            const icon = button.querySelector("i");

            if (password.type === "password") {
                password.type = "text";
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
            } else {
                password.type = "password";
                icon.classList.remove("bi-eye-slash");
                icon.classList.add("bi-eye");
            }
        }

        const phoneInput = document.getElementById('phone');
        const prefix = '+62 ';

        // Saat halaman load, set nilai default jika kosong
        window.addEventListener('load', () => {
            if (!phoneInput.value.startsWith(prefix)) {
                phoneInput.value = prefix;
            }
            setCaretToEnd();
        });

        // Fungsi untuk memindahkan cursor ke akhir input
        function setCaretToEnd() {
            const len = phoneInput.value.length;
            phoneInput.setSelectionRange(len, len);
        }

        phoneInput.addEventListener('keydown', function(e) {
            // Cegah hapus prefix
            if ((phoneInput.selectionStart <= prefix.length) &&
                (e.key === 'Backspace' || e.key === 'Delete' || e.key === 'ArrowLeft')) {
                e.preventDefault();
                setCaretToEnd();
            }
        });

        phoneInput.addEventListener('click', function() {
            if (phoneInput.selectionStart < prefix.length) {
                setCaretToEnd();
            }
        });

        phoneInput.addEventListener('input', function(e) {
            let value = phoneInput.value;

            // Jika prefix hilang, kembalikan prefix
            if (!value.startsWith(prefix)) {
                value = prefix + value.replace(/\D/g, '');
            } else {
                // Hapus semua karakter non-digit setelah prefix
                let afterPrefix = value.slice(prefix.length).replace(/\D/g, '');
                value = prefix + afterPrefix;
            }

            // Batasi maksimal 11 digit setelah prefix
            let digits = value.slice(prefix.length).slice(0, 11);

            // Format nomor: 123 - 4567 - 8901
            let formatted = prefix;
            if (digits.length <= 3) {
                formatted += digits;
            } else if (digits.length <= 7) {
                formatted += digits.slice(0, 3) + ' - ' + digits.slice(3);
            } else {
                formatted += digits.slice(0, 3) + ' - ' + digits.slice(3, 7) + ' - ' + digits.slice(7);
            }

            phoneInput.value = formatted;
            setCaretToEnd();
        });
    </script>
</body>

</html>