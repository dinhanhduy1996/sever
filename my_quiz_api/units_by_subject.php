<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$response = array();
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id > 0) {
    $stmt = $conn->prepare("SELECT unit_id, unit_name FROM units WHERE subject_id = ? ORDER BY unit_id ASC");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $units = array();
        while ($row = $result->fetch_assoc()) {
            $units[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy danh sách unit thành công.';
        $response['data'] = $units;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy unit nào cho môn học này.';
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