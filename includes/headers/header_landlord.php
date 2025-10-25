<?php
/**
 * Landlord Header (Corrected)
 *
 * This header is shown to logged-in landlords.
 */

// We ONLY need to include notifications.php here.
require_once __DIR__ . '/../notifications.php';

// Fetch notifications
$user_id = $_SESSION['user_id'] ?? 0;
// Make sure $pdo is available here (it should be passed via get_header($pdo))
$unread_count = 0;
$notifications = [];

if (isset($pdo)) {
    $unread_count = get_unread_notification_count($pdo, $user_id);
    $notifications = get_notifications($pdo, $user_id, 5);
} else {
    error_log("Warning: \$pdo not available in header_landlord.php");
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"> <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body class="dashboard-body bg-light d-flex flex-column h-100">

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand text-orange fw-bold" href="<?php echo BASE_URL; ?>/landlord/dashboard.php">
            <i class="bi bi-house-door-fill"></i> <?php echo SITE_NAME; ?> (Landlord)
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#landlordNavbar" aria-controls="landlordNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="landlordNavbar">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/landlord/dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/landlord/my_hostels.php">My Hostels</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/landlord/bookings.php">Bookings</a>
                </li>
                <li class="nav-item">
                    <a class="btn btn-orange btn-sm ms-lg-2" href="<?php echo BASE_URL; ?>/landlord/add_hostel.php">
                        <i class="bi bi-plus-circle-fill me-1"></i> Add Hostel
                    </a>
                </li>

                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link text-dark position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6em;">
                                <?php echo $unread_count; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="notificationDropdown" style="width: 300px;">
                        <li class="px-3 py-2 fw-bold">Notifications</li>
                        <li><hr class="dropdown-divider my-0"></li>
                         <div class="notification-list-wrapper"> <?php if (empty($notifications)): ?>
                                <li class="px-3 py-3 text-muted text-center"> <i class="bi bi-bell-slash d-block fs-4 mb-1"></i>
                                    <small>No new notifications.</small>
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
                                            <div class="d-flex"> <div class="pe-2 pt-1">
                                                     <i class="bi <?php
                                                        $icon_map = ['success' => 'bi-check-circle-fill text-success', 'info' => 'bi-info-circle-fill text-primary', 'warning' => 'bi-exclamation-triangle-fill text-warning', 'danger' => 'bi-x-circle-fill text-danger'];
                                                        echo $icon_map[$notif['type']] ?? 'bi-bell-fill text-secondary';
                                                     ?> fs-5"></i>
                                                </div>
                                                <div class="notification-content">
                                                    <small class="d-block"><?php echo sanitize($notif['title']); ?></small>
                                                    <small class="d-block text-muted" style="font-size: 0.8em;"><?php echo substr(sanitize($notif['message']), 0, 70); ?>...</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <li><hr class="dropdown-divider my-0"></li>
                        <li><a class="dropdown-item text-center text-orange py-2" href="<?php echo BASE_URL; ?>/landlord/notifications.php">
                            <small>View All Notifications</small>
                        </a></li>
                    </ul>
                </li>
                
                <li class="nav-item dropdown ms-lg-2">
                    <a class="nav-link dropdown-toggle text-dark d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" alt="Avatar" class="rounded-circle" width="32" height="32"> <span class="ms-2 d-none d-lg-inline"><?php echo sanitize($_SESSION['user_name'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="profileDropdown">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/landlord/profile.php"><i class="bi bi-person-fill me-2"></i>My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="flex-shrink-0">

    <div class="container-fluid p-4">
        