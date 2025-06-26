-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th6 26, 2025 lúc 02:31 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `quiz_app_db`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`) VALUES
(1, '10A1'),
(2, '10A2'),
(3, '11B1'),
(4, '11B2'),
(5, '12C1');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `questions`
--

CREATE TABLE `questions` (
  `question_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `questions`
--

INSERT INTO `questions` (`question_id`, `subject_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `created_at`, `updated_at`) VALUES
(1, 1, '2 + 3 = ?', '4', '5', '6', '7', 'B', '2025-06-25 18:54:45', '2025-06-25 18:54:45'),
(2, 2, 'Đơn vị của lực là gì?', 'Joule', 'Watt', 'Newton', 'Volt', 'C', '2025-06-25 18:54:45', '2025-06-25 18:54:45'),
(3, 4, 'Chiến thắng Điện Biên Phủ diễn ra vào năm nào?', '1945', '1954', '1975', '1968', 'B', '2025-06-25 18:54:45', '2025-06-25 18:54:45');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `quiz_attempts`
--

CREATE TABLE `quiz_attempts` (
  `attempt_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `quiz_date` datetime DEFAULT current_timestamp(),
  `correct_answers` int(11) NOT NULL,
  `wrong_answers` int(11) NOT NULL,
  `total_questions_quizzed` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `quiz_attempts`
--

INSERT INTO `quiz_attempts` (`attempt_id`, `student_id`, `unit_id`, `quiz_date`, `correct_answers`, `wrong_answers`, `total_questions_quizzed`) VALUES
(1, 1, 1, '2025-06-25 21:30:36', 3, 2, 5),
(2, 4, 1, '2025-06-25 21:32:37', 1, 6, 7),
(3, 4, 1, '2025-06-25 22:13:43', 2, 5, 7),
(4, 4, 2, '2025-06-25 22:16:01', 4, 0, 4);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_name` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `students`
--

INSERT INTO `students` (`student_id`, `student_name`, `username`, `password_hash`, `class_id`, `created_at`) VALUES
(1, 'Nguyễn Văn A', 'nguyenvana', '$2y$10$p0/yC.2z/eR.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t.1t', 1, '2025-06-25 18:54:12'),
(2, 'Trần Thị B', 'tranthib', '$2y$10$f0/xX.2y/eR.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u.2u', NULL, '2025-06-25 18:54:12'),
(3, 'Nguyen Van C', 'nguyenvanc', '$2y$10$DrCDN9/91G093jyW7NbCyeldNpOXx9cCtWtN7EaBz9dZNQIsrHPmS', NULL, '2025-06-25 19:10:54'),
(4, 'định anh', 'anhduy1900', '$2y$10$ojymCi/G5pGvQa/lWg/bf.1uMBbhkDTQylKQZgkFhSqAl04.8Liam', NULL, '2025-06-25 19:27:27');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_answers`
--

CREATE TABLE `student_answers` (
  `student_answer_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `chosen_option` enum('A','B','C','D') NOT NULL,
  `is_correct_attempt` tinyint(1) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `student_answers`
--

INSERT INTO `student_answers` (`student_answer_id`, `student_id`, `question_id`, `chosen_option`, `is_correct_attempt`, `attempt_time`) VALUES
(1, 1, 1, 'B', 1, '2025-06-25 18:55:21'),
(2, 1, 3, 'A', 0, '2025-06-25 18:55:21'),
(3, 2, 2, 'C', 1, '2025-06-25 18:55:21');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_vocabulary_answers`
--

CREATE TABLE `student_vocabulary_answers` (
  `answer_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `vocabulary_id` int(11) NOT NULL,
  `chosen_meaning` varchar(255) NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `answer_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `student_vocabulary_answers`
--

INSERT INTO `student_vocabulary_answers` (`answer_id`, `student_id`, `vocabulary_id`, `chosen_meaning`, `is_correct`, `answer_time`) VALUES
(1, 4, 5, 'xin lỗi', 1, '2025-06-25 13:39:18'),
(2, 4, 2, 'tạm biệt', 1, '2025-06-25 13:39:20'),
(3, 4, 4, 'làm ơn', 1, '2025-06-25 13:39:24'),
(4, 4, 6, 'vâng', 1, '2025-06-25 13:39:27'),
(5, 4, 7, 'không', 1, '2025-06-25 13:39:30'),
(6, 4, 3, 'cảm ơn', 1, '2025-06-25 13:39:32'),
(7, 4, 1, 'xin chào', 1, '2025-06-25 13:39:34'),
(8, 4, 3, 'cảm ơn', 1, '2025-06-25 13:42:30'),
(9, 4, 6, 'vâng', 1, '2025-06-25 13:42:40'),
(10, 4, 1, 'xin chào', 1, '2025-06-25 13:42:42'),
(11, 4, 5, 'vâng', 0, '2025-06-25 13:42:46'),
(12, 4, 7, 'không', 1, '2025-06-25 13:42:48'),
(13, 4, 4, 'làm ơn', 1, '2025-06-25 13:42:51'),
(14, 4, 2, 'tạm biệt', 1, '2025-06-25 13:42:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `subjects`
--

INSERT INTO `subjects` (`subject_id`, `subject_name`) VALUES
(3, 'Hóa học'),
(4, 'Lịch sử'),
(5, 'tiếng Anh'),
(1, 'Toán học'),
(2, 'Vật lý');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `units`
--

CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `unit_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `units`
--

INSERT INTO `units` (`unit_id`, `subject_id`, `unit_name`) VALUES
(1, 1, 'Unit 1: Greetings'),
(2, 1, 'Unit 2: Family'),
(3, 1, 'Unit 3: School');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_vocabularies`
--

CREATE TABLE `user_vocabularies` (
  `user_vocabulary_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `english_word` varchar(255) NOT NULL,
  `vietnamese_meaning` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `user_vocabularies`
--

INSERT INTO `user_vocabularies` (`user_vocabulary_id`, `student_id`, `english_word`, `vietnamese_meaning`, `created_at`) VALUES
(1, 4, 'hello', 'xin chào', '2025-06-25 14:48:39'),
(2, 4, 'now', 'bây giờ', '2025-06-25 14:49:15');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vocabulary`
--

CREATE TABLE `vocabulary` (
  `vocabulary_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `english_word` varchar(255) NOT NULL,
  `vietnamese_meaning` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vocabulary`
--

INSERT INTO `vocabulary` (`vocabulary_id`, `unit_id`, `english_word`, `vietnamese_meaning`) VALUES
(1, 1, 'hello', 'xin chào'),
(2, 1, 'goodbye', 'tạm biệt'),
(3, 1, 'thank you', 'cảm ơn'),
(4, 1, 'please', 'làm ơn'),
(5, 1, 'excuse me', 'xin lỗi'),
(6, 1, 'yes', 'vâng'),
(7, 1, 'no', 'không'),
(8, 2, 'mother', 'mẹ'),
(9, 2, 'father', 'cha'),
(10, 2, 'sister', 'chị/em gái'),
(11, 2, 'brother', 'anh/em trai');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `wrong_answers`
--

CREATE TABLE `wrong_answers` (
  `wrong_answer_id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `vocabulary_id` int(11) NOT NULL,
  `student_answer` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `wrong_answers`
--

INSERT INTO `wrong_answers` (`wrong_answer_id`, `attempt_id`, `vocabulary_id`, `student_answer`) VALUES
(1, 1, 10, 'meaning_A'),
(3, 2, 6, 'tạm biệt'),
(4, 2, 4, 'xin chào'),
(5, 2, 5, 'vâng'),
(6, 2, 3, 'vâng'),
(7, 2, 1, 'không'),
(8, 2, 2, 'cảm ơn'),
(9, 3, 7, 'tạm biệt'),
(10, 3, 3, 'vâng'),
(11, 3, 6, 'xin chào'),
(12, 3, 4, 'xin lỗi'),
(13, 3, 2, 'làm ơn');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`),
  ADD UNIQUE KEY `class_name` (`class_name`);

--
-- Chỉ mục cho bảng `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`question_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Chỉ mục cho bảng `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD PRIMARY KEY (`attempt_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Chỉ mục cho bảng `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_student_class` (`class_id`);

--
-- Chỉ mục cho bảng `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`student_answer_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Chỉ mục cho bảng `student_vocabulary_answers`
--
ALTER TABLE `student_vocabulary_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `vocabulary_id` (`vocabulary_id`);

--
-- Chỉ mục cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`);

--
-- Chỉ mục cho bảng `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Chỉ mục cho bảng `user_vocabularies`
--
ALTER TABLE `user_vocabularies`
  ADD PRIMARY KEY (`user_vocabulary_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `vocabulary`
--
ALTER TABLE `vocabulary`
  ADD PRIMARY KEY (`vocabulary_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Chỉ mục cho bảng `wrong_answers`
--
ALTER TABLE `wrong_answers`
  ADD PRIMARY KEY (`wrong_answer_id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `vocabulary_id` (`vocabulary_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `questions`
--
ALTER TABLE `questions`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  MODIFY `attempt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `student_answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `student_vocabulary_answers`
--
ALTER TABLE `student_vocabulary_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `user_vocabularies`
--
ALTER TABLE `user_vocabularies`
  MODIFY `user_vocabulary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `vocabulary`
--
ALTER TABLE `vocabulary`
  MODIFY `vocabulary_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `wrong_answers`
--
ALTER TABLE `wrong_answers`
  MODIFY `wrong_answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `quiz_attempts`
--
ALTER TABLE `quiz_attempts`
  ADD CONSTRAINT `quiz_attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `quiz_attempts_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`);

--
-- Các ràng buộc cho bảng `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`);

--
-- Các ràng buộc cho bảng `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`question_id`) ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `student_vocabulary_answers`
--
ALTER TABLE `student_vocabulary_answers`
  ADD CONSTRAINT `student_vocabulary_answers_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_vocabulary_answers_ibfk_2` FOREIGN KEY (`vocabulary_id`) REFERENCES `vocabulary` (`vocabulary_id`);

--
-- Các ràng buộc cho bảng `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Các ràng buộc cho bảng `user_vocabularies`
--
ALTER TABLE `user_vocabularies`
  ADD CONSTRAINT `user_vocabularies_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `vocabulary`
--
ALTER TABLE `vocabulary`
  ADD CONSTRAINT `vocabulary_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`);

--
-- Các ràng buộc cho bảng `wrong_answers`
--
ALTER TABLE `wrong_answers`
  ADD CONSTRAINT `wrong_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`attempt_id`),
  ADD CONSTRAINT `wrong_answers_ibfk_2` FOREIGN KEY (`vocabulary_id`) REFERENCES `vocabulary` (`vocabulary_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
