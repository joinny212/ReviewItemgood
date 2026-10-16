<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require '../config/database.php';
require_once '../config/image_storage.php';

$image_gallery_available = true;
$image_gallery_error = null;
$product_image_has_filename = false;
try {
    $pdo->query("SELECT image, sort_order FROM product_images LIMIT 0");
    $columns = $pdo->query("SHOW COLUMNS FROM product_images")->fetchAll(PDO::FETCH_COLUMN);
    $product_image_has_filename = in_array('filename', $columns, true);
} catch (PDOException $exception) {
    if (!in_array($exception->getCode(), ['42S02', '42S22'], true)) {
        throw $exception;
    }
    $image_gallery_available = false;
    $image_gallery_error = 'Database Render thiếu cấu trúc bảng ảnh sản phẩm. Hãy chạy lệnh ALTER TABLE được hướng dẫn để bật lưu nhiều ảnh.';
    error_log('Product image gallery schema is missing: ' . $exception->getMessage());
}

function insertProductImage(PDO $pdo, int $productId, string $image, int $sortOrder, bool $hasFilename): void
{
    if ($hasFilename) {
        $imagePath = parse_url($image, PHP_URL_PATH);
        $filename = basename(is_string($imagePath) ? $imagePath : $image);
        $stmt = $pdo->prepare(
            "INSERT INTO product_images (id, product_id, image, sort_order, filename) VALUES (?, ?, ?, ?, ?)"
        );
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO product_images (id, product_id, image, sort_order) VALUES (?, ?, ?, ?)"
        );
    }

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $id = random_int(1, 2147483647);
        try {
            $values = [$id, $productId, $image, $sortOrder];
            if ($hasFilename) {
                $values[] = $filename;
            }
            $stmt->execute($values);
            return;
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }
        }
    }

    throw new RuntimeException('Không thể cấp ID duy nhất cho ảnh sản phẩm sau nhiều lần thử.');
}

function uploadProductImages(): array
{
    if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
        return [];
    }

    $files = $_FILES['images'];
    $mime_extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $uploaded_images = [];

    foreach ($files['error'] as $index => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'Ảnh vượt giới hạn upload_max_filesize của Render.',
                UPLOAD_ERR_FORM_SIZE => 'Ảnh vượt giới hạn dung lượng của biểu mẫu.',
                UPLOAD_ERR_PARTIAL => 'Ảnh chỉ được tải lên một phần. Hãy thử lại.',
                UPLOAD_ERR_NO_TMP_DIR => 'Máy chủ thiếu thư mục tạm để nhận ảnh.',
                UPLOAD_ERR_CANT_WRITE => 'Máy chủ không ghi được ảnh tải lên.',
                UPLOAD_ERR_EXTENSION => 'PHP extension đã chặn việc tải ảnh lên.',
            ];
            throw new RuntimeException($upload_errors[$error] ?? 'Tải ảnh lên thất bại. Vui lòng thử lại.');
        }

        $tmp_name = $files['tmp_name'][$index];
        $mime_type = (new finfo(FILEINFO_MIME_TYPE))->file($tmp_name);
        if (!isset($mime_extensions[$mime_type])) {
            throw new RuntimeException('Chỉ chấp nhận ảnh JPEG, PNG, GIF hoặc WebP.');
        }

        if (!is_uploaded_file($tmp_name)) {
            throw new RuntimeException('Tệp ảnh tải lên không hợp lệ.');
        }

        $uploaded_images[] = uploadProductImageToCloudinary($tmp_name, $mime_type);
    }

    return $uploaded_images;
}

