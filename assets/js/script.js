// ============================================
// AFFILIATE REVIEW SITE - MAIN JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ========== GLOBAL VARIABLES ==========
    const API_BASE = 'admin/api/';
    
    // ========== UTILITY FUNCTIONS ==========
    
    /**
     * Hiển thị thông báo
     */
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Tự động xóa sau 5s
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    /**
     * Xác nhận hành động
     */
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
    
    /**
     * Loading spinner
     */
    function showLoading(element) {
        element.innerHTML = `
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <small class="d-block mt-2">Đang tải...</small>
            </div>
        `;
    }
    
    /**
     * Format tiền Việt Nam
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(amount);
    }
    
    // ========== ADMIN FUNCTIONS ==========
    
    /**
     * Load thống kê dashboard
     */
    async function loadDashboardStats() {
        try {
            const response = await fetch(`${API_BASE}stats.php`);
            const data = await response.json();
            
            document.getElementById('totalProducts').textContent = data.products || 0;
            document.getElementById('totalReviews').textContent = data.reviews || 0;
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    /**
     * CRUD Products
     */
    window.editProduct = async function(id) {
        try {
            const response = await fetch(`${API_BASE}get_product.php?id=${id}`);
            const product = await response.json();
            
            // Điền form
            document.getElementById('product_id').value = product.id;
            document.getElementById('name').value = product.name;
            document.getElementById('price').value = product.price;
            document.getElementById('affiliate_link').value = product.affiliate_link;
            document.getElementById('description').value = product.description;
            
            // Scroll đến form
            document.querySelector('.card-body form').scrollIntoView({ behavior: 'smooth' });
            
            showNotification('Đã load thông tin sản phẩm!', 'info');
        } catch (error) {
            showNotification('Lỗi khi load sản phẩm!', 'danger');
        }
    };
    
    window.resetForm = function() {
        document.querySelector('form').reset();
        document.getElementById('product_id').value = '';
        showNotification('Đã reset form!', 'info');
    };
    
    /**
     * Xóa sản phẩm với AJAX
     */
    window.deleteProduct = async function(id, name) {
        confirmAction(`Bạn có chắc muốn xóa "${name}"?`, async () => {
            try {
                const response = await fetch(`${API_BASE}delete_product.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                    showNotification('Xóa sản phẩm thành công!', 'success');
                } else {
                    showNotification('Lỗi khi xóa!', 'danger');
                }
            } catch (error) {
                showNotification('Lỗi kết nối!', 'danger');
            }
        });
    };
    
    /**
     * CRUD Reviews (tương tự products)
     */
    window.editReview = async function(id) {
        try {
            const response = await fetch(`${API_BASE}get_review.php?id=${id}`);
            const review = await response.json();
            
            document.getElementById('review_id').value = review.id;
            document.getElementById('review_title').value = review.title;
            document.getElementById('review_content').value = review.content;
            document.getElementById('review_rating').value = review.rating;
            document.getElementById('review_product_id').value = review.product_id;
            
            showNotification('Đã load thông tin review!', 'info');
        } catch (error) {
            showNotification('Lỗi khi load review!', 'danger');
        }
    };
    
    // ========== FRONTEND FUNCTIONS ==========
    
    /**
     * Smooth scroll cho navigation
     */
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    /**
     * Intersection Observer cho animation
     */
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Áp dụng animation cho cards
    document.querySelectorAll('.product-card, .review-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        observer.observe(el);
    });
    
    /**
     * Affiliate button hover effect
     */
    document.querySelectorAll('.affiliate-btn').forEach(btn => {
        btn.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.02)';
        });
        
        btn.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    /**
     * Star rating interactive
     */
    document.querySelectorAll('.rating-stars i').forEach(star => {
        star.addEventListener('mouseover', function() {
            const stars = this.parentNode.children;
            for (let i = 0; i < stars.length; i++) {
                stars[i].classList.toggle('filled', i < this.dataset.index);
            }
        });
        
        star.addEventListener('mouseout', function() {
            // Reset về trạng thái gốc
            const rating = this.parentNode.dataset.rating || 0;
            const stars = this.parentNode.children;
            for (let i = 0; i < stars.length; i++) {
                stars[i].classList.toggle('filled', i < rating);
            }
        });
    });
    
    /**
     * Search functionality
     */
    window.searchProducts = function(query) {
        const cards = document.querySelectorAll('.product-card');
        const searchTerm = query.toLowerCase();
        
        cards.forEach(card => {
            const title = card.querySelector('.product-title').textContent.toLowerCase();
            if (title.includes(searchTerm)) {
                card.style.display = 'block';
                card.style.opacity = '1';
            } else {
                card.style.display = 'none';
            }
        });
    };
    
    /**
     * Filter by category (nếu có)
     */
    window.filterByCategory = function(category) {
        const cards = document.querySelectorAll('.product-card');
        cards.forEach(card => {
            const cat = card.dataset.category || '';
            if (category === 'all' || cat.includes(category)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    };
    
    // ========== FORM VALIDATION ==========
    
    /**
     * Validate form trước khi submit
     */
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Vui lòng điền đầy đủ thông tin!', 'warning');
            }
        });
    });
    
    /**
     * Real-time form validation
     */
    document.querySelectorAll('input, textarea, select').forEach(input => {
        input.addEventListener('blur', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
        });
    });
    
    // ========== PRICE RANGE SLIDER (nếu cần) ==========
    
    /**
     * Khởi tạo price slider
     */
    function initPriceSlider() {
        const slider = document.getElementById('priceSlider');
        const minPrice = document.getElementById('minPrice');
        const maxPrice = document.getElementById('maxPrice');
        
        if (slider) {
            const range = document.getElementById('priceRange');
            
            slider.addEventListener('input', function() {
                const value = this.value;
                const min = Math.floor(value / 100) * 100;
                const max = Math.ceil(value / 100) * 100 + 500000;
                
                minPrice.textContent = formatCurrency(min);
                maxPrice.textContent = formatCurrency(max);
                
                // Filter products theo giá
                filterByPrice(min, max);
            });
        }
    }
    
    function filterByPrice(min, max) {
        document.querySelectorAll('.product-card').forEach(card => {
            const price = parseInt(card.dataset.price);
            if (price >= min && price <= max) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // ========== LAZY LOADING IMAGES ==========
    
    /**
     * Lazy load images
     */
    function initLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
    }
    
    // ========== INIT APPLICATION ==========
    
    /**
     * Khởi tạo toàn bộ app
     */
    function initApp() {
        console.log('🎉 Affiliate Review Site initialized!');
        
        // Load dashboard stats nếu có
        if (document.getElementById('totalProducts')) {
            loadDashboardStats();
        }
        
        // Init price slider nếu có
        initPriceSlider();
        
        // Init lazy loading
        initLazyLoading();
        
        // Set interval update stats (5 phút)
        if (document.getElementById('totalProducts')) {
            setInterval(loadDashboardStats, 300000);
        }
    }
    
    // Start app
    initApp();
    
    // Window resize handler
    window.addEventListener('resize', function() {
        // Re-init responsive elements
    });
    
    // Prevent double submit
    document.querySelectorAll('form').forEach(form => {
        let isSubmitting = false;
        form.addEventListener('submit', function() {
            if (isSubmitting) {
                return false;
            }
            isSubmitting = true;
            showLoading(this.querySelector('button[type="submit"]'));
        });
    });
    
});

// ============================================
// PWA SERVICE WORKER (Optional)
// ============================================

if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/sw.js')
            .then(function(registration) {
                console.log('SW registered: ', registration);
            })
            .catch(function(registrationError) {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Export functions for global use
window.showNotification = showNotification;
window.formatCurrency = formatCurrency;
window.searchProducts = searchProducts;