    <?php
    // get_admin_info.php

    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    include 'db_connect.php';

    $response = array();

    // Tìm student_id của tài khoản có username là 'admin'
    $stmt = $conn->prepare("SELECT student_id, student_name FROM students WHERE username = 'admin' LIMIT 1");
    if ($stmt === false) {
        $response['status'] = 'error';
        $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        echo json_encode($response);
        $conn->close();
        exit();
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin_info = $result->fetch_assoc();
        $response['status'] = 'success';
        $response['message'] = 'Lấy thông tin admin thành công.';
        $response['data'] = $admin_info;
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Không tìm thấy tài khoản admin.';
        $response['data'] = [];
    }
    $stmt->close();

    $conn->close();
    echo json_encode($response);
    ?>
    