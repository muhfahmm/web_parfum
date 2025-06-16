<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Noorden Website</title>
    <link rel="icon" href="../img/logo/icon web.svg">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .construction-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            padding: 60px 0;
            text-align: center;
        }

        .construction-image {
            max-width: 100%;
            height: auto;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .countdown {
            font-size: 1.5rem;
            margin: 20px 0;
            font-weight: bold;
            color: #343a40;
        }

        .social-icons {
            margin-top: 30px;
        }

        .social-icons a {
            display: inline-block;
            margin: 0 10px;
            font-size: 1.5rem;
            color: #6c757d;
            transition: all 0.3s;
        }

        .social-icons a:hover {
            color: #0d6efd;
            transform: translateY(-3px);
        }

        .progress {
            height: 10px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="construction-page">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <img src="../img/maintenance/kontruksi.jpg"
                        alt="Under Construction"
                        class="construction-image img-fluid">

                    <h1 class="display-4 fw-bold mb-3">Halaman Sedang Dalam Pembangunan</h1>
                    <p class="lead mb-4">Kami sedang bekerja keras untuk menyelesaikan halaman ini. Silakan kembali lagi nanti atau ikuti perkembangan kami melalui media sosial.</p>

                    <div class="progress mb-4">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                            role="progressbar"
                            style="width: 80%;"
                            aria-valuenow="65"
                            aria-valuemin="0"
                            aria-valuemax="100">80%</div>
                    </div>

                    <div class="countdown mb-4">
                        <i class="fas fa-clock me-2"></i>Perkiraan selesai: 5 Hari Lagi
                    </div>

                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mb-5">
                        <a href="../home.php" class="btn btn-primary btn-lg px-4 gap-3">
                            <i class="fas fa-home me-2"></i>Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>