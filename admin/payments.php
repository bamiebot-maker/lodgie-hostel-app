<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Manage Payments";

// --- Fetch All Payments ---
$sql = "SELECT 
            p.*, 
            u_tenant.name as tenant_name,
            b.hostel_id,
            h.name as hostel_name
        FROM payments p
        JOIN users u_tenant ON p.tenant_id = u_tenant.id
        JOIN bookings b ON p.booking_id = b.id
        JOIN hostels h ON b.hostel_id = h.id
        ORDER BY p.created_at DESC
";
$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll();

// --- Header ---
get_header($pdo); // Pass $pdo for notifications
?>

<h1 class="h3 mb-4">All Payments</h1>

<?php display_flash_messages(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Payment History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Tenant</th>
                        <th>Hostel</th>
                        <th>Amount</th>
                        <th>Reference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="6" class="text-center p-4">No payments found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M j, Y, g:i a', strtotime($payment['created_at'])); ?></td>
                                <td><?php echo sanitize($payment['tenant_name']); ?></td>
                                <td><?php echo sanitize($payment['hostel_name']); ?></td>
                                <td class="fw-bold text-orange">
                                    <?php echo format_price($payment['amount']); ?>
                                </td>
                                <td>
                                    <?php echo sanitize($payment['paystack_reference']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        $status_map = ['pending' => 'warning', 'success' => 'success', 'failed' => 'danger'];
                                        echo $status_map[$payment['status']] ?? 'secondary'; 
                                    ?>">
                                        <?php echo ucfirst(sanitize($payment['status'])); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


<?php
// --- Footer ---
?>
        </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>