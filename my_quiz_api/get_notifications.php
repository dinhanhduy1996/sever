<?php
// get_notifications.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include 'db_connect.php';

$response = array();

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$unread_only = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;

if ($student_id > 0) {
    $sql = "SELECT notification_id, student_id, message, type, is_read, created_at FROM notifications WHERE student_id = ?";
    if ($unread_only) {
        $sql .= " AND is_read = 0";
    }
    $sql .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $notifications = [];
        while($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $response['status'] = 'success';
        $response['message'] = 'Lấy thông báo thành công.';
        $response['data'] = $notifications;
    } else {
        $response['status'] = 'success';
        $response['message'] = 'Không có thông báo nào.';
        $response['data'] = [];
    }
    $stmt->close();
} else {
    $response['status'] = 'error';
    $response['message'] = 'Thiếu student_id.';
}

$conn->close();
echo json_encode($response);
?>
