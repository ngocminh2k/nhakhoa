-- Seed Data for Nha Khoa Kim Dung
SET NAMES utf8mb4;

-- Business Hours: Monday (1) to Sunday (0)
-- Open 08:00 - 19:30 every day
INSERT INTO `business_hours` (`weekday`, `start_time`, `end_time`, `active`) VALUES
(1, '08:00:00', '19:30:00', 1), -- Thứ 2
(2, '08:00:00', '19:30:00', 1), -- Thứ 3
(3, '08:00:00', '19:30:00', 1), -- Thứ 4
(4, '08:00:00', '19:30:00', 1), -- Thứ 5
(5, '08:00:00', '19:30:00', 1), -- Thứ 6
(6, '08:00:00', '19:30:00', 1), -- Thứ 7
(0, '08:00:00', '19:30:00', 1)  -- Chủ Nhật
ON DUPLICATE KEY UPDATE `start_time` = VALUES(`start_time`), `end_time` = VALUES(`end_time`);

-- Dental Services
INSERT INTO `services` (`id`, `name`, `slug`, `description`, `duration_minutes`, `price`, `active`, `sort_order`) VALUES
(1, 'Trồng răng Implant', 'trong-rang-implant', 'Cấy ghép trụ Implant phục hồi răng đã mất vĩnh viễn, ăn nhai tự nhiên.', 45, 0, 1, 1),
(2, 'Niềng răng thẩm mỹ', 'nieng-rang-tham-my', 'Chỉnh nha mắc cài, máng trong suốt giúp nụ cười đều đặn và chuẩn khớp cắn.', 45, 0, 1, 2),
(3, 'Bọc răng sứ thẩm mỹ', 'boc-rang-su-tham-my', 'Phục hình răng sứ thẩm mỹ cao cấp, bền chắc, màu sắc tự nhiên.', 45, 0, 1, 3),
(4, 'Nha khoa tổng quát / Khám & Tư vấn', 'nha-khoa-tong-quat', 'Khám răng tổng quát, chụp phim và tư vấn kế hoạch điều trị chi tiết.', 30, 0, 1, 4),
(5, 'Lấy cao răng & Chăm sóc nướu', 'lay-cao-rang', 'Làm sạch cao răng bằng sóng siêu âm không đau, bảo vệ men răng và nướu.', 30, 0, 1, 5),
(6, 'Tẩy trắng răng', 'tay-trang-rang', 'Tẩy trắng răng bằng công nghệ ánh sáng hiện đại, bật tông nhanh chóng an toàn.', 45, 0, 1, 6),
(7, 'Nhổ răng khôn (Piezotome)', 'nho-rang-khon', 'Nhổ răng khôn bằng sóng siêu âm Piezotome êm ái, hạn chế sưng đau, lành thương nhanh.', 45, 0, 1, 7),
(8, 'Điều trị tủy & Hàn trám răng', 'dieu-tri-tuy-han-tram-rang', 'Chữa tủy triệt để, hàn trám phục hồi răng sâu hoặc sứt mẻ thẩm mỹ.', 30, 0, 1, 8),
(9, 'Nha khoa trẻ em', 'nha-khoa-tre-em', 'Khám, nhổ răng sữa, hàn răng sâu và chỉnh nha tăng trưởng cho trẻ.', 30, 0, 1, 9)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `duration_minutes` = VALUES(`duration_minutes`),
  `description` = VALUES(`description`),
  `active` = VALUES(`active`),
  `sort_order` = VALUES(`sort_order`);

-- Sample API Key for external automation:
-- Raw key: kd_live_sec_7a8f9b2c3d4e5f6a1b2c3d4e5f6a7b8c
-- SHA-256 hash: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855 (dummy placeholder, setup script sets real one)
INSERT INTO `api_keys` (`id`, `name`, `key_hash`, `active`) VALUES
(1, 'Default Automation Bot', '7f4bc38b3a72688b1b5a93df757eef2c2c0697a22ef45b5463f619717cf7b196', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
