<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

Auth::requireRole(['admin']);
$page_title = "Add Hostel";
require_once '../includes/headers/header_admin.php';

$db = new Database();
$error = '';
$success = '';

// Get landlords for dropdown
$stmt = $db->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'landlord' AND is_active = TRUE ORDER BY first_name, last_name");
$landlords = $stmt->fetchAll();

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

        // Validate landlord
        $stmt = $db->query("SELECT id FROM users WHERE id = ? AND role = 'landlord'", [$_POST['landlord_id']]);
        if ($stmt->rowCount() === 0) {
            throw new Exception("Invalid landlord selected");
        }

        // Prepare amenities and rules
        $amenities = isset($_POST['amenities']) ? json_encode($_POST['amenities']) : '[]';
        $rules = isset($_POST['rules']) ? json_encode($_POST['rules']) : '[]';

        // Insert hostel
        $sql = "INSERT INTO hostels (landlord_id, name, description, address, city, state, 
                                    school_proximity, price_per_month, total_rooms, available_rooms,
                                    amenities, rules, latitude, longitude, is_approved, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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
            $_POST['total_rooms'], // Available rooms same as total initially
            $amenities,
            $rules,
            $_POST['latitude'] ?? null,
            $_POST['longitude'] ?? null,
            isset($_POST['is_approved']) ? 1 : 0,
            isset($_POST['is_active']) ? 1 : 0
        ]);

        $hostel_id = $db->lastInsertId();

        // Handle image uploads
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
                            // Mark first image as primary
                            $is_primary = ($key === 0) ? 1 : 0;
                            
                            $db->query("
                                INSERT INTO hostel_images (hostel_id, image_path, is_primary)
                                VALUES (?, ?, ?)
                            ", [$hostel_id, $filename, $is_primary]);
                        }
                    }
                }
            }
        }

        // Send notification to landlord if approved
        if (isset($_POST['is_approved']) && $_POST['is_approved']) {
            require_once '../includes/notifications.php';
            $notification = new Notification();
            $notification->notifyHostelApproval($hostel_id, true);
        }

        $success = "Hostel added successfully!" . (isset($_POST['is_approved']) ? " The hostel has been approved and is now visible to tenants." : " The hostel is pending approval.");
        
        // Reset form
        $_POST = [];

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
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center py-3 mb-4 border-bottom">
    <h1 class="h2">Add New Hostel</h1>
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
                                                <?php echo ($_POST['landlord_id'] ?? '') == $landlord['id'] ? 'selected' : ''; ?>>
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
                                       value="<?php echo $_POST['name'] ?? ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price Per Month (₦) *</label>
                                <input type="number" name="price_per_month" class="form-control" 
                                       value="<?php echo $_POST['price_per_month'] ?? ''; ?>" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total Rooms *</label>
                                <input type="number" name="total_rooms" class="form-control" 
                                       value="<?php echo $_POST['total_rooms'] ?? ''; ?>" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-control" rows="4" required><?php echo $_POST['description'] ?? ''; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?php echo $_POST['city'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">State *</label>
                                <input type="text" name="state" class="form-control" 
                                       value="<?php echo $_POST['state'] ?? ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nearby Schools/Universities</label>
                                <input type="text" name="school_proximity" class="form-control" 
                                       value="<?php echo $_POST['school_proximity'] ?? ''; ?>" 
                                       placeholder="e.g., University of Lagos, 2km away">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Address *</label>
                        <textarea name="address" class="form-control" rows="2" required><?php echo $_POST['address'] ?? ''; ?></textarea>
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
                                       <?php echo (isset($_POST['amenities']) && in_array($amenity, $_POST['amenities'])) ? 'checked' : ''; ?>>
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
                                       <?php echo (isset($_POST['rules']) && in_array($rule, $_POST['rules'])) ? 'checked' : ''; ?>>
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
            <!-- Images -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Hostel Images</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Upload Images</label>
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        <div class="form-text">
                            Upload multiple images. First image will be used as primary. Maximum 5MB per image.
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
                               value="<?php echo $_POST['latitude'] ?? ''; ?>" 
                               placeholder="e.g., 6.5244">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Longitude</label>
                        <input type="text" name="longitude" class="form-control" 
                               value="<?php echo $_POST['longitude'] ?? ''; ?>" 
                               placeholder="e.g., 3.3792">
                    </div>
                    <div class="form-text">
                        Optional: Provide coordinates for map display. You can get these from Google Maps.
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
                                   id="is_approved" <?php echo isset($_POST['is_approved']) ? 'checked' : 'checked'; ?>>
                            <label class="form-check-label" for="is_approved">
                                Approved
                            </label>
                        </div>
                        <div class="form-text">
                            If approved, the hostel will be immediately visible to tenants.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" 
                                   id="is_active" <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
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
                        <i class="bi bi-plus-circle"></i> Add Hostel
                    </button>
                    <div class="form-text text-center mt-2">
                        The hostel will be created with the specified status settings.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php include '../includes/footer.php'; ?>