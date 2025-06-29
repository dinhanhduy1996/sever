<?php
session_start(); // Bắt đầu phiên (session)

// BẬT HIỂN THỊ LỖI PHP (CHỈ DÙNG TRONG MÔI TRƯỜNG PHÁT TRIỂN)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bao gồm file kết nối database
include 'db_connect.php';

$response = array(); // Mảng để lưu trữ phản hồi từ các API endpoint

// Xử lý đăng nhập từ form HTML trên trang này
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action']) && $_POST['login_action'] === 'admin_login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        // Chuẩn bị câu lệnh SQL để tìm học sinh/admin theo username
        $stmt = $conn->prepare("SELECT student_id, student_name, username, password_hash FROM students WHERE username = ?");
        if ($stmt === false) {
            $response['status'] = 'error';
            $response['message'] = 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $hashed_password_from_db = $user['password_hash'];

                // Kiểm tra mật khẩu và đảm bảo đây là tài khoản 'admin'
                if ($user['username'] === 'admin' && password_verify($password, $hashed_password_from_db)) {
                    // Đăng nhập thành công với quyền admin
                    $_SESSION['student_id'] = $user['student_id'];
                    $_SESSION['student_name'] = $user['student_name'];
                    $_SESSION['username'] = $user['username']; // Đặt biến session cho username

                    // Chuyển hướng về trang admin panel (GET request để xóa POST data)
                    header("Location: admin_panel.php");
                    exit();
                } else {
                    $response['status'] = 'error';
                    $response['message'] = 'Tên đăng nhập hoặc mật khẩu không đúng, hoặc bạn không có quyền admin.';
                }
            } else {
                $response['status'] = 'error';
                $response['message'] = 'Tên đăng nhập không tồn tại.';
            }
            $stmt->close();
        }
    } else {
        $response['status'] = 'error';
        $response['message'] = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    }
}


// Cho phép CORS (quan trọng khi gọi từ các nguồn khác)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS"); // Đảm bảo hỗ trợ OPTIONS, PUT, DELETE, OPTIONS (cho preflight requests)
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- PHP API Endpoints ---
// File này sẽ hoạt động như một API endpoint dựa trên tham số 'action' trong GET hoặc POST
// Hoặc phương thức HTTP (PUT/DELETE)

