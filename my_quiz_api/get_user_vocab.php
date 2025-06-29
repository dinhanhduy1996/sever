<?php
// get_user_vocab.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$response = array();

// Lấy student_id, class_id, subject_id, unit_id từ query string
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

if ($student_id > 0) {
    $sql = "SELECT user_vocabulary_id, student_id, english_word, vietnamese_meaning, wrong_marks, class_id, subject_id, unit_id FROM user_vocabularies WHERE student_id = ?";
    $params = [$student_id];
    $types = "i";

    // Thêm điều kiện lọc nếu các ID được cung cấp
    if ($class_id > 0) {
        $sql .= " AND class_id = ?";
        $params[] = $class_id;
        $types .= "i";
    }
    if ($subject_id > 0) {
        $sql .= " AND subject_id = ?";
        $params[] = $subject_id;
        $types .= "i";
    }
    if ($unit_id > 0) {
        $sql .= " AND unit_id = ?";
        $params[] = $unit_id;
        $types .= "i";
    }

    $sql .= " ORDER BY english_word ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }

    // Bind tham số động
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vocabularies = [];
        while($row = $result->fetch_assoc()) {
            // Đảm bảo wrong_marks luôn là JSON string hợp lệ hoặc '[]'
            $row['wrong_marks'] = $row['wrong_marks'] ?? '[]'; // Nếu null, đặt thành chuỗi JSON rỗng
            $vocabularies[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy từ vựng cá nhân thành công.';
        $response['data'] = $vocabularies;
    } else {
        $response['status'] = 'success'; // Vẫn là success nếu không có dữ liệu, chỉ message khác
        $response['message'] = 'Không có từ vựng cá nhân nào phù hợp với điều kiện lọc.';
        $response['data'] = [];
    }
    $stmt->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Thiếu student_id.';
}

$conn->close();
echo json_encode($response);
?>
