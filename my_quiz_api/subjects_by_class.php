<?php
// subjects_by_class.php

// Cho phép các yêu cầu từ mọi nguồn (quan trọng khi phát triển cục bộ từ Flutter)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET"); // Chỉ chấp nhận phương thức GET
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
include 'db_connect.php'; // Đảm bảo đường dẫn này đúng với vị trí file db_connect.php của bạn

$response = array(); // Khởi tạo mảng để chứa dữ liệu trả về

// Lấy class_id từ tham số URL (query parameter)
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

if ($class_id > 0) {
    // Câu truy vấn SQL để lấy các môn học thuộc lớp đã chọn.
    // GIẢ ĐỊNH: Bạn có một bảng liên kết 'class_subjects' để kết nối 'classes' và 'subjects'.
    // Nếu cấu trúc database của bạn khác (ví dụ: bảng 'subjects' có cột 'class_id'),
    // bạn cần điều chỉnh câu truy vấn SQL này cho phù hợp.
    $stmt = $conn->prepare("SELECT s.subject_id, s.subject_name 
                            FROM subjects s
                            JOIN class_subjects cs ON s.subject_id = cs.subject_id
                            WHERE cs.class_id = ? 
                            ORDER BY s.subject_name ASC");
    
    // Kiểm tra nếu prepare thất bại
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $stmt->bind_param("i", $class_id); // "i" nghĩa là tham số là kiểu integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $subjects = array();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách môn học theo lớp thành công.';
        $response['data'] = $subjects;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy môn học nào cho lớp này.';
        $response['data'] = []; // Trả về mảng rỗng nếu không có môn học
    }
    $stmt->close(); // Đóng prepared statement
} else {
    // Nếu thiếu class_id hoặc class_id không hợp lệ
    $response['status'] = 'error';
    $response['message'] = 'Thiếu ID lớp hoặc ID lớp không hợp lệ.';
}

$conn->close(); // Đóng kết nối database
echo json_encode($response);
?>
