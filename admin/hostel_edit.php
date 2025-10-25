<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "Edit Hostel";
require_once '../includes/headers/header_admin.php';

$db = new Database();
$error = '';
$success = '';

// Get hostel data
$hostel_id = intval($_GET['id'] ?? 0);
if (!$hostel_id) {
    header('Location: hostels.php');
    exit;
}

$stmt = $db->query("
    SELECT h.*, u.first_name, u.last_name, u.email as landlord_email
    FROM hostels h 
    JOIN users u ON h.landlord_id = u.id 
    WHERE h.id = ?
", [$hostel_id]);
$hostel = $stmt->fetch();

if (!$hostel) {
    $_SESSION['error'] = "Hostel not found";
    header('Location: hostels.php');
    exit;
}

// Get landlords for dropdown
$stmt = $db->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'landlord' AND is_active = TRUE ORDER BY first_name, last_name");
$landlords = $stmt->fetchAll();

// Get hostel images
$stmt = $db->query("SELECT * FROM hostel_images WHERE hostel_id = ? ORDER BY is_primary DESC", [$hostel_id]);
$images = $stmt->fetchAll();

// Handle form submission
if ($_POST) {
    try {
        // Validate required fields
        $required = ['name', 'description', 'address', 'city', 'state', 'price_per_month', 'total_rooms', 'landlord_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }

        // Validate numbers
        if (!is_numeric($_POST['price_per_month']) || $_POST['price_per_month'] <= 0) {
            throw new Exception("Price must be a positive number");
        }

        if (!is_numeric($_POST['total_rooms']) || $_POST['total_rooms'] <= 0) {
            throw new Exception("Total rooms must be a positive number");
        }

        // Calculate available rooms
        $stmt = $db->query("SELECT COUNT(*) as booked_rooms FROM bookings WHERE hostel_id = ? AND status IN ('confirmed', 'pending')", [$hostel_id]);
        $booked_rooms = $stmt->fetch()['booked_rooms'];
        $available_rooms = max(0, $_POST['total_rooms'] - $booked_rooms);

        // Prepare amenities and rules
        $amenities = isset($_POST['amenities']) ? json_encode($_POST['amenities']) : '[]';
        $rules = isset($_POST['rules']) ? json_encode($_POST['rules']) : '[]';

        // Update hostel
        $sql = "UPDATE hostels SET 
                landlord_id = ?, name = ?, description = ?, address = ?, city = ?, state = ?,
                school_proximity = ?, price_per_month = ?, total_rooms = ?, available_rooms = ?,
                amenities = ?, rules = ?, latitude = ?, longitude = ?, 
                is_approved = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?";

        $db->query($sql, [
            $_POST['landlord_id'],
            sanitize_input($_POST['name']),
            sanitize_input($_POST['description']),
            sanitize_input($_POST['address']),
            sanitize_input($_POST['city']),
            sanitize_input($_POST['state']),
            sanitize_input($_POST['school_proximity'] ?? ''),
            $_POST['price_per_month'],
            $_POST['total_rooms'],
            $available_rooms,
            $amenities,
            $rules,
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            isset($_POST['is_approved']) ? 1 : 0,
            isset($_POST['is_active']) ? 1 : 0,
            $hostel_id
        ]);

        // Handle new image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $upload_dir = '../uploads/hostels';
            
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                    $validation_errors = validate_image_upload([
                        'name' => $_FILES['images']['name'][$key],
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $tmp_name,
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ]);

                    if (empty($validation_errors)) {
                        $filename = upload_image([
                            'name' => $_FILES['images']['name'][$key],
                            'tmp_name' => $tmp_name
                        ], $upload_dir);

                        if ($filename) {
                            // Check if we need a primary image
                            $is_primary = (count($images) === 0 && $key === 0) ? 1 : 0;
                            
                            $db->query("
                                INSERT INTO hostel_images (hostel_id, image_path, is_primary)
                                VALUES (?, ?, ?)
                            ", [$hostel_id, $filename, $is_primary]);
                        }
                    }
                }
            }
        }

        // Send notification if approval status changed
        if ($hostel['is_approved'] != (isset($_POST['is_approved']) ? 1 : 0)) {
            require_once '../includes/notifications.php';
            $notification = new Notification();
            $notification->notifyHostelApproval($hostel_id, isset($_POST['is_approved']));
        }

        $success = "Hostel updated successfully!";
        
        // Refresh hostel data
        $stmt = $db->query("
            SELECT h.*, u.first_name, u.last_name, u.email as landlord_email
            FROM hostels h 
            JOIN users u ON h.landlord_id = u.id 
            WHERE h.id = ?
        ", [$hostel_id]);
        $hostel = $stmt->fetch();

        // Refresh images
        $stmt = $db->query("SELECT * FROM hostel_images WHERE hostel_id = ? ORDER BY is_primary DESC", [$hostel_id]);
        $images = $stmt->fetchAll();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Common amenities and rules for checkboxes
$common_amenities = [
    'WiFi', 'Water Supply', 'Electricity', 'Security', 'Furnished',
    'Parking', 'Laundry', 'Kitchen', 'Common Room', 'Study Area',
    'Air Conditioning', 'Fan', 'Refrigerator', 'TV', 'CCTV'
];

$common_rules = [
    'No Smoking', 'No Pets', 'No Parties', 'Visitors Allowed',
    'Curfew Time', 'Keep Clean', 'No Loud Music', 'Separate Gender Hostels'
];

// Decode current amenities and rules
$current_amenities = json_decode($hostel['amenities'] ?? '[]', true);
$current_rules = json_decode($hostel['rules'] ?? '[]', true);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">Edit Hostel</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="hostels.php" class="btn btn-outline-orange">
            <i class="bi bi-arrow-left"></i> Back to Hostels
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Landlord *</label>
                                <select name="landlord_id" class="form-select" required>
                                    <option value="">Select Landlord</option>
                                    <?php foreach ($landlords as $landlord): ?>
                                        <option value="<?php echo $landlord['id']; ?>" 
                                                <?php echo ($hostel['landlord_id'] == $landlord['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($landlord['first_name'] . ' ' . $landlord['last_name']); ?> 
                                            (<?php echo htmlspecialchars($landlord['email']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Hostel Name *</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($hostel['name']); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price Per Month (₦) *</label>
                                <input type="number" name="price_per_month" class="form-control" 
                                       value="<?php echo $hostel['price_per_month']; ?>" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total Rooms *</label>
                                <input type="number" name="total_rooms" class="form-control" 
                                       value="<?php echo $hostel['total_rooms']; ?>" min="1" required>
                                <div class="form-text">
                                    Available rooms: <?php echo $hostel['available_rooms']; ?> (automatically calculated)
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" required><?php echo htmlspecialchars($hostel['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?php echo htmlspecialchars($hostel['city']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">State *</label>
                                <input type="text" name="state" class="form-control" 
                                       value="<?php echo htmlspecialchars($hostel['state']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nearby Schools/Universities</label>
                                <input type="text" name="school_proximity" class="form-control" 
                                       value="<?php echo htmlspecialchars($hostel['school_proximity'] ?? ''); ?>" 
                                       placeholder="e.g., University of Lagos, 2km away">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Address *</label>
                        <textarea name="address" class="form-control" rows="2" required><?php echo htmlspecialchars($hostel['address']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Amenities -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Amenities</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($common_amenities as $amenity): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" 
                                       value="<?php echo $amenity; ?>" 
                                       id="amenity_<?php echo strtolower(str_replace(' ', '_', $amenity)); ?>"
                                       <?php echo in_array($amenity, $current_amenities) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="amenity_<?php echo strtolower(str_replace(' ', '_', $amenity)); ?>">
                                    <?php echo $amenity; ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Rules -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hostel Rules</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($common_rules as $rule): ?>
                        <div class="col-md-4 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="rules[]" 
                                       value="<?php echo $rule; ?>" 
                                       id="rule_<?php echo strtolower(str_replace(' ', '_', $rule)); ?>"
                                       <?php echo in_array($rule, $current_rules) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="rule_<?php echo strtolower(str_replace(' ', '_', $rule)); ?>">
                                    <?php echo $rule; ?>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Current Images -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Current Images</h5>
                    <span class="badge bg-orange"><?php echo count($images); ?> images</span>
                </div>
                <div class="card-body">
                    <?php if (empty($images)): ?>
                        <p class="text-muted text-center">No images uploaded</p>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($images as $image): ?>
                            <div class="col-6">
                                <div class="position-relative">
                                    <img src="../uploads/hostels/<?php echo $image['image_path']; ?>" 
                                         class="img-fluid rounded" style="height: 80px; object-fit: cover;">
                                    <?php if ($image['is_primary']): ?>
                                        <span class="position-absolute top-0 start-0 badge bg-primary">Primary</span>
                                    <?php endif; ?>
                                    <a href="?action=delete_image&image_id=<?php echo $image['id']; ?>&hostel_id=<?php echo $hostel_id; ?>" 
                                       class="position-absolute top-0 end-0 btn btn-sm btn-danger"
                                       onclick="return confirm('Delete this image?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add New Images -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add New Images</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Upload Images</label>
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <div class="form-text">
                            Upload multiple images. Maximum 5MB per image.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Location Coordinates</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Latitude</label>
                        <input type="text" name="latitude" class="form-control" 
                               value="<?php echo htmlspecialchars($hostel['latitude'] ?? ''); ?>" 
                               placeholder="e.g., 6.5244">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control" 
                               value="<?php echo htmlspecialchars($hostel['longitude'] ?? ''); ?>" 
                               placeholder="e.g., 3.3792">
                    </div>
                </div>
            </div>

            <!-- Status Settings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Status Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_approved" 
                                   id="is_approved" <?php echo $hostel['is_approved'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_approved">
                                Approved
                            </label>
                        </div>
                        <div class="form-text">
                            If approved, the hostel will be visible to tenants.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" 
                                   id="is_active" <?php echo $hostel['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                        <div class="form-text">
                            If inactive, the hostel won't be visible to anyone.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-orange btn-lg w-100">
                        <i class="bi bi-check-circle"></i> Update Hostel
                    </button>
                    <div class="form-text text-center mt-2">
                        Last updated: <?php echo format_date($hostel['updated_at']); ?>
                    </div>
                </div>
            </div>

            <!-- Hostel Stats -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hostel Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $db->query("
                        SELECT 
                            COUNT(*) as total_bookings,
                            AVG(r.rating) as avg_rating,
                            COUNT(r.id) as total_reviews
                        FROM bookings b
                        LEFT JOIN reviews r ON b.id = r.booking_id
                        WHERE b.hostel_id = ?
                    ", [$hostel_id]);
                    $stats = $stmt->fetch();
                    ?>
                    <div class="row text-center">
                        <div class="col-4">
                            <h6 class="mb-0"><?php echo $stats['total_bookings']; ?></h6>
                            <small class="text-muted">Bookings</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0"><?php echo $stats['avg_rating'] ? number_format($stats['avg_rating'], 1) : 'N/A'; ?></h6>
                            <small class="text-muted">Rating</small>
                        </div>
                        <div class="col-4">
                            <h6 class="mb-0"><?php echo $stats['total_reviews']; ?></h6>
                            <small class="text-muted">Reviews</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../includes/footer.php'; ?>