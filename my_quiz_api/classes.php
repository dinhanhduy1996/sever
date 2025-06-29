<?php
// classes.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php'; // Đảm bảo đường dẫn đúng

$response = array();

// Lấy danh sách lớp học từ bảng 'classes'
$sql = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        $classes = array();
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách lớp học thành công.';
        $response['data'] = $classes;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy lớp học nào.';
        $response['data'] = []; // Trả về mảng rỗng nếu không có lớp học
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Lỗi truy vấn database: ' . $conn->error;
}

$conn->close();
echo json_encode($response);
?>