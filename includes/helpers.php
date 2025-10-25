<?php
/**
 * Global Helper Functions (Corrected)
 *
 * Contains utility functions used throughout the application.
 */

require_once __DIR__ . '/config.php';

/**
 * Redirects the user to a specified URL.
 */
function redirect($url) {
    if (headers_sent()) {
        echo "<script>window.location.href='" . BASE_URL . '/' . ltrim($url, '/') . "';</script>";
        exit();
    } else {
        header('Location: ' . BASE_URL . '/' . ltrim($url, '/'));
        exit();
    }
}

/**
 * Sanitizes user input to prevent XSS attacks.
 */
function sanitize($input) {
    $input = (string) $input;
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Checks if a user is logged in.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Checks if the logged-in user has a specific role.
 */
function check_role($role) {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Protects a page, ensuring only users with a specific role can access it.
 */
function protect_page($role) {
    if (!is_logged_in()) {
        $_SESSION['error_flash'] = 'You must be logged in to view that page.';
        redirect('login.php');
    }
    if (!check_role($role)) {
        $_SESSION['error_flash'] = 'You do not have permission to access that page.';
        $dashboard_map = [
            'admin' => 'admin/dashboard.php',
            'landlord' => 'landlord/dashboard.php',
            'tenant' => 'tenant/dashboard.php'
        ];
        $redirect_url = $dashboard_map[$_SESSION['role']] ?? 'index.php';
        redirect($redirect_url);
    }
}

/**
 * Generates a CSRF token.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = 'fallback_token_' . time();
        }
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a CSRF token.
 */
function verify_csrf_token($token) {
    if (empty($token)) {
        return false;
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Displays flash messages.
 */
function display_flash_messages() {
    $output = '';
    if (isset($_SESSION['success_flash'])) {
        $output .= '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . sanitize($_SESSION['success_flash']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_flash']);
    }

    if (isset($_SESSION['error_flash'])) {
        $output .= '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . sanitize($_SESSION['error_flash']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_flash']);
    }
    echo $output;
}

/**
 * Dynamically includes the correct header.
 */
function get_header($pdo = null) {
    $header_path = __DIR__ . '/headers/';
    
    if (check_role('admin')) {
        if ($pdo === null) {
            trigger_error("Admin header requires a PDO connection.", E_USER_WARNING);
        }
        // Add a define for the admin footer to check against
        if (!defined('IS_ADMIN_PAGE')) {
            define('IS_ADMIN_PAGE', true);
        }
        include_once $header_path . 'header_admin.php';
    } elseif (check_role('landlord')) {
        if ($pdo === null) {
            trigger_error("Landlord header requires a PDO connection.", E_USER_WARNING);
        }
        include_once $header_path . 'header_landlord.php';
    } elseif (check_role('tenant')) {
        if ($pdo === null) {
            trigger_error("Tenant header requires a PDO connection.", E_USER_WARNING);
        }
        include_once $header_path . 'header_tenant.php';
    } else {
        // Public (not logged in)
        include_once $header_path . 'header_public.php';
    }
}

/**
 * Includes the correct footer.
 *
 * This function is now role-aware.
 * - Public pages get the full dark footer.
 * - Tenant/Landlord dashboards get NO footer (just scripts).
 * - Admin pages are skipped (they have their own footer).
 */
function get_footer() {
    
    // Admin pages have their own custom footer, so we skip everything.
    if (defined('IS_ADMIN_PAGE') && IS_ADMIN_PAGE) {
        return; 
    }
    
    // Check if we are on a dashboard page (tenant or landlord)
    $is_dashboard = check_role('tenant') || check_role('landlord');
    
    if ($is_dashboard) {
        // Close the container-fluid div opened in dashboard headers
        echo '</div> ';
    }

    // Close the main tag opened in all public/tenant/landlord headers
    echo '</main> ';

    
    // **THIS IS THE FIX:**
    // Only show the dark footer if the user is NOT logged in.
    if (!is_logged_in()) {
        echo '
        <footer class="bg-dark text-white p-4 text-center mt-auto">
            <div class="container">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All Rights Reserved.</p>
                <p>
                    <a href="#" class="text-white text-decoration-none mx-2">Privacy Policy</a> |
                    <a href="#" class="text-white text-decoration-none mx-2">Terms of Service</a>
                </p>
            </div>
        </footer>';
    }

    // Echo the common scripts for ALL (public, tenant, landlord)
    echo '
        <script>
            const BASE_URL = "' . BASE_URL . '";
        </script>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        
        <script src="' . BASE_URL . '/assets/js/main.js"></script>
        
    </body>
    </html>';
}

/**
 * Formats a price with the site's currency.
 */
function format_price($price) {
    // Ensure $price is numeric
    $price = (float) $price;
    return SITE_CURRENCY_SYMBOL . number_format($price, 2);
}

?>