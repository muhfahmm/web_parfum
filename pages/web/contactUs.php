<?php
require '../db.php';

// Proses form jika dikirim
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $message = $_POST['message'];

    // Validasi input
    $errors = [];

    if (empty($firstName)) {
        $errors[] = "First name is required";
    }

    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }

    if (empty($message)) {
        $errors[] = "Message is required";
    }

    // Jika tidak ada error, simpan ke database
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO tb_contact (first_name, last_name, email, phone_number, message) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $phone, $message);

        if ($stmt->execute()) {
            $modalTitle = "Success!";
            $modalMessage = "Thank you for contacting us! We'll get back to you soon.";
            $modalType = "success";
        } else {
            $modalTitle = "Error";
            $modalMessage = "Failed to save your message. Please try again.";
            $modalType = "danger";
        }

        $stmt->close();
    } else {
        $modalTitle = "Validation Error";
        $modalMessage = implode("<br>", $errors);
        $modalType = "danger";
    }

    // Set flag untuk menampilkan modal
    $showModal = true;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background-color: #f8f9fa;
        }

        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }

        .card-header {
            border-bottom: none;
        }

        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        .btn-primary {
            width: 100%;
            padding: 10px;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <div class="container mt-5 pt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../home.php" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
            </ol>
        </nav>
    </div>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h3 class="card-title mb-0">Contact Us</h3>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="">
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3 mb-md-0">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required autocomplete="off">
                            </div>

                            <div class="mb-4">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required autocomplete="off"></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">Submit</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal untuk Notifikasi -->
        <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-<?php echo isset($modalType) ? $modalType : 'primary'; ?> text-white">
                        <h5 class="modal-title" id="notificationModalLabel"><?php echo isset($modalTitle) ? $modalTitle : 'Notification'; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo isset($modalMessage) ? $modalMessage : ''; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-<?php echo isset($modalType) ? $modalType : 'primary'; ?>" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>



        <?php if (isset($showModal) && $showModal): ?>
            <script>
                // Tampilkan modal ketika halaman selesai dimuat
                document.addEventListener('DOMContentLoaded', function() {
                    var myModal = new bootstrap.Modal(document.getElementById('notificationModal'));
                    myModal.show();

                    // Reset form jika sukses
                    <?php if (isset($modalType) && $modalType === 'success'): ?>
                        document.querySelector('form').reset();
                    <?php endif; ?>
                });
            </script>
        <?php endif; ?>
    </div>
    <div class="container">
        <div class="admin-card">
            <div class="admin-header">
                <h2>Detail Admin</h2>
            </div>

            <div class="admin-content">
                <div class="admin-section">
                    <h3>Nomor Telepon</h3>
                    <div class="admin-info">
                        <p><strong>Admin 1:</strong> +62 812-3456-7890</p>
                        <p><strong>Admin 2:</strong> +62 823-4567-8901</p>
                    </div>
                </div>

                <div class="admin-divider"></div>

                <div class="admin-section">
                    <h3>Email</h3>
                    <div class="admin-info">
                        <p><strong>Admin 1:</strong> admin1@example.com</p>
                        <p><strong>Admin 2:</strong> admin2@example.com</p>
                    </div>
                </div>
            </div>
            <style>
                .admin-card {
                    margin: 20px auto;
                    border-radius: 10px;
                    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                    font-family: Arial, sans-serif;
                }

                .admin-header {
                    background-color: #4a6fa5;
                    color: white;
                    padding: 15px 20px;
                }

                .admin-header h2 {
                    margin: 0;
                    font-size: 1.5rem;
                }

                .admin-content {
                    padding: 20px;
                    background-color: white;
                }

                .admin-section h3 {
                    color: #333;
                    margin-top: 0;
                    margin-bottom: 15px;
                    font-size: 1.2rem;
                }

                .admin-info p {
                    margin: 8px 0;
                    color: #555;
                }

                .admin-divider {
                    height: 1px;
                    background-color: #e0e0e0;
                    margin: 20px 0;
                }
            </style>
        </div>
    </div>


    <script src="../js/darkmode.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <!-- Logo -->
                <div class="col-md-2 offset-md-1 mb-3">
                    <div class="fw-bold"><img src="../img/logo/logo.svg" height="50px"></div>
                </div>

                <!-- Menu Navigasi -->
                <div class="col-md-2 mb-3">
                    <h5>Menu</h5>
                    <ul class="list-unstyled">
                        <li><a href="home.php" class="text-white text-decoration-none">Home</a></li>
                        <li><a href="../products/product.php" class="text-white text-decoration-none">Produk</a></li>
                        <li><a href="../web/aboutUs.php" class="text-white text-decoration-none">About Us</a></li>
                        <li><a href="../web/blog.php" class="text-white text-decoration-none">Blog</a></li>
                        <li><a href="../web/contactUs.php" class="text-white text-decoration-none">Contact Us</a></li>
                    </ul>
                </div>

                <!-- Sosial Media -->
                <div class="col-md-3 mb-3">
                    <h5>Social Media</h5>
                    <a href="#" class="text-white me-3"><i class="bi bi-instagram fs-4"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-tiktok fs-4"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-facebook fs-4"></i></a>
                </div>

                <!-- Marketplace -->
                <div class="col-md-2 mb-3">
                    <h5>Marketplace</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white text-decoration-none">Shopee</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Tokopedia</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>