$form_error = $image_gallery_available ? null : $image_gallery_error;
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
    $action = $_POST['action'] ?? (
        isset($_POST['edit_product']) ? 'edit' : (isset($_POST['add_product']) ? 'add' : '')
    );
    if ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $form_error = 'Mã sản phẩm cần xóa không hợp lệ.';
        } else {
            $pdo->beginTransaction();
            if ($image_gallery_available) {
                $delete_images = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $delete_images->execute([$id]);
            }
            $delete_product = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $delete_product->execute([$id]);
            $pdo->commit();
            header('Location: products.php');
            exit;
        }
    } elseif (
        !$image_gallery_available
        && in_array($action, ['add', 'edit'], true)
    ) {
        $form_error = $image_gallery_error;
    } elseif ($action === 'add') {
        if (!empty($_POST['id'])) {
            $form_error = 'Đang sửa một sản phẩm. Hãy bấm Cập nhật, không bấm Thêm mới.';
        }
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        if ($form_error === null) {
            try {
                $images = uploadProductImages();
            } catch (RuntimeException $exception) {
                $form_error = $exception->getMessage();
            }
        }

        if ($form_error === null) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO products (name, price, affiliate_link, image, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $link, $images[0] ?? '', $desc]);
            $product_id = $pdo->lastInsertId();

            if ($images) {
                foreach ($images as $sort_order => $image) {
                    insertProductImage($pdo, (int) $product_id, $image, $sort_order, $product_image_has_filename);
                }
            }

            $pdo->commit();
            header('Location: products.php');
            exit;
        }
    }
    
    if ($action === 'edit' && $image_gallery_available) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            $form_error = 'Hãy chọn một sản phẩm hợp lệ để cập nhật.';
        }
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        if ($form_error === null) {
            try {
                $images = uploadProductImages();
            } catch (RuntimeException $exception) {
                $form_error = $exception->getMessage();
            }
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

                $all_images = array_merge($images, $existing_images);
                $delete_stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
                $delete_stmt->execute([$id]);
                foreach ($all_images as $sort_order => $image) {
                    insertProductImage($pdo, $id, $image, $sort_order, $product_image_has_filename);
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

// Lấy danh sách sản phẩm
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();
$product_images = [];
if ($image_gallery_available) {
    $image_stmt = $pdo->query("SELECT product_id, image FROM product_images ORDER BY sort_order, id");
    foreach ($image_stmt->fetchAll() as $product_image) {
        $product_images[$product_image['product_id']][] = $product_image['image'];
    }
}
foreach ($products as &$product) {
    $product['images'] = normalizeProductImages(array_merge(
        [$product['image'] ?? ''],
        $product_images[$product['id']] ?? []
    ));
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
                    <input type="hidden" name="action" id="product_action" value="add">
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
                                <input type="file" class="form-control" name="images[]" id="images" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
                                <div class="form-text">Chọn nhiều ảnh cùng lúc hoặc bấm Chọn tệp thêm lần nữa để cộng dồn ảnh. Tối đa 30 ảnh, 20 MB mỗi ảnh.</div>
                                <div id="selectedImageCount" class="small text-muted mt-1">Chưa chọn ảnh mới</div>
                                <div id="selectedImageNames" class="small text-muted"></div>
                                <div id="imagePreview" class="d-flex flex-wrap gap-2 mt-2"></div>
                            </div>
                            <button type="submit" id="addProductButton" class="btn btn-primary me-2">Thêm mới</button>
                            <button type="submit" id="updateProductButton" class="btn btn-warning" hidden>Cập nhật</button>
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
                                            <?php if (preg_match('#^https?://#i', $image) || is_file(__DIR__ . '/../uploads/' . basename($image))): ?>
                                                <img src="<?php echo htmlspecialchars(productImageUrl($image, '../uploads/'), ENT_QUOTES, 'UTF-8'); ?>" width="50" height="50" class="img-thumbnail" style="object-fit: cover;" alt="">
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
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Xóa sản phẩm này?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                                    </form>
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

        function clearSelectedImageInputs() {
            document.querySelectorAll('#productForm input[name="images[]"]').forEach((input, index) => {
                if (index === 0) {
                    input.value = '';
                    input.hidden = false;
                    input.id = 'images';
                } else {
                    input.remove();
                }
            });
            selectedProductFiles = [];
        }

        function renderImagePreview() {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            currentProductImages.forEach(image => {
                const imageElement = document.createElement('img');
                imageElement.src = /^https?:\/\//i.test(image)
                    ? image
                    : `../uploads/${encodeURIComponent(image)}`;
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
            document.getElementById('selectedImageNames').textContent = selectedProductFiles
                .map(file => file.name)
                .join(', ');
        }

        function editProduct(id) {
            clearSelectedImageInputs();
            renderImagePreview();
            fetch(`api/get_product.php?id=${encodeURIComponent(id)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Không tải được thông tin sản phẩm.');
                    }
                    return response.json();
                })
                .then(product => {
                    if (!product.id) {
                        throw new Error('Không tìm thấy sản phẩm cần sửa.');
                    }
                    document.getElementById('product_id').value = product.id;
                    document.getElementById('name').value = product.name;
                    document.getElementById('price').value = product.price;
                    document.getElementById('affiliate_link').value = product.affiliate_link;
                    document.getElementById('description').value = product.description;
                    document.getElementById('product_action').value = 'edit';
                    document.getElementById('addProductButton').hidden = true;
                    document.getElementById('updateProductButton').hidden = false;
                    currentProductImages = product.images || [];
                    renderImagePreview();
                    document.getElementById('productForm').scrollIntoView({ behavior: 'smooth' });
                })
                .catch(error => window.alert(error.message));
        }

        function resetForm() {
            document.getElementById('productForm').reset();
            document.getElementById('product_id').value = '';
            document.getElementById('product_action').value = 'add';
            document.getElementById('addProductButton').hidden = false;
            document.getElementById('updateProductButton').hidden = true;
            currentProductImages = [];
            clearSelectedImageInputs();
            renderImagePreview();
        }

        document.getElementById('productForm').addEventListener('change', event => {
            const fileInput = event.target;
            if (!(fileInput instanceof HTMLInputElement) || fileInput.type !== 'file' || fileInput.name !== 'images[]') {
                return;
            }

            const newFiles = Array.from(fileInput.files);
            if (selectedProductFiles.length + newFiles.length > 30) {
                window.alert('Mỗi sản phẩm chỉ được tải tối đa 30 ảnh.');
                fileInput.value = '';
                return;
            }
            if (newFiles.some(file => file.size > 20 * 1024 * 1024)) {
                window.alert('Mỗi ảnh phải có dung lượng tối đa 20 MB.');
                fileInput.value = '';
                return;
            }

            if (!newFiles.length) {
                return;
            }

            selectedProductFiles.push(...newFiles);
            fileInput.hidden = true;
            fileInput.removeAttribute('id');

            const nextInput = fileInput.cloneNode();
            nextInput.value = '';
            nextInput.hidden = false;
            nextInput.id = 'images';
            fileInput.after(nextInput);
            renderImagePreview();
        });

        document.getElementById('productForm').addEventListener('submit', event => {
            const action = document.getElementById('product_action').value;
            const productId = document.getElementById('product_id').value;
            if (action === 'edit' && !productId) {
                event.preventDefault();
                window.alert('Hãy bấm Sửa sản phẩm trước khi cập nhật.');
                return;
            }
            if (action === 'edit' && selectedProductFiles.length === 0) {
                const continueWithoutImages = window.confirm(
                    'Bạn chưa chọn ảnh mới. Chỉ lưu thông tin sản phẩm, không tải ảnh lên Cloudinary. Tiếp tục?'
                );
                if (!continueWithoutImages) {
                    event.preventDefault();
                }
            }
        });
    </script>
</body>
</html>