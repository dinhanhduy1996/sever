<?php
// Thông tin kết nối database
$servername = "localhost"; // Máy chủ MySQL
$username = "root";        // Tên người dùng MySQL (mặc định XAMPP là root)
$password = "";            // Mật khẩu MySQL (mặc định XAMPP là rỗng)
$dbname = "quiz_app_db";   // Tên database bạn đã tạo

// Tạo kết nối bằng MySQLi (hoặc PDO nếu bạn muốn chuyên nghiệp hơn)
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    // Trả về lỗi dưới dạng JSON nếu không thể kết nối database
    http_response_code(500); // Internal Server Error
    echo json_encode(array("status" => "error", "message" => "Lỗi kết nối database: " . $conn->connect_error));
    die(); // Dừng thực thi script
}

// Thiết lập charset để hỗ trợ tiếng Việt và các ký tự đặc biệt
$conn->set_charset("utf8mb4");

// Lưu ý: Kết nối sẽ được đóng ở cuối mỗi API script.
?>