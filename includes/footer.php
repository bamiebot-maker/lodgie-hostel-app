<!-- Add this file to your includes directory -->
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Image gallery functionality
        function changeMainImage(src) {
            document.getElementById('mainImage').src = src;
        }

        // --- UI Redesign Interactions ---
        
        // 1. Transparent Navbar Scroll Effect
        const mainNav = document.getElementById('mainNav');
        if (mainNav && mainNav.classList.contains('navbar-transparent')) {
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    mainNav.classList.remove('navbar-transparent', 'navbar-dark');
                    mainNav.classList.add('navbar-scrolled', 'navbar-light');
                } else {
                    mainNav.classList.add('navbar-transparent', 'navbar-dark');
                    mainNav.classList.remove('navbar-scrolled', 'navbar-light');
                }
            });
        }

        // 2. Fade Up Scroll Animations
        const fadeUpElements = document.querySelectorAll('.fade-up');
        if (fadeUpElements.length > 0) {
            const appearOptions = {
                threshold: 0.15,
                rootMargin: "0px 0px -50px 0px"
            };

            const appearOnScroll = new IntersectionObserver(function(entries, observer) {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) {
                        return;
                    } else {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, appearOptions);

            fadeUpElements.forEach(el => {
                appearOnScroll.observe(el);
            });
        }
    </script>
</body>
</html>