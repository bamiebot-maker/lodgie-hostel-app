<?php
// --- Core Includes ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- Page-Specific Logic ---
protect_page('admin');
$page_title = "Site Settings";

/**
 * NOTE: For a real application, settings should be stored in a
 * database table (e.g., `settings` table with key/value pairs).
 *
 * For security, sensitive keys (DB_PASS, PAYSTACK_SECRET_KEY)
 * should *NEVER* be editable from a web UI. They should remain
 * in the `config.php` file, which is protected on the server.
 *
 * This page will serve as a placeholder for non-sensitive settings.
 */

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $_SESSION['error_flash'] = 'Invalid request.';
    } else {
        // Example: Get settings from form
        $site_name = sanitize($_POST['site_name']);
        $site_currency = sanitize($_POST['site_currency']);
        
        // --- Logic to update settings in the database would go here ---
        // e.g., $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'site_name'")->execute([$site_name]);
        // e.g., $pdo->prepare("UPDATE settings SET value = ? WHERE name = 'site_currency'")->execute([$site_currency]);
        
        // For this demo, we'll just show a success message
        $_SESSION['success_flash'] = 'Settings saved successfully! (Demo)';
        
        // Note: Updating constants defined in config.php is not possible at runtime.
        // This confirms the need for a database settings table.
    }
    redirect('admin/settings.php');
}

// --- Fetch current settings (from config constants for this demo) ---
$current_settings = [
    'site_name' => SITE_NAME,
    'site_currency' => SITE_CURRENCY,
    'paystack_public_key' => PAYSTACK_PUBLIC_KEY
];

$csrf_token = generate_csrf_token();

// --- Header ---
get_header($pdo); 
?>

<h1 class="h3 mb-4">Site Settings</h1>

<?php display_flash_messages(); ?>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h5 class="mb-0">General Settings</h5>
    </div>
    <div class="card-body p-4">
        
        <div class="alert alert-info">
            <strong>Note:</strong> In a real application, these settings would be saved to a database. For this project, they are read from the `config.php` file. Sensitive keys (like Paystack Secret) are intentionally not shown here for security.
        </div>
        
        <form action="settings.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-3 row">
                <label for="site_name" class="col-sm-3 col-form-label">Site Name</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo sanitize($current_settings['site_name']); ?>">
                </div>
            </div>

            <div class="mb-3 row">
                <label for="site_currency" class="col-sm-3 col-form-label">Currency Code</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control" id="site_currency" name="site_currency" value="<?php echo sanitize($current_settings['site_currency']); ?>" placeholder="e.g., NGN, GHS, USD">
                </div>
            </div>

            <hr>
            
            <h5 class="mb-3">API Keys</h5>
            
            <div class="mb-3 row">
                <label for="paystack_public_key" class="col-sm-3 col-form-label">Paystack Public Key</label>
                <div class="col-sm-9">
                    <input type="text" readonly class="form-control-plaintext" id="paystack_public_key" value="<?php echo sanitize($current_settings['paystack_public_key']); ?>">
                    <div class="form-text">This value is set in `includes/config.php` and cannot be changed here.</div>
                </div>
            </div>
            
            <div class="mb-3 row">
                <label class="col-sm-3 col-form-label">Paystack Secret Key</label>
                <div class="col-sm-9">
                    <input type="text" readonly class="form-control-plaintext" value="************************ (Set in config.php)">
                </div>
            </div>

            <div class="text-end mt-4">
                <button type="submit" name="save_settings" class="btn btn-orange">Save Settings</button>
            </div>
        </form>
    </div>
</div>


<?php
// --- Footer ---
?>
        </main> </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
</body>
</html>