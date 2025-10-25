<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';

// Check if user is logged in and is a landlord
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'landlord') {
    $_SESSION['message'] = 'Access denied';
    $_SESSION['message_type'] = 'danger';
    header('Location: ../index.php');
    exit;
}

$hostel_id = $_GET['id'] ?? 0;

if (!$hostel_id) {
    $_SESSION['message'] = 'No hostel specified';
    $_SESSION['message_type'] = 'danger';
    header('Location: my.php');
    exit;
}

try {
    // Verify hostel belongs to landlord
    $hostel = $db->fetch(
        "SELECT id, title FROM hostels WHERE id = ? AND landlord_id = ?",
        [$hostel_id, $_SESSION['user_id']]
    );

    if (!$hostel) {
        $_SESSION['message'] = 'Hostel not found';
        $_SESSION['message_type'] = 'danger';
        header('Location: my.php');
        exit;
    }

    // First delete hostel images files
    $images = $db->fetchAll(
        "SELECT filename FROM hostel_images WHERE hostel_id = ?",
        [$hostel_id]
    );
    
    foreach ($images as $image) {
        $file_path = __DIR__ . '/../uploads/hostels/' . $image['filename'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // Delete hostel from database
    $deleted = $db->delete("DELETE FROM hostels WHERE id = ?", [$hostel_id]);

    if ($deleted) {
        $_SESSION['message'] = 'Hostel "' . htmlspecialchars($hostel['title']) . '" deleted successfully';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Failed to delete hostel';
        $_SESSION['message_type'] = 'danger';
    }

} catch (Exception $e) {
    $_SESSION['message'] = 'Error deleting hostel: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

header('Location: my.php');
exit;
?>