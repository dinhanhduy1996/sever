<?php
// units_by_subject.php

// Cho phép các yêu cầu từ mọi nguồn (quan trọng khi phát triển cục bộ từ Flutter)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET"); // Chỉ chấp nhận phương thức GET
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
include 'db_connect.php'; // Đảm bảo đường dẫn này đúng với vị trí file db_connect.php của bạn

$response = array(); // Khởi tạo mảng để chứa dữ liệu trả về

// Lấy subject_id và class_id từ tham số URL (query parameter)
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0; // Lấy thêm class_id

if ($subject_id > 0 && $class_id > 0) {
    // Câu truy vấn SQL để lấy các units thuộc subject_id VÀ class_id đã chọn.
    $stmt = $conn->prepare("SELECT unit_id, unit_name, subject_id, class_id 
                            FROM units 
                            WHERE subject_id = ? AND class_id = ? 
                            ORDER BY unit_name ASC");
    
    // Kiểm tra nếu prepare thất bại
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $stmt->bind_param("ii", $subject_id, $class_id); // "ii" nghĩa là hai tham số đều là kiểu integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $units = array();
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách units theo môn học và lớp thành công.';
        $response['data'] = $units;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy units nào cho môn học và lớp này.';
        $response['data'] = []; // Trả về mảng rỗng nếu không có units
    }
    $stmt->close(); // Đóng prepared statement
} else {
    // Nếu thiếu subject_id hoặc class_id hoặc không hợp lệ
    $response['status'] = 'error';
    $response['message'] = 'Thiếu ID môn học hoặc ID lớp, hoặc ID không hợp lệ.';
}

$conn->close(); // Đóng kết nối database
echo json_encode($response);
?>
