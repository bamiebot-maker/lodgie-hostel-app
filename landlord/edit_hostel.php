<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
// We don't need notifications.php here, but the header does

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "Edit Hostel";
$landlord_id = $_SESSION['user_id'];
$hostel_id = (int)($_GET['id'] ?? 0);

if ($hostel_id === 0) {
    $_SESSION['error_flash'] = 'Invalid hostel ID.';
    redirect('landlord/my_hostels.php');
}

// --- Fetch Hostel Data ---
$stmt = $pdo->prepare("SELECT * FROM hostels WHERE id = ? AND landlord_id = ?");
$stmt->execute([$hostel_id, $landlord_id]);
$hostel = $stmt->fetch();

if (!$hostel) {
    $_SESSION['error_flash'] = 'Hostel not found or you do not have permission to edit it.';
    redirect('landlord/my_hostels.php');
}

// (All form handling logic as before, it was correct)
// ...
// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_hostel'])) {
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
        redirect('landlord/edit_hostel.php?id=' . $hostel_id);
    }

    // 2. Sanitize Text Inputs
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $price = (float) sanitize($_POST['price']);
    $amenities = isset($_POST['amenities']) ? implode(',', array_map('sanitize', $_POST['amenities'])) : '';

    $errors = [];

    // 3. Validate Text Inputs
    if (empty($name)) $errors[] = "Hostel name is required.";
    if ($price <= 0) $errors[] = "Price must be a positive number.";

    // 4. Update Database
    if (empty($errors)) {
        try {
            // Update `hostels` table
            // Set status to 'pending' to force re-approval
            $sql_hostel = "UPDATE hostels SET 
                                name = ?, 
                                description = ?, 
                                address = ?, 
                                city = ?, 
                                price_per_month = ?, 
                                amenities = ?,
                                status = 'pending'
                           WHERE id = ? AND landlord_id = ?";
            $stmt_hostel = $pdo->prepare($sql_hostel);
            $stmt_hostel->execute([$name, $description, $address, $city, $price, $amenities, $hostel_id, $landlord_id]);
            
            $_SESSION['success_flash'] = 'Hostel updated successfully! It is now pending re-approval.';
            redirect('landlord/my_hostels.php');

        } catch (PDOException $e) {
            $_SESSION['error_flash'] = 'Database error. Could not update hostel.';
            error_log('Edit Hostel Error: ' . $e->getMessage());
            redirect('landlord/edit_hostel.php?id=' . $hostel_id);
        }
    } else {
        // Display errors
        $_SESSION['error_flash'] = implode('<br>', $errors);
        redirect('landlord/edit_hostel.php?id=' . $hostel_id);
    }
}

// Fetch existing images (for display)
$stmt_img = $pdo->prepare("SELECT id, image_path FROM hostel_images WHERE hostel_id = ? ORDER BY is_thumbnail DESC");
$stmt_img->execute([$hostel_id]);
$existing_images = $stmt_img->fetchAll();

// Get amenities
$current_amenities = !empty($hostel['amenities']) ? explode(',', $hostel['amenities']) : [];
$csrf_token = generate_csrf_token();

// --- Header ---
// **MUST** pass $pdo to get_header() for notifications
get_header($pdo); 
?>

<h1 class="h3 mb-4">Edit Hostel: <?php echo sanitize($hostel['name']); ?></h1>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            
            <?php display_flash_messages(); ?>

            <form action="edit_hostel.php?id=<?php echo $hostel_id; ?>" method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Editing your hostel will set its status back to "Pending" for admin re-approval.
                </div>

                <h5 class="mb-3">Basic Information</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">Hostel Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize($hostel['name']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">Price (per month)</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo SITE_CURRENCY_SYMBOL; ?></span>
                            <input type="number" class="form-control" id="price" name="price" value="<?php echo sanitize($hostel['price_per_month']); ?>" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo sanitize($hostel['description']); ?></textarea>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Location</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="address" class="form-label">Full Address</label>
                        <input type="text" class="form-control" id="address" name="address" value="<?php echo sanitize($hostel['address']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="city" class="form-label">City / Town</label>
                        <input type="text" class="form-control" id="city" name="city" value="<?php echo sanitize($hostel['city']); ?>" required>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Amenities</h5>
                <div class="row g-2">
                    <?php 
                    $common_amenities = ['Wi-Fi', 'Water', 'Electricity', 'Security', 'Parking', 'Kitchen', 'Private Bathroom', 'AC', 'Fan', 'Cleaning Service'];
                    foreach ($common_amenities as $amenity): 
                        $id = strtolower(str_replace(' ', '-', $amenity));
                        $checked = in_array($amenity, $current_amenities) ? 'checked' : '';
                    ?>
                        <div class="col-md-4 col-sm-6">
                            <div classs="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="<?php echo $amenity; ?>" id="amenity-<?php echo $id; ?>" <?php echo $checked; ?>>
                                <label class="form-check-label" for="amenity-<?php echo $id; ?>">
                                    <?php echo $amenity; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Existing Images</h5>
                <p class="text-muted small">To add or remove images, please delete and re-create the hostel listing. (Full image management on the edit page is a complex feature).</p>
                <div class="row g-2">
                    <?php foreach ($existing_images as $image): ?>
                        <div class="col-4 col-md-2">
                            <img src="<?php echo BASE_URL . '/uploads/hostels/' . sanitize($image['image_path']); ?>" class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <hr class="my-4">

                <div class="text-end">
                    <a href="my_hostels.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="update_hostel" class="btn btn-orange">Update Hostel</button>
                </div>
            </form>
        </div>
    </div>

<?php
// --- Footer ---
get_footer(); 
?>