/**
 * Lodgie - Custom JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // --- Admin Sidebar Toggle ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('sidebar-toggled');
        });
    }

    // --- Notification Read Click ---
    const notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(item => {
        item.addEventListener('click', function (e) {
            const notificationId = this.dataset.id;
            if (!notificationId || this.classList.contains('fw-bold') === false) {
                // If it's already read or has no ID, follow the link normally
                return;
            }

            // Prevent link navigation to mark as read first
            e.preventDefault();
            const href = this.href;

            // Get base URL from the global variable defined in footer
            // This is safer than hard-coding
            if (typeof BASE_URL === 'undefined') {
                console.error('BASE_URL is not defined in the HTML.');
                window.location.href = href; // Fail gracefully
                return;
            }

            fetch(BASE_URL + '/ajax/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ id: notificationId })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Mark as read visually
                    this.classList.remove('fw-bold');
                    
                    // Update badge count
                    const badge = document.querySelector('.badge.rounded-pill.bg-danger');
                    if (badge) {
                        let count = parseInt(badge.textContent);
                        if (count > 1) {
                            badge.textContent = count - 1;
                        } else {
                            badge.remove();
                        }
                    }
                }
                // Always navigate after fetch, regardless of success
                window.location.href = href;
            })
            .catch(error => {
                console.error('Fetch error:', error);
                // Still navigate on error
                window.location.href = href;
            });
        });
    });

    // --- Form Validation (Bootstrap) ---
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // --- File Upload Preview (for Add Hostel) ---
    const imageUpload = document.getElementById('hostelImages');
    const imagePreview = document.getElementById('imagePreview');
    if (imageUpload && imagePreview) {
        imageUpload.addEventListener('change', function(e) {
            // Clear previous previews
            imagePreview.innerHTML = '';
            
            if (this.files.length === 0) {
                imagePreview.innerHTML = '<p class="text-muted">No images selected for preview.</p>';
                return;
            }

            // Check file count
            if (this.files.length > 5) { // Set a limit
                imagePreview.innerHTML = '<p class="text-danger">You can only upload a maximum of 5 images.</p>';
                this.value = ''; // Clear the selection
                return;
            }

            const row = document.createElement('div');
            row.className = 'row g-2';
            imagePreview.appendChild(row);

            Array.from(this.files).forEach((file, index) => {
                // Check file type
                if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) {
                    const errorCol = document.createElement('div');
                    errorCol.className = 'col-6 col-md-3';
                    errorCol.innerHTML = `<div class="alert alert-danger p-2 small">${file.name} is not a valid image.</div>`;
                    row.appendChild(errorCol);
                    return; // Skip this file
                }

                // Check file size (e.g., 2MB)
                if (file.size > 2 * 1024 * 1024) {
                    const errorCol = document.createElement('div');
                    errorCol.className = 'col-6 col-md-3';
                    errorCol.innerHTML = `<div class="alert alert-danger p-2 small">${file.name} is too large (Max 2MB).</div>`;
                    row.appendChild(errorCol);
                    return; // Skip this file
                }

                // Create preview
                const reader = new FileReader();
                reader.onload = function(event) {
                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-3';
                    col.innerHTML = `
                        <div class="position-relative">
                            <img src="${event.target.result}" class="img-fluid rounded" style="height: 100px; width: 100%; object-fit: cover;">
                            <small class="d-block text-truncate small" title="${file.name}">${file.name}</small>
                            ${index === 0 ? '<span class="badge bg-orange position-absolute top-0 start-0">Thumbnail</span>' : ''}
                        </div>
                    `;
                    row.appendChild(col);
                };
                reader.readAsDataURL(file);
            });
        });
    }
});