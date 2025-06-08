<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metode Pembayaran</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome untuk icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-method {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }

        .payment-method .method-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .payment-method .method-header.collapsed .fa-chevron-down {
            transform: rotate(0deg);
        }

        .payment-method .method-header .fa-chevron-down {
            transform: rotate(180deg);
            transition: transform 0.3s;
        }

        .method-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
        }

        .method-option:hover {
            background-color: #f1f1f1;
        }

        .method-option.active {
            background-color: #e7f1ff;
        }

        .method-option img {
            width: 40px;
            margin-right: 15px;
        }

        .selected-method {
            font-weight: bold;
            color: #0d6efd;
        }

        .sub-accordion .payment-method {
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ringkasan Pembayaran</h5>
                        <div class="mb-3">
                            <label class="form-label">Metode Pembayaran</label>
                            <button type="button" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#paymentModal">
                                <span id="selectedPaymentText">Metode pembayaran belum dipilih</span>
                                <i class="fas fa-chevron-right ms-2"></i>
                            </button>
                        </div>
                        <button class="btn btn-primary w-100">Bayar Sekarang</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title fs-5" id="paymentModalLabel">Pilih Metode Pembayaran</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="accordion" id="paymentMethodsAccordion">
                        <!-- E-Wallet -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseEwallet" aria-expanded="false" aria-controls="collapseEwallet">
                                <h5 class="mb-0">E-Wallet</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapseEwallet" class="collapse" aria-labelledby="headingEwallet" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="Gopay">
                                        <img src="./img/e wallet/gopay.png" alt="Gopay">
                                        <span>Gopay</span>
                                    </div>
                                    <div class="method-option" data-method="OVO">
                                        <img src="https://via.placeholder.com/40?text=OVO" alt="OVO">
                                        <span>OVO</span>
                                    </div>
                                    <div class="method-option" data-method="DANA">
                                        <img src="https://via.placeholder.com/40?text=DANA" alt="DANA">
                                        <span>DANA</span>
                                    </div>
                                    <div class="method-option" data-method="ShopeePay">
                                        <img src="https://via.placeholder.com/40?text=ShopeePay" alt="ShopeePay">
                                        <span>ShopeePay</span>
                                    </div>
                                    <div class="method-option" data-method="LinkAja">
                                        <img src="https://via.placeholder.com/40?text=LinkAja" alt="LinkAja">
                                        <span>LinkAja</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Virtual Account -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseBankTransfer" aria-expanded="false" aria-controls="collapseBankTransfer">
                                <h5 class="mb-0">Virtual Account</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapseBankTransfer" class="collapse" aria-labelledby="headingBankTransfer" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="Virtual Account BCA">
                                        <img src="https://via.placeholder.com/40?text=BCA" alt="BCA">
                                        <span>BCA</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account Mandiri">
                                        <img src="https://via.placeholder.com/40?text=Mandiri" alt="Mandiri">
                                        <span>Mandiri</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account BNI">
                                        <img src="https://via.placeholder.com/40?text=BNI" alt="BNI">
                                        <span>BNI</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account BRI">
                                        <img src="https://via.placeholder.com/40?text=BRI" alt="BRI">
                                        <span>BRI</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account BSI">
                                        <img src="https://via.placeholder.com/40?text=BSI" alt="BSI">
                                        <span>BSI</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account CIMB">
                                        <img src="https://via.placeholder.com/40?text=CIMB" alt="CIMB">
                                        <span>CIMB Niaga</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account Muamalat">
                                        <img src="https://via.placeholder.com/40?text=Muamalat" alt="Muamalat">
                                        <span>Bank Muamalat</span>
                                    </div>
                                    <div class="method-option" data-method="Virtual Account Mega">
                                        <img src="https://via.placeholder.com/40?text=Mega" alt="Mega">
                                        <span>Bank Mega</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Kartu Debit -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseDebitCard" aria-expanded="false" aria-controls="collapseDebitCard">
                                <h5 class="mb-0">Kartu Debit</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapseDebitCard" class="collapse" aria-labelledby="headingDebitCard" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="Visa">
                                        <img src="https://via.placeholder.com/40?text=Visa" alt="Visa">
                                        <span>Visa</span>
                                    </div>
                                    <div class="method-option" data-method="Mastercard">
                                        <img src="https://via.placeholder.com/40?text=Mastercard" alt="Mastercard">
                                        <span>Mastercard</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pay Later -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapsePayLater" aria-expanded="false" aria-controls="collapsePayLater">
                                <h5 class="mb-0">Pay Later</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapsePayLater" class="collapse" aria-labelledby="headingPayLater" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="ShopeePay Later">
                                        <img src="https://via.placeholder.com/40?text=SPLater" alt="ShopeePay Later">
                                        <span>ShopeePay Later</span>
                                    </div>
                                    <div class="method-option" data-method="Gopay Later">
                                        <img src="https://via.placeholder.com/40?text=GPLater" alt="Gopay Later">
                                        <span>Gopay Later</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Offline -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseOffline" aria-expanded="false" aria-controls="collapseOffline">
                                <h5 class="mb-0">Offline</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapseOffline" class="collapse" aria-labelledby="headingOffline" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="QRIS">
                                        <img src="https://via.placeholder.com/40?text=QRIS" alt="QRIS">
                                        <span>QRIS</span>
                                    </div>
                                    <div class="method-option" data-method="Cash on Delivery">
                                        <img src="https://via.placeholder.com/40?text=COD" alt="Cash on Delivery">
                                        <span>Cash on Delivery</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gerai Offline -->
                        <div class="payment-method">
                            <div class="method-header collapsed" data-bs-toggle="collapse" data-bs-target="#collapseRetail" aria-expanded="false" aria-controls="collapseRetail">
                                <h5 class="mb-0">Gerai Offline</h5>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div id="collapseRetail" class="collapse" aria-labelledby="headingRetail" data-bs-parent="#paymentMethodsAccordion">
                                <div class="method-content pt-3">
                                    <div class="method-option" data-method="Alfamart">
                                        <img src="https://via.placeholder.com/40?text=Alfamart" alt="Alfamart">
                                        <span>Alfamart</span>
                                    </div>
                                    <div class="method-option" data-method="Indomaret">
                                        <img src="https://via.placeholder.com/40?text=Indomaret" alt="Indomaret">
                                        <span>Indomaret</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" id="confirmPaymentMethod">Konfirmasi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let selectedMethod = null;

            // Pilih metode pembayaran
            const methodOptions = document.querySelectorAll('.method-option');
            methodOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Hapus active class dari semua opsi
                    methodOptions.forEach(opt => opt.classList.remove('active'));

                    // Tambahkan active class ke opsi yang dipilih
                    this.classList.add('active');
                    selectedMethod = this.getAttribute('data-method');
                });
            });

            // Konfirmasi pilihan
            document.getElementById('confirmPaymentMethod').addEventListener('click', function() {
                if (selectedMethod) {
                    document.getElementById('selectedPaymentText').textContent = selectedMethod;
                    document.getElementById('selectedPaymentText').classList.add('selected-method');

                    // Tutup modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    modal.hide();
                } else {
                    alert('Silakan pilih metode pembayaran terlebih dahulu');
                }
            });

            // Reset pilihan saat modal dibuka
            document.getElementById('paymentModal').addEventListener('show.bs.modal', function() {
                methodOptions.forEach(opt => opt.classList.remove('active'));
                selectedMethod = null;
            });
        });
    </script>
</body>

</html>