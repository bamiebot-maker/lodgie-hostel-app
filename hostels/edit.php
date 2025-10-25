<?php
$page_title = "Edit Hostel - Lodgie";
require_once '../includes/header-auth.php';
require_role('landlord');

$hostel_id = $_GET['id'] ?? 0;

// Get hostel details
$hostel = $db->fetch(
    "SELECT * FROM hostels WHERE id = ? AND landlord_id = ?",
    [$hostel_id, $_SESSION['user_id']]
);

if (!$hostel) {
    $_SESSION['message'] = 'Hostel not found';
    $_SESSION['message_type'] = 'danger';
    redirect('my.php');
}

// Get hostel images
$images = $db->fetchAll(
    "SELECT * FROM hostel_images WHERE hostel_id = ? ORDER BY is_cover DESC",
    [$hostel_id]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validate($_POST['csrf_token'])) {
        $_SESSION['message'] = 'Invalid CSRF token';
        $_SESSION['message_type'] = 'danger';
    } else {
        $hostelData = [
            'title' => $_POST['title'],
            'description' => $_POST['description'],
            'address' => $_POST['address'],
            'city' => $_POST['city'],
            'state' => $_POST['state'],
            'price_per_month' => $_POST['price_per_month'],
            'room_type' => $_POST['room_type'],
            'total_rooms' => $_POST['total_rooms'],
            'available_rooms' => $_POST['available_rooms']
        ];

        // Validate required fields
        $errors = [];
        if (empty(trim($hostelData['title']))) $errors['title'] = 'Title is required';
        if (empty(trim($hostelData['city']))) $errors['city'] = 'City is required';
        if (empty($hostelData['price_per_month']) || $hostelData['price_per_month'] <= 0) {
            $errors['price_per_month'] = 'Valid price is required';
        }

        if (empty($errors)) {
            // Update hostel
            $updated = $db->update(
                "UPDATE hostels SET title = ?, description = ?, address = ?, city = ?, state = ?, 
                 price_per_month = ?, room_type = ?, total_rooms = ?, available_rooms = ?, is_published = ?, updated_at = NOW()
                 WHERE id = ? AND landlord_id = ?",
                [
                    sanitize_input($hostelData['title']),
                    sanitize_input($hostelData['description']),
                    sanitize_input($hostelData['address']),
                    sanitize_input($hostelData['city']),
                    sanitize_input($hostelData['state']),
                    $hostelData['price_per_month'],
                    $hostelData['room_type'],
                    $hostelData['total_rooms'],
                    $hostelData['available_rooms'],
                    isset($_POST['publish']) ? 1 : 0,
                    $hostel_id,
                    $_SESSION['user_id']
                ]
            );

            if ($updated) {
                // Handle new image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $hasCover = $db->fetch("SELECT id FROM hostel_images WHERE hostel_id = ? AND is_cover = 1", [$hostel_id]);
                    $coverSet = !$hasCover;
                    
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['images']['name'][$key],
                                'type' => $_FILES['images']['type'][$key],
                                'tmp_name' => $tmp_name,
                                'error' => $_FILES['images']['error'][$key],
                                'size' => $_FILES['images']['size'][$key]
                            ];

                            $fileErrors = validate_file_upload($file);
                            if (empty($fileErrors)) {
                                $filename = upload_file($file);
                                if ($filename) {
                                    $is_cover = $coverSet ? 1 : 0;
                                    $coverSet = false;
                                    
                                    $db->insert(
                                        "INSERT INTO hostel_images (hostel_id, filename, is_cover, created_at) 
                                         VALUES (?, ?, ?, NOW())",
                                        [$hostel_id, $filename, $is_cover]
                                    );
                                }
                            }
                        }
                    }
                }

                $_SESSION['message'] = 'Hostel updated successfully!';
                $_SESSION['message_type'] = 'success';
                redirect('my.php');
            } else {
                $errors['general'] = 'Failed to update hostel';
            }
        }
    }
}
?>

