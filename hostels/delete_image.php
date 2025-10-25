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

$image_id = $_GET['id'] ?? 0;

if (!$image_id) {
    $_SESSION['message'] = 'No image specified';
    $_SESSION['message_type'] = 'danger';
    header('Location: my.php');
    exit;
}

try {
    // Verify image belongs to user's hostel
    $image = $db->fetch(
        "SELECT hi.*, h.title as hostel_title 
         FROM hostel_images hi 
         JOIN hostels h ON hi.hostel_id = h.id 
         WHERE hi.id = ? AND h.landlord_id = ?",
        [$image_id, $_SESSION['user_id']]
    );

    if (!$image) {
        $_SESSION['message'] = 'Image not found';
        $_SESSION['message_type'] = 'danger';
        header('Location: my.php');
        exit;
    }

    $hostel_id = $image['hostel_id'];
    $is_cover = $image['is_cover'];

    // Delete image file
    $file_path = __DIR__ . '/../uploads/hostels/' . $image['filename'];
    $file_deleted = false;
    
    if (file_exists($file_path)) {
        if (unlink($file_path)) {
            $file_deleted = true;
        }
    }

    // Delete image record from database
    $db_deleted = $db->delete("DELETE FROM hostel_images WHERE id = ?", [$image_id]);

    if (!$db_deleted) {
        throw new Exception("Failed to delete image from database");
    }

    // If deleted image was cover, set a new cover image
    if ($is_cover) {
        $new_cover = $db->fetch(
            "SELECT id FROM hostel_images WHERE hostel_id = ? ORDER BY id LIMIT 1",
            [$hostel_id]
        );
        
        if ($new_cover) {
            $db->update(
                "UPDATE hostel_images SET is_cover = 1 WHERE id = ?",
                [$new_cover['id']]
            );
        }
    }

    $_SESSION['message'] = 'Image deleted successfully';
    $_SESSION['message_type'] = 'success';
    header('Location: edit.php?id=' . $hostel_id);
    exit;

} catch (Exception $e) {
    $_SESSION['message'] = 'Error deleting image: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    
    if (isset($hostel_id)) {
        header('Location: edit.php?id=' . $hostel_id);
    } else {
        header('Location: my.php');
    }
    exit;
}
?>