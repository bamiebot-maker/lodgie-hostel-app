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

<section class="hero-section text-center text-white">
    <div class="container">
        <h1>Find Your Perfect Hostel</h1>
        <p class="lead">Book student accommodation easily and securely.</p>
        
        <form action="browse.php" method="GET" class="hero-search-bar">
            <div class="input-group input-group-lg">
                <input type="text" class="form-control" name="search" placeholder="Search by location, school, or hostel name..." aria-label="Search">
                <button class="btn btn-dark" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">How It Works</h2>
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <i class="bi bi-search text-orange" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">1. Find</h4>
                    <p class="mb-0">Search our listings to find the perfect hostel near your campus with the amenities you need.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <i class="bi bi-calendar2-check text-orange" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">2. Book</h4>
                    <p class="mb-0">Select your dates, review the details, and book your room securely with our online payment.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <i class="bi bi-key-fill text-orange" style="font-size: 3rem;"></i>
                    <h4 class="mt-3">3. Move In</h4>
                    <p class="mb-0">Receive your booking confirmation, contact the landlord, and get ready to move into your new home.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-white">
    <div class="container">
        <h2 class="text-center mb-4">Featured Hostels</h2>
        <div class="row g-4">
            
            <?php if (empty($featured_hostels)): ?>
                <p class="text-center text-muted">No featured hostels available at the moment.</p>
            <?php else: ?>
                <?php foreach ($featured_hostels as $hostel): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card hostel-card h-100">
                            <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>">
                                <img src="<?php echo BASE_URL . '/uploads/hostels/' . ($hostel['thumbnail'] ? sanitize($hostel['thumbnail']) : 'hostel_placeholder.jpg'); ?>" class="card-img-top" alt="<?php echo sanitize($hostel['name']); ?>">
                            </a>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo sanitize($hostel['name']); ?></h5>
                                <p class="card-text text-muted mb-2"><i class="bi bi-geo-alt-fill me-1"></i> <?php echo sanitize($hostel['city']); ?></p>
                                <p class="card-text small text-muted"><?php echo substr(sanitize($hostel['description']), 0, 100); ?>...</p>
                                
                                <div class="mt-auto d-flex justify-content-between align-items-center">
                                    <div class="price">
                                        <?php echo format_price($hostel['price_per_month']); ?>
                                        <span class="duration">/ month</span>
                                    </div>
                                    <a href="hostel_details.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-outline-orange">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <div class="text-center mt-4">
            <a href="browse.php" class="btn btn-orange btn-lg">Browse All Hostels</a>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-4">What Our Tenants Say</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto" width="80" height="80" alt="Tenant 1">
                    <div class="ratings mt-3 text-warning">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-half"></i>
                    </div>
                    <blockquote class="blockquote mt-3 mb-0">
                        <p class="small">"Lodgie made it so easy to find a great place near my university. The booking process was fast and secure!"</p>
                        <footer class="blockquote-footer mt-2">Ifeoluwa</footer>
                    </blockquote>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto" width="80" height="80" alt="Tenant 2">
                    <div class="ratings mt-3 text-warning">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <blockquote class="blockquote mt-3 mb-0">
                        <p class="small">"As a landlord, this platform is a game-changer. I get quality tenants and manage my bookings all in one place."</p>
                        <footer class="blockquote-footer mt-2">Mallam Marzuq (Landlord)</footer>
                    </blockquote>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <img src="<?php echo BASE_URL; ?>/assets/images/default_avatar.png" class="rounded-circle mx-auto" width="80" height="80" alt="Tenant 3">
                    <div class="ratings mt-3 text-warning">
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star-fill"></i>
                        <i class="bi bi-star"></i>
                    </div>
                    <blockquote class="blockquote mt-3 mb-0">
                        <p class="small">"Found a decent room at a good price. The landlord was responsive. Happy with the service."</p>
                        <footer class="blockquote-footer mt-2">Chukwudi</footer>
                    </blockquote>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 bg-orange text-white text-center">
    <div class="container">
        <h2>Got a Hostel to List?</h2>
        <p class="lead mb-4">Join our network of landlords and reach thousands of students today.</p>
        <a href="<?php echo BASE_URL; ?>/register.php?role=landlord" class="btn btn-light btn-lg text-orange fw-bold">List Your Hostel Now</a>
    </div>
</section>

<?php
// --- Footer ---
get_footer(); 
?>