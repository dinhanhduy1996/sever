<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$response = array();
$unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;
$max_words = isset($_GET['max_words']) ? intval($_GET['max_words']) : 0; // Số từ tối đa yêu cầu

if ($unit_id <= 0) {
    $response['status'] = 'error';
    $response['message'] = 'Thiếu ID Unit.';
    echo json_encode($response);
    exit();
}

$words = [];
$total_words_in_unit = 0;

// Lấy tổng số từ trong unit
$stmt_count = $conn->prepare("SELECT COUNT(*) AS total FROM vocabulary WHERE unit_id = ?");
$stmt_count->bind_param("i", $unit_id);
$stmt_count->execute();
$result_count = $stmt_count->get_result();
if ($row_count = $result_count->fetch_assoc()) {
    $total_words_in_unit = $row_count['total'];
}
$stmt_count->close();

// Xác định số lượng từ sẽ lấy
$limit_clause = "";
if ($max_words > 0 && $max_words < $total_words_in_unit) {
    $limit_clause = "LIMIT ?"; // Nếu max_words nhỏ hơn tổng số từ, thì giới hạn
}


// Lấy các từ vựng cho bài dò
// Cần lấy cả 4 đáp án ngẫu nhiên (bao gồm 1 đáp án đúng)
$stmt = null;
if (!empty($limit_clause)) {
    // Lấy số lượng giới hạn và ngẫu nhiên
    $stmt = $conn->prepare("SELECT vocabulary_id, english_word, vietnamese_meaning FROM vocabulary WHERE unit_id = ? ORDER BY RAND() $limit_clause");
    $stmt->bind_param("ii", $unit_id, $max_words);
} else {
    // Lấy tất cả từ trong unit nếu không giới hạn hoặc max_words lớn hơn tổng số từ
    $stmt = $conn->prepare("SELECT vocabulary_id, english_word, vietnamese_meaning FROM vocabulary WHERE unit_id = ? ORDER BY RAND()");
    $stmt->bind_param("i", $unit_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $all_meanings = []; // Để tạo các đáp án sai ngẫu nhiên
    $temp_stmt = $conn->prepare("SELECT vietnamese_meaning FROM vocabulary WHERE unit_id = ?");
    $temp_stmt->bind_param("i", $unit_id);
    $temp_stmt->execute();
    $temp_result = $temp_stmt->get_result();
    while ($row = $temp_result->fetch_assoc()) {
        $all_meanings[] = $row['vietnamese_meaning'];
    }
    $temp_stmt->close();


    while ($row = $result->fetch_assoc()) {
        $correct_meaning = $row['vietnamese_meaning'];
        $options = [$correct_meaning]; // Bắt đầu với đáp án đúng

        // Lấy 3 đáp án sai ngẫu nhiên
        $incorrect_meanings = array_diff($all_meanings, [$correct_meaning]); // Lấy tất cả trừ đáp án đúng
        shuffle($incorrect_meanings); // Xáo trộn để lấy ngẫu nhiên
        $incorrect_meanings = array_slice($incorrect_meanings, 0, 3); // Lấy 3 cái đầu tiên

        $options = array_merge($options, $incorrect_meanings);
        shuffle($options); // Xáo trộn tất cả các đáp án

        $words[] = [
            'vocabulary_id' => $row['vocabulary_id'],
            'english_word' => $row['english_word'],
            'correct_meaning' => $correct_meaning, // Thêm đáp án đúng vào để Flutter kiểm tra
            'options' => $options // 4 đáp án đã được xáo trộn
        ];
    }
    $response['status'] = 'success';
    $response['message'] = 'Lấy từ vựng dò bài thành công.';
    $response['data'] = $words;
    $response['total_words_in_unit'] = $total_words_in_unit;
    $response['words_to_quiz'] = count($words);
} else {
    $response['status'] = 'error';
    $response['message'] = 'Không tìm thấy từ vựng cho unit này.';
    $response['data'] = [];
    $response['total_words_in_unit'] = $total_words_in_unit;
    $response['words_to_quiz'] = 0;
}

$stmt->close();
$conn->close();
echo json_encode($response);
?>