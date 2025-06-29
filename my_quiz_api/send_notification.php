<?php
// send_notification.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit();
}

$student_id = $data['admin_id'] ?? null; // ID của admin nhận thông báo (đã đổi tên từ 'student_id' trong Flutter thành 'admin_id' ở đây cho rõ nghĩa)
$message = $data['message'] ?? null;
$type = $data['type'] ?? 'general';

if ($student_id && $message) {
    $stmt = $conn->prepare("INSERT INTO notifications (student_id, message, type) VALUES (?, ?, ?)");
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param("iss", $student_id, $message, $type);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Thông báo đã được gửi.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi gửi thông báo: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu ID người nhận hoặc nội dung thông báo.']);
}

$conn->close();
?>