<?php
// admin_panel.php

// Cho phép CORS (quan trọng khi gọi từ các nguồn khác)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Bao gồm file kết nối database
// Đảm bảo đường dẫn này đúng với vị trí file db_connect.php của bạn
// Ví dụ: nếu admin_panel.php và db_connect.php cùng cấp, thì chỉ cần 'db_connect.php'
// Nếu admin_panel.php nằm trong thư mục con, ví dụ: my_quiz_api/admin_panel.php và db_connect.php là my_quiz_api/db_connect.php
// thì vẫn là 'db_connect.php' hoặc './db_connect.php'
include 'db_connect.php';

$response = array();

// --- PHP API Endpoints ---
// File này sẽ hoạt động như một API endpoint dựa trên tham số 'action' trong GET hoặc POST

if (isset($_GET['action'])) {
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

        case 'get_units_by_subject':
            $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
            if ($subject_id > 0) {
                $stmt = $conn->prepare("SELECT unit_id, unit_name FROM units WHERE subject_id = ? ORDER BY unit_id ASC");
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
                    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy unit nào cho môn học này.', 'data' => []]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID môn học.']);
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

        case 'get_user_vocabularies':
            $student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
            if ($student_id > 0) {
                $stmt = $conn->prepare("SELECT user_vocabulary_id, english_word, vietnamese_meaning FROM user_vocabularies WHERE student_id = ? ORDER BY english_word ASC");
                $stmt->bind_param("i", $student_id);
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

        case 'add_user_vocabulary':
            $input = json_decode(file_get_contents('php://input'), true);

            $student_id = $input['student_id'] ?? null;
            $english_word = $input['english_word'] ?? null;
            $vietnamese_meaning = $input['vietnamese_meaning'] ?? null;

            if ($student_id && $english_word && $vietnamese_meaning) {
                $stmt = $conn->prepare("INSERT INTO user_vocabularies (student_id, english_word, vietnamese_meaning) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $student_id, $english_word, $vietnamese_meaning);
                if ($stmt->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'Từ vựng đã được thêm cho học sinh.']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi khi thêm từ vựng: ' . $stmt->error]);
                }
                $stmt->close();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin từ vựng hoặc student_id.']);
            }
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
            break;
    }
    $conn->close();
    exit();
}
// --- Kết thúc PHP API Endpoints ---

