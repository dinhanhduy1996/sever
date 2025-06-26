<?php
// Cho phép các yêu cầu từ mọi nguồn (quan trọng khi phát triển cục bộ từ Flutter)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST"); // Chỉ chấp nhận phương thức POST
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
include 'db_connect.php';

$response = array(); // Khởi tạo mảng để chứa dữ liệu trả về

// Lấy dữ liệu gửi từ Flutter (dưới dạng JSON trong body của request)
$data = json_decode(file_get_contents("php://input"));

// Kiểm tra xem dữ liệu có đủ không
if (!empty($data->student_name) && !empty($data->username) && !empty($data->password)) {
    $student_name = $data->student_name;
    $username = $data->username;
    $password = $data->password;

    // 1. Kiểm tra tên đăng nhập đã tồn tại chưa
    $stmt_check = $conn->prepare("SELECT student_id FROM students WHERE username = ?");
    $stmt_check->bind_param("s", $username); // "s" nghĩa là biến username là string
    $stmt_check->execute();
    $stmt_check->store_result(); // Lưu kết quả để kiểm tra số dòng

    if ($stmt_check->num_rows > 0) {
        // Tên đăng nhập đã tồn tại
        $response['status'] = 'error';
        $response['message'] = 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
    } else {
        // 2. Hash mật khẩu trước khi lưu vào database
        // PASSWORD_DEFAULT sử dụng thuật toán băm mạnh mẽ (bcrypt) và tự động quản lý salt
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // 3. Chèn dữ liệu học sinh mới vào database
        $stmt_insert = $conn->prepare("INSERT INTO students (student_name, username, password_hash) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("sss", $student_name, $username, $hashed_password); // "sss" là 3 biến kiểu string

        if ($stmt_insert->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Đăng ký tài khoản thành công!';
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Đăng ký thất bại: ' . $stmt_insert->error;
        }
        $stmt_insert->close(); // Đóng prepared statement
    }
    $stmt_check->close(); // Đóng prepared statement kiểm tra
} else {
    // Dữ liệu không đủ
    $response['status'] = 'error';
    $response['message'] = 'Thiếu thông tin đăng ký (họ tên, tên đăng nhập hoặc mật khẩu).';
}

// Đóng kết nối database
$conn->close();

// Trả về kết quả dưới dạng JSON cho Flutter
echo json_encode($response);
?>