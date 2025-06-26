<?php
// Cho phép các yêu cầu từ mọi nguồn
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
include 'db_connect.php';

$response = array();

// Lấy dữ liệu từ body của request
$data = json_decode(file_get_contents("php://input"));

// Kiểm tra xem có đủ username và password không
if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;

    // 1. Chuẩn bị câu lệnh SQL để tìm học sinh theo username
    $stmt = $conn->prepare("SELECT student_id, student_name, username, password_hash FROM students WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        // 2. Tìm thấy học sinh, lấy thông tin
        $student = $result->fetch_assoc();
        $hashed_password_from_db = $student['password_hash'];

        // 3. Kiểm tra mật khẩu bằng password_verify()
        if (password_verify($password, $hashed_password_from_db)) {
            // Đăng nhập thành công!
            $response['status'] = 'success';
            $response['message'] = 'Đăng nhập thành công!';
            $response['student_info'] = array(
                'student_id' => $student['student_id'],
                'student_name' => $student['student_name'],
                'username' => $student['username']
                // KHÔNG BAO GỒM password_hash HOẶC BẤT KỲ THÔNG TIN NHẠY CẢM NÀO KHÁC
            );
            // *** TRONG ỨNG DỤNG THỰC TẾ, BẠN SẼ TẠO VÀ GỬI MỘT JWT TOKEN Ở ĐÂY ***
            // *** ĐỂ CLIENT DÙNG CHO CÁC YÊU CẦU TIẾP THEO CẦN XÁC THỰC ***
        } else {
            // Mật khẩu không đúng
            $response['status'] = 'error';
            $response['message'] = 'Mật khẩu không đúng.';
        }
    } else {
        // Tên đăng nhập không tồn tại
        $response['status'] = 'error';
        $response['message'] = 'Tên đăng nhập không tồn tại.';
    }

    $stmt->close(); // Đóng prepared statement
} else {
    // Dữ liệu không đủ
    $response['status'] = 'error';
    $response['message'] = 'Thiếu tên đăng nhập hoặc mật khẩu.';
}

// Đóng kết nối database
$conn->close();

// Trả về kết quả dưới dạng JSON cho Flutter
echo json_encode($response);
?>