// --- HTML & JavaScript cho giao diện người dùng ---
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Lý Từ Vựng Cá Nhân</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 25px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        select, input[type="text"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
        }
        input[type="text"]:focus, select:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.2);
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #218838;
        }
        #message {
            margin-top: 20px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        /* CSS cho thanh cuộn */
        .data-list {
            max-height: 200px; /* Tăng chiều cao tối đa lên 200px để dễ thấy hơn */
            overflow-y: auto;  /* Tự động hiển thị thanh cuộn dọc nếu nội dung vượt quá max-height */
            border: 1px solid #eee;
            padding: 10px;
            border-radius: 4px;
            background-color: #fff;
        }
        .data-list p {
            margin: 5px 0;
            padding: 5px;
            background-color: #e9e9e9;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bảng Quản Lý Từ Vựng</h1>
        <div id="message"></div>

        <div class="section">
            <h2>Xem Dữ Liệu</h2>
            <div class="form-group">
                <label for="studentSelect">Chọn Học Sinh:</label>
                <select id="studentSelect">
                    <option value="">Đang tải...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="classSelect">Chọn Lớp:</label>
                <select id="classSelect">
                    <option value="">Đang tải...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="subjectSelect">Chọn Môn Học:</label>
                <select id="subjectSelect">
                    <option value="">Đang tải...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="unitSelect">Chọn Unit (nếu là Tiếng Anh):</label>
                <select id="unitSelect">
                    <option value="">Vui lòng chọn môn học trước</option>
                </select>
            </div>
            <h3>Từ Vựng Cá Nhân của Học Sinh <span id="selectedStudentName"></span>:</h3>
            <div id="userVocabList" class="data-list">
                Không có từ vựng cá nhân nào được chọn.
            </div>
        </div>

        <div class="section">
            <h2>Thêm Từ Vựng Cá Nhân</h2>
            <p>Chọn học sinh ở trên để thêm từ vựng riêng cho học sinh đó.</p>
            <div class="form-group">
                <label for="addEnglishWord">Từ tiếng Anh:</label>
                <input type="text" id="addEnglishWord" placeholder="Nhập từ tiếng Anh">
            </div>
            <div class="form-group">
                <label for="addVietnameseMeaning">Nghĩa tiếng Việt:</label>
                <input type="text" id="addVietnameseMeaning" placeholder="Nhập nghĩa tiếng Việt">
            </div>
            <button id="addVocabularyButton">Thêm Từ Vựng</button>
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
        const selectedStudentNameSpan = document.getElementById('selectedStudentName');
        const userVocabListDiv = document.getElementById('userVocabList');

        let studentsData = []; // Lưu trữ dữ liệu học sinh
        let selectedStudentId = null;

        // Lấy URL gốc của file hiện tại để gọi các action API
        const API_BASE_URL = window.location.origin + window.location.pathname;

        function showMessage(msg, type) {
            messageDiv.textContent = msg;
            messageDiv.className = `message ${type}`;
            setTimeout(() => {
                messageDiv.textContent = '';
                messageDiv.className = 'message';
            }, 3000);
        }

        async function fetchData(action, params = {}) {
            let url = new URL(API_BASE_URL);
            url.searchParams.append('action', action);
            for (const key in params) {
                url.searchParams.append(key, params[key]);
            }
            try {
                const response = await fetch(url);
                return await response.json();
            } catch (error) {
                console.error(`Lỗi khi tải dữ liệu cho ${action}:`, error);
                showMessage(`Lỗi kết nối khi tải ${action}.`, 'error');
                return { status: 'error', message: 'Lỗi kết nối.' };
            }
        }

        async function loadStudents() {
            const response = await fetchData('get_students');
            studentSelect.innerHTML = '<option value="">-- Chọn Học Sinh --</option>';
            if (response.status === 'success' && response.data.length > 0) {
                studentsData = response.data; // Lưu trữ dữ liệu học sinh
                response.data.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.student_id;
                    option.textContent = `${student.student_name} (${student.username}) - ${student.class_name || 'Chưa có lớp'}`;
                    studentSelect.appendChild(option);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách học sinh.', 'error');
            }
        }

        async function loadClasses() {
            const response = await fetchData('get_classes');
            classSelect.innerHTML = '<option value="">-- Chọn Lớp --</option>';
            if (response.status === 'success' && response.data.length > 0) {
                response.data.forEach(cls => {
                    const option = document.createElement('option');
                    option.value = cls.class_id;
                    option.textContent = cls.class_name;
                    classSelect.appendChild(option);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách lớp.', 'error');
            }
        }

        async function loadSubjects() {
            const response = await fetchData('get_subjects');
            subjectSelect.innerHTML = '<option value="">-- Chọn Môn Học --</option>';
            if (response.status === 'success' && response.data.length > 0) {
                response.data.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject.subject_id;
                    option.textContent = subject.subject_name;
                    subjectSelect.appendChild(option);
                });
            } else {
                showMessage(response.message || 'Không thể tải danh sách môn học.', 'error');
            }
        }

        async function loadUnitsBySubject(subjectId) {
            unitSelect.innerHTML = '<option value="">Đang tải...</option>';
            unitSelect.disabled = true;
            if (subjectId) {
                const response = await fetchData('get_units_by_subject', { subject_id: subjectId });
                unitSelect.innerHTML = '<option value="">-- Chọn Unit --</option>';
                if (response.status === 'success' && response.data.length > 0) {
                    response.data.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.unit_id;
                        option.textContent = unit.unit_name;
                        unitSelect.appendChild(option);
                    });
                    unitSelect.disabled = false;
                } else {
                    showMessage(response.message || 'Không tìm thấy units cho môn này.', 'error');
                    unitSelect.innerHTML = '<option value="">Không có Unit nào</option>';
                }
            } else {
                unitSelect.innerHTML = '<option value="">Vui lòng chọn môn học trước</option>';
            }
        }

        async function loadUserVocabularies(studentId) {
            userVocabListDiv.innerHTML = 'Đang tải từ vựng cá nhân...';
            if (!studentId) {
                userVocabListDiv.innerHTML = 'Vui lòng chọn học sinh.';
                selectedStudentNameSpan.textContent = '';
                return;
            }
            const response = await fetchData('get_user_vocabularies', { student_id: studentId });

            if (response.status === 'success' && response.data.length > 0) {
                userVocabListDiv.innerHTML = ''; // Xóa nội dung cũ
                response.data.forEach(word => {
                    const p = document.createElement('p');
                    p.textContent = `${word.english_word}: ${word.vietnamese_meaning}`;
                    userVocabListDiv.appendChild(p);
                });
            } else {
                userVocabListDiv.innerHTML = response.message || 'Không có từ vựng cá nhân nào cho học sinh này.';
            }
        }

        async function addUserVocabulary() {
            if (!selectedStudentId) {
                showMessage('Vui lòng chọn một học sinh trước khi thêm từ vựng.', 'error');
                return;
            }
            const englishWord = addEnglishWordInput.value.trim();
            const vietnameseMeaning = addVietnameseMeaningInput.value.trim();

            if (!englishWord || !vietnameseMeaning) {
                showMessage('Vui lòng nhập đầy đủ từ tiếng Anh và nghĩa tiếng Việt.', 'error');
                return;
            }

            const response = await fetch(API_BASE_URL + '?action=add_user_vocabulary', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    student_id: selectedStudentId,
                    english_word: englishWord,
                    vietnamese_meaning: vietnameseMeaning
                })
            });
            const result = await response.json();

            if (result.status === 'success') {
                showMessage('Thêm từ vựng thành công!', 'success');
                addEnglishWordInput.value = '';
                addVietnameseMeaningInput.value = '';
                loadUserVocabularies(selectedStudentId); // Tải lại danh sách từ vựng cá nhân
            } else {
                showMessage(result.message || 'Thêm từ vựng thất bại.', 'error');
            }
        }

        // --- Event Listeners ---
        studentSelect.addEventListener('change', () => {
            selectedStudentId = studentSelect.value;
            const selectedStudent = studentsData.find(s => s.student_id == selectedStudentId);
            selectedStudentNameSpan.textContent = selectedStudent ? selectedStudent.student_name : '';
            loadUserVocabularies(selectedStudentId);
        });

        subjectSelect.addEventListener('change', () => {
            const subjectId = subjectSelect.value;
            loadUnitsBySubject(subjectId);
        });

        addVocabularyButton.addEventListener('click', addUserVocabulary);

        // Cho phép nhấn Enter để thêm từ vựng
        addEnglishWordInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addVietnameseMeaningInput.focus();
            }
        });
        addVietnameseMeaningInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addUserVocabulary();
            }
        });

        // --- Khởi tạo khi tải trang ---
        document.addEventListener('DOMContentLoaded', () => {
            loadStudents();
            loadClasses();
            loadSubjects();
        });
    </script>
</body>
</html>