if (isset($_GET['action'])) {
    // !!! QUAN TRỌNG: Đảm bảo chỉ admin mới có thể gọi các API này !!!
    // Nếu không có session admin, từ chối yêu cầu API
    if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(['status' => 'error', 'message' => 'Truy cập API bị từ chối. Vui lòng đăng nhập với tài khoản admin.']);
        exit();
    }

    $action = $_GET['action'];
    header("Content-Type: application/json; charset=UTF-8"); // Đảm bảo trả về JSON cho các API này

    switch ($action) {
        case 'get_students':
            $sql = "SELECT s.student_id, s.student_name, s.username, c.class_name
                    FROM students s
                    LEFT JOIN classes c ON s.class_id = c.class_id
                    ORDER BY s.student_name ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                $students = [];
                while($row = $result->fetch_assoc()) {
                    $students[] = $row;
                }
                echo json_encode(['status' => 'success', 'data' => $students]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy học sinh nào.', 'data' => []]);
            }
            break;

        case 'get_subjects':
            $sql = "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                $subjects = [];
                while($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
                echo json_encode(['status' => 'success', 'data' => $subjects]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy môn học nào.', 'data' => []]);
            }
            break;

        case 'get_subjects_by_class': // Lấy môn học theo lớp (dành cho dropdown lớp/môn)
            $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
            if ($class_id > 0) {
                $stmt = $conn->prepare("SELECT s.subject_id, s.subject_name FROM subjects s JOIN class_subjects cs ON s.subject_id = cs.subject_id WHERE cs.class_id = ? ORDER BY s.subject_name ASC");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $subjects = [];
                    while($row = $result->fetch_assoc()) {
                        $subjects[] = $row;
                    }
                    echo json_encode(['status' => 'success', 'data' => $subjects]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy môn học nào cho lớp này.', 'data' => []]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID lớp.']);
            }
            break;

        case 'get_units_by_subject_and_class': // Lấy units theo Subject VÀ Class
            $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
            $class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

            if ($subject_id > 0 && $class_id > 0) {
                $stmt = $conn->prepare("SELECT unit_id, unit_name FROM units WHERE subject_id = ? AND class_id = ? ORDER BY unit_name ASC");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("ii", $subject_id, $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $units = [];
                    while($row = $result->fetch_assoc()) {
                        $units[] = $row;
                    }
                    echo json_encode(['status' => 'success', 'data' => $units]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy unit nào cho môn học và lớp này.', 'data' => []]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID môn học hoặc ID lớp.']);
            }
            break;

        case 'get_all_units_by_subject': // Action mới để lấy tất cả units theo môn học (không lọc theo lớp)
            $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

            if ($subject_id > 0) {
                $stmt = $conn->prepare("SELECT unit_id, unit_name, class_id FROM units WHERE subject_id = ? ORDER BY unit_name ASC");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("i", $subject_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $units = [];
                    while($row = $result->fetch_assoc()) {
                        $units[] = $row;
                    }
                    echo json_encode(['status' => 'success', 'data' => $units]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy unit nào cho môn học này (tổng thể).', 'data' => []]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID môn học.']);
            }
            break;

        case 'add_unit':
            $input = json_decode(file_get_contents('php://input'), true);
            $unit_name = $input['unit_name'] ?? null;
            $subject_id = $input['subject_id'] ?? null;
            $class_id = $input['class_id'] ?? null;

            if ($unit_name && $subject_id && $class_id) {
                $stmt = $conn->prepare("INSERT INTO units (subject_id, class_id, unit_name) VALUES (?, ?, ?)");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("iis", $subject_id, $class_id, $unit_name);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Unit đã được thêm thành công.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm unit: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu tên unit, ID môn học hoặc ID lớp.']);
            }
            break;

        case 'assign_subject_to_class': // Action: Gán môn học cho lớp
            $input = json_decode(file_get_contents('php://input'), true);
            $subject_id = $input['subject_id'] ?? null;
            $class_id = $input['class_id'] ?? null;

            if ($subject_id && $class_id) {
                // Kiểm tra xem liên kết đã tồn tại chưa
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM class_subjects WHERE class_id = ? AND subject_id = ?");
                if ($check_stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh kiểm tra: ' . $conn->error]);
                    break;
                }
                $check_stmt->bind_param("ii", $class_id, $subject_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row = $check_result->fetch_row();
                $count = $row[0];
                $check_stmt->close();

                if ($count > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Môn học này đã được gán cho lớp này rồi.']);
                } else {
                    // Chèn liên kết mới
                    $insert_stmt = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (?, ?)");
                    if ($insert_stmt === false) {
                        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh chèn: ' . $conn->error]);
                        break;
                    }
                    $insert_stmt->bind_param("ii", $class_id, $subject_id);
                    if ($insert_stmt->execute()) {
                        echo json_encode(['status' => 'success', 'message' => 'Môn học đã được gán cho lớp thành công.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi gán môn học cho lớp: ' . $insert_stmt->error]);
                    }
                    $insert_stmt->close();
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID môn học hoặc ID lớp.']);
            }
            break;

        case 'get_classes':
            $sql = "SELECT class_id, class_name FROM classes ORDER BY class_name ASC";
            $result = $conn->query($sql);
            if ($result && $result->num_rows > 0) {
                $classes = [];
                while($row = $result->fetch_assoc()) {
                    $classes[] = $row;
                }
                echo json_encode(['status' => 'success', 'data' => $classes]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy lớp học nào.', 'data' => []]);
            }
            break;
            
        case 'get_user_vocabulary_details': // Mới: Lấy chi tiết một từ vựng cá nhân để sửa
            $user_vocabulary_id = isset($_GET['user_vocabulary_id']) ? intval($_GET['user_vocabulary_id']) : 0;
            if ($user_vocabulary_id > 0) {
                // ĐÃ THÊM: subject_id
                $stmt = $conn->prepare("SELECT user_vocabulary_id, english_word, vietnamese_meaning, student_id, class_id, subject_id, unit_id FROM user_vocabularies WHERE user_vocabulary_id = ?");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("i", $user_vocabulary_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    echo json_encode(['status' => 'success', 'data' => $result->fetch_assoc()]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy từ vựng cá nhân này.']);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID từ vựng cá nhân.']);
            }
            break;

        case 'get_user_vocabularies':
            $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
            // Lọc theo class_id và unit_id nếu được cung cấp
            $filter_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
            $filter_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0; // MỚI: Thêm filter subject_id
            $filter_unit_id = isset($_GET['unit_id']) ? intval($_GET['unit_id']) : 0;

            if ($student_id > 0) {
                // ĐÃ SỬA: JOIN với subjects để lấy subject_name
                $sql = "SELECT uv.user_vocabulary_id, uv.english_word, uv.vietnamese_meaning, uv.class_id, uv.unit_id, uv.subject_id,
                        c.class_name, u.unit_name, s.subject_name
                        FROM user_vocabularies uv
                        LEFT JOIN classes c ON uv.class_id = c.class_id
                        LEFT JOIN units u ON uv.unit_id = u.unit_id
                        LEFT JOIN subjects s ON uv.subject_id = s.subject_id
                        WHERE uv.student_id = ?";
                $params = [$student_id];
                $types = "i";

                if ($filter_class_id > 0) {
                    $sql .= " AND uv.class_id = ?";
                    $params[] = $filter_class_id;
                    $types .= "i";
                }
                if ($filter_subject_id > 0) { // MỚI: Thêm điều kiện lọc theo subject_id
                    $sql .= " AND uv.subject_id = ?";
                    $params[] = $filter_subject_id;
                    $types .= "i";
                }
                if ($filter_unit_id > 0) {
                    $sql .= " AND uv.unit_id = ?";
                    $params[] = $filter_unit_id;
                    $types .= "i";
                }

                $sql .= " ORDER BY uv.english_word ASC";

                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                // bind_param cần 1 chuỗi các loại biến và sau đó là các biến đó
                // Sử dụng ...$params để truyền mảng params làm các đối số riêng lẻ
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $vocabularies = [];
                    while($row = $result->fetch_assoc()) {
                        $vocabularies[] = $row;
                    }
                    echo json_encode(['status' => 'success', 'data' => $vocabularies]);
                } else {
                    echo json_encode(['status' => 'success', 'message' => 'Không có từ vựng cá nhân nào cho học sinh này.', 'data' => []]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID học sinh.']);
            }
            break;

        case 'add_user_vocabulary': // Đã sửa: Xử lý cả thêm và cập nhật
            $input = json_decode(file_get_contents('php://input'), true);

            $user_vocabulary_id = $input['user_vocabulary_id'] ?? null; // ID nếu đang sửa
            $student_id = $input['student_id'] ?? null;
            $english_word = $input['english_word'] ?? null;
            $vietnamese_meaning = $input['vietnamese_meaning'] ?? null;
            $class_id = $input['class_id'] ?? null; // Nhận class_id (có thể null)
            $subject_id = $input['subject_id'] ?? null; // MỚI: Nhận subject_id (có thể null)
            $unit_id = $input['unit_id'] ?? null;   // Nhận unit_id (có thể null)

            // Chuyển đổi null sang DB NULL
            $class_id = $class_id === '' ? null : $class_id;
            $subject_id = $subject_id === '' ? null : $subject_id;
            $unit_id = $unit_id === '' ? null : $unit_id;

            if ($student_id && $english_word && $vietnamese_meaning) {
                if ($user_vocabulary_id) {
                    // Cập nhật từ vựng hiện có
                    // ĐÃ SỬA: Thêm subject_id vào UPDATE
                    // Dùng COALESCE để đảm bảo giá trị NULL nếu rỗng/không chọn
                    $stmt = $conn->prepare("UPDATE user_vocabularies SET english_word = ?, vietnamese_meaning = ?, class_id = ?, unit_id = ?, subject_id = ? WHERE user_vocabulary_id = ? AND student_id = ?");
                    if ($stmt === false) {
                        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh UPDATE: ' . $conn->error]);
                        break;
                    }
                    // sssiiiii: string, string, string (null/int), string (null/int), string (null/int), int, int
                    // Giải thích: english_word (s), vietnamese_meaning (s), class_id (i), unit_id (i), subject_id (i), user_vocabulary_id (i), student_id (i)
                    // Dùng "s" cho các giá trị có thể là NULL để bind dưới dạng string, MySQL sẽ tự cast
                    $stmt->bind_param("ssiiiii", $english_word, $vietnamese_meaning, $class_id, $unit_id, $subject_id, $user_vocabulary_id, $student_id);
                } else {
                    // Thêm từ vựng mới
                    // ĐÃ SỬA: Thêm subject_id vào INSERT
                    // Dùng COALESCE để đảm bảo giá trị NULL nếu rỗng/không chọn
                    $stmt = $conn->prepare("INSERT INTO user_vocabularies (student_id, class_id, unit_id, subject_id, english_word, vietnamese_meaning) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt === false) {
                        echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh INSERT: ' . $conn->error]);
                        break;
                    }
                    // iiisss: int, string (null/int), string (null/int), string (null/int), string, string
                    // Giải thích: student_id (i), class_id (i), unit_id (i), subject_id (i), english_word (s), vietnamese_meaning (s)
                    $stmt->bind_param("iiiiss", $student_id, $class_id, $unit_id, $subject_id, $english_word, $vietnamese_meaning);
                }
                
                if ($stmt->execute()) {
                    $message = $user_vocabulary_id ? 'Từ vựng đã được cập nhật.' : 'Từ vựng đã được thêm cho học sinh.';
                    echo json_encode(['status' => 'success', 'message' => $message]);
                } else {
                    $message = $user_vocabulary_id ? 'Lỗi khi cập nhật từ vựng: ' : 'Lỗi khi thêm từ vựng: ';
                    echo json_encode(['status' => 'error', 'message' => $message . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin từ vựng hoặc student_id.']);
            }
            break;
        
        case 'delete_user_vocabulary': // Mới: Xóa từ vựng cá nhân
            $input = json_decode(file_get_contents('php://input'), true);
            $user_vocabulary_id = $input['user_vocabulary_id'] ?? null;
            $student_id = $input['student_id'] ?? null; // Đảm bảo chỉ xóa từ của học sinh hiện tại

            if ($user_vocabulary_id && $student_id) {
                $stmt = $conn->prepare("DELETE FROM user_vocabularies WHERE user_vocabulary_id = ? AND student_id = ?");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh DELETE: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("ii", $user_vocabulary_id, $student_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['status' => 'success', 'message' => 'Từ vựng đã được xóa thành công.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy từ vựng để xóa hoặc không thuộc học sinh này.']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi xóa từ vựng: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID từ vựng hoặc ID học sinh.']);
            }
            break;

        // --- NEW USER MANAGEMENT ENDPOINTS ---
        case 'add_student_account':
            $input = json_decode(file_get_contents('php://input'), true);
            $student_name = $input['student_name'] ?? null;
            $username = $input['username'] ?? null;
            $password = $input['password'] ?? null;
            $class_id = $input['class_id'] ?? null;

            if ($student_name && $username && $password) {
                // Kiểm tra tên đăng nhập đã tồn tại chưa
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE username = ?");
                $check_stmt->bind_param("s", $username);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $row = $check_result->fetch_row();
                if ($row[0] > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.']);
                    $check_stmt->close();
                    break;
                }
                $check_stmt->close();

                // Hash mật khẩu
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO students (student_name, username, password_hash, class_id) VALUES (?, ?, ?, ?)");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                // sssi: string, string, string, int (hoặc null nếu không có lớp)
                $stmt->bind_param("sssi", $student_name, $username, $hashed_password, $class_id);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Tài khoản học sinh đã được tạo thành công.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi tạo tài khoản: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin tên học sinh, tên đăng nhập hoặc mật khẩu.']);
            }
            break;

        case 'update_student_password':
            $input = json_decode(file_get_contents('php://input'), true);
            $student_id = $input['student_id'] ?? null;
            $new_password = $input['new_password'] ?? null;

            if ($student_id && $new_password) {
                // Hash mật khẩu mới
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE students SET password_hash = ? WHERE student_id = ?");
                if ($stmt === false) {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi chuẩn bị câu lệnh SQL: ' . $conn->error]);
                    break;
                }
                $stmt->bind_param("si", $hashed_password, $student_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['status' => 'success', 'message' => 'Mật khẩu đã được cập nhật thành công.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy học sinh để cập nhật mật khẩu.']);
                    }
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi cập nhật mật khẩu: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID học sinh hoặc mật khẩu mới.']);
            }
            break;
        // --- END NEW USER MANAGEMENT ENDPOINTS ---
        case 'logout':
            session_unset(); // Xóa tất cả các biến session
            session_destroy(); // Hủy session
            echo json_encode(['status' => 'success', 'message' => 'Đăng xuất thành công.']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
            break;
    }
    $conn->close();
    exit();
}
// --- Kết thúc PHP API Endpoints ---

// Nếu người dùng CHƯA ĐĂNG NHẬP HOẶC KHÔNG PHẢI ADMIN, hiển thị form đăng nhập
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Admin</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f0f2f5;
            color: #333;
        }
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2em;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        input[type="text"], input[type="password"] {
            width: calc(100% - 22px);
            padding: 12px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1em;
            color: #333;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        button {
            background-color: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            width: 100%;
        }
        button:hover {
            background-color: #2980b9;
            transform: translateY(-1px);
        }
        button:active {
            transform: translateY(0);
            box-shadow: none;
        }
        .message {
            margin-top: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Đăng Nhập Quản Trị</h1>
        <?php if (!empty($response['message'])) { ?>
            <div class="message <?php echo $response['status']; ?>">
                <?php echo $response['message']; ?>
            </div>
        <?php } ?>
        <form method="POST" action="admin_panel.php">
            <input type="hidden" name="login_action" value="admin_login">
            <div class="form-group">
                <label for="username">Tên đăng nhập:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Đăng Nhập</button>
        </form>
    </div>
</body>
</html>
<?php
exit(); // Dừng script nếu chưa đăng nhập hoặc không phải admin
}

// --- HTML & JavaScript cho giao diện người dùng (chỉ hiển thị nếu đã đăng nhập admin) ---
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Quản Lý Từ Vựng</title>
    <style>
        body {
            font-family: 'Inter', Arial, sans-serif; /* Sử dụng Inter font */
            margin: 20px;
            background-color: #f0f2f5;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 960px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px; /* Rounded corners */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 700;
        }
        h2 {
            color: #34495e;
            margin-bottom: 20px;
            font-size: 1.6em;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
            margin-top: 30px;
        }
        .message {
            margin-bottom: 20px;
            padding: 12px 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 0.95em;
            transition: all 0.3s ease;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            flex-wrap: wrap; /* Cho phép các tab xuống dòng trên màn hình nhỏ */
        }
        .tab-button {
            background-color: #ecf0f1;
            color: #34495e;
            padding: 12px 25px;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            margin: 0 5px;
            transition: all 0.3s ease;
            white-space: nowrap; /* Ngăn chặn wrap văn bản bên trong nút */
            margin-bottom: 10px; /* Khoảng cách giữa các tab khi wrap */
        }
        .tab-button:hover {
            background-color: #dbe4e6;
        }
        .tab-button.active {
            background-color: #3498db;
            color: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .tab-content {
            display: none;
            padding: 25px;
            border: 1px solid #e0e0e0;
            border-top: none;
            border-radius: 0 0 12px 12px;
            background-color: #fdfdfd;
        }
        .tab-content.active {
            display: block;
        }
        .section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            background-color: #fefefe;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.05);
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 0.95em;
        }
        select, input[type="text"], input[type="password"], textarea {
            width: calc(100% - 22px); /* Adjust for padding and border */
            padding: 12px;
            border: 1px solid #dcdcdc;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.95em;
            color: #333;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        select:focus, input[type="text"]:focus, input[type="password"]:focus, textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        button {
            background-color: #2ecc71; /* Green */
            color: white;
            padding: 12px 22px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-right: 12px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        button:hover {
            background-color: #27ae60;
            transform: translateY(-1px);
        }
        button:active {
            transform: translateY(0);
            box-shadow: none;
        }
        .btn-edit {
            background-color: #f39c12; /* Orange */
            color: #fff;
        }
        .btn-edit:hover {
            background-color: #e67e22;
        }
        .btn-delete {
            background-color: #e74c3c; /* Red */
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .btn-cancel {
            background-color: #95a5a6; /* Grey */
        }
        .btn-cancel:hover {
            background-color: #7f8c8d;
        }
        .data-list {
            max-height: 300px; /* Tăng chiều cao tối đa */
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            background-color: #fdfdfd;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .data-list p {
            margin: 8px 0;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
            border-left: 4px solid #3498db;
        }
        .data-list p:nth-child(even) {
            background-color: #f8fcfd;
            border-left: 4px solid #2ecc71;
        }
        .data-list p button {
            margin-left: 10px;
            padding: 6px 12px;
            font-size: 0.85em;
            border-radius: 4px;
            box-shadow: none;
        }
        .inline-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap; /* Cho phép nút xuống dòng nếu cần */
        }
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }
            .tabs {
                flex-direction: column;
                align-items: stretch;
            }
            .tab-button {
                margin: 5px 0;
                border-radius: 8px;
            }
            select, input[type="text"], input[type="password"], textarea {
                width: 100%;
            }
            button {
                width: auto;
                margin-bottom: 10px;
                margin-right: 0;
            }
            .inline-buttons {
                flex-direction: column;
                gap: 5px;
                width: 100%;
            }
            .inline-buttons button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bảng Quản Lý Dữ Liệu</h1>
        <div id="message" class="message" style="display:none;"></div>

        <div class="tabs">
            <button class="tab-button active" onclick="openTab('viewDataTab')">Xem Dữ Liệu</button>
            <button class="tab-button" onclick="openTab('addEditVocabTab')">Thêm/Sửa Từ Vựng</button>
            <button class="tab-button" onclick="openTab('batchAddVocabTab')">Thêm Hàng Loạt</button>
            <button class="tab-button" onclick="openTab('addUnitTab')">Thêm Unit Mới</button>
            <button class="tab-button" onclick="openTab('manageSubjectsTab')">Quản Lý Môn Học</button>
            <button class="tab-button" onclick="openTab('userManagementTab')">Quản Lý Người Dùng</button>
            <button class="tab-button btn-delete" onclick="logoutAdmin()">Đăng Xuất</button>
        </div>

        <div id="viewDataTab" class="tab-content active">
            <h2>Xem Dữ Liệu</h2>
            <div class="section">
                <div class="form-group">
                    <label for="studentSelect">Chọn Học Sinh:</label>
                    <select id="studentSelect">
                        <option value="">-- Chọn Học Sinh --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="classSelect">Chọn Lớp (Lọc):</label>
                    <select id="classSelect">
                        <option value="">-- Tất cả Lớp --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subjectSelect">Chọn Môn Học (Lọc):</label>
                    <select id="subjectSelect" disabled>
                        <option value="">-- Tất cả Môn Học --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="unitSelect">Chọn Unit (Lọc):</label>
                    <select id="unitSelect" disabled>
                        <option value="">-- Tất cả Unit --</option>
                    </select>
                </div>
                <h3>Từ Vựng Cá Nhân của Học Sinh <span id="selectedStudentName"></span>:</h3>
                <div id="userVocabList" class="data-list">
                    Vui lòng chọn học sinh để xem từ vựng.
                </div>
                <h3>Units của Lớp <span id="selectedClassName"></span> - Môn <span id="selectedSubjectName"></span>:</h3>
                <div id="unitsList" class="data-list">
                    Vui lòng chọn lớp và môn học để xem units.
                </div>
            </div>
        </div>

        <div id="addEditVocabTab" class="tab-content">
            <h2>Thêm/Sửa Từ Vựng Cá Nhân</h2>
            <div class="section">
                <input type="hidden" id="editVocabId">
                <p>Chọn học sinh ở tab "Xem Dữ Liệu" để thêm/sửa từ vựng riêng cho học sinh đó.</p>
                <div class="form-group">
                    <label for="addVocabClassSelect">Chọn Lớp (tùy chọn):</label>
                    <select id="addVocabClassSelect">
                        <option value="">-- Không có lớp --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addVocabSubjectSelect">Chọn Môn Học (tùy chọn):</label>
                    <select id="addVocabSubjectSelect" disabled>
                        <option value="">Vui lòng chọn lớp trước</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addVocabUnitSelect">Chọn Unit (tùy chọn):</label>
                    <select id="addVocabUnitSelect" disabled>
                        <option value="">Vui lòng chọn môn học và lớp trước</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="addEnglishWord">Từ tiếng Anh:</label>
                    <input type="text" id="addEnglishWord" placeholder="Nhập từ tiếng Anh">
                </div>
                <div class="form-group">
                    <label for="addVietnameseMeaning">Nghĩa tiếng Việt:</label>
                    <input type="text" id="addVietnameseMeaning" placeholder="Nhập nghĩa tiếng Việt">
                </div>
                <button id="addVocabularyButton">Thêm Từ Vựng</button>
                <button id="cancelEditButton" class="btn-cancel" style="display:none;">Hủy Sửa</button>
            </div>
        </div>

        <div id="batchAddVocabTab" class="tab-content">
            <h2>Thêm Từ Vựng Từ Văn Bản (Hàng Loạt)</h2>
            <div class="section">
                <p>Nhập các cặp từ vựng theo định dạng: <code>tiếng_anh:nghĩa_tiếng_việt</code>, mỗi cặp một dòng.</p>
                <div class="form-group">
                    <label for="batchVocabClassSelect">Chọn Lớp mặc định (tùy chọn cho hàng loạt):</label>
                    <select id="batchVocabClassSelect">
                        <option value="">-- Không có lớp --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="batchVocabSubjectSelect">Chọn Môn học mặc định (tùy chọn cho hàng loạt):</label>
                    <select id="batchVocabSubjectSelect" disabled>
                        <option value="">Vui lòng chọn lớp trước</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="batchVocabUnitSelect">Chọn Unit mặc định (tùy chọn cho hàng loạt):</label>
                    <select id="batchVocabUnitSelect" disabled>
                        <option value="">Vui lòng chọn môn học và lớp trước</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="batchVocabText">Văn bản từ vựng:</label>
                    <textarea id="batchVocabText" rows="10" placeholder="Ví dụ:&#x0A;hello:xin chào&#x0A;dog:chó&#x0A;cat:mèo"></textarea>
                </div>
                <button id="batchAddVocabularyButton">Thêm Hàng Loạt</button>
            </div>
        </div>

        <div id="addUnitTab" class="tab-content">
            <h2>Thêm Unit Mới</h2>
            <div class="section">
                <p>Chọn Lớp và Môn học ở tab "Xem Dữ Liệu" để thêm Unit mới cho Lớp và Môn đó.</p>
                <div class="form-group">
                    <label for="newUnitName">Tên Unit mới:</label>
                    <input type="text" id="newUnitName" placeholder="Nhập tên Unit mới">
                </div>
                <button id="addUnitButton">Thêm Unit</button>
            </div>
        </div>

        <div id="manageSubjectsTab" class="tab-content">
            <h2>Quản Lý Môn Học Chung</h2>
            <div class="section">
                <div class="form-group">
                    <label for="allSubjectsSelect">Chọn Môn Học (Để quản lý Units hoặc Gán cho Lớp):</label>
                    <select id="allSubjectsSelect">
                        <option value="">Đang tải...</option>
                    </select>
                </div>
                <p>Chọn môn học ở trên và một lớp ở tab "Xem Dữ Liệu" để gán môn học này cho lớp đó.</p>
                <button id="assignSubjectToClassButton">Gán Môn Học cho Lớp</button>
                <h3>Tất cả Units cho Môn <span id="selectedAllSubjectName"></span>:</h3>
                <div id="allUnitsForSubjectList" class="data-list">
                    Vui lòng chọn môn học.
                </div>
            </div>
        </div>

        <div id="userManagementTab" class="tab-content">
            <h2>Quản Lý Người Dùng</h2>
            <div class="section">
                <h3>Tạo Tài Khoản Học Sinh Mới</h3>
                <div class="form-group">
                    <label for="newStudentName">Tên Học Sinh:</label>
                    <input type="text" id="newStudentName" placeholder="Nhập tên học sinh mới">
                </div>
                <div class="form-group">
                    <label for="newUsername">Tên Đăng Nhập:</label>
                    <input type="text" id="newUsername" placeholder="Nhập tên đăng nhập (duy nhất)">
                </div>
                <div class="form-group">
                    <label for="newPassword">Mật Khẩu:</label>
                    <input type="password" id="newPassword" placeholder="Nhập mật khẩu">
                </div>
                <div class="form-group">
                    <label for="newStudentClassSelect">Chọn Lớp (tùy chọn):</label>
                    <select id="newStudentClassSelect">
                        <option value="">-- Không có lớp --</option>
                    </select>
                </div>
                <button id="createStudentAccountButton">Tạo Tài Khoản</button>
            </div>

            <div class="section">
                <h3>Đổi Mật Khẩu Người Dùng</h3>
                <div class="form-group">
                    <label for="changePasswordStudentSelect">Chọn Học Sinh:</label>
                    <select id="changePasswordStudentSelect">
                        <option value="">-- Chọn Học Sinh --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="newPasswordForUser">Mật Khẩu Mới:</label>
                    <input type="password" id="newPasswordForUser" placeholder="Nhập mật khẩu mới">
                </div>
                <button id="changeUserPasswordButton">Đổi Mật Khẩu</button>
            </div>
        </div>

    </div>

    <script>
        const messageDiv = document.getElementById('message');
        const studentSelect = document.getElementById('studentSelect');
        const classSelect = document.getElementById('classSelect');
        const subjectSelect = document.getElementById('subjectSelect');
        const unitSelect = document.getElementById('unitSelect');
        const addEnglishWordInput = document.getElementById('addEnglishWord');
        const addVietnameseMeaningInput = document.getElementById('addVietnameseMeaning');
        const addVocabularyButton = document.getElementById('addVocabularyButton');
        const cancelEditButton = document.getElementById('cancelEditButton');
        const editVocabIdInput = document.getElementById('editVocabId');
        const selectedStudentNameSpan = document.getElementById('selectedStudentName');
        const userVocabListDiv = document.getElementById('userVocabList');
        const newUnitNameInput = document.getElementById('newUnitName');
        const addUnitButton = document.getElementById('addUnitButton');
        const unitsListDiv = document.getElementById('unitsList'); 
        const selectedClassNameSpan = document.getElementById('selectedClassName');
        const selectedSubjectNameSpan = document.getElementById('selectedSubjectName');

        // Elements for all subjects management
        const allSubjectsSelect = document.getElementById('allSubjectsSelect');
        const selectedAllSubjectNameSpan = document.getElementById('selectedAllSubjectName');
        const allUnitsForSubjectList = document.getElementById('allUnitsForSubjectList');
        const assignSubjectToClassButton = document.getElementById('assignSubjectToClassButton');

        // Elements for adding/editing individual vocabulary
        const addVocabClassSelect = document.getElementById('addVocabClassSelect');
        const addVocabSubjectSelect = document.getElementById('addVocabSubjectSelect');
        const addVocabUnitSelect = document.getElementById('addVocabUnitSelect');

        // Elements for batch adding vocabulary
        const batchVocabClassSelect = document.getElementById('batchVocabClassSelect');
        const batchVocabSubjectSelect = document.getElementById('batchVocabSubjectSelect');
        const batchVocabUnitSelect = document.getElementById('batchVocabUnitSelect');
        const batchVocabText = document.getElementById('batchVocabText');
        const batchAddVocabularyButton = document.getElementById('batchAddVocabularyButton');

        // NEW: User Management Elements
        const newStudentNameInput = document.getElementById('newStudentName');
        const newUsernameInput = document.getElementById('newUsername');
        const newPasswordInput = document.getElementById('newPassword');
        const newStudentClassSelect = document.getElementById('newStudentClassSelect');
        const createStudentAccountButton = document.getElementById('createStudentAccountButton');
        const changePasswordStudentSelect = document.getElementById('changePasswordStudentSelect');
        const newPasswordForUserInput = document.getElementById('newPasswordForUser');
        const changeUserPasswordButton = document.getElementById('changeUserPasswordButton');


        let studentsData = []; // Stores student data
        let classesData = []; // Stores class data
        let allSubjectsData = []; // Stores all subject data

        let selectedStudentId = null; // Currently selected student ID for data viewing
        let selectedClassId = null; // Currently selected class ID for data viewing
        let selectedSubjectId = null; // Currently selected subject ID for data viewing
        let selectedAllSubjectId = null; // Currently selected subject ID in "Manage Subjects" section

        // Class/Subject/Unit IDs for add/edit individual vocabulary form
        let addVocabSelectedClassId = null;
        let addVocabSelectedSubjectId = null;
        let addVocabSelectedUnitId = null;
        let editingVocabId = null; // Stores vocabulary ID being edited

        // Class/Subject/Unit IDs for batch add vocabulary form
        let batchVocabSelectedClassId = null;
        let batchVocabSelectedSubjectId = null;
        let batchVocabSelectedUnitId = null;

        // Base URL for API calls - This needs to point to the admin_panel.php itself to handle actions
        const API_BASE_URL = window.location.origin + window.location.pathname;

        // Function to display messages (success/error)
        function showMessage(msg, type) {
            messageDiv.textContent = msg;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block'; // Ensure message is visible
            setTimeout(() => {
                messageDiv.style.display = 'none'; // Hide after a delay
                messageDiv.textContent = '';
                messageDiv.className = 'message';
            }, 3000);
        }

        // Generic function for fetching data from PHP API
        async function fetchData(action, params = {}, method = 'GET', body = null) {
            let url = new URL(API_BASE_URL);
            url.searchParams.append('action', action);
            for (const key in params) {
                if (params[key] !== null && params[key] !== undefined && params[key] !== '') { // Only add if not null, undefined, or empty string
                    url.searchParams.append(key, params[key]);
                }
            }

            try {
                const options = { method: method };
                if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
                    options.headers = { 'Content-Type': 'application/json' };
                    options.body = JSON.stringify(body);
                }
                const response = await fetch(url, options);
                // Check for HTTP errors before parsing JSON
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
                }
                return await response.json();
            } catch (error) {
                console.error(`Lỗi khi tải dữ liệu cho ${action}:`, error);
                showMessage(`Lỗi kết nối hoặc xử lý dữ liệu cho ${action}: ${error.message}`, 'error');
                return { status: 'error', message: 'Lỗi kết nối.' };
            }
        }

        // Load students into dropdowns
        async function loadStudents() {
            const response = await fetchData('get_students');
            // Clear and add default option for main student select
            studentSelect.innerHTML = '<option value="">-- Chọn Học Sinh --</option>';
            // Clear and add default option for change password student select
            changePasswordStudentSelect.innerHTML = '<option value="">-- Chọn Học Sinh --</option>';

            if (response.status === 'success' && response.data.length > 0) {
                studentsData = response.data; // Store student data
                response.data.forEach(student => {
                    const option1 = document.createElement('option');
                    option1.value = student.student_id;
                    option1.textContent = `${student.student_name} (${student.username}) - ${student.class_name || 'Chưa có lớp'}`;
                    studentSelect.appendChild(option1);

                    const option2 = document.createElement('option');
                    option2.value = student.student_id;
                    option2.textContent = `${student.student_name} (${student.username})`;
                    changePasswordStudentSelect.appendChild(option2);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách học sinh.', 'error');
            }
        }

        // Load classes into specified dropdown
        async function loadClasses(targetSelect) {
            const response = await fetchData('get_classes');
            let defaultOptionText = '-- Chọn Lớp --';
            if (targetSelect === addVocabClassSelect || targetSelect === batchVocabClassSelect || targetSelect === newStudentClassSelect) {
                defaultOptionText = '-- Không có lớp --';
            }
            targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;

            if (response.status === 'success' && response.data.length > 0) {
                classesData = response.data; // Store class data
                response.data.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.class_id;
                    option.textContent = cls.class_name;
                    targetSelect.appendChild(option);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách lớp.', 'error');
            }
        }

        // Load subjects based on class ID (for filtered dropdowns)
        async function loadSubjectsByClass(classId, targetSelect, resetUnits = true) { 
            targetSelect.innerHTML = '<option value="">Đang tải...</option>';
            targetSelect.disabled = true;

            let defaultOptionText = '-- Chọn Môn Học --';
            if (targetSelect === subjectSelect) { // For main filter
                defaultOptionText = '-- Tất cả Môn Học --';
            } else if (targetSelect === addVocabSubjectSelect || targetSelect === batchVocabSubjectSelect) {
                defaultOptionText = '-- Không có môn học --';
            }

            if (resetUnits) {
                const targetUnitSelect = (targetSelect === subjectSelect) ? unitSelect : 
                                         (targetSelect === addVocabSubjectSelect ? addVocabUnitSelect : batchVocabUnitSelect);
                targetUnitSelect.innerHTML = '<option value="">Vui lòng chọn môn học và lớp trước</option>';
                targetUnitSelect.disabled = true;
                if (targetSelect === subjectSelect) {
                    unitsListDiv.innerHTML = 'Không có units nào được chọn.';
                    selectedSubjectNameSpan.textContent = '';
                }
            }

            if (classId) {
                const response = await fetchData('get_subjects_by_class', { class_id: classId }); 
                targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(subject => {
                        const option = document.createElement('option');
                        option.value = subject.subject_id;
                        option.textContent = subject.subject_name;
                        targetSelect.appendChild(option);
                    });
                    targetSelect.disabled = false;
                } else {
                    showMessage(response.message || 'Không tìm thấy môn học nào cho lớp này.', 'error');
                    targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
                }
            } else {
                targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
            }
        }
        
        // Load all subjects (for general management dropdowns)
        async function loadAllSubjectsForDropdown(targetSelect) {
            const response = await fetchData('get_subjects');
            targetSelect.innerHTML = '<option value="">-- Chọn Môn Học --</option>';
            if (response.status === 'success' && response.data.length > 0) {
                allSubjectsData = response.data;
                response.data.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = subject.subject_name;
                    targetSelect.appendChild(option);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách môn học.', 'error');
                targetSelect.innerHTML = '<option value="">Không có Môn học nào</option>';
            }
        }

        // Load units based on subject and class
        async function loadUnitsBySubjectAndClass(subjectId, classId, targetSelect, displayList = null) {
            targetSelect.innerHTML = '<option value="">Đang tải...</option>';
            targetSelect.disabled = true;
            if (displayList) {
                displayList.innerHTML = 'Đang tải Units...';
            }
            
            let defaultOptionText = '-- Chọn Unit --';
            if (targetSelect === unitSelect) { // For main filter
                 defaultOptionText = '-- Tất cả Unit --';
            } else if (targetSelect === addVocabUnitSelect || targetSelect === batchVocabUnitSelect) {
                defaultOptionText = '-- Không có unit --';
            }

            if (subjectId && classId) {
                const response = await fetchData('get_units_by_subject_and_class', { subject_id: subjectId, class_id: classId });
                targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
                if (displayList) {
                    displayList.innerHTML = '';
                }

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.unit_id;
                        option.textContent = unit.unit_name;
                        targetSelect.appendChild(option);

                        if (displayList) {
                            const p = document.createElement('p');
                            p.textContent = `ID: ${unit.unit_id} - ${unit.unit_name} (Class ID: ${unit.class_id})`; // Updated to show class_id if needed
                            displayList.appendChild(p);
                        }
                    });
                    targetSelect.disabled = false;
                    if (targetSelect === unitSelect) {
                        selectedSubjectNameSpan.textContent = allSubjectsData.find(s => s.subject_id == subjectId)?.subject_name || '';
                    }
                } else {
                    showMessage(response.message || 'Không tìm thấy units cho môn học và lớp này.', 'error');
                    targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
                    if (displayList) {
                        displayList.innerHTML = response.message || 'Không có units nào cho môn học và lớp này.';
                    }
                    if (targetSelect === unitSelect) {
                        selectedSubjectNameSpan.textContent = allSubjectsData.find(s => s.subject_id == subjectId)?.subject_name || '';
                    }
                }
            } else {
                targetSelect.innerHTML = `<option value="">${defaultOptionText}</option>`;
                if (displayList) {
                    displayList.innerHTML = 'Không có units nào được chọn.';
                }
                if (targetSelect === unitSelect) {
                    selectedSubjectNameSpan.textContent = '';
                }
            }
        }

        // Load all units for a specific subject (no class filter)
        async function loadAllUnitsForSubject(subjectId) {
            allUnitsForSubjectList.innerHTML = 'Đang tải Units...';
            if (subjectId) {
                const response = await fetchData('get_all_units_by_subject', { subject_id: subjectId });
                allUnitsForSubjectList.innerHTML = '';

                const selectedSubjectName = allSubjectsData.find(s => s.subject_id == subjectId)?.subject_name || '';
                selectedAllSubjectNameSpan.textContent = selectedSubjectName;

                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(unit => {
                        const className = classesData.find(c => c.class_id == unit.class_id)?.class_name || 'Không rõ lớp';
                        const p = document.createElement('p');
                        p.textContent = `ID: ${unit.unit_id} - ${unit.unit_name} (Lớp: ${className})`;
                        allUnitsForSubjectList.appendChild(p);
                    });
                } else {
                    allUnitsForSubjectList.innerHTML = response.message || 'Không có units nào cho môn học này.';
                }
            } else {
                allUnitsForSubjectList.innerHTML = 'Vui lòng chọn môn học.';
                selectedAllSubjectNameSpan.textContent = '';
            }
        }

        // Load user vocabularies based on selected student and filters
        async function loadUserVocabularies(studentId) {
            userVocabListDiv.innerHTML = 'Đang tải từ vựng cá nhân...';
            if (!studentId) {
                userVocabListDiv.innerHTML = 'Vui lòng chọn học sinh.';
                selectedStudentNameSpan.textContent = '';
                return;
            }

            const currentFilterClassId = classSelect.value ? parseInt(classSelect.value) : null;
            const currentFilterSubjectId = subjectSelect.value ? parseInt(subjectSelect.value) : null;
            const currentFilterUnitId = unitSelect.value ? parseInt(unitSelect.value) : null;

            const response = await fetchData('get_user_vocabularies', { 
                student_id: studentId,
                class_id: currentFilterClassId,
                subject_id: currentFilterSubjectId,
                unit_id: currentFilterUnitId
            });

            const selectedStudent = studentsData.find(s => s.student_id == studentId);
            selectedStudentNameSpan.textContent = selectedStudent ? selectedStudent.student_name : '';

            if (response.status === 'success' && response.data.length > 0) {
                userVocabListDiv.innerHTML = '';
                response.data.forEach(word => {
                    const classInfo = word.class_name ? ` (Lớp: ${word.class_name})` : '';
                    const subjectInfo = word.subject_name ? ` (Môn: ${word.subject_name})` : '';
                    const unitInfo = word.unit_name ? ` (Unit: ${word.unit_name})` : '';
                    const p = document.createElement('p');
                    p.innerHTML = `${word.english_word}: ${word.vietnamese_meaning}${classInfo}${subjectInfo}${unitInfo}
                                    <div class="inline-buttons">
                                        <button class="btn-edit" data-id="${word.user_vocabulary_id}" 
                                                data-english="${word.english_word}" 
                                                data-vietnamese="${word.vietnamese_meaning}"
                                                data-class-id="${word.class_id || ''}"
                                                data-subject-id="${word.subject_id || ''}"
                                                data-unit-id="${word.unit_id || ''}">Sửa</button>
                                        <button class="btn-delete" data-id="${word.user_vocabulary_id}">Xóa</button>
                                    </div>`;
                    userVocabListDiv.appendChild(p);
                });
                userVocabListDiv.querySelectorAll('.btn-edit').forEach(button => {
                    button.addEventListener('click', (e) => editUserVocabulary(
                        e.target.dataset.id,
                        e.target.dataset.english,
                        e.target.dataset.vietnamese,
                        e.target.dataset.classId,
                        e.target.dataset.subjectId,
                        e.target.dataset.unitId
                    ));
                });
                userVocabListDiv.querySelectorAll('.btn-delete').forEach(button => {
                    button.addEventListener('click', (e) => deleteUserVocabulary(e.target.dataset.id));
                });

            } else {
                userVocabListDiv.innerHTML = response.message || 'Không có từ vựng cá nhân nào cho học sinh này.';
            }
        }

        // Populate form fields for editing existing vocabulary
        async function editUserVocabulary(vocabId, english, vietnamese, classId, subjectId, unitId) {
            if (!selectedStudentId) {
                showMessage('Vui lòng chọn một học sinh để sửa từ vựng.', 'error');
                return;
            }
            editingVocabId = vocabId;
            addEnglishWordInput.value = english;
            addVietnameseMeaningInput.value = vietnamese;
            
            // Set class dropdown and load subjects
            addVocabClassSelect.value = classId;
            addVocabSelectedClassId = classId ? parseInt(classId) : null;

            if (addVocabSelectedClassId) {
                await loadSubjectsByClass(addVocabSelectedClassId, addVocabSubjectSelect, false); // Do not reset units yet
                // Set subject dropdown and load units
                addVocabSubjectSelect.value = subjectId;
                addVocabSelectedSubjectId = subjectId ? parseInt(subjectId) : null;

                if (addVocabSelectedSubjectId && addVocabSelectedClassId) {
                    await loadUnitsBySubjectAndClass(addVocabSelectedSubjectId, addVocabSelectedClassId, addVocabUnitSelect);
                    // Set unit dropdown
                    addVocabUnitSelect.value = unitId;
                    addVocabSelectedUnitId = unitId ? parseInt(unitId) : null;
                } else {
                    addVocabUnitSelect.innerHTML = '<option value="">Vui lòng chọn môn học trước</option>';
                    addVocabUnitSelect.disabled = true;
                    addVocabSelectedUnitId = null;
                }
            } else {
                addVocabSubjectSelect.innerHTML = '<option value="">Vui lòng chọn lớp trước</option>';
                addVocabSubjectSelect.disabled = true;
                addVocabUnitSelect.innerHTML = '<option value="">Vui lòng chọn môn học và lớp trước</option>';
                addVocabUnitSelect.disabled = true;
                addVocabSelectedSubjectId = null;
                addVocabSelectedUnitId = null;
            }

            addVocabularyButton.textContent = 'Cập Nhật Từ Vựng';
            cancelEditButton.style.display = 'inline-block';
            openTab('addEditVocabTab'); // Switch to the add/edit tab
        }

        // Delete user vocabulary
        async function deleteUserVocabulary(vocabId) {
            if (!selectedStudentId) {
                showMessage('Vui lòng chọn một học sinh để xóa từ vựng.', 'error');
                return;
            }
            if (!confirm('Bạn có chắc chắn muốn xóa từ vựng này không?')) {
                return;
            }
            const response = await fetchData('delete_user_vocabulary', {}, 'POST', {
                user_vocabulary_id: vocabId,
                student_id: selectedStudentId
            });
            if (response.status === 'success') {
                showMessage('Xóa từ vựng thành công!', 'success');
                loadUserVocabularies(selectedStudentId); // Reload list
            } else {
                showMessage(response.message || 'Xóa từ vựng thất bại.', 'error');
            }
        }

        // Handle adding or updating individual user vocabulary
        async function handleAddOrUpdateUserVocabulary() {
            if (!selectedStudentId) {
                showMessage('Vui lòng chọn một học sinh trước khi thêm/sửa từ vựng.', 'error');
                return;
            }
            const englishWord = addEnglishWordInput.value.trim();
            const vietnameseMeaning = addVietnameseMeaningInput.value.trim();

            if (!englishWord || !vietnameseMeaning) {
                showMessage('Vui lòng nhập đầy đủ từ tiếng Anh và nghĩa tiếng Việt.', 'error');
                return;
            }

            const body = {
                student_id: selectedStudentId,
                class_id: addVocabSelectedClassId, 
                subject_id: addVocabSelectedSubjectId,
                unit_id: addVocabSelectedUnitId,   
                english_word: englishWord,
                vietnamese_meaning: vietnameseMeaning
            };

            let response;
            if (editingVocabId) {
                body.user_vocabulary_id = editingVocabId;
                response = await fetchData('add_user_vocabulary', {}, 'POST', body);
            } else {
                response = await fetchData('add_user_vocabulary', {}, 'POST', body);
            }
            
            if (response.status === 'success') {
                showMessage(response.message, 'success');
                resetAddVocabForm(); // Reset form after add/edit
                loadUserVocabularies(selectedStudentId); // Reload personal vocabulary list for that student
            } else {
                showMessage(response.message || 'Thêm/Sửa từ vựng thất bại.', 'error');
            }
        }

        // Add multiple vocabularies from text
        async function batchAddVocabulary() {
            if (!selectedStudentId) {
                showMessage('Vui lòng chọn một học sinh trước khi thêm từ vựng hàng loạt.', 'error');
                return;
            }
            const rawText = batchVocabText.value.trim();
            if (!rawText) {
                showMessage('Vui lòng nhập văn bản từ vựng.', 'error');
                return;
            }

            const lines = rawText.split('\n').filter(line => line.trim() !== '');
            if (lines.length === 0) {
                showMessage('Không có từ vựng nào được tìm thấy trong văn bản.', 'error');
                return;
            }

            let successCount = 0;
            let errorCount = 0;
            const totalWords = lines.length;

            for (const line of lines) {
                const parts = line.split(':');
                if (parts.length === 2) {
                    const englishWord = parts[0].trim();
                    const vietnameseMeaning = parts[1].trim();

                    if (englishWord && vietnameseMeaning) {
                        const response = await fetchData('add_user_vocabulary', {}, 'POST', {
                            student_id: selectedStudentId,
                            class_id: batchVocabSelectedClassId,
                            subject_id: batchVocabSelectedSubjectId,
                            unit_id: batchVocabSelectedUnitId,
                            english_word: englishWord,
                            vietnamese_meaning: vietnameseMeaning
                        });

                        if (response.status === 'success') {
                            successCount++;
                        } else {
                            errorCount++;
                            console.error(`Lỗi khi thêm từ '${englishWord}': ${response.message}`);
                        }
                    } else {
                        errorCount++;
                    }
                } else {
                    errorCount++;
                }
            }
            
            showMessage(`Đã xử lý ${totalWords} từ. Thành công: ${successCount}, Lỗi: ${errorCount}.`, 
                        errorCount === 0 ? 'success' : (successCount > 0 ? 'success' : 'error'));
            batchVocabText.value = ''; // Clear content after adding
            loadUserVocabularies(selectedStudentId); // Reload personal vocabulary list
        }

        // Add a new unit
        async function addUnit() {
            const unitName = newUnitNameInput.value.trim();
            if (!unitName) {
                showMessage('Vui lòng nhập tên Unit.', 'error');
                return;
            }
            if (!selectedSubjectId) {
                showMessage('Vui lòng chọn một Môn học từ mục "Xem Dữ Liệu" trước.', 'error');
                return;
            }
            if (!selectedClassId) {
                showMessage('Vui lòng chọn một Lớp từ mục "Xem Dữ Liệu" trước.', 'error');
                return;
            }

            const response = await fetchData('add_unit', {}, 'POST', {
                unit_name: unitName,
                subject_id: selectedSubjectId,
                class_id: selectedClassId
            });

            if (response.status === 'success') {
                showMessage('Thêm Unit thành công!', 'success');
                newUnitNameInput.value = '';
                loadUnitsBySubjectAndClass(selectedSubjectId, selectedClassId, unitSelect, unitsListDiv);
                if (selectedAllSubjectId == selectedSubjectId) {
                    loadAllUnitsForSubject(selectedAllSubjectId);
                }
            } else {
                showMessage(response.message || 'Thêm Unit thất bại.', 'error');
            }
        }

        // Assign a subject to a class
        async function assignSubjectToClass() {
            if (!selectedAllSubjectId) {
                showMessage('Vui lòng chọn một Môn học từ dropdown "Chọn Môn Học (Để quản lý Units)".', 'error');
                return;
            }
            if (!selectedClassId) {
                showMessage('Vui lòng chọn một Lớp ở mục "Xem Dữ Liệu" trước.', 'error');
                return;
            }

            const response = await fetchData('assign_subject_to_class', {}, 'POST', {
                subject_id: selectedAllSubjectId,
                class_id: selectedClassId
            });

            if (response.status === 'success') {
                showMessage('Gán Môn Học cho Lớp thành công!', 'success');
                if (selectedClassId) {
                    loadSubjectsByClass(selectedClassId, subjectSelect);
                    if (addVocabSelectedClassId == selectedClassId) {
                       loadSubjectsByClass(addVocabSelectedClassId, addVocabSubjectSelect);
                    }
                    if (batchVocabSelectedClassId == selectedClassId) {
                       loadSubjectsByClass(batchVocabSelectedClassId, batchVocabSubjectSelect);
                    }
                }
            } else {
                showMessage(response.message || 'Gán Môn Học cho Lớp thất bại.', 'error');
            }
        }

        // NEW: Create a new student account
        async function createStudentAccount() {
            const studentName = newStudentNameInput.value.trim();
            const username = newUsernameInput.value.trim();
            const password = newPasswordInput.value.trim();
            const classId = newStudentClassSelect.value ? parseInt(newStudentClassSelect.value) : null;

            if (!studentName || !username || !password) {
                showMessage('Vui lòng nhập đầy đủ Tên Học Sinh, Tên Đăng Nhập và Mật Khẩu.', 'error');
                return;
            }

            const response = await fetchData('add_student_account', {}, 'POST', {
                student_name: studentName,
                username: username,
                password: password,
                class_id: classId
            });

            if (response.status === 'success') {
                showMessage(response.message, 'success');
                newStudentNameInput.value = '';
                newUsernameInput.value = '';
                newPasswordInput.value = '';
                newStudentClassSelect.value = '';
                loadStudents(); // Reload student lists in all relevant dropdowns
            } else {
                showMessage(response.message || 'Tạo tài khoản thất bại.', 'error');
            }
        }

        // NEW: Change user password
        async function changeUserPassword() {
            const studentId = changePasswordStudentSelect.value;
            const newPassword = newPasswordForUserInput.value.trim();

            if (!studentId) {
                showMessage('Vui lòng chọn một học sinh để đổi mật khẩu.', 'error');
                return;
            }
            if (!newPassword) {
                showMessage('Vui lòng nhập mật khẩu mới.', 'error');
                return;
            }

            const response = await fetchData('update_student_password', {}, 'POST', {
                student_id: studentId,
                new_password: newPassword
            });

            if (response.status === 'success') {
                showMessage(response.message, 'success');
                newPasswordForUserInput.value = '';
                changePasswordStudentSelect.value = ''; // Reset selection
            } else {
                showMessage(response.message || 'Đổi mật khẩu thất bại.', 'error');
            }
        }
        
        // NEW: Admin Logout Function
        function logoutAdmin() {
            // Send a request to clear the session on the server
            fetch(API_BASE_URL + '?action=logout', { method: 'GET' }) // Use GET for simplicity here, but POST is generally safer for state changes
                .then(response => {
                    // Even if the server request fails, clear the local session (browser will forget the session cookie)
                    // and redirect to ensure a clean state.
                    window.location.reload(); // Reload the page, which will trigger the login form
                })
                .catch(error => {
                    console.error('Lỗi khi đăng xuất:', error);
                    window.location.reload(); // Still try to reload
                });
        }

        // --- Event Listeners ---
        studentSelect.addEventListener('change', () => {
            selectedStudentId = studentSelect.value;
            loadUserVocabularies(selectedStudentId);
            resetAddVocabForm();
            resetBatchAddForm();
        });

        classSelect.addEventListener('change', () => {
            selectedClassId = classSelect.value;
            const selectedClass = classesData.find(c => c.class_id == selectedClassId);
            selectedClassNameSpan.textContent = selectedClass ? selectedClass.class_name : '';
            subjectSelect.value = '';
            selectedSubjectId = null;
            loadSubjectsByClass(selectedClassId, subjectSelect);
            unitSelect.innerHTML = '<option value="">Vui lòng chọn môn học và lớp trước</option>';
            unitSelect.disabled = true;
            unitsListDiv.innerHTML = 'Không có units nào được chọn.';
            selectedSubjectNameSpan.textContent = '';

            if (selectedStudentId) {
                loadUserVocabularies(selectedStudentId);
            }
        });

        subjectSelect.addEventListener('change', () => {
            selectedSubjectId = subjectSelect.value;
            if (selectedSubjectId && selectedClassId) {
                loadUnitsBySubjectAndClass(selectedSubjectId, selectedClassId, unitSelect, unitsListDiv);
            } else {
                unitSelect.innerHTML = '<option value="">Vui lòng chọn môn học và lớp trước</option>';
                unitSelect.disabled = true;
                unitsListDiv.innerHTML = 'Không có units nào được chọn.';
                selectedSubjectNameSpan.textContent = '';
            }
            if (selectedStudentId) {
                loadUserVocabularies(selectedStudentId);
            }
        });

        unitSelect.addEventListener('change', () => {
            if (selectedStudentId) {
                loadUserVocabularies(selectedStudentId);
            }
        });

        allSubjectsSelect.addEventListener('change', () => {
            selectedAllSubjectId = allSubjectsSelect.value;
            loadAllUnitsForSubject(selectedAllSubjectId);
        });

        assignSubjectToClassButton.addEventListener('click', assignSubjectToClass);
        addVocabularyButton.addEventListener('click', handleAddOrUpdateUserVocabulary);

        cancelEditButton.addEventListener('click', resetAddVocabForm);

        addUnitButton.addEventListener('click', addUnit);

        batchAddVocabularyButton.addEventListener('click', batchAddVocabulary);

        addVocabClassSelect.addEventListener('change', async () => {
            addVocabSelectedClassId = addVocabClassSelect.value ? parseInt(addVocabClassSelect.value) : null;
            addVocabSelectedSubjectId = null;
            addVocabSelectedUnitId = null;
            addVocabSubjectSelect.value = '';
            addVocabUnitSelect.value = '';
            await loadSubjectsByClass(addVocabSelectedClassId, addVocabSubjectSelect, true);
        });

        addVocabSubjectSelect.addEventListener('change', async () => {
            addVocabSelectedSubjectId = addVocabSubjectSelect.value ? parseInt(addVocabSubjectSelect.value) : null;
            addVocabSelectedUnitId = null;
            addVocabUnitSelect.value = '';
            if (addVocabSelectedSubjectId && addVocabSelectedClassId) {
                await loadUnitsBySubjectAndClass(addVocabSelectedSubjectId, addVocabSelectedClassId, addVocabUnitSelect);
            } else {
                addVocabUnitSelect.innerHTML = '<option value="">Vui lòng chọn môn học trước</option>';
                addVocabUnitSelect.disabled = true;
            }
        });

        addVocabUnitSelect.addEventListener('change', () => {
            addVocabSelectedUnitId = addVocabUnitSelect.value ? parseInt(addVocabUnitSelect.value) : null;
        });

        batchVocabClassSelect.addEventListener('change', async () => {
            batchVocabSelectedClassId = batchVocabClassSelect.value ? parseInt(batchVocabClassSelect.value) : null;
            batchVocabSelectedSubjectId = null;
            batchVocabSelectedUnitId = null;
            batchVocabSubjectSelect.value = '';
            batchVocabUnitSelect.value = '';
            await loadSubjectsByClass(batchVocabSelectedClassId, batchVocabSubjectSelect, true);
        });

        batchVocabSubjectSelect.addEventListener('change', async () => {
            batchVocabSelectedSubjectId = batchVocabSubjectSelect.value ? parseInt(batchVocabSubjectSelect.value) : null;
            batchVocabSelectedUnitId = null;
            batchVocabUnitSelect.value = '';
            if (batchVocabSelectedSubjectId && batchVocabSelectedClassId) {
                await loadUnitsBySubjectAndClass(batchVocabSelectedSubjectId, batchVocabSelectedClassId, batchVocabUnitSelect);
            } else {
                batchVocabUnitSelect.innerHTML = '<option value="">Vui lòng chọn môn học và lớp trước</option>';
                batchVocabUnitSelect.disabled = true;
            }
        });

        batchVocabUnitSelect.addEventListener('change', () => {
            batchVocabSelectedUnitId = batchVocabUnitSelect.value ? parseInt(batchVocabUnitSelect.value) : null;
        });

        // NEW: User Management Event Listeners
        createStudentAccountButton.addEventListener('click', createStudentAccount);
        changeUserPasswordButton.addEventListener('click', changeUserPassword);


        addEnglishWordInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addVietnameseMeaningInput.focus(); } });
        addVietnameseMeaningInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); handleAddOrUpdateUserVocabulary(); } });
        newUnitNameInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addUnit(); } });
        newPasswordInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); createStudentAccount(); } });
        newPasswordForUserInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); changeUserPassword(); } });


        // Reset form for adding/editing individual vocabulary
        function resetAddVocabForm() {
            editingVocabId = null;
            addEnglishWordInput.value = '';
            addVietnameseMeaningInput.value = '';
            addVocabClassSelect.value = '';
            addVocabSelectedClassId = null;
            addVocabSubjectSelect.value = '';
            addVocabSelectedSubjectId = null;
            addVocabUnitSelect.value = '';
            addVocabSelectedUnitId = null;
            addVocabSubjectSelect.disabled = true;
            addVocabUnitSelect.disabled = true;
            addVocabularyButton.textContent = 'Thêm Từ Vựng';
            cancelEditButton.style.display = 'none';
        }

        // Reset form for batch adding vocabulary
        function resetBatchAddForm() {
            batchVocabText.value = '';
            batchVocabClassSelect.value = '';
            batchVocabSelectedClassId = null;
            batchVocabSubjectSelect.value = '';
            batchVocabSelectedSubjectId = null;
            batchVocabUnitSelect.value = '';
            batchVocabSelectedUnitId = null;
            batchVocabSubjectSelect.disabled = true;
            batchVocabUnitSelect.disabled = true;
        }

        // --- Tab Switching Logic ---
        function openTab(tabId) {
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });

            document.getElementById(tabId).classList.add('active');
            document.querySelector(`.tab-button[onclick="openTab('${tabId}')"]`).classList.add('active');

            // Optionally, trigger data loads when a tab is opened
            if (tabId === 'viewDataTab' && selectedStudentId) {
                loadUserVocabularies(selectedStudentId);
            } else if (tabId === 'manageSubjectsTab' && selectedAllSubjectId) {
                loadAllUnitsForSubject(selectedAllSubjectId);
            }
            // For user management, ensure student list is up-to-date
            if (tabId === 'userManagementTab') {
                loadStudents(); // Reload students for user management dropdowns
            }
        }

        // --- Initialization on page load ---
        document.addEventListener('DOMContentLoaded', () => {
            loadStudents();
            loadClasses(classSelect); // Main filter dropdown
            loadClasses(addVocabClassSelect); // Add/Edit vocab dropdown
            loadClasses(batchVocabClassSelect); // Batch add vocab dropdown
            loadClasses(newStudentClassSelect); // NEW: New student account class dropdown
            loadAllSubjectsForDropdown(allSubjectsSelect); // General subject management dropdown

            // Set initial selected class/subject to null if not already set by history/params
            selectedClassId = classSelect.value ? parseInt(classSelect.value) : null;
            selectedSubjectId = subjectSelect.value ? parseInt(subjectSelect.value) : null;
            selectedAllSubjectId = allSubjectsSelect.value ? parseInt(allSubjectsSelect.value) : null;

            // Open the default tab (View Data)
            openTab('viewDataTab');
        });
    </script>
</body>
</html>
