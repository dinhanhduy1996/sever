<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$response = array();
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->student_id) && !empty($data->vocabulary_id) && isset($data->chosen_meaning)) {
    $student_id = intval($data->student_id);
    $vocabulary_id = intval($data->vocabulary_id);
    $chosen_meaning = $conn->real_escape_string($data->chosen_meaning);

    // Lấy đáp án đúng từ database
    $stmt_correct = $conn->prepare("SELECT vietnamese_meaning FROM vocabulary WHERE vocabulary_id = ?");
    $stmt_correct->bind_param("i", $vocabulary_id);
    $stmt_correct->execute();
    $result_correct = $stmt_correct->get_result();
    $correct_word_row = $result_correct->fetch_assoc();
    $stmt_correct->close();

    if ($correct_word_row) {
        $correct_meaning = $correct_word_row['vietnamese_meaning'];
        $is_correct = ($chosen_meaning == $correct_meaning) ? 1 : 0; // 1 nếu đúng, 0 nếu sai

        // Lưu kết quả vào bảng student_vocabulary_answers
        $stmt_insert = $conn->prepare("INSERT INTO student_vocabulary_answers (student_id, vocabulary_id, chosen_meaning, is_correct) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("iisi", $student_id, $vocabulary_id, $chosen_meaning, $is_correct);

        if ($stmt_insert->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Đáp án từ vựng đã được ghi nhận.';
            $response['is_correct'] = (bool)$is_correct;
            $response['correct_meaning'] = $correct_meaning;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Không thể lưu đáp án: ' . $stmt_insert->error;
        }
        $stmt_insert->close();
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy từ vựng.';
    }
} else {
    $response['status'] = 'error';
    $response['message'] = 'Thiếu thông tin đáp án (student_id, vocabulary_id, chosen_meaning).';
}

$conn->close();
echo json_encode($response);
?>