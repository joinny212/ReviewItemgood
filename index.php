<?php
require 'config/database.php';
require_once 'config/image_storage.php';

// Lấy sản phẩm nổi bật (6 sản phẩm mới nhất)
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 6");
$featured_products = $stmt->fetchAll();

$product_images = [];
foreach ($featured_products as &$product) {
    $product['images'] = $product['image'] ? [$product['image']] : [];
}
unset($product);

$featured_product_ids = array_column($featured_products, 'id');
if ($featured_product_ids) {
    $placeholders = implode(',', array_fill(0, count($featured_product_ids), '?'));
    try {
        $image_stmt = $pdo->prepare("SELECT product_id, image FROM product_images WHERE product_id IN ($placeholders) ORDER BY sort_order, id");
        $image_stmt->execute($featured_product_ids);
        foreach ($image_stmt->fetchAll() as $product_image) {
            $product_images[$product_image['product_id']][] = $product_image['image'];
        }

        foreach ($featured_products as &$product) {
            if (!empty($product_images[$product['id']])) {
                $product['images'] = $product_images[$product['id']];
            }
        }
        unset($product);
    } catch (PDOException $exception) {
        if (!in_array($exception->getCode(), ['42S02', '42S22'], true)) {
            throw $exception;
        }
        error_log('Product image gallery schema is missing: ' . $exception->getMessage());
    }
}

// Lấy bài review mới nhất (4 bài)
$stmt = $pdo->query("SELECT r.*, p.name as product_name, p.image as product_image 
                     FROM reviews r 
                     LEFT JOIN products p ON r.product_id = p.id 
                     ORDER BY r.created_at DESC LIMIT 4");
$latest_reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review & Affiliate - Đánh giá sản phẩm tốt nhất</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body>
    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow-sm py-2">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4 text-white" href="index.php">
                <i class="fas fa-fire text-warning me-2"></i>Ninja Review
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="index.php"><i class="fas fa-home me-1"></i> Trang chủ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white-50" href="#products"><i class="fas fa-box me-1"></i> Sản phẩm</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white-50" href="#reviews"><i class="fas fa-star me-1"></i> Bài review</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="admin/index.php" class="btn btn-warning btn-sm px-3 py-2 fw-bold text-dark shadow-sm">
                        <i class="fas fa-user-shield me-1"></i> Quản trị (Admin)
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1>Review Sản Phẩm <br> <span class="text-warning">Chân Thực Nhất</span></h1>
            <p>Chúng tôi review và giới thiệu những sản phẩm chất lượng với link affiliate tốt nhất thị trường</p>
            <a href="#products" class="cta-button">Khám phá ngay <i class="fas fa-arrow-right ms-2"></i></a>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="products-grid" id="products">
        <div class="container">
            <div class="section-title">
                <h2>Sản phẩm nổi bật</h2>
                <p>Những sản phẩm được đánh giá cao nhất</p>
            </div>
            
            <div class="row g-4">
                <?php foreach($featured_products as $product): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="product-card h-100">
                        <div class="product-image">
                            <?php if(!empty($product['images'])): ?>
                                <img class="product-main-image" src="<?php echo htmlspecialchars(productImageUrl($product['images'][0], 'uploads/'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>" onerror="this.hidden=true; this.nextElementSibling.hidden=false;">
                                <div class="product-image-placeholder" hidden>Ảnh chưa khả dụng</div>
                            <?php else: ?>
                                <div class="product-image-placeholder">Chưa có ảnh</div>
                            <?php endif; ?>
                            <div class="product-badge">Hot</div>
                        </div>
                        <div class="product-thumbnails<?php echo count($product['images']) > 1 ? '' : ' product-thumbnails-empty'; ?>" aria-label="Ảnh sản phẩm">
                            <?php if (count($product['images']) > 1): ?>
                                <?php foreach ($product['images'] as $image): ?>
                                    <button type="button" class="product-thumbnail" aria-label="Xem ảnh sản phẩm" data-image-url="<?php echo htmlspecialchars(productImageUrl($image, 'uploads/'), ENT_QUOTES, 'UTF-8'); ?>" onclick="showProductImage(this)">
                                        <img src="<?php echo htmlspecialchars(productImageUrl($image, 'uploads/'), ENT_QUOTES, 'UTF-8'); ?>" alt="" onerror="this.parentElement.hidden=true;">
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</div>
                            <a href="<?php echo $product['affiliate_link']; ?>" 
                               class="btn affiliate-btn" 
                               target="_blank">
                                <i class="fas fa-shopping-cart me-2"></i>Mua ngay
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Latest Reviews -->
    <section class="reviews-grid" id="reviews">
        <div class="container">
            <div class="section-title">
                <h2>Bài review mới nhất</h2>
                <p>Đánh giá chi tiết từ người dùng thực tế</p>
            </div>
            
            <div class="row g-4">
                <?php foreach($latest_reviews as $review): ?>
                <div class="col-lg-6 col-md-12">
                    <div class="review-card h-100">
                        <div class="card-header">
                            <div class="review-rating">
                                <div class="rating-stars">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-number"><?php echo $review['rating']; ?>/5</span>
                            </div>
                            <h4><?php echo htmlspecialchars($review['title']); ?></h4>
                        </div>
                        <div class="card-body p-4">
                            <p class="text-muted mb-3"><?php echo substr($review['content'], 0, 200); ?>...</p>
                            <?php if($review['product_image']): ?>
                                <img src="<?php echo htmlspecialchars(productImageUrl($review['product_image'], 'uploads/'), ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="img-fluid rounded mb-3" 
                                     style="max-height: 150px; object-fit: cover;"
                                     alt="<?php echo $review['product_name']; ?>">
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></small>
                                <a href="#readmore" class="text-primary fw-semibold">Đọc thêm →</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2026 Ninja Review. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showProductImage(button) {
            const card = button.closest('.product-card');
            const mainImage = card.querySelector('.product-main-image');
            const placeholder = card.querySelector('.product-image-placeholder');
            mainImage.src = button.dataset.imageUrl;
            mainImage.hidden = false;
            placeholder.hidden = true;
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Animate on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        document.querySelectorAll('.product-card, .review-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>