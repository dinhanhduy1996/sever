<?php
// Cho phép các yêu cầu từ mọi nguồn
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET"); // Chỉ chấp nhận GET
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
include 'db_connect.php';

$response = array();

// Lấy danh sách môn học từ bảng 'subjects'
$sql = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC";
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        $subjects = array();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách môn học thành công.';
        $response['data'] = $subjects;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy môn học nào.';
        $response['data'] = []; // Trả về mảng rỗng nếu không có môn học
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Lỗi truy vấn database: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>