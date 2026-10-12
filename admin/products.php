<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require '../config/database.php';

// Xử lý thêm/sửa/xóa
if ($_POST) {
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        $target_dir = "../uploads/";
        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $image = time() . '_' . $_FILES['image']['name'];
            move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $image);
        }
        
        $stmt = $pdo->prepare("INSERT INTO products (name, price, affiliate_link, image, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $price, $link, $image, $desc]);
        header('Location: products.php');
        exit;
    }
    
    if (isset($_POST['edit_product'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $price = $_POST['price'];
        $link = $_POST['affiliate_link'];
        $desc = $_POST['description'];
        
        if (!empty($_FILES['image']['name'])) {
            $target_dir = "../uploads/";
            $image = time() . '_' . $_FILES['image']['name'];
            move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $image);
            $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, affiliate_link=?, description=?, image=? WHERE id=?");
            $stmt->execute([$name, $price, $link, $desc, $image, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, affiliate_link=?, description=? WHERE id=?");
            $stmt->execute([$name, $price, $link, $desc, $id]);
        }
        header('Location: products.php');
        exit;
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
                <form method="POST" enctype="multipart/form-data">
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
                                <label>Ảnh sản phẩm</label>
                                <input type="file" class="form-control" name="image" id="image" accept="image/*">
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
                                    <?php if($product['image']): ?>
                                        <img src="../uploads/<?php echo $product['image']; ?>" width="50" class="img-thumbnail">
                                    <?php endif; ?>
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
        function editProduct(id) {
            fetch(`api/get_product.php?id=${id}`)
                .then(response => response.json())
                .then(product => {
                    document.getElementById('product_id').value = product.id;
                    document.getElementById('name').value = product.name;
                    document.getElementById('price').value = product.price;
                    document.getElementById('affiliate_link').value = product.affiliate_link;
                    document.getElementById('description').value = product.description;
                });
        }

        function resetForm() {
            document.querySelector('form').reset();
            document.getElementById('product_id').value = '';
        }
    </script>
</body>
</html>