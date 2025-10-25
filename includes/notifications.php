<?php
/**
 * Notification System Functions
 *
 * Handles creating, fetching, and marking notifications as read.
 */

/**
 * Creates a new notification for a user.
 * (This function is likely correct, no changes needed)
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'info', $link = null) {
    try {
        $sql = "INSERT INTO notifications (user_id, title, message, type, link) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$user_id, $title, $message, $type, $link]);
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches notifications for a specific user.
 *
 * **THIS IS THE FUNCTION WITH THE ERROR**
 */
function get_notifications($pdo, $user_id, $limit = 5, $unread_only = false) {
    
    // **FIX:** We must use only positional (?) placeholders.
    
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    
    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }
    
    // **FIX 1:** Changed :limit to ?
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    
    // **FIX 2:** Bind all values by position
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int) $limit, PDO::PARAM_INT); // Limit is now the 2nd parameter
    
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * Counts unread notifications for a user.
 * (This function is correct)
 */
function get_unread_notification_count($pdo, $user_id) {
    $sql = "SELECT COUNT(id) FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Marks a notification as read.
 * (This function is correct)
 */
function mark_notification_as_read($pdo, $notification_id, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 
            WHERE id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$notification_id, $user_id]);
}

/**
 * Marks all notifications for a user as read.
 * (This function is correct)
 */
function mark_all_notifications_as_read($pdo, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$user_id]);
}

?>