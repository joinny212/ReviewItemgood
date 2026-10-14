CREATE DATABASE IF NOT EXISTS affiliate_review;
USE affiliate_review;

-- 1. Bảng tài khoản admin
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Thêm tài khoản admin mặc định: admin / admin123
INSERT INTO admin_users (username, password) 
VALUES ('admin', '$2y$12$9DPb4losEtU3OXybagacLuXD26vo3Yn6d2lc8ASzCvfTFioTno7bu')
ON DUPLICATE KEY UPDATE id=id;

-- 2. Bảng sản phẩm
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(12, 2) DEFAULT 0,
    affiliate_link VARCHAR(500),
    image VARCHAR(255),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng bài viết review
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    rating INT NOT NULL DEFAULT 5,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Thêm dữ liệu mẫu để web hiển thị ngay
INSERT INTO products (name, price, affiliate_link, description) VALUES
('Tai nghe Sony WH-1000XM5 Chống Ồn', 6990000, 'https://shopee.vn', 'Tai nghe chống ồn đỉnh cao của Sony với thời lượng pin 30 giờ.'),
('Bàn phím cơ không dây Keychron K2', 1890000, 'https://shopee.vn', 'Bàn phím cơ nhỏ gọn, hỗ trợ kết nối Bluetooth 3 thiết bị cùng lúc.'),
('Chuột công thái học Logitech MX Master 3S', 2190000, 'https://shopee.vn', 'Chuột công thái học cao cấp cho dân văn phòng và lập trình viên.');

INSERT INTO reviews (product_id, title, content, rating) VALUES
(1, 'Đánh giá Sony WH-1000XM5: Đỉnh cao chống ồn', 'Sau 1 tháng trải nghiệm, chất âm và khả năng chống ồn của tai nghe này thực sự xuất sắc, thiết kế nhẹ và đeo cả ngày không đau tai.', 5),
(2, 'Trải nghiệm Keychron K2: Bàn phím cơ văn phòng', 'Cảm giác gõ phím rất đầm tay, kết nối Bluetooth chuyển đổi nhanh giữa điện thoại và máy tính, thời lượng pin rất trâu.', 5);

