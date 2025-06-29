<?php
// save_quiz_results.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db_connect.php'; 

$data = json_decode(file_get_contents("php://input"), true);

if ($data === null) {
    echo json_encode(['status' => 'error', 'message' => 'Dữ liệu JSON không hợp lệ.']);
    exit();
}

$student_id = $data['student_id'] ?? null;
$unit_id = $data['unit_id'] ?? null; 
$total_questions_quizzed = $data['total_questions_quizzed'] ?? null;
$correct_answers = $data['correct_answers'] ?? null;
$wrong_answers_count = $data['wrong_answers'] ?? 0;
$wrong_words_data = $data['wrong_words'] ?? []; // Mảng chứa các từ sai chi tiết từ Flutter

if ($student_id === null || $total_questions_quizzed === null || $correct_answers === null || !is_array($wrong_words_data)) {
    echo json_encode(['status' => 'error', 'message' => 'Thiếu dữ liệu cần thiết (student_id, total_questions_quizzed, correct_answers, wrong_words_data).']);
    exit();
}

$conn->begin_transaction();

$errors_during_wrong_word_save = [];

try {
    // 1. Chèn bản ghi vào bảng quiz_attempts
    $insertAttemptQuery = "INSERT INTO quiz_attempts (student_id, unit_id, correct_answers, wrong_answers, total_questions_quizzed, quiz_date)
                            VALUES (?, ?, ?, ?, ?, NOW())";

    $stmtAttempt = $conn->prepare($insertAttemptQuery);
    if ($stmtAttempt === false) {
        throw new Exception("Lỗi chuẩn bị câu lệnh INSERT quiz_attempts: " . $conn->error);
    }
    $stmtAttempt->bind_param("iiiii", $student_id, $unit_id, $correct_answers, $wrong_answers_count, $total_questions_quizzed);
    
    if (!$stmtAttempt->execute()) {
        throw new Exception("Lỗi khi lưu kết quả bài dò vào quiz_attempts: " . $stmtAttempt->error);
    }
    $attemptId = $conn->insert_id;
    $stmtAttempt->close();

    // 2. Chèn các từ sai vào bảng wrong_answers và cập nhật wrong_marks cho từ vựng cá nhân
    if (!empty($wrong_words_data)) {
        $insertWrongWordQuery = "INSERT INTO wrong_answers (attempt_id, vocabulary_id, user_vocabulary_id, student_answer) VALUES (?, ?, ?, ?)";
        $stmtWrongWord = $conn->prepare($insertWrongWordQuery);
        if ($stmtWrongWord === false) {
            throw new Exception("Lỗi chuẩn bị câu lệnh INSERT wrong_answers: " . $conn->error);
        }

        foreach ($wrong_words_data as $word) {
            $vocabId = $word['vocabulary_id'] ?? null;
            $userVocabId = $word['user_vocabulary_id'] ?? null;
            $studentAnswer = $word['student_answer'] ?? null;

            if (($vocabId === null || $vocabId <= 0) && ($userVocabId === null || $userVocabId <= 0)) {
                $errors_during_wrong_word_save[] = "Từ sai không có ID từ vựng hợp lệ (vocab_id hoặc user_vocab_id): " . json_encode($word);
                continue;
            }
            
            $vocabId = ($vocabId !== null && $vocabId > 0) ? intval($vocabId) : null;
            $userVocabId = ($userVocabId !== null && $userVocabId > 0) ? intval($userVocabId) : null;

            $stmtWrongWord->bind_param("iiis", $attemptId, $vocabId, $userVocabId, $studentAnswer);
            
            if (!$stmtWrongWord->execute()) {
                $errors_during_wrong_word_save[] = "Lỗi khi lưu từ sai vào wrong_answers (attempt_id: $attemptId, vocab_id: $vocabId, user_vocab_id: $userVocabId): " . $stmtWrongWord->error;
            }

            // MỚI: Cập nhật wrong_marks cho từ vựng cá nhân nếu từ này là từ vựng cá nhân
            if ($userVocabId !== null) {
                // Lấy wrong_marks hiện tại
                $selectWrongMarksStmt = $conn->prepare("SELECT wrong_marks FROM user_vocabularies WHERE user_vocabulary_id = ?");
                if ($selectWrongMarksStmt === false) {
                    $errors_during_wrong_word_save[] = "Lỗi chuẩn bị câu lệnh SELECT wrong_marks: " . $conn->error;
                    continue;
                }
                $selectWrongMarksStmt->bind_param("i", $userVocabId);
                $selectWrongMarksStmt->execute();
                $resultWrongMarks = $selectWrongMarksStmt->get_result();
                $currentWrongMarks = []; // Mặc định là mảng rỗng
                if ($resultWrongMarks->num_rows > 0) {
                    $row = $resultWrongMarks->fetch_assoc();
                    $jsonString = $row['wrong_marks'];
                    // Kiểm tra và parse JSON
                    if ($jsonString !== null && $jsonString !== '') {
                        $decoded = json_decode($jsonString, true);
                        if (is_array($decoded)) {
                            $currentWrongMarks = $decoded;
                        }
                    }
                }
                $selectWrongMarksStmt->close();

                // Thêm một dấu 'X' mới vào ĐẦU mảng
                array_unshift($currentWrongMarks, 'X');
                
                // Giới hạn số lượng 'X' tối đa là 4
                if (count($currentWrongMarks) > 4) {
                    $currentWrongMarks = array_slice($currentWrongMarks, 0, 4);
                }

                $updatedWrongMarksJson = json_encode($currentWrongMarks);

                // Cập nhật lại wrong_marks vào database
                $updateWrongMarksStmt = $conn->prepare("UPDATE user_vocabularies SET wrong_marks = ? WHERE user_vocabulary_id = ?");
                if ($updateWrongMarksStmt === false) {
                    $errors_during_wrong_word_save[] = "Lỗi chuẩn bị câu lệnh UPDATE wrong_marks: " . $conn->error;
                    continue;
                }
                $updateWrongMarksStmt->bind_param("si", $updatedWrongMarksJson, $userVocabId);
                if (!$updateWrongMarksStmt->execute()) {
                    $errors_during_wrong_word_save[] = "Lỗi khi cập nhật wrong_marks cho user_vocabulary_id $userVocabId: " . $updateWrongMarksStmt->error;
                }
                $updateWrongMarksStmt->close();
            }
        }
        $stmtWrongWord->close();
    }

    // 3. Gửi thông báo đến tài khoản admin nếu là tài khoản 'admin' (username = 'admin')
    $admin_id = null;
    $stmtAdmin = $conn->prepare("SELECT student_id FROM students WHERE username = 'admin' LIMIT 1");
    if ($stmtAdmin) {
        $stmtAdmin->execute();
        $resultAdmin = $stmtAdmin->get_result();
        if ($resultAdmin->num_rows > 0) {
            $admin_row = $resultAdmin->fetch_assoc();
            $admin_id = $admin_row['student_id'];
        }
        $stmtAdmin->close();
    }
    
    if ($admin_id !== null) {
        $student_name = "";
        $stmtStudentName = $conn->prepare("SELECT student_name FROM students WHERE student_id = ? LIMIT 1");
        if ($stmtStudentName) {
            $stmtStudentName->bind_param("i", $student_id);
            $stmtStudentName->execute();
            $resultStudentName = $stmtStudentName->get_result();
            if ($resultStudentName->num_rows > 0) {
                $student_row = $resultStudentName->fetch_assoc();
                $student_name = $student_row['student_name'];
            }
            $stmtStudentName->close();
        }

        $notification_message = "Học sinh $student_name vừa hoàn thành bài dò bài. Số câu đúng: $correct_answers, số câu sai: $wrong_answers_count.";
        $notification_type = "quiz_result";

        $stmtNotify = $conn->prepare("INSERT INTO notifications (student_id, message, type, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmtNotify === false) {
            $errors_during_wrong_word_save[] = "Lỗi chuẩn bị câu lệnh INSERT notifications: " . $conn->error;
        } else {
            $stmtNotify->bind_param("iss", $admin_id, $notification_message, $notification_type);
            if (!$stmtNotify->execute()) {
                $errors_during_wrong_word_save[] = "Lỗi khi gửi thông báo cho admin: " . $stmtNotify->error;
            }
            $stmtNotify->close();
        }
    }


    $conn->commit();
    
    $response_message = 'Kết quả dò bài đã được lưu thành công.';
    if (!empty($errors_during_wrong_word_save)) {
        $response_message .= ' Tuy nhiên, có lỗi khi lưu chi tiết một số từ sai hoặc cập nhật dấu X: ' . implode('; ', $errors_during_wrong_word_save);
        echo json_encode(['status' => 'warning', 'message' => $response_message, 'details' => $errors_during_wrong_word_save]);
    } else {
        echo json_encode(['status' => 'success', 'message' => $response_message]);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi lưu kết quả dò bài: ' . $e->getMessage()]);
} finally {
    if ($conn && !$conn->connect_error) {
        $conn->close();
    }
}
?>
