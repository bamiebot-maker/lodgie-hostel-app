<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // Notification functions

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Admin Notifications";
$user_id = $_SESSION['user_id']; // Admin's own user ID

// --- Handle "Broadcast Notification" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        $title = sanitize($_POST['title']);
        $message = sanitize($_POST['message']);
        $type = sanitize($_POST['type']);
        $role = sanitize($_POST['role']); // 'all', 'tenants', 'landlords'
        
        try {
            $sql_users = "SELECT id FROM users WHERE role != 'admin'";
            $params = [];
            if ($role === 'landlord' || $role === 'tenant') {
                $sql_users .= " AND role = ?";
                $params[] = $role;
            }
            
            $stmt_users = $pdo->prepare($sql_users);
            $stmt_users->execute($params);
            $user_ids = $stmt_users->fetchAll(PDO::FETCH_COLUMN);
            
            $pdo->beginTransaction();
            foreach ($user_ids as $uid) {
                create_notification($pdo, $uid, $title, $message, $type);
            }
            $pdo->commit();
            
            $_SESSION['success_flash'] = 'Broadcast notification sent to ' . count($user_ids) . ' user(s).';

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_flash'] = 'Failed to send broadcast.';
            error_log('Broadcast Error: ' . $e->getMessage());
        }
    }
    redirect('admin/notifications.php');
}


// Fetch admin's own notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$all_notifications = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); 
?>

<h1 class="h3 mb-4">Admin Notifications</h1>

<?php display_flash_messages(); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">Broadcast Notification</h5>
            </div>
            <div class="card-body">
                <form action="notifications.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="mb-3">
                        <label for="role" class="form-label">Send To:</label>
                        <select id="role" name="role" class="form-select" required>
                            <option value="all">All Users (Tenants & Landlords)</option>
                            <option value="tenant">Tenants Only</option>
                            <option value="landlord">Landlords Only</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Notification Type</label>
                        <select id="type" name="type" class="form-select" required>
                            <option value="info">Info (Blue)</option>
                            <option value="success">Success (Green)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="danger">Danger (Red)</option>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="broadcast" class="btn btn-orange">Send Broadcast</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0">My Notifications</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($all_notifications)): ?>
                    <div class="text-center p-5">
                        <i class="bi bi-bell-slash" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">You have no notifications.</h4>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($all_notifications as $notif): ?>
                            <li class="list-group-item p-3 <?php echo $notif['is_read'] ? 'bg-light text-muted' : 'fw-bold'; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h5 class="mb-1 text-<?php echo sanitize($notif['type']); ?>">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <?php echo sanitize($notif['title']); ?>
                                    </h5>
                                    <small><?php echo date('M j, Y, g:i a', strtotime($notif['created_at'])); ?></small>
                                </div>
                                <p class="mb-1"><?php echo sanitize($notif['message']); ?></p>
                                <?php if ($notif['link']): ?>
                                    <a href="<?php echo BASE_URL . '/' . ltrim(sanitize($notif['link']), '/'); ?>" class="btn btn-sm btn-outline-primary notification-item" data-id="<?php echo $notif['id']; ?>">
                                        View Details
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
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