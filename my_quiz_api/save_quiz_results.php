<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php'; // Đảm bảo đường dẫn đến file kết nối DB của bạn là đúng

// Thêm các dòng này để hiển thị lỗi PHP nếu có (chỉ dùng trong môi trường dev)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit();
}

$studentId = $data['student_id'] ?? null;
$unitId = $data['unit_id'] ?? null;
$totalQuestionsQuizzed = $data['total_questions_quizzed'] ?? null;
$correctAnswers = $data['correct_answers'] ?? null;
$wrongAnswers = $data['wrong_answers'] ?? null;
$wrongWords = $data['wrong_words'] ?? []; // Mảng chứa các từ sai: [{'vocabulary_id': id, 'student_answer': 'meaning'}]

if ($studentId === null || $unitId === null || $totalQuestionsQuizzed === null || $correctAnswers === null || $wrongAnswers === null) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu cần thiết.']);
    exit();
}

$conn->begin_transaction(); // Bắt đầu transaction

try {
    // 1. Chèn bản ghi vào bảng quiz_attempts
    $insertAttemptQuery = "INSERT INTO quiz_attempts (student_id, unit_id, correct_answers, wrong_answers, total_questions_quizzed, quiz_date)
                           VALUES (?, ?, ?, ?, ?, NOW())"; // NOW() để lấy thời gian hiện tại của server

    $stmtAttempt = $conn->prepare($insertAttemptQuery);
    // SỬA ĐỔI Ở ĐÂY: "iiiii" vì có 5 tham số kiểu integer
    $stmtAttempt->bind_param("iiiii", $studentId, $unitId, $correctAnswers, $wrongAnswers, $totalQuestionsQuizzed);
    $stmtAttempt->execute();

    $attemptId = $conn->insert_id; // Lấy attempt_id vừa được tạo tự động

    // 2. Chèn các từ sai vào bảng wrong_answers
    if (!empty($wrongWords)) {
        $insertWrongWordQuery = "INSERT INTO wrong_answers (attempt_id, vocabulary_id, student_answer) VALUES (?, ?, ?)";
        $stmtWrongWord = $conn->prepare($insertWrongWordQuery);

        foreach ($wrongWords as $word) {
            $vocabId = $word['vocabulary_id'] ?? null;
            $studentAnswer = $word['student_answer'] ?? null;

            if ($vocabId !== null && $studentAnswer !== null) {
                $stmtWrongWord->bind_param("iis", $attemptId, $vocabId, $studentAnswer);
                $stmtWrongWord->execute();
            }
        }
        $stmtWrongWord->close();
    }

    $conn->commit(); // Commit transaction nếu mọi thứ thành công
    echo json_encode(['status' => 'success', 'message' => 'Kết quả dò bài đã được lưu thành công.']);

} catch (mysqli_sql_exception $e) {
    $conn->rollback(); // Rollback nếu có lỗi xảy ra
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi lưu kết quả dò bài: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>