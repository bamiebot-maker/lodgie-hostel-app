<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "All Bookings";

// --- Fetch All Bookings ---
$sql = "SELECT 
            b.*, 
            h.name as hostel_name,
            u_tenant.name as tenant_name,
            u_landlord.name as landlord_name
        FROM bookings b
        JOIN hostels h ON b.hostel_id = h.id
        JOIN users u_tenant ON b.tenant_id = u_tenant.id
        JOIN users u_landlord ON b.landlord_id = u_landlord.id
        ORDER BY b.created_at DESC
";
$stmt = $pdo->query($sql);
$bookings = $stmt->fetchAll();

// --- Header ---
get_header($pdo); // Pass $pdo for notifications
?>

<h1 class="h3 mb-4">All Bookings</h1>

<?php display_flash_messages(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">Booking History</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Booking ID</th>
                        <th>Tenant</th>
                        <th>Landlord</th>
                        <th>Hostel</th>
                        <th>Total Price</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr><td colspan="6" class="text-center p-4">No bookings found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                <td><?php echo sanitize($booking['tenant_name']); ?></td>
                                <td><?php echo sanitize($booking['landlord_name']); ?></td>
                                <td><?php echo sanitize($booking['hostel_name']); ?></td>
                                <td class="fw-bold text-orange">
                                    <?php echo format_price($booking['total_price']); ?>
                                </td>
                                <td>
                                    <span class="badge fs-6 bg-<?php 
                                        $status_map = ['pending' => 'warning', 'paid' => 'success', 'confirmed' => 'primary', 'cancelled' => 'danger', 'completed' => 'secondary'];
                                        echo $status_map[$booking['status']] ?? 'light'; 
                                    ?>">
                                        <?php echo ucfirst(sanitize($booking['status'])); ?>
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