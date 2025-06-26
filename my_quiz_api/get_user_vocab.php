<?php
// get_user_vocab.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php'; // Đảm bảo đường dẫn đúng

$response = array();

if (isset($_GET['student_id'])) {
    $student_id = intval($_GET['student_id']);

    if ($student_id <= 0) {
        $response = ['status' => 'error', 'message' => 'ID học sinh không hợp lệ.'];
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("SELECT english_word, vietnamese_meaning FROM user_vocabularies WHERE student_id = ? ORDER BY english_word ASC");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vocabularies = [];
        while($row = $result->fetch_assoc()) {
            $vocabularies[] = $row;
        }
        $response = ['status' => 'success', 'data' => $vocabularies];
    } else {
        $response = ['status' => 'success', 'message' => 'Không tìm thấy từ vựng nào cho học sinh này.', 'data' => []];
    }
    $stmt->close();
} else {
    $response = ['status' => 'error', 'message' => 'Thiếu ID học sinh.'];
}

$conn->close();
echo json_encode($response);
?>