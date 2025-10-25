<?php
// --- Core Includes ---
// 1. Config (for session)
require_once __DIR__ . '/../includes/config.php';
// 2. Database (for $pdo)
require_once __DIR__ . '/../includes/db.php';
// 3. Helpers (for protect_page, etc.)
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "My Hostels";
$landlord_id = $_SESSION['user_id'];

// --- Handle Delete Hostel Action ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hostel'])) {
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
    } else {
        $hostel_id_to_delete = (int) $_POST['hostel_id'];
        
        try {
            // First, get all image paths to delete them from the server
            $stmt_img = $pdo->prepare("SELECT image_path FROM hostel_images WHERE hostel_id = ?");
            $stmt_img->execute([$hostel_id_to_delete]);
            $images = $stmt_img->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete from DB (ON DELETE CASCADE in schema.sql will handle images, bookings, etc.)
            $stmt_del = $pdo->prepare("DELETE FROM hostels WHERE id = ? AND landlord_id = ?");
            $stmt_del->execute([$hostel_id_to_delete, $landlord_id]);
            
            if ($stmt_del->rowCount() > 0) {
                // Now delete the actual image files
                $upload_dir = __DIR__ . '/../uploads/hostels/';
                foreach ($images as $image) {
                    if ($image && file_exists($upload_dir . $image)) {
                        @unlink($upload_dir . $image); // @ supresses errors if file not found
                    }
                }
                $_SESSION['success_flash'] = 'Hostel successfully deleted.';
            } else {
                 $_SESSION['error_flash'] = 'Could not delete hostel or permission denied.';
            }
            
        } catch (PDOException $e) {
            // Handle foreign key constraint errors (e.g., if a booking exists and ON DELETE isn't set to CASCADE)
            $_SESSION['error_flash'] = 'Could not delete hostel. It may have active bookings.';
            error_log('Delete Hostel Error: ' . $e->getMessage());
        }
    }
    redirect('landlord/my_hostels.php');
}


// --- Fetch all hostels for this landlord ---
$stmt = $pdo->prepare("
    SELECT h.*, 
           (SELECT image_path FROM hostel_images hi WHERE hi.hostel_id = h.id ORDER BY hi.is_thumbnail DESC, hi.id ASC LIMIT 1) as thumbnail,
           (SELECT COUNT(b.id) FROM bookings b WHERE b.hostel_id = h.id AND b.status IN ('paid', 'confirmed')) as active_bookings
    FROM hostels h
    WHERE h.landlord_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$landlord_id]);
$hostels = $stmt->fetchAll();

$csrf_token = generate_csrf_token();

// --- Header ---
// **IMPORTANT:** Pass the $pdo connection to the header
get_header($pdo); 
?>

<div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">My Hostels</h1>
        <a href="add_hostel.php" class="btn btn-orange">
            <i class="bi bi-plus-circle me-1"></i> Add New Hostel
        </a>
    </div>

    <?php display_flash_messages(); ?>

    <?php if (empty($hostels)): ?>
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="bi bi-building-slash" style="font-size: 4rem;"></i>
                <h4 class="mt-3">You haven't listed any hostels yet.</h4>
                <p>Click the button above to get started!</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($hostels as $hostel): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card hostel-card h-100">
                        <img src="<?php echo BASE_URL . '/uploads/hostels/' . ($hostel['thumbnail'] ? sanitize($hostel['thumbnail']) : 'hostel_placeholder.jpg'); ?>" class="card-img-top" alt="<?php echo sanitize($hostel['name']); ?>">
                        
                        <?php
                        $status_map = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                        $status_color = $status_map[$hostel['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $status_color; ?> position-absolute top-0 end-0 m-2 fs-6">
                            <?php echo ucfirst(sanitize($hostel['status'])); ?>
                        </span>
                        
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo sanitize($hostel['name']); ?></h5>
                            <p class="card-text text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i> <?php echo sanitize($hostel['city']); ?></p>
                            
                            <div class="price mt-auto">
                                <?php echo format_price($hostel['price_per_month']); ?>
                                <span class="duration">/ month</span>
                            </div>
                            <p class="text-primary fw-bold mt-2"><?php echo $hostel['active_bookings']; ?> Active Booking(s)</p>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between">
                            <a href="edit_hostel.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-fill me-1"></i> Edit
                            </a>
                            
                            <form action="my_hostels.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this hostel? This action cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="hostel_id" value="<?php echo $hostel['id']; ?>">
                                <button type="submit" name="delete_hostel" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash-fill me-1"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php
// --- Footer ---
get_footer(); 
?>