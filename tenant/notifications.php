<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // Notification functions

// --- Page-Specific Logic ---
protect_page('tenant');
$page_title = "My Notifications";
$user_id = $_SESSION['user_id'];

// --- Handle "Mark All as Read" ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (verify_csrf_token($_POST['csrf_token'])) {
        if (mark_all_notifications_as_read($pdo, $user_id)) {
            $_SESSION['success_flash'] = 'All notifications marked as read.';
        } else {
            $_SESSION['error_flash'] = 'Could not mark all as read.';
        }
        redirect('tenant/notifications.php');
    }
}

// Fetch all notifications for this user (not just 5)
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$all_notifications = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); 
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Notifications</h1>
        <form action="notifications.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-orange">
                <i class="bi bi-check-all me-1"></i> Mark All as Read
            </button>
        </form>
    </div>

    <?php display_flash_messages(); ?>

    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($all_notifications)): ?>
                <div class="text-center p-5">
                    <i class="bi bi-bell-slash" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">You have no notifications.</h4>
                    <p>We'll let you know when something important happens.</p>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($all_notifications as $notif): ?>
                        <li class="list-group-item p-3 <?php echo $notif['is_read'] ? 'bg-light text-muted' : 'fw-bold'; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1 text-<?php echo sanitize($notif['type']); ?>">
                                    <?php 
                                    // Icon mapping
                                    $icon_map = [
                                        'success' => 'bi-check-circle-fill',
                                        'info' => 'bi-info-circle-fill',
                                        'warning' => 'bi-exclamation-triangle-fill',
                                        'danger' => 'bi-x-circle-fill'
                                    ];
                                    ?>
                                    <i class="bi <?php echo $icon_map[$notif['type']] ?? 'bi-bell-fill'; ?> me-2"></i>
                                    <?php echo sanitize($notif['title']); ?>
                                </h5>
                                <small><?php echo date('M j, Y \a\t g:i a', strtotime($notif['created_at'])); ?></small>
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

<?php
// --- Footer ---
get_footer(); 
?>