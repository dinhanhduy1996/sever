<?php
// update_user_vocabulary.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT"); // Cho phép POST và PUT
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit();
}

$action = $data['action'] ?? null; // Để phân biệt các loại update

if ($action === 'update_wrong_marks') {
    $user_vocabulary_id = $data['user_vocabulary_id'] ?? null;
    $wrong_marks_array = $data['wrong_marks'] ?? null; // Sẽ là một mảng string (ví dụ: ["X", "X"])

    if ($user_vocabulary_id && is_array($wrong_marks_array)) {
        // Chuyển mảng wrong_marks thành chuỗi JSON để lưu vào TEXT column
        $wrong_marks_json = json_encode($wrong_marks_array);

        $stmt = $conn->prepare("UPDATE user_vocabularies SET wrong_marks = ? WHERE user_vocabulary_id = ?");
        if ($stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("si", $wrong_marks_json, $user_vocabulary_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật wrong_marks thành công.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật wrong_marks: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Thiếu user_vocabulary_id hoặc wrong_marks không hợp lệ.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ hoặc thiếu.']);
}

$conn->close();
?>