<div class="row">
    <div class="col-12">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="my.php">My Hostels</a></li>
                <li class="breadcrumb-item active">Edit Hostel</li>
            </ol>
        </nav>
        
        <h1 class="h2 mb-4">Edit Hostel</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Edit Hostel Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?php echo CSRF::field(); ?>
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="title" class="form-label">Hostel Title *</label>
                            <input type="text" class="form-control <?php echo isset($errors['title']) ? 'is-invalid' : ''; ?>" 
                                   id="title" name="title" value="<?php echo $_POST['title'] ?? $hostel['title']; ?>" required>
                            <?php if (isset($errors['title'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['title']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="room_type" class="form-label">Room Type *</label>
                            <select class="form-select" id="room_type" name="room_type" required>
                                <option value="shared" <?php echo ($_POST['room_type'] ?? $hostel['room_type']) === 'shared' ? 'selected' : ''; ?>>Shared</option>
                                <option value="single" <?php echo ($_POST['room_type'] ?? $hostel['room_type']) === 'single' ? 'selected' : ''; ?>>Single</option>
                                <option value="self-contained" <?php echo ($_POST['room_type'] ?? $hostel['room_type']) === 'self-contained' ? 'selected' : ''; ?>>Self-Contained</option>
                                <option value="others" <?php echo ($_POST['room_type'] ?? $hostel['room_type']) === 'others' ? 'selected' : ''; ?>>Others</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $_POST['description'] ?? $hostel['description']; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="city" class="form-label">City *</label>
                            <input type="text" class="form-control <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" 
                                   id="city" name="city" value="<?php echo $_POST['city'] ?? $hostel['city']; ?>" required>
                            <?php if (isset($errors['city'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['city']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">State *</label>
                            <input type="text" class="form-control" id="state" name="state" 
                                   value="<?php echo $_POST['state'] ?? $hostel['state']; ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Full Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo $_POST['address'] ?? $hostel['address']; ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="price_per_month" class="form-label">Price per Month (₦) *</label>
                            <input type="number" class="form-control <?php echo isset($errors['price_per_month']) ? 'is-invalid' : ''; ?>" 
                                   id="price_per_month" name="price_per_month" 
                                   value="<?php echo $_POST['price_per_month'] ?? $hostel['price_per_month']; ?>" min="0" step="0.01" required>
                            <?php if (isset($errors['price_per_month'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['price_per_month']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="total_rooms" class="form-label">Total Rooms</label>
                            <input type="number" class="form-control" id="total_rooms" name="total_rooms" 
                                   value="<?php echo $_POST['total_rooms'] ?? $hostel['total_rooms']; ?>" min="1">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="available_rooms" class="form-label">Available Rooms</label>
                            <input type="number" class="form-control" id="available_rooms" name="available_rooms" 
                                   value="<?php echo $_POST['available_rooms'] ?? $hostel['available_rooms']; ?>" min="0">
                        </div>
                    </div>

                    <!-- Current Images -->
                    <?php if (!empty($images)): ?>
                        <div class="mb-3">
                            <label class="form-label">Current Images</label>
                            <div class="row g-2">
                                <?php foreach ($images as $image): ?>
                                    <div class="col-auto">
                                        <div class="position-relative">
                                            <img src="../uploads/hostels/<?php echo $image['filename']; ?>" 
                                                 class="rounded" style="width: 100px; height: 80px; object-fit: cover;">
                                            <?php if ($image['is_cover']): ?>
                                                <span class="position-absolute top-0 start-0 badge bg-primary">Cover</span>
                                            <?php endif; ?>
                                            <a href="delete_image.php?id=<?php echo $image['id']; ?>" 
                                               class="position-absolute top-0 end-0 btn btn-sm btn-danger"
                                               onclick="return confirm('Delete this image?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="images" class="form-label">Add More Images</label>
                        <input type="file" class="form-control" id="images" name="images[]" 
                               accept="image/*" multiple>
                        <div class="form-text">Upload additional images. First image will be used as cover if no cover exists.</div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="publish" name="publish" value="1" 
                                   <?php echo isset($_POST['publish']) ? 'checked' : ($hostel['is_published'] ? 'checked' : ''); ?>>
                            <label class="form-check-label" for="publish">
                                Publish this hostel
                            </label>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="my.php" class="btn btn-secondary me-md-2">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Hostel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>