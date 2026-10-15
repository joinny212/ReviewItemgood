<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require '../config/database.php';

function uploadProductImages(): array
{
    if (!isset($_FILES['images'])) {
        return [];
    }

    $files = $_FILES['images'];
    $target_dir = __DIR__ . '/../uploads/';
    $mime_extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $uploaded_images = [];

    try {
        foreach ($files['error'] as $index => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Tải ảnh lên thất bại. Vui lòng thử lại.');
            }

            $tmp_name = $files['tmp_name'][$index];
            $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($tmp_name);
            if (!isset($mime_extensions[$mime_type])) {
                throw new RuntimeException('Chỉ chấp nhận ảnh JPEG, PNG, GIF hoặc WebP.');
            }

            if (!is_dir($target_dir) && !mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
                throw new RuntimeException('Không thể tạo thư mục lưu ảnh.');
            }

            $image_name = bin2hex(random_bytes(16)) . '.' . $mime_extensions[$mime_type];
            if (!move_uploaded_file($tmp_name, $target_dir . $image_name)) {
                throw new RuntimeException('Không thể lưu ảnh đã tải lên.');
            }
            $uploaded_images[] = $image_name;
        }
    } catch (RuntimeException $exception) {
        foreach ($uploaded_images as $image_name) {
            unlink($target_dir . $image_name);
        }
        throw $exception;
    }

    return $uploaded_images;
}

$form_error = null;
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && empty($_POST)
    && empty($_FILES)
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    $form_error = 'Tổng dung lượng ảnh vượt giới hạn tải lên của máy chủ. Hãy chọn ít ảnh hơn hoặc ảnh có dung lượng nhỏ hơn.';
}

// Xử lý thêm/sửa/xóa
if ($_POST) {
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        try {
            $images = uploadProductImages();
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }

        if ($form_error === null) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO products (name, price, affiliate_link, image, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $link, $images[0] ?? '', $desc]);
            $product_id = $pdo->lastInsertId();

            if ($images) {
                $image_stmt = $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)");
                foreach ($images as $sort_order => $image) {
                    $image_stmt->execute([$product_id, $image, $sort_order]);
                }
            }

            $pdo->commit();
            header('Location: products.php');
            exit;
        }
    }
    
    if (isset($_POST['edit_product'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        try {
            $images = uploadProductImages();
        } catch (RuntimeException $exception) {
            $form_error = $exception->getMessage();
        }

        if ($form_error === null) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, affiliate_link=?, description=? WHERE id=?");
            $stmt->execute([$name, $price, $link, $desc, $id]);

            if ($images) {
                $image_stmt = $pdo->prepare("SELECT image FROM product_images WHERE product_id = ? ORDER BY sort_order, id");
                $image_stmt->execute([$id]);
                $existing_images = $image_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!$existing_images) {
                    $legacy_stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                    $legacy_stmt->execute([$id]);
                    $legacy_image = $legacy_stmt->fetchColumn();
                    if ($legacy_image) {
                        $existing_images[] = $legacy_image;
                    }
                }

                $all_images = array_merge($existing_images, $images);
                $delete_stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $delete_stmt->execute([$id]);
                $insert_image = $pdo->prepare("INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)");
                foreach ($all_images as $sort_order => $image) {
                    $insert_image->execute([$id, $image, $sort_order]);
                }
                $update_primary = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
                $update_primary->execute([$all_images[0], $id]);
            }

            $pdo->commit();
            header('Location: products.php');
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id=?");
    $stmt->execute([$id]);
    header('Location: products.php');
    exit;
}

// Lấy danh sách sản phẩm
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();
$image_stmt = $pdo->query("SELECT product_id, image FROM product_images ORDER BY sort_order, id");
$product_images = [];
foreach ($image_stmt->fetchAll() as $product_image) {
    $product_images[$product_image['product_id']][] = $product_image['image'];
}
foreach ($products as &$product) {
    if (empty($product_images[$product['id']]) && $product['image']) {
        $product_images[$product['id']] = [$product['image']];
    }
    $product['images'] = $product_images[$product['id']] ?? [];
}
unset($product);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quản lý Sản phẩm</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-box"></i> Quản lý Sản phẩm</h2>
            <a href="index.php" class="btn btn-secondary">← Quay lại</a>
        </div>

        <!-- Form thêm/sửa sản phẩm -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Thêm/Sửa sản phẩm</h5>
            </div>
            <div class="card-body">
                <?php if ($form_error !== null): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($form_error); ?></div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <div class="row">
                        <div class="col-md-6">
                            <input type="hidden" name="id" id="product_id">
                            <div class="mb-3">
                                <label>Tên sản phẩm</label>
                                <input type="text" class="form-control" name="name" id="name" required>
                            </div>
                            <div class="mb-3">
                                <label>Giá</label>
                                <input type="number" step="0.01" class="form-control" name="price" id="price">
                            </div>
                            <div class="mb-3">
                                <label>Link Affiliate</label>
                                <input type="url" class="form-control" name="affiliate_link" id="affiliate_link">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Mô tả</label>
                                <textarea class="form-control" name="description" id="description" rows="4"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="images">Ảnh sản phẩm</label>
                                <input type="file" class="form-control" id="images" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                                <div class="form-text">Chọn tối đa 30 ảnh (tối đa 20 MB mỗi ảnh). Có thể chọn thêm ảnh nhiều lần; khi cập nhật, ảnh mới sẽ được thêm vào bộ ảnh hiện có.</div>
                                <div id="selectedImageCount" class="small text-muted mt-1">Chưa chọn ảnh mới</div>
                                <div id="imagePreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                            <button type="submit" name="add_product" class="btn btn-primary me-2">Thêm mới</button>
                            <button type="submit" name="edit_product" class="btn btn-warning">Cập nhật</button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">Reset</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách sản phẩm -->
        <div class="card">
            <div class="card-header">
                <h5>Danh sách sản phẩm</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tên</th>
                                <th>Giá</th>
                                <th>Link Affiliate</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($product['images'] as $image): ?>
                                            <?php if (is_file(__DIR__ . '/../uploads/' . basename($image))): ?>
                                                <img src="../uploads/<?php echo rawurlencode($image); ?>" width="50" height="50" class="img-thumbnail" style="object-fit: cover;" alt="">
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark align-self-center">Thiếu file ảnh</span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</td>
                                <td>
                                    <a href="<?php echo $product['affiliate_link']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        Xem link
                                    </a>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="editProduct(<?php echo $product['id']; ?>)">
                                        Sửa
                                    </button>
                                    <a href="?delete=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Xóa sản phẩm này?')">
                                        Xóa
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentProductImages = [];
        let selectedProductFiles = [];

        function addSelectedFilesToForm(files) {
            if (!files.length) {
                return;
            }
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.name = 'images[]';
            fileInput.multiple = true;
            fileInput.hidden = true;
            fileInput.files = files;
            document.getElementById('productForm').appendChild(fileInput);
        }

        function renderImagePreview() {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            currentProductImages.forEach(image => {
                const imageElement = document.createElement('img');
                imageElement.src = `../uploads/${encodeURIComponent(image)}`;
                imageElement.width = 80;
                imageElement.height = 80;
                imageElement.className = 'img-thumbnail';
                imageElement.style.objectFit = 'cover';
                imageElement.alt = 'Ảnh hiện tại';
                imageElement.onerror = () => {
                    imageElement.hidden = true;
                };
                preview.appendChild(imageElement);
            });
            selectedProductFiles.forEach(file => {
                const imageElement = document.createElement('img');
                imageElement.src = URL.createObjectURL(file);
                imageElement.width = 80;
                imageElement.height = 80;
                imageElement.className = 'img-thumbnail';
                imageElement.style.objectFit = 'cover';
                imageElement.alt = file.name;
                preview.appendChild(imageElement);
            });

            const count = selectedProductFiles.length;
            document.getElementById('selectedImageCount').textContent = count
                ? `Đã chọn ${count} ảnh mới`
                : 'Chưa chọn ảnh mới';
        }

        function editProduct(id) {
            selectedProductFiles = [];
            document.querySelectorAll('#productForm input[name="images[]"]').forEach(input => input.remove());
            document.getElementById('images').value = '';
            fetch(`api/get_product.php?id=${id}`)
                .then(response => response.json())
                .then(product => {
                    document.getElementById('product_id').value = product.id;
                    document.getElementById('name').value = product.name;
                    document.getElementById('price').value = product.price;
                    document.getElementById('affiliate_link').value = product.affiliate_link;
                    document.getElementById('description').value = product.description;
                    currentProductImages = product.images || [];
                    renderImagePreview();
                });
        }

        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('product_id').value = '';
            currentProductImages = [];
            selectedProductFiles = [];
            document.querySelectorAll('#productForm input[name="images[]"]').forEach(input => input.remove());
            renderImagePreview();
        }

        document.getElementById('images').addEventListener('change', event => {
            const newFiles = Array.from(event.target.files);
            if (selectedProductFiles.length + newFiles.length > 30) {
                window.alert('Bạn chỉ có thể tải tối đa 30 ảnh cho mỗi lần lưu.');
                event.target.value = '';
                return;
            }
            if (newFiles.some(file => file.size > 20 * 1024 * 1024)) {
                window.alert('Mỗi ảnh phải có dung lượng tối đa 20 MB.');
                event.target.value = '';
                return;
            }
            addSelectedFilesToForm(event.target.files);
            selectedProductFiles.push(...newFiles);
            event.target.value = '';
            renderImagePreview();
        });
    </script>
</body>
</html>