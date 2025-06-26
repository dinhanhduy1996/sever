<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php'; // Đảm bảo đường dẫn đến file kết nối DB của bạn là đúng

// Lấy student_id từ query string (ví dụ: /get_quiz_history.php?student_id=1)
if (isset($_GET['student_id'])) {
    $studentId = intval($_GET['student_id']);

    // Bước 1: Lấy các bản ghi quiz_attempts (lịch sử dò bài tổng quát)
    $quizAttemptsQuery = "SELECT
                            qa.attempt_id,
                            qa.quiz_date,
                            qa.correct_answers,
                            qa.wrong_answers,
                            qa.total_questions_quizzed,
                            u.unit_name,
                            s.subject_name
                          FROM
                            quiz_attempts qa
                          JOIN
                            units u ON qa.unit_id = u.unit_id
                          JOIN
                            subjects s ON u.subject_id = s.subject_id
                          WHERE
                            qa.student_id = ?
                          ORDER BY
                            qa.quiz_date DESC"; // Sắp xếp từ mới nhất đến cũ nhất

    $stmtAttempts = $conn->prepare($quizAttemptsQuery);
    $stmtAttempts->bind_param("i", $studentId);
    $stmtAttempts->execute();
    $resultAttempts = $stmtAttempts->get_result();

    $history = [];
    if ($resultAttempts->num_rows > 0) {
        while ($attempt = $resultAttempts->fetch_assoc()) {
            $attemptId = $attempt['attempt_id'];

            // Bước 2: Với mỗi attempt, lấy các từ sai chi tiết từ wrong_answers
            $wrongWordsQuery = "SELECT
                                    wa.vocabulary_id,
                                    v.english_word,
                                    v.correct_meaning
                                FROM
                                    wrong_answers wa
                                JOIN
                                    vocabulary v ON wa.vocabulary_id = v.vocabulary_id
                                WHERE
                                    wa.attempt_id = ?";
            $stmtWrongWords = $conn->prepare($wrongWordsQuery);
            $stmtWrongWords->bind_param("i", $attemptId);
            $stmtWrongWords->execute();
            $resultWrongWords = $stmtWrongWords->get_result();

            $wrongWords = [];
            while ($wrongWord = $resultWrongWords->fetch_assoc()) {
                $wrongWords[] = [
                    'vocabulary_id' => $wrongWord['vocabulary_id'],
                    'english_word' => $wrongWord['english_word'],
                    'correct_meaning' => $wrongWord['correct_meaning']
                ];
            }
            $stmtWrongWords->close();

            // Thêm thông tin từ sai vào bản ghi lịch sử
            $attempt['wrong_words_details'] = $wrongWords;
            $history[] = $attempt;
        }
    }
    $stmtAttempts->close();
    $conn->close();

    echo json_encode(['status' => 'success', 'data' => $history]);

} else {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu student_id.']);
}
?>