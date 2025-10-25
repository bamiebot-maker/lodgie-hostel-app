<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/notifications.php'; // For admin notification

// --- Page-Specific Logic ---
protect_page('landlord');
$page_title = "Add New Hostel";
$landlord_id = $_SESSION['user_id'];

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hostel'])) {
    
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request. Please try again.';
        redirect('landlord/add_hostel.php');
    }

    // 2. Sanitize Text Inputs
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $address = sanitize($_POST['address']);
    $city = sanitize($_POST['city']);
    $price = (float) sanitize($_POST['price']);
    // Get amenities as a comma-separated string
    $amenities = isset($_POST['amenities']) ? implode(',', array_map('sanitize', $_POST['amenities'])) : '';

    $errors = [];

    // 3. Validate Text Inputs
    if (empty($name)) $errors[] = "Hostel name is required.";
    if (empty($description)) $errors[] = "Description is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (empty($city)) $errors[] = "City is required.";
    if ($price <= 0) $errors[] = "Price must be a positive number.";

    // 4. Validate File Uploads
    $uploaded_images = [];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024; // 2MB
    $upload_dir = __DIR__ . '/../uploads/hostels/';
    
    // *** NEW: Check if upload directory exists and is writable ***
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $errors[] = "Critical Error: Upload directory does not exist and could not be created.";
        }
    }
    if (!is_writable($upload_dir)) {
        $errors[] = "Critical Error: Upload directory '$upload_dir' is not writable. Please check server permissions (chmod 755).";
    }

    if (isset($_FILES['hostel_images']) && !empty(array_filter($_FILES['hostel_images']['name'])) && empty($errors)) {
        $file_count = count($_FILES['hostel_images']['name']);
        if ($file_count > 5) {
            $errors[] = "You can upload a maximum of 5 images.";
        }

        for ($i = 0; $i < $file_count; $i++) {
            $file_name = $_FILES['hostel_images']['name'][$i];
            $file_tmp = $_FILES['hostel_images']['tmp_name'][$i];
            $file_type = $_FILES['hostel_images']['type'][$i];
            $file_size = $_FILES['hostel_images']['size'][$i];
            $file_error = $_FILES['hostel_images']['error'][$i];

            if ($file_error === UPLOAD_ERR_OK) {
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Invalid file type: $file_name. Only JPG, JPEG, PNG allowed.";
                    continue;
                }
                if ($file_size > $max_size) {
                    $errors[] = "File is too large: $file_name. Max 2MB allowed.";
                    continue;
                }
                
                // Create a unique filename
                $new_filename = uniqid('hostel_', true) . '.' . strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_images[] = $new_filename;
                } else {
                    $errors[] = "Failed to move uploaded file: $file_name.";
                }
            }
        }
    } elseif (empty($errors)) {
        $errors[] = "At least one image is required.";
    }

    // 5. If no errors, Insert into Database
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert into `hostels` table
            $sql_hostel = "INSERT INTO hostels (landlord_id, name, description, address, city, price_per_month, amenities, status)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt_hostel = $pdo->prepare($sql_hostel);
            $stmt_hostel->execute([$landlord_id, $name, $description, $address, $city, $price, $amenities]);
            
            $hostel_id = $pdo->lastInsertId();

            // Insert into `hostel_images` table
            $sql_image = "INSERT INTO hostel_images (hostel_id, image_path, is_thumbnail) VALUES (?, ?, ?)";
            $stmt_image = $pdo->prepare($sql_image);
            
            foreach ($uploaded_images as $index => $image_path) {
                $is_thumbnail = ($index === 0); // First image is the thumbnail
                $stmt_image->execute([$hostel_id, $image_path, $is_thumbnail]);
            }

            // Notify Admin
            $admin_id = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
            if ($admin_id) {
                create_notification(
                    $pdo, 
                    $admin_id, 
                    'New Hostel Pending Approval', 
                    "Landlord " . $_SESSION['user_name'] . " submitted '$name'.", 
                    'warning', 
                    'admin/hostels.php?status=pending'
                );
            }
            
            $pdo->commit();
            
            $_SESSION['success_flash'] = 'Hostel submitted successfully! It is now pending admin approval.';
            redirect('landlord/my_hostels.php');

        } catch (PDOException $e) {
            $pdo->rollBack();
            // Clean up uploaded files on DB error
            foreach ($uploaded_images as $image_path) {
                if(file_exists($upload_dir . $image_path)) {
                    @unlink($upload_dir . $image_path);
                }
            }
            $_SESSION['error_flash'] = 'Database error. Could not add hostel. ' . $e->getMessage();
            error_log('Add Hostel Error: ' . $e->getMessage());
            redirect('landlord/add_hostel.php');
        }
    } else {
        // Display errors
        $_SESSION['error_flash'] = implode('<br>', $errors);
        redirect('landlord/add_hostel.php');
    }
}

$csrf_token = generate_csrf_token();

// --- Header ---
// ***** THIS IS THE FIX *****
get_header($pdo); 
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Add a New Hostel</h1>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            
            <?php display_flash_messages(); ?>

            <form action="add_hostel.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <h5 class="mb-3">Basic Information</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="name" class="form-label">Hostel Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                        <div class="invalid-feedback">Please enter the hostel name.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="price" class="form-label">Price (per month)</label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo SITE_CURRENCY_SYMBOL; ?></span>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                            <span class="input-group-text">.00</span>
                            <div class="invalid-feedback">Please enter a valid price.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        <div class="invalid-feedback">Please enter a description.</div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Location</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="address" class="form-label">Full Address</label>
                        <input type="text" class="form-control" id="address" name="address" required>
                        <div class="invalid-feedback">Please enter the address.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="city" class="form-label">City / Town</label>
                        <input type="text" class="form-control" id="city" name="city" required>
                        <div class="invalid-feedback">Please enter the city.</div>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Amenities</h5>
                <p class="text-muted small">Select all that apply.</p>
                <div class="row g-2">
                    <?php 
                    $common_amenities = ['Wi-Fi', 'Water', 'Electricity', 'Security', 'Parking', 'Kitchen', 'Private Bathroom', 'AC', 'Fan', 'Cleaning Service'];
                    foreach ($common_amenities as $amenity): 
                        $id = strtolower(str_replace(' ', '-', $amenity));
                    ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="<?php echo $amenity; ?>" id="amenity-<?php echo $id; ?>">
                                <label class="form-check-label" for="amenity-<?php echo $id; ?>">
                                    <?php echo $amenity; ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <hr class="my-4">

                <h5 class="mb-3">Hostel Images</h5>
                <div class="mb-3">
                    <label for="hostelImages" class="form-label">Upload Images (Max 5, first image is thumbnail)</label>
                    <input class="form-control" type="file" id="hostelImages" name="hostel_images[]" multiple accept="image/png, image/jpeg, image/jpg" required>
                    <div class="form-text">Allowed: JPG, JPEG, PNG. Max size: 2MB per image.</div>
                    <div class="invalid-feedback">Please upload at least one image.</div>
                </div>
                
                <div id="imagePreview" class="mt-3 p-3 bg-light rounded border">
                    <p class="text-muted">No images selected for preview.</p>
                </div>
                
                <hr class="my-4">

                <div class="text-end">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="add_hostel" class="btn btn-orange">Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// --- Footer ---
get_footer(); 
?>