<?php
// BẬT HIỂN THỊ LỖI CHI TIẾT (CHỈ DÙNG TRONG MÔI TRƯỜNG PHÁT TRIỂN/DEBUG)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php'; // Đảm bảo đường dẫn đến file kết nối DB của bạn là đúng

// Lấy student_id từ query string (ví dụ: /get_quiz_history.php?student_id=1)
if (isset($_GET['student_id'])) {
    $studentId = intval($_GET['student_id']);

    // Mảng để lưu trữ toàn bộ lịch sử dò bài
    $history = [];

    // Bước 1: Lấy các bản ghi quiz_attempts (lịch sử dò bài tổng quát)
    // SỬA ĐỔI: Sử dụng LEFT JOIN để bao gồm các bài dò cá nhân (unit_id IS NULL)
    $quizAttemptsQuery = "SELECT
                                qa.attempt_id,
                                qa.quiz_date,
                                qa.correct_answers,
                                qa.wrong_answers,
                                qa.total_questions_quizzed,
                                u.unit_name,    -- Có thể là NULL nếu là quiz cá nhân
                                s.subject_name  -- Có thể là NULL nếu là quiz cá nhân (do unit_name NULL)
                            FROM
                                quiz_attempts qa
                            LEFT JOIN   -- ĐÃ SỬA ĐỔI: Dùng LEFT JOIN
                                units u ON qa.unit_id = u.unit_id
                            LEFT JOIN   -- ĐÃ SỬA ĐỔI: Dùng LEFT JOIN
                                subjects s ON u.subject_id = s.subject_id
                            WHERE
                                qa.student_id = ?
                            ORDER BY
                                qa.quiz_date DESC"; // Sắp xếp từ mới nhất đến cũ nhất

    $stmtAttempts = $conn->prepare($quizAttemptsQuery);

    // Thêm kiểm tra lỗi cho prepare của truy vấn quizAttemptsQuery
    if ($stmtAttempts === false) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi SQL khi chuẩn bị truy vấn quiz_attempts: ' . $conn->error]);
        die();
    }

    $stmtAttempts->bind_param("i", $studentId);
    $stmtAttempts->execute();
    $resultAttempts = $stmtAttempts->get_result();

    if ($resultAttempts->num_rows > 0) {
        while ($attempt = $resultAttempts->fetch_assoc()) {
            $attemptId = $attempt['attempt_id'];

            // Bước 2: Với mỗi attempt, lấy các từ sai chi tiết từ wrong_answers
            // SỬA ĐỔI: Sử dụng UNION ALL để lấy từ sai từ cả vocabulary và user_vocabularies
            // Đảm bảo cột ID được đặt tên là 'id' để khớp với Flutter model
            $wrongWordsQuery = "
                SELECT
                    wa.vocabulary_id AS id,            -- ID từ vựng chung
                    v.english_word,
                    v.vietnamese_meaning AS correct_meaning
                FROM
                    wrong_answers wa
                JOIN
                    vocabulary v ON wa.vocabulary_id = v.vocabulary_id
                WHERE
                    wa.attempt_id = ? AND wa.vocabulary_id IS NOT NULL

                UNION ALL

                SELECT
                    wa.user_vocabulary_id AS id,       -- ID từ vựng cá nhân
                    uv.english_word,
                    uv.vietnamese_meaning AS correct_meaning
                FROM
                    wrong_answers wa
                JOIN
                    user_vocabularies uv ON wa.user_vocabulary_id = uv.user_vocabulary_id
                WHERE
                    wa.attempt_id = ? AND wa.user_vocabulary_id IS NOT NULL
            ";
            $stmtWrongWords = $conn->prepare($wrongWordsQuery);

            // Thêm kiểm tra lỗi cho prepare của truy vấn wrongWordsQuery
            if ($stmtWrongWords === false) {
                echo json_encode(['status' => 'error', 'message' => 'Lỗi SQL khi chuẩn bị truy vấn wrong_answers: ' . $conn->error]);
                die(); // Dừng script để dễ dàng debug
            }

            // Bind param cho cả hai phần của UNION ALL
            $stmtWrongWords->bind_param("ii", $attemptId, $attemptId);
            $stmtWrongWords->execute();
            $resultWrongWords = $stmtWrongWords->get_result();

            $wrongWords = [];
            while ($wrongWord = $resultWrongWords->fetch_assoc()) {
                $wrongWords[] = [
                    'id' => $wrongWord['id'], // Trả về ID chung (từ vocab_id hoặc user_vocab_id)
                    'english_word' => $wrongWord['english_word'],
                    'correct_meaning' => $wrongWord['correct_meaning']
                ];
            }
            $stmtWrongWords->close();

            // Thêm thông tin từ sai vào bản ghi lịch sử
            $attempt['wrong_words_details'] = $wrongWords;
            // Xử lý trường hợp unit_name hoặc subject_name là null cho hiển thị
            $attempt['unit_name'] = $attempt['unit_name'] ?? 'Từ vựng cá nhân';
            $attempt['subject_name'] = $attempt['subject_name'] ?? 'Không xác định';

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
