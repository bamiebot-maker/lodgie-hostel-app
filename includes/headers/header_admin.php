<?php
/**
 * Admin Header (Refined & Corrected)
 *
 * This header is for the admin backend. Includes sidebar navigation.
 */

// We assume config.php, db.php, and helpers.php are loaded by the page
require_once __DIR__ . '/../notifications.php';

// Fetch notifications
$user_id = $_SESSION['user_id'] ?? 0;
// Make sure $pdo is available here (it should be passed via get_header($pdo))
$unread_count = 0;
$notifications = [];
$pending_hostels_count = 0;

if (isset($pdo)) {
    $unread_count = get_unread_notification_count($pdo, $user_id);
    $notifications = get_notifications($pdo, $user_id, 5); // Fetch notifications for the dropdown

    // Get pending hostels count
    try {
        $stmt_pending = $pdo->query("SELECT COUNT(id) FROM hostels WHERE status = 'pending'");
        $pending_hostels_count = $stmt_pending->fetchColumn();
    } catch (PDOException $e) {
        // Log error or handle gracefully if DB isn't ready
        error_log("Failed to get pending hostel count: " . $e->getMessage());
        $pending_hostels_count = 0; // Default to 0 on error
    }
} else {
    // Optional: Log a warning if $pdo isn't set, although helpers.php should trigger an error first
    error_log("Warning: \$pdo not available in header_admin.php");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Admin' : SITE_NAME . ' Admin'; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">

    <nav class="navbar navbar-expand navbar-dark bg-dark-orange fixed-top shadow-sm">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-outline-light me-2" id="sidebarToggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                    <?php echo SITE_NAME; ?> Admin
                </a>
            </div>

            <ul class="navbar-nav ms-auto d-flex flex-row align-items-center">
                
                <li class="nav-item dropdown ms-2">
                    <a class="nav-link text-white position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.6em; padding: .25em .4em;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="notificationDropdown" style="width: 320px;">
                        <li class="px-3 py-2 fw-bold d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $unread_count; ?> New</span>
                        </li>
                        <li><hr class="dropdown-divider my-0"></li>
                        
                        <div class="notification-list-wrapper">
                            <?php if (empty($notifications)): ?>
                                <li class="px-3 py-3 text-muted text-center">
                                    <i class="bi bi-bell-slash d-block fs-4 mb-1"></i> <small>No new notifications.</small>
                                </li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <li>
                                        <?php
                                        // **THIS IS THE FIX:** Construct the full URL
                                        $notification_url = '#'; // Default if no link
                                        if (!empty($notif['link'])) {
                                            $relative_link = ltrim(sanitize($notif['link']), '/');
                                            $notification_url = BASE_URL . '/' . $relative_link;
                                        }
                                        ?>
                                        <a class="dropdown-item notification-item <?php echo $notif['is_read'] ? '' : 'fw-bold'; ?>" href="<?php echo $notification_url; ?>" data-id="<?php echo $notif['id']; ?>">
                                            <div class="d-flex">
                                                <div class="pe-2 pt-1"> <i class="bi <?php 
                                                        $icon_map = ['success' => 'bi-check-circle-fill text-success', 'info' => 'bi-info-circle-fill text-primary', 'warning' => 'bi-exclamation-triangle-fill text-warning', 'danger' => 'bi-x-circle-fill text-danger'];
                                                        echo $icon_map[$notif['type']] ?? 'bi-bell-fill text-secondary';
                                                    ?> fs-5"></i>
                                                </div>
                                                <div class="notification-content"> <small class="d-block"><?php echo sanitize($notif['title']); ?></small>
                                                    <small class="text-muted d-block" style="font-size: 0.8em;"><?php echo substr(sanitize($notif['message']), 0, 70); ?>...</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <li><hr class="dropdown-divider my-0"></li>
                        <li><a class="dropdown-item text-center text-orange py-2" href="<?php echo BASE_URL; ?>/admin/notifications.php">
                            <small>View All Notifications</small>
                        </a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown ms-3">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" alt="Avatar" class="rounded-circle" width="32" height="32">
                        <span class="d-none d-sm-inline ms-2"><?php echo sanitize($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                        <li><span class="dropdown-item-text fw-bold"><?php echo sanitize($_SESSION['user_name'] ?? 'Admin'); ?></span></li>
                        <li><span class="dropdown-item-text text-muted small"><?php echo sanitize($_SESSION['user_email'] ?? ''); ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/profile.php"><i class="bi bi-person-fill me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/admin/settings.php"><i class="bi bi-gear-fill me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="d-flex" id="adminWrapper">

        <nav id="adminSidebar" class="bg-dark-orange text-white">
            <div class="sidebar-sticky py-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="<?php echo BASE_URL; ?>/admin/dashboard.php">
                            <i class="bi bi-grid-fill me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/users.php">
                            <i class="bi bi-people-fill me-2"></i> Manage Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/hostels.php">
                            <i class="bi bi-building-fill me-2"></i> Manage Hostels
                            <?php if ($pending_hostels_count > 0): ?>
                                <span class="badge bg-warning text-dark ms-auto"><?php echo $pending_hostels_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/bookings.php">
                            <i class="bi bi-calendar-check-fill me-2"></i> All Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/payments.php">
                            <i class="bi bi-credit-card-fill me-2"></i> Payments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/notifications.php">
                            <i class="bi bi-bell-fill me-2"></i> Notifications
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="<?php echo BASE_URL; ?>/admin/settings.php">
                            <i class="bi bi-gear-fill me-2"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <main id="adminContent" class="flex-grow-1 p-4">
            