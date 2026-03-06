<?php
// --- Core Includes ---
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/helpers.php';

// --- Page-Specific Logic ---
$page_title = "Welcome to " . SITE_NAME;

// Fetch some featured hostels (e.g., 6 approved hostels)
$stmt = $pdo->prepare("
    SELECT h.*, 
           (SELECT image_path FROM hostel_images hi WHERE hi.hostel_id = h.id ORDER BY hi.is_thumbnail DESC, hi.id ASC LIMIT 1) as thumbnail
    FROM hostels h
    WHERE h.status = 'approved'
    ORDER BY h.created_at DESC
    LIMIT 6
");
$stmt->execute();
$featured_hostels = $stmt->fetchAll();

// --- Header ---
get_header($pdo); 
?>

<section class="hero-section text-center text-white d-flex align-items-center">
    <div class="container fade-up">
        <h1 class="display-4 fw-bolder mb-3">Elevate Your Student Living</h1>
        <p class="lead mb-5 opacity-75">Discover premium hostels and co-living spaces near your campus.</p>
        
        <form action="browse.php" method="GET" class="hero-search-bar shadow-lg">
            <div class="input-group input-group-lg">
                <input type="text" class="form-control bg-transparent text-dark border-0 shadow-none ps-4" name="search" placeholder="Search by location, school, or name..." aria-label="Search">
                <button class="btn btn-orange rounded-pill px-4" type="submit">
                    <i class="bi bi-search me-2"></i> Find Hostels
                </button>
            </div>
        </form>
    </div>
</section>

<section class="py-6 py-md-8 bg-white overflow-hidden" style="padding: 6rem 0;">
    <div class="container">
        <div class="text-center mb-5 fade-up">
            <span class="text-orange fw-bold tracking-wide text-uppercase small">Simple Process</span>
            <h2 class="display-6 fw-bold mt-2 mb-3">How It Works</h2>
            <p class="text-muted lead mx-auto" style="max-width: 600px;">Getting your dream room is easier than ever. Just three simple steps.</p>
        </div>
        
        <div class="row text-center g-4">
            <div class="col-md-4 fade-up" style="transition-delay: 0.1s;">
                <div class="card feature-card h-100 bg-light border-0 rounded-xl">
                    <div class="feature-icon-wrapper">
                        <i class="bi bi-search fs-2 text-orange"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-3">1. Explore Options</h4>
                    <p class="text-muted mb-0">Browse our curated list of high-quality hostels with verified amenities and genuine reviews.</p>
                </div>
            </div>
            <div class="col-md-4 fade-up" style="transition-delay: 0.2s;">
                <div class="card feature-card h-100 bg-light border-0 rounded-xl">
                    <div class="feature-icon-wrapper">
                        <i class="bi bi-calendar2-check fs-2 text-orange"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-3">2. Book Securely</h4>
                    <p class="text-muted mb-0">Select your preferred dates and pay safely through our integrated payment gateway.</p>
                </div>
            </div>
            <div class="col-md-4 fade-up" style="transition-delay: 0.3s;">
                <div class="card feature-card h-100 bg-light border-0 rounded-xl">
                    <div class="feature-icon-wrapper">
                        <i class="bi bi-key-fill fs-2 text-orange"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-3">3. Move In</h4>
                    <p class="text-muted mb-0">Get instant confirmation. Connect with your landlord and move into your new home smoothly.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-6 py-md-8 bg-light" style="padding: 6rem 0;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-5 fade-up">
            <div>
                <span class="text-orange fw-bold tracking-wide text-uppercase small">Top Rated</span>
                <h2 class="display-6 fw-bold mt-2 mb-0">Featured Hostels</h2>
            </div>
            <a href="browse.php" class="btn btn-outline-orange d-none d-md-inline-block">View All Hostels <i class="bi bi-arrow-right ms-2"></i></a>
        </div>
        
        <div class="row g-4">
            <?php if (empty($featured_hostels)): ?>
                <div class="col-12 text-center py-5 fade-up">
                    <div class="p-5 bg-white rounded-xl shadow-sm border border-light">
                        <i class="bi bi-house-x text-muted mb-3" style="font-size: 3rem;"></i>
                        <h4 class="text-slate fw-bold">No hostels available yet</h4>
                        <p class="text-muted mb-0">Check back later or register as a landlord to list yours.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($featured_hostels as $index => $hostel): ?>
                    <div class="col-md-6 col-lg-4 fade-up" style="transition-delay: <?php echo $index * 0.1; ?>s;">
                        <div class="card hostel-card border-0 rounded-xl">
                            <span class="badge-featured"><i class="bi bi-star-fill me-1"></i> Featured</span>
                            <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>" class="img-wrapper d-block">
                                <img src="<?php echo BASE_URL . '/uploads/hostels/' . ($hostel['thumbnail'] ? sanitize($hostel['thumbnail']) : 'hostel_placeholder.jpg'); ?>" alt="<?php echo sanitize($hostel['name']); ?>">
                            </a>
                            <div class="card-body d-flex flex-column bg-white">
                                <h5 class="card-title text-truncate mb-1"><?php echo sanitize($hostel['name']); ?></h5>
                                <p class="card-text text-muted mb-3 small"><i class="bi bi-geo-alt-fill text-orange me-1"></i> <?php echo sanitize($hostel['city']); ?></p>
                                
                                <div class="mt-auto pt-3 border-top border-light d-flex justify-content-between align-items-center">
                                    <div class="price">
                                        <?php echo format_price($hostel['price_per_month']); ?>
                                        <span class="duration">/ month</span>
                                    </div>
                                    <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-dark rounded-pill px-3">Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-5 d-md-none fade-up">
            <a href="browse.php" class="btn btn-outline-orange w-100">View All Hostels</a>
        </div>
    </div>
</section>

<section class="py-6 py-md-8 bg-white" style="padding: 6rem 0;">
    <div class="container">
        <div class="text-center mb-5 fade-up">
            <h2 class="display-6 fw-bold">What Our Users Say</h2>
            <p class="text-muted lead">Don't just take our word for it.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4 fade-up" style="transition-delay: 0.1s;">
                <div class="card p-4 text-center h-100 border-0 shadow-sm rounded-xl">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto mb-3 border border-3 border-white shadow-sm" width="70" height="70" alt="Tenant 1">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="fst-italic text-muted mb-4 px-2">"Lodgie made it so easy to find a great place near my university. The booking process was incredibly fast and secure!"</p>
                    <h6 class="fw-bold text-slate mb-0">Ifeoluwa. A</h6>
                    <small class="text-muted">Student</small>
                </div>
            </div>
            <div class="col-md-4 fade-up" style="transition-delay: 0.2s;">
                <div class="card p-4 text-center h-100 border-0 shadow-sm rounded-xl">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto mb-3 border border-3 border-white shadow-sm" width="70" height="70" alt="Landlord">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="fst-italic text-muted mb-4 px-2">"As a landlord, this platform is a game-changer. I get quality tenants and manage my bookings flawlessly in one place."</p>
                    <h6 class="fw-bold text-slate mb-0">Mallam Marzuq</h6>
                    <small class="text-muted">Verified Landlord</small>
                </div>
            </div>
            <div class="col-md-4 fade-up" style="transition-delay: 0.3s;">
                <div class="card p-4 text-center h-100 border-0 shadow-sm rounded-xl">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto mb-3 border border-3 border-white shadow-sm" width="70" height="70" alt="Tenant 3">
                    <div class="text-warning mb-3">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                    </div>
                    <p class="fst-italic text-muted mb-4 px-2">"Found a decent premium room at a good price. The landlord was responsive and the Lodgie support was helpful."</p>
                    <h6 class="fw-bold text-slate mb-0">Chukwudi O.</h6>
                    <small class="text-muted">Student</small>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-6 py-md-8 position-relative overflow-hidden" style="background: linear-gradient(135deg, var(--bs-slate-800) 0%, var(--bs-dark) 100%); padding: 6rem 0;">
    <div class="container position-relative z-1 text-center text-white fade-up">
        <h2 class="display-5 fw-bold mb-3">Got a Hostel to List?</h2>
        <p class="lead mb-5 opacity-75 mx-auto" style="max-width: 600px;">Join our premium network of landlords and reach thousands of verified students looking for accommodation today.</p>
        <div class="d-flex justify-content-center gap-3">
            <a href="<?php echo BASE_URL; ?>/register.php?role=landlord" class="btn btn-orange btn-lg rounded-pill px-5 shadow-lg">List Your Hostel</a>
        </div>
    </div>
</section>

<?php
// --- Footer ---
get_footer(); 
?>