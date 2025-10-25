<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('tenant');
$tenant_id = $_SESSION['user_id'];
$booking_id = (int)($_GET['id'] ?? 0);

if ($booking_id === 0) {
    die('Invalid booking ID.'); // Simple error for a printable page
}

// --- Fetch Booking & Payment Details ---
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.*,
            p.paystack_reference, p.paid_at,
            h.name as hostel_name, h.address as hostel_address,
            u_landlord.name as landlord_name
        FROM bookings b
        JOIN payments p ON b.id = p.booking_id
        JOIN hostels h ON b.hostel_id = h.id
        JOIN users u_landlord ON b.landlord_id = u_landlord.id
        WHERE b.id = ? 
          AND b.tenant_id = ? 
          AND (b.status = 'paid' OR b.status = 'confirmed' OR b.status = 'completed')
          AND p.status = 'success'
    ");
    $stmt->execute([$booking_id, $tenant_id]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        die('Receipt not found, not paid, or you do not have permission to view it.');
    }

} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

$page_title = "Receipt #" . $receipt['paystack_reference'];

// We won't use get_header() or get_footer() for this printable page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .receipt-container {
            max-width: 800px;
            margin: 40px auto;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .receipt-header {
            background-color: #f97316;
            color: #fff;
            padding: 2rem;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
        }
        .receipt-header h1 {
            margin: 0;
            font-weight: 600;
        }
        .receipt-body {
            padding: 2rem;
        }
        .receipt-footer {
            padding: 2rem;
            background-color: #f8f9fa;
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
            text-align: center;
        }
        .line-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .line-item:last-child {
            border-bottom: none;
        }
        .total {
            font-size: 1.5rem;
            font-weight: 600;
            color: #f97316;
        }
        @media print {
            body {
                background-color: #fff;
            }
            .receipt-container {
                margin: 0;
                border: none;
                box-shadow: none;
                max-width: 100%;
            }
            .btn-print {
                display: none;
            }
            .receipt-footer {
                background-color: #fff;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="receipt-header">
            <h1>Payment Receipt</h1>
            <p class="mb-0">Thank you for your payment!</p>
        </div>
        <div class="receipt-body">
            <div class="row mb-4">
                <div class="col-sm-6">
                    <h5 class="text-orange">Billed To:</h5>
                    <p class="mb-0"><?php echo sanitize($_SESSION['user_name']); ?></p>
                    <p class="mb-0"><?php echo sanitize($_SESSION['user_email']); ?></p>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <h5 class="text-orange">Payment Details:</h5>
                    <p class="mb-0"><strong>Transaction ID:</strong> <?php echo sanitize($receipt['paystack_reference']); ?></p>
                    <p class="mb-0"><strong>Paid On:</strong> <?php echo date('M j, Y, g:i a', strtotime($receipt['paid_at'])); ?></p>
                </div>
            </div>

            <h5 class="text-orange">Order Summary</h5>
            <div class="line-item">
                <div>
                    <h6 class="mb-0"><?php echo sanitize($receipt['hostel_name']); ?></h6>
                    <small class="text-muted"><?php echo sanitize($receipt['hostel_address']); ?></small>
                </div>
                <span class="fw-bold"><?php echo format_price($receipt['total_price']); ?></span>
            </div>
            <div class="line-item">
                <span>Duration</span>
                <span><?php echo $receipt['duration_months']; ?> month(s)</span>
            </div>
            <div class="line-item">
                <span>Move-in Date</span>
                <span><?php echo date('M j, Y', strtotime($receipt['start_date'])); ?></span>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <div class="text-end">
                    <div class="mb-2">
                        <span class="text-muted">Subtotal:</span>
                        <span class="fw-bold ms-3"><?php echo format_price($receipt['total_price']); ?></span>
                    </div>
                    <div class="total">
                        <span>Total Paid:</span>
                        <span class="ms-3"><?php echo format_price($receipt['total_price']); ?></span>
                    </div>
                </div>
            </div>

            <hr class="my-4">
            <p class="text-muted small">
                This receipt confirms your payment for the above-listed hostel booking. Please contact the landlord, 
                <strong><?php echo sanitize($receipt['landlord_name']); ?></strong>, 
                to arrange your move-in.
            </p>
        </div>
        <div class="receipt-footer">
            <p class="text-muted mb-2">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All Rights Reserved.</p>
            <button class="btn btn-orange btn-print" onclick="window.print()">
                <i class="bi bi-printer-fill me-1"></i> Print Receipt
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>