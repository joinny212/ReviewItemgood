<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require '../config/database.php';

// Lấy danh sách products để dropdown
$products_stmt = $pdo->query("SELECT id, name FROM products ORDER BY name");
$products = $products_stmt->fetchAll();

// Xử lý thêm/sửa/xóa review
if ($_POST) {
    if (isset($_POST['add_review'])) {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $product_id = $_POST['product_id'] ?: null;
        $rating = $_POST['rating'];
        
        $target_dir = "../uploads/";
        $image = $_FILES['image']['name'];
        if ($image) {
            $image_name = time() . '_' . $image;
            move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $image_name);
            $image = $image_name;
        }
        
        $stmt = $pdo->prepare("INSERT INTO reviews (title, content, product_id, rating, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $product_id, $rating, $image]);
        
        $success = "Thêm bài review thành công!";
    }
    
    if (isset($_POST['edit_review'])) {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $product_id = $_POST['product_id'] ?: null;
        $rating = $_POST['rating'];
        
        if ($_FILES['image']['name']) {
            $target_dir = "../uploads/";
            $image_name = time() . '_' . $_FILES['image']['name'];
            move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $image_name);
            $image = $image_name;
            
            $stmt = $pdo->prepare("UPDATE reviews SET title=?, content=?, product_id=?, rating=?, image=? WHERE id=?");
            $stmt->execute([$title, $content, $product_id, $rating, $image, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE reviews SET title=?, content=?, product_id=?, rating=? WHERE id=?");
            $stmt->execute([$title, $content, $product_id, $rating, $id]);
        }
        
        $success = "Cập nhật bài review thành công!";
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id=?");
    $stmt->execute([$id]);
    $success = "Xóa bài review thành công!";
}

// Lấy danh sách reviews với thông tin product
$stmt = $pdo->query("
    SELECT r.*, p.name as product_name 
    FROM reviews r 
    LEFT JOIN products p ON r.product_id = p.id 
    ORDER BY r.created_at DESC
");
$reviews = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Bài Review</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .star-rating {
            display: flex;
            gap: 5px;
            font-size: 1.5rem;
            color: #ddd;
        }
        .star-rating i.filled {
            color: #ffc107;
        }
        .preview-img {
            max-width: 100px;
            max-height: 100px;
            border-radius: 8px;
            object-fit: cover;
        }
        .form-floating textarea {
            min-height: 120px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-star text-warning me-2"></i>Quản lý Bài Review</h2>
            <div>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại Dashboard
                </a>
            </div>
        </div>

        <!-- Form thêm/sửa review -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-gradient text-white">
                <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Thêm/Sửa Bài Review</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="reviewForm">
                    <input type="hidden" name="id" id="review_id">
                    
                    <div class="row">
                        <!-- Cột trái -->
                        <div class="col-lg-8">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Tiêu đề <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="review_title" required 
                                           placeholder="VD: Review tai nghe Sony WH-1000XM5">
                                </div>
                                
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Nội dung <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="content" id="review_content" required 
                                              placeholder="Viết chi tiết đánh giá sản phẩm..."></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Điểm đánh giá <span class="text-danger">*</span></label>
                                    <select class="form-select" name="rating" id="review_rating" required>
                                        <option value="">Chọn điểm</option>
                                        <option value="1">★☆☆☆☆ (1/5)</option>
                                        <option value="2">★★☆☆☆ (2/5)</option>
                                        <option value="3">★★★☆☆ (3/5)</option>
                                        <option value="4">★★★★☆ (4/5)</option>
                                        <option value="5">★★★★★ (5/5)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Sản phẩm liên quan</label>
                                    <select class="form-select" name="product_id" id="review_product_id">
                                        <option value="">-- Không chọn --</option>
                                        <?php foreach($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cột phải - Upload ảnh -->
                        <div class="col-lg-4">
                            <div class="card border-0 bg-light h-100">
                                <div class="card-body text-center">
                                    <label class="form-label fw-bold mb-3 d-block">
                                        <i class="fas fa-image text-primary me-2"></i>Hình ảnh minh họa
                                    </label>
                                    <div id="imagePreview" class="mb-3 p-4 border-dashed border-2 rounded-3 bg-white">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">Click để upload ảnh</p>
                                        <small class="text-muted">JPG, PNG (Max 5MB)</small>
                                    </div>
                                    <input type="file" class="form-control" name="image" id="review_image" 
                                           accept="image/*" style="display: none;">
                                    <div class="current-image mt-3" id="currentImage"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="resetReviewForm()">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                        <button type="submit" name="edit_review" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Cập nhật
                        </button>
                        <button type="submit" name="add_review" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Thêm mới
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách reviews -->
        <div class="card shadow-sm">
            <div class="card-header bg-gradient text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách Review (<?php echo count($reviews); ?>)</h5>
                <div class="input-group w-50">
                    <input type="text" class="form-control" id="searchReviews" 
                           placeholder="Tìm kiếm theo tiêu đề..." onkeyup="searchReviews(this.value)">
                    <button class="btn btn-outline-light" type="button">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="50">Ảnh</th>
                                <th>Tiêu đề</th>
                                <th>Sản phẩm</th>
                                <th>Điểm</th>
                                <th>Ngày tạo</th>
                                <th width="150">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTableBody">
                            <?php foreach($reviews as $review): ?>
                            <tr data-title="<?php echo strtolower($review['title']); ?>">
                                <td>
                                    <?php if($review['image']): ?>
                                        <img src="../uploads/<?php echo $review['image']; ?>" 
                                             class="preview-img" 
                                             alt="Review image"
                                             onerror="this.src='https://via.placeholder.com/80x80?text=No+Img'">
                                    <?php else: ?>
                                        <div class="bg-light rounded p-3 d-flex align-items-center justify-content-center" style="width:80px;height:80px;">
                                            <i class="fas fa-image fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars(substr($review['title'], 0, 50)); ?></strong>
                                    <?php if(strlen($review['title']) > 50): ?>...<?php endif; ?>
                                    <br><small class="text-muted"><?php echo substr(strip_tags($review['content']), 0, 80); ?>...</small>
                                </td>
                                <td>
                                    <?php if($review['product_name']): ?>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($review['product_name']); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Không có</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="star-rating" data-rating="<?php echo $review['rating']; ?>">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>" 
                                               data-index="<?php echo $i; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?php echo $review['rating']; ?>/5</small>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($review['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-info" 
                                                onclick="editReview(<?php echo $review['id']; ?>)"
                                                title="Sửa">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $review['id']; ?>" 
                                           class="btn btn-outline-danger" 
                                           onclick="return confirm('Xóa review này?')"
                                           title="Xóa">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <button class="btn btn-outline-secondary" 
                                                onclick="previewReview(<?php echo $review['id']; ?>)"
                                                title="Xem trước">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($reviews)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                        <h5>Chưa có bài review nào</h5>
                                        <p>Hãy thêm bài review đầu tiên!</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xem trước Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent">
                    <!-- Nội dung preview sẽ load ở đây -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/script.js"></script>
    <script>
        // Image upload preview
        document.getElementById('review_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            const currentImage = document.getElementById('currentImage');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <img src="${e.target.result}" class="preview-img mb-2" id="previewImg">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-success btn-sm flex-fill" onclick="setAsCurrentImage()">
                                <i class="fas fa-check me-1"></i>Sử dụng
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearImagePreview()">
                                <i class="fas fa-times me-1"></i>Hủy
                            </button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            }
        });

        function setAsCurrentImage() {
            const previewImg = document.getElementById('previewImg');
            document.getElementById('currentImage').innerHTML = `
                <small class="text-success d-block mb-1">
                    <i class="fas fa-check-circle me-1"></i>Ảnh đã chọn
                </small>
                <img src="${previewImg.src}" class="preview-img">
            `;
        }

        function clearImagePreview() {
            document.getElementById('imagePreview').innerHTML = `
                <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                <p class="text-muted mb-0">Click để upload ảnh</p>
                <small class="text-muted">JPG, PNG (Max 5MB)</small>
            `;
            document.getElementById('review_image').value = '';
            document.getElementById('currentImage').innerHTML = '';
        }

        // Trigger file input
        document.getElementById('imagePreview').addEventListener('click', function() {
            document.getElementById('review_image').click();
        });

        // Reset form
        window.resetReviewForm = function() {
            document.getElementById('reviewForm').reset();
            document.getElementById('review_id').value = '';
            clearImagePreview();
            document.querySelector('.star-rating').classList.remove('filled');
            showNotification('Đã reset form review!', 'info');
        };

        // Search reviews
        window.searchReviews = function(query) {
            const rows = document.querySelectorAll('#reviewsTableBody tr[data-title]');
            query = query.toLowerCase();
            
            rows.forEach(row => {
                const title = row.dataset.title;
                if (title.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        };

        // Edit review
        window.editReview = async function(id) {
            try {
                const response = await fetch(`api/get_review.php?id=${id}`);
                const review = await response.json();
                
                // Fill form
                document.getElementById('review_id').value = review.id;
                document.getElementById('review_title').value = review.title;
                document.getElementById('review_content').value = review.content;
                document.getElementById('review_rating').value = review.rating;
                document.getElementById('review_product_id').value = review.product_id || '';
                
                // Show current image
                if (review.image) {
                    document.getElementById('currentImage').innerHTML = `
                        <small class="text-success d-block mb-1">
                            <i class="fas fa-check-circle me-1"></i>Ảnh hiện tại
                        </small>
                        <img src="../uploads/${review.image}" class="preview-img">
                    `;
                }
                
                // Scroll to form
                document.querySelector('#reviewForm').scrollIntoView({ behavior: 'smooth' });
                showNotification('Đã load thông tin review!', 'info');
                
            } catch (error) {
                showNotification('Lỗi khi load review!', 'danger');
            }
        };

        // Preview review
        window.previewReview = function(id) {
            document.getElementById('previewContent').innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            fetch(`api/get_review.php?id=${id}`)
                .then(response => response.json())
                .then(review => {
                    document.getElementById('previewContent').innerHTML = `
                        <div class="review-preview">
                            <div class="star-rating mb-3" data-rating="${review.rating}">
                                ${Array(5).fill().map((_, i) => 
                                    `<i class="fas fa-star ${i < review.rating ? 'filled text-warning' : 'text-muted'}" data-index="${i+1}"></i>`
                                ).join('')}
                            </div>
                            <h4 class="fw-bold mb-3">${review.title}</h4>
                            ${review.image ? `<img src="../uploads/${review.image}" class="img-fluid rounded mb-3" style="max-height: 300px;">` : ''}
                            <div class="review-content">${review.content.replace(/\n/g, '<br>')}</div>
                            ${review.product_name ? `<div class="mt-3 p-2 bg-light rounded">
                                <strong>Sản phẩm:</strong> ${review.product_name}
                            </div>` : ''}
                        </div>
                    `;
                    
                    new bootstrap.Modal(document.getElementById('previewModal')).show();
                });
        };

        // Star rating hover effect
        document.querySelectorAll('.star-rating').forEach(rating => {
            rating.addEventListener('mouseleave', function() {
                const ratingValue = this.dataset.rating || 0;
                const stars = this.querySelectorAll('i');
                stars.forEach((star, index) => {
                    star.classList.toggle('filled', index < ratingValue);
                });
            });
        });
    </script>
</body>
</html>