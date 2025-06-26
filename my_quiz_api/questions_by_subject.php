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

// Lấy subject_id từ tham số URL (query parameter)
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id > 0) {
    // Lấy các câu hỏi thuộc môn học đã chọn
    // Chúng ta chỉ lấy question_id và question_text cho dropdown
    $stmt = $conn->prepare("SELECT question_id, question_text FROM questions WHERE subject_id = ? ORDER BY question_id ASC");
    $stmt->bind_param("i", $subject_id); // "i" nghĩa là tham số là kiểu integer
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $questions = array();
        while ($row = $result->fetch_assoc()) {
            $questions[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách câu hỏi thành công.';
        $response['data'] = $questions;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy câu hỏi nào cho môn học này.';
        $response['data'] = [];
    }
    $stmt->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Thiếu ID môn học.';
}

$conn->close();
echo json_encode($response);
?>