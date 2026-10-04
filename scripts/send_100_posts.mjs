// scripts/send_100_posts.mjs
import http from 'http';

const API_KEY = 'kd_batch_publisher_secret_key_2026';
const API_URL = 'http://127.0.0.1:8080/api/v1/posts';

// 100 Unique Dental Topics across 5 Specialties
const topics = [
  // 1. Trồng răng Implant (20 topics)
  { cat: "Implant", title: "Cấy Ghép Implant Toàn Hàm All-on-4: Giải Pháp Cho Người Mất Hết Răng", service: "/website/trong-rang-implant.html", tag: "trồng răng Implant All-on-4" },
  { cat: "Implant", title: "Trụ Implant Straumann Thụy Sĩ: Tích Hợp Xương Đỉnh Cao Trong 3 Tuần", service: "/website/trong-rang-implant.html", tag: "trụ Implant Straumann" },
  { cat: "Implant", title: "Trụ Implant Dentium Hàn Quốc: Lựa Chọn Tiết Kiệm Độ Bền Trên 20 Năm", service: "/website/trong-rang-implant.html", tag: "Implant Dentium Hàn Quốc" },
  { cat: "Implant", title: "Kỹ Thuật Ghép Xương Nhân Tạo Trong Cấy Ghép Nha Khoa Chuyên Sâu", service: "/website/trong-rang-implant.html", tag: "ghép xương nhân tạo" },
  { cat: "Implant", title: "Nâng Xoang Hàm Kín Và Hở: Khi Nào Bắt Buộc Phải Chỉ Định?", service: "/website/trong-rang-implant.html", tag: "nâng xoang cấy ghép răng" },
  { cat: "Implant", title: "Trồng Răng Implant Tức Thì Sau Nhổ Răng: Điều Kiện Để Thực Hiện", service: "/website/trong-rang-implant.html", tag: "cấy Implant tức thì" },
  { cat: "Implant", title: "Tuổi Thọ Của Răng Implant: Có Thực Sự Bền Vững Trọn Đời?", service: "/website/trong-rang-implant.html", tag: "tuổi thọ răng Implant" },
  { cat: "Implant", title: "So Sánh Răng Implant Và Cầu Răng Sứ Truyền Thống: Chọn Phương Án Nào?", service: "/website/trong-rang-implant.html", tag: "so sánh Implant và cầu răng" },
  { cat: "Implant", title: "Bảng Giá Cấy Ghép Răng Implant Trọn Gói Mới Nhất Tại Nha Khoa Kim Dung", service: "/website/bang-gia.html", tag: "bảng giá cấy Implant" },
  { cat: "Implant", title: "Quy Trình Chăm Sóc Vệ Sinh Răng Implant Hạn Chế Viêm Quanh Trụ", service: "/website/trong-rang-implant.html", tag: "vệ sinh răng Implant" },
  { cat: "Implant", title: "Cấy Ghép Răng Implant All-on-6 Cho Khung Hàm Răng Dày Chắc Khỏe", service: "/website/trong-rang-implant.html", tag: "cấy ghép All-on-6" },
  { cat: "Implant", title: "Người Bị Bệnh Tiểu Đường Có Trồng Răng Implant Được Không?", service: "/website/trong-rang-implant.html", tag: "trồng răng cho người tiểu đường" },
  { cat: "Implant", title: "Công Nghệ Định Vị Phẫu Thuật 3D Máng Hướng Dẫn Cấy Răng Implant", service: "/website/trong-rang-implant.html", tag: "máng định vị cấy ghép 3D" },
  { cat: "Implant", title: "Biến Chứng Nguy Hiểm Khi Cấy Ghép Implant Giá Rẻ Tại Cơ Sở Kém Chất Lượng", service: "/website/trong-rang-implant.html", tag: "biến chứng Implant giá rẻ" },
  { cat: "Implant", title: "Mất Răng Cửa Lâu Năm Bị Tiêu Xương: Giải Pháp Khắc Phục Toàn Diện", service: "/website/trong-rang-implant.html", tag: "tiêu xương răng cửa" },
  { cat: "Implant", title: "Trụ Implant Nobel Biocare Mỹ: Tiêu Chuẩn Vàng Phục Hình Răng Đã Mất", service: "/website/trong-rang-implant.html", tag: "Implant Nobel Biocare" },
  { cat: "Implant", title: "Tại Sao Nên Chờ Từ 3 Đến 6 Tháng Để Gắn Mão Sứ Lên Trụ Implant?", service: "/website/trong-rang-implant.html", tag: "thời gian tích hợp xương" },
  { cat: "Implant", title: "Khám Phá Phòng Phẫu Thuật Vô Trùng Khép Kín Chuẩn Bộ Y Tế", service: "/website/bac-si.html", tag: "phòng phẫu thuật vô trùng" },
  { cat: "Implant", title: "Người Cao Tuổi Mất Răng Toàn Hàm: Lấy Lại Khả Năng Ăn Nhai Nhờ Implant", service: "/website/trong-rang-implant.html", tag: "trồng răng người cao tuổi" },
  { cat: "Implant", title: "Bảo Hành Chính Hãng Trụ Implant Kim Dung: Cam Kết Bằng Văn Bản", service: "/website/bang-gia.html", tag: "chính sách bảo hành Implant" },

  // 2. Bọc răng sứ & Dán sứ Veneer (20 topics)
  { cat: "Răng sứ", title: "Dán Sứ Veneer Siêu Mỏng: Bảo Tồn 100% Tủy Răng Sống Thật", service: "/website/boc-rang-su.html", tag: "dán sứ Veneer không mài" },
  { cat: "Răng sứ", title: "Bọc Răng Sứ Cercon HT: Đẹp Tự Nhiên Không Bao Giờ Đen Viền Lợi", service: "/website/boc-rang-su.html", tag: "răng toàn sứ Cercon HT" },
  { cat: "Răng sứ", title: "Răng Sứ Thẩm Mỹ Lava Plus 3M Mỹ: Khả Năng Chịu Lực 2000 MPa", service: "/website/boc-rang-su.html", tag: "răng sứ Lava Plus" },
  { cat: "Răng sứ", title: "Nên Bọc Răng Sứ Hay Dán Veneer? Bác Sĩ Chuyên Khoa Giải Đáp Chi Tiết", service: "/website/boc-rang-su.html", tag: "so sánh bọc sứ và dán Veneer" },
  { cat: "Răng sứ", title: "Cách Chọn Màu Sắc Răng Sứ Hài Hòa Với Khuôn Mặt Và Màu Da", service: "/website/boc-rang-su.html", tag: "chọn màu răng sứ thẩm mỹ" },
  { cat: "Răng sứ", title: "Quy Trình Bọc Răng Sứ Chuẩn Y Khoa Không Đau Tại Thái Nguyên", service: "/website/boc-rang-su.html", tag: "quy trình bọc răng sứ" },
  { cat: "Răng sứ", title: "Khắc Phục Tình Trạng Hở Viền Nướu Khi Làm Răng Sứ Kém Chất Lượng", service: "/website/boc-rang-su.html", tag: "xử lý đen viền nướu" },
  { cat: "Răng sứ", title: "Bọc Răng Sứ Cho Răng Cửa Bị Sứt Mẻ Lấy Lại Nụ Cười Rạng Rỡ", service: "/website/boc-rang-su.html", tag: "phục hình răng cửa sứ" },
  { cat: "Răng sứ", title: "Chế Độ Ăn Uống Sau Khi Bọc Răng Sứ Để Giữ Độ Bóng Sáng Lâu Bền", service: "/website/boc-rang-su.html", tag: "chăm sóc răng bọc sứ" },
  { cat: "Răng sứ", title: "Răng Toàn Sứ Emax Press: Độ Trong Mờ Hoàn Hảo Như Men Răng Thật", service: "/website/boc-rang-su.html", tag: "sứ Emax Press cao cấp" },
  { cat: "Răng sứ", title: "Răng Sứ Katana Nhật Bản: Dòng Răng Toàn Sứ Quốc Dân Giá Hợp Lý", service: "/website/boc-rang-su.html", tag: "răng sứ Katana Nhật Bản" },
  { cat: "Răng sứ", title: "Bọc Răng Sứ Khắc Phục Răng Nhiễm Màu Kháng Sinh Tetracycline Nặng", service: "/website/boc-rang-su.html", tag: "chữa răng nhiễm Tetracycline" },
  { cat: "Răng sứ", title: "Công Nghệ Lấy Dấu Hàm 3D Scan Không Buồn Nôn Tại Kim Dung", service: "/website/boc-rang-su.html", tag: "lấy dấu răng scan 3D" },
  { cat: "Răng sứ", title: "Răng Sứ Bị Mẻ Phải Làm Sao? Cách Xử Lý Nhanh Chóng Kịp Thời", service: "/website/boc-rang-su.html", tag: "xử lý răng sứ bị mẻ" },
  { cat: "Răng sứ", title: "Có Nên Bọc Răng Sứ Cho Răng Hàm Không? Độ Bền Và Khả Năng Nhai", service: "/website/boc-rang-su.html", tag: "bọc sứ răng hàm nhai" },
  { cat: "Răng sứ", title: "Tuổi Thọ Của Răng Toàn Sứ Cao Cấp Kéo Dài Được Bao Nhiêu Năm?", service: "/website/boc-rang-su.html", tag: "độ bền răng toàn sứ" },
  { cat: "Răng sứ", title: "Dán Sứ Veneer 2 Răng Cửa Bị Thưa: Đẹp Ngay Chỉ Sau 2 Lần Hẹn", service: "/website/boc-rang-su.html", tag: "dán Veneer khe thưa" },
  { cat: "Răng sứ", title: "Bảng Giá Các Dòng Răng Sứ Chính Hãng Đức Mỹ Nhật Năm 2026", service: "/website/bang-gia.html", tag: "bảng giá làm răng sứ" },
  { cat: "Răng sứ", title: "Thẻ Bảo Hành Răng Sứ Điện Tử Chính Hãng Quét Mã QR Tra Cứu", service: "/website/boc-rang-su.html", tag: "thẻ bảo hành răng sứ" },
  { cat: "Răng sứ", title: "Phục Hình Cầu Răng Sứ: Giải Pháp Cổ Điển Tiết Kiệm Cho Người Mất Răng", service: "/website/boc-rang-su.html", tag: "cầu răng sứ thẩm mỹ" },

  // 3. Niềng răng & Chỉnh nha (20 topics)
  { cat: "Niềng răng", title: "Niềng Răng Trong Suốt Invisalign: Thẩm Mỹ Vô Hình Khó Nhận Biết", service: "/website/nieng-rang-tham-my.html", tag: "niềng răng Invisalign" },
  { cat: "Niềng răng", title: "Niềng Răng Mắc Cài Kim Loại Tự Buộc: Rút Ngắn Thời Gian 6 Tháng", service: "/website/nieng-rang-mac-cai.html", tag: "mắc cài kim loại tự buộc" },
  { cat: "Niềng răng", title: "Niềng Răng Mắc Cài Sứ Thẩm Mỹ: Tự Tin Giao Tiếp Mọi Lúc", service: "/website/nieng-rang-mac-cai.html", tag: "niềng răng mắc cài sứ" },
  { cat: "Niềng răng", title: "Thời Điểm Vàng Để Niềng Răng Cho Trẻ Em: Từ 7 Đến 12 Tuổi", service: "/website/nieng-rang-tham-my.html", tag: "chỉnh nha trẻ em thời điểm vàng" },
  { cat: "Niềng răng", title: "30 Tuổi Có Niềng Răng Được Không? Kết Quả Thực Tế Đầy Bất Ngờ", service: "/website/nieng-rang-tham-my.html", tag: "niềng răng người lớn tuổi" },
  { cat: "Niềng răng", title: "Niềng Răng Có Bị Hóp Má Không? Nguyên Nhân Và Cách Khắc Phục", service: "/website/nieng-rang-tham-my.html", tag: "niềng răng bị hóp má" },
  { cat: "Niềng răng", title: "Khí Cụ Nong Hàm Trong Chỉnh Nha: Khi Nào Bác Sĩ Chỉ Định?", service: "/website/nieng-rang-mac-cai.html", tag: "nong hàm chỉnh nha" },
  { cat: "Niềng răng", title: "Hướng Dẫn Vệ Sinh Răng Miệng Cho Người Đeo Niềng Với Máy Tăm Nước", service: "/website/nieng-rang-mac-cai.html", tag: "chăm sóc răng niềng" },
  { cat: "Niềng răng", title: "Niềng Răng Hô Móm Lệch Lạc Khớp Cắn: Cải Thiện Khuôn Mặt V-line", service: "/website/nieng-rang-tham-my.html", tag: "niềng răng thay đổi khuôn mặt" },
  { cat: "Niềng răng", title: "Chính Sách Niềng Răng Trả Góp 0% Lãi Suất Tại Nha Khoa Kim Dung", service: "/website/bang-gia.html", tag: "niềng răng trả góp 0%" },
  { cat: "Niềng răng", title: "Có Cần Phải Nhổ Răng Khôn Trước Khi Bắt Đầu Niềng Răng Không?", service: "/website/nieng-rang-mac-cai.html", tag: "nhổ răng khôn niềng răng" },
  { cat: "Niềng răng", title: "Gắn Miniscrew Trong Niềng Răng: Kỹ Thuật Kéo Răng Tăng Tốc Độ", service: "/website/nieng-rang-mac-cai.html", tag: "cắm miniscrew chỉnh nha" },
  { cat: "Niềng răng", title: "Tầm Quan Trọng Của Việc Đeo Hàm Duy Trì Sau Khi Tháo Niềng Răng", service: "/website/nieng-rang-tham-my.html", tag: "đeo hàm duy trì sau niềng" },
  { cat: "Niềng răng", title: "Răng Khấp Khểnh Khớp Cắn Ngược Ở Trẻ: Điều Trị Sớm Bằng Khí Cụ Myobrace", service: "/website/nieng-rang-tham-my.html", tag: "tiền chỉnh nha Myobrace" },
  { cat: "Niềng răng", title: "So Sánh Khay Niềng Trong Suốt Và Mắc Cài Truyền Thống Toàn Diện", service: "/website/nieng-rang-tham-my.html", tag: "so sánh khay niềng và mắc cài" },
  { cat: "Niềng răng", title: "Ăn Uống Gì Trong Những Ngày Đầu Tiên Mới Gắn Mắc Cài Niềng Răng?", service: "/website/nieng-rang-mac-cai.html", tag: "thực đơn cho người niềng răng" },
  { cat: "Niềng răng", title: "Kế Hoạch Chỉnh Nha Kỹ Thuật Số ClinCheck Thấy Trước Kết Quả 3D", service: "/website/nieng-rang-tham-my.html", tag: "mô phỏng 3D ClinCheck" },
  { cat: "Niềng răng", title: "Niềng Răng Mắc Cài Mặt Trong (Mặt Lưỡi): Bí Mật Chỉnh Nha Kín Đáo", service: "/website/nieng-rang-mac-cai.html", tag: "mắc cài mặt trong" },
  { cat: "Niềng răng", title: "Bao Lâu Thì Phải Tái Khám Một Lần Khi Đang Niềng Răng Chuyên Khoa?", service: "/website/nieng-rang-mac-cai.html", tag: "lịch tái khám niềng răng" },
  { cat: "Niềng răng", title: "Đội Ngũ Bác Sĩ Chỉnh Nha Tu Nghiệp Quốc Tế Tại Nha Khoa Kim Dung", service: "/website/bac-si.html", tag: "bác sĩ niềng răng uy tín" },

  // 4. Nha khoa tổng quát & Điều trị bệnh lý (20 topics)
  { cat: "Điều trị", title: "Nhổ Răng Khôn Bằng Sóng Siêu Âm Piezotome: Không Đau Mau Lành", service: "/website/nha-khoa-tong-quat.html", tag: "nhổ răng khôn sóng siêu âm" },
  { cat: "Điều trị", title: "Điều Trị Viêm Tủy Răng Bằng Công Nghệ Vi Phẫu Bảo Tồn Răng Thật", service: "/website/nha-khoa-tong-quat.html", tag: "điều trị tủy răng vi phẫu" },
  { cat: "Điều trị", title: "Trám Răng Thẩm Mỹ Bằng Composite: Tái Tạo Hình Dáng Răng Tự Nhiên", service: "/website/nha-khoa-tong-quat.html", tag: "trám răng thẩm mỹ Composite" },
  { cat: "Điều trị", title: "Cạo Vôi Răng Định Kỳ 6 Tháng Một Lần: Lá Chắn Bảo Vệ Nụ Cười", service: "/website/nha-khoa-tong-quat.html", tag: "lấy cao răng siêu âm" },
  { cat: "Điều trị", title: "Điều Trị Viêm Nha Chu Triệt Để: Chấm Dứt Nỗi Lo Chảy Máu Chân Răng", service: "/website/nha-khoa-tong-quat.html", tag: "chữa bệnh viêm nha chu" },
  { cat: "Điều trị", title: "Chữa Hôi Miệng Tận Gốc: Tìm Ra Đúng Nguyên Nhân Bệnh Lý Răng Miệng", service: "/website/nha-khoa-tong-quat.html", tag: "chữa dứt điểm hôi miệng" },
  { cat: "Điều trị", title: "Hàn Trám Răng Sâu Bằng Miếng Trám Inlay Onlay Bằng Sứ Siêu Bền", service: "/website/nha-khoa-tong-quat.html", tag: "hàn răng Inlay Onlay" },
  { cat: "Điều trị", title: "Cảnh Báo Biến Chứng Nguy Hiểm Khi Răng Khôn Mọc Lệch Mọc Ngầm", service: "/website/nha-khoa-tong-quat.html", tag: "răng khôn mọc ngầm lệch" },
  { cat: "Điều trị", title: "Ê Buốt Răng Khi Uống Nước Lạnh: Dấu Hiệu Mòn Men Cổ Chân Răng", service: "/website/nha-khoa-tong-quat.html", tag: "điều trị ê buốt chân răng" },
  { cat: "Điều trị", title: "Quy Trình Chụp Phim X-Quang Cone Beam CT 3D Liều Thấp An Toàn", service: "/website/nha-khoa-tong-quat.html", tag: "chụp X-Quang ConeBeam CT" },
  { cat: "Điều trị", title: "Viêm Lợi Trùm Răng Khôn: Triệu Chứng Và Cách Xử Lý Nhanh Hết Sưng", service: "/website/nha-khoa-tong-quat.html", tag: "chữa viêm lợi trùm" },
  { cat: "Điều trị", title: "Áp Xe Răng Là Gì? Cấp Cứu Kịp Thời Tránh Nhiễm Trùng Lan Rộng", service: "/website/nha-khoa-tong-quat.html", tag: "xử lý áp xe răng" },
  { cat: "Điều trị", title: "Bảo Tồn Răng Tối Đa: Khi Nào Răng Bắt Buộc Phải Chỉ Định Nhổ Bỏ?", service: "/website/nha-khoa-tong-quat.html", tag: "chỉ định nhổ răng bệnh lý" },
  { cat: "Điều trị", title: "Tại Sao Phải Bọc Răng Sứ Sau Khi Đã Diệt Tủy Răng?", service: "/website/boc-rang-su.html", tag: "bọc răng sau chữa tủy" },
  { cat: "Điều trị", title: "Thói Quen Nghiến Răng Khi Ngủ: Nguyên Nhân Và Máng Chống Nghiến Răng", service: "/website/nha-khoa-tong-quat.html", tag: "máng chống nghiến răng" },
  { cat: "Điều trị", title: "Điều Trị Loét Miệng Nhiệt Miệng Kéo Dài Bằng Thuốc Bôi Chuyên Dụng", service: "/website/nha-khoa-tong-quat.html", tag: "chữa nhiệt miệng kéo dài" },
  { cat: "Điều trị", title: "Tụt Nướu Răng: Nguyên Nhân Gây Lộ Cổ Chân Răng Và Phẫu Thuật Ghép Lợi", service: "/website/nha-khoa-tong-quat.html", tag: "ghép mô lợi chữa tụt nướu" },
  { cat: "Điều trị", title: "Cách Phòng Ngừa Sâu Răng Hiệu Quả Cho Người Có Men Răng Yếu", service: "/website/nha-khoa-tong-quat.html", tag: "phòng ngừa sâu răng" },
  { cat: "Điều trị", title: "Trám Răng Không Đau Bằng Kỹ Thuật Đặt Đê Cao Su Cách Ly Tuyệt Đối", service: "/website/nha-khoa-tong-quat.html", tag: "kỹ thuật đặt đê cao su" },
  { cat: "Điều trị", title: "Bảng Giá Điều Trị Bệnh Lý Răng Miệng Niêm Yết Minh Bạch 2026", service: "/website/bang-gia.html", tag: "bảng giá khám tổng quát" },

  // 5. Thẩm mỹ nụ cười, Tẩy trắng & Nha khoa trẻ em (20 topics)
  { cat: "Thẩm mỹ", title: "Tẩy Trắng Răng Laser Whitening: Bật 3 Đến 5 Tông Chỉ Trong 45 Phút", service: "/website/tay-trang-rang.html", tag: "tẩy trắng răng Laser" },
  { cat: "Thẩm mỹ", title: "Phẫu Thuật Cắt Lợi Thẩm Mỹ: Tạm Biệt Cười Hở Lợi Đẹp Tự Nhiên", service: "/website/boc-rang-su.html", tag: "cắt lợi chữa cười hở lợi" },
  { cat: "Thẩm mỹ", title: "Bôi Vecni Fluor Cho Bé: Áo Giáp Bảo Vệ Men Răng Khỏi Axit Sâu Răng", service: "/website/nha-khoa-tong-quat.html", tag: "bôi vecni fluor ngừa sâu răng" },
  { cat: "Thẩm mỹ", title: "Chăm Sóc Răng Sữa Cho Bé: Bí Quyết Để Trẻ Thay Răng Đều Và Đẹp", service: "/website/nha-khoa-tong-quat.html", tag: "chăm sóc răng sữa trẻ em" },
  { cat: "Thẩm mỹ", title: "Đính Đá Lên Răng Thẩm Mỹ: Điểm Nhấn Tỏa Sáng Cho Nụ Cười Xinh", service: "/website/tay-trang-rang.html", tag: "đính đá răng thẩm mỹ" },
  { cat: "Thẩm mỹ", title: "Tẩy Trắng Răng Tại Nhà Bằng Máng Tẩy: Hướng Dẫn Chi Tiết Từ Bác Sĩ", service: "/website/tay-trang-rang.html", tag: "máng tẩy trắng răng tại nhà" },
  { cat: "Thẩm mỹ", title: "Răng Ố Vàng Do Cà Phê Thuốc Lá: Cách Làm Trắng Răng Nhanh Chóng", service: "/website/tay-trang-rang.html", tag: "tẩy răng ố vàng cà phê" },
  { cat: "Thẩm mỹ", title: "Trám Răng Cửa Bị Thưa Bằng Composite: Chi Phí Thấp Đẹp Ngay Tức Thì", service: "/website/nha-khoa-tong-quat.html", tag: "trám khe thưa răng cửa" },
  { cat: "Thẩm mỹ", title: "Thiết Kế Nụ Cười Digital Smile Design (DSD): Chuẩn Đoán Thẩm Mỹ 3D", service: "/website/boc-rang-su.html", tag: "thiết kế nụ cười DSD" },
  { cat: "Thẩm mỹ", title: "Tẩy Trắng Răng Có Hại Men Răng Không? Chuyên Gia Giải Đáp Khoa Học", service: "/website/tay-trang-rang.html", tag: "tẩy trắng răng có hại không" },
  { cat: "Thẩm mỹ", title: "Nhổ Răng Sữa Không Đau Cho Trẻ: Tạo Trải Nghiệm Đi Nha Khoa Vui Vẻ", service: "/website/nha-khoa-tong-quat.html", tag: "nhổ răng sữa cho trẻ" },
  { cat: "Thẩm mỹ", title: "Đánh Giá Độ Bền Màu Sau Khi Tẩy Trắng Răng: Giữ Được Mấy Năm?", service: "/website/tay-trang-rang.html", tag: "độ bền tẩy trắng răng" },
  { cat: "Thẩm mỹ", title: "Trám Bít Hố Rãnh Cho Trẻ Em: Ngăn Ngừa Sâu Răng Hàm Sớm Hiệu Quả", service: "/website/nha-khoa-tong-quat.html", tag: "trám bít hố rãnh răng hàm" },
  { cat: "Thẩm mỹ", title: "Cách Chọn Bàn Chải Đánh Răng Điện Và Kem Đánh Răng Chống Ê Buốt", service: "/website/nha-khoa-tong-quat.html", tag: "chọn bàn chải điện tốt" },
  { cat: "Thẩm mỹ", title: "Tại Sao Răng Lại Bị Đổi Màu Sau Khi Chấn Thương Va Đập?", service: "/website/nha-khoa-tong-quat.html", tag: "răng đổi màu do chấn thương" },
  { cat: "Thẩm mỹ", title: "Tẩy Trắng Răng Kết Hợp Dán Sứ Veneer: Combo Nụ Cười Hoàn Hảo", service: "/website/boc-rang-su.html", tag: "combo nụ cười toàn diện" },
  { cat: "Thẩm mỹ", title: "Hạn Chế Bánh Kẹo Nước Ngọt: Bảo Vệ Hàm Răng Cho Con Trưởng Thành", service: "/website/nha-khoa-tong-quat.html", tag: "dinh dưỡng bảo vệ răng bé" },
  { cat: "Thẩm mỹ", title: "Hướng Dẫn Chải Răng Đúng Cách Theo Phương Pháp Bass Cải Tiến", service: "/website/nha-khoa-tong-quat.html", tag: "kỹ thuật chải răng chuẩn" },
  { cat: "Thẩm mỹ", title: "Đăng Ký Khám Và Tư Vấn Răng Miệng Trực Tuyến Tiết Kiệm Thời Gian", service: "/website/dat-lich.html", tag: "đặt lịch khám răng trực tuyến" },
  { cat: "Thẩm mỹ", title: "Chào Mừng Quý Khách Đến Với Không Gian Nha Khoa Xanh Chuẩn Luxury", service: "/website/index.html", tag: "không gian phòng khám chuẩn sang" },
];

function makeContent(t, index) {
  return `<h2>1. Tổng quan về ${t.title}</h2>
<p>Trong nha khoa hiện đại, dịch vụ <strong><a href="${t.service}">${t.tag}</a></strong> đóng vai trò vô cùng quan trọng đối với sức khỏe và thẩm mỹ hàm răng. Tại Nha Khoa Kim Dung Thái Nguyên, chúng tôi ứng dụng quy chuẩn điều trị theo tiêu chuẩn Châu Âu giúp bệnh nhân hoàn toàn an tâm.</p>

<h2>2. Lợi ích vượt trội khi thực hiện tại Nha Khoa Kim Dung</h2>
<ul>
  <li>Đội ngũ bác sĩ hơn 15 năm kinh nghiệm trực tiếp thăm khám và lên phác đồ chuyên biệt.</li>
  <li>Trang thiết bị chẩn đoán hình ảnh kỹ thuật số 3D sắc nét, hỗ trợ <strong><a href="${t.service}">${t.tag}</a></strong> an toàn, nhanh chóng và chính xác.</li>
  <li>Chính sách chi phí minh bạch, quý khách có thể tra cứu chi tiết tại <a href="/website/bang-gia.html"><strong>bảng giá dịch vụ niêm yết</strong></a>.</li>
</ul>

<div style="background:#FFFDF6; border-left:4px solid #EABF0E; padding:14px 18px; margin:20px 0; border-radius:4px; color:#18181b;">
  <strong style="color:#005A36;">💡 Lời khuyên từ Bác sĩ Kim Dung:</strong>
  <p style="margin:6px 0 0 0;">Khách hàng nên thăm khám và kiểm tra răng định kỳ ít nhất 6 tháng/lần để phát hiện sớm các dấu hiệu bất thường. Liên hệ hotline hoặc truy cập trang <a href="/website/dat-lich.html"><strong>đặt lịch hẹn trực tuyến</strong></a> để được ưu tiên thăm khám không phải chờ đợi.</p>
</div>

<h2>3. Kết luận</h2>
<p>Sở hữu một hàm răng khỏe đẹp là nền tảng của sự tự tin và thành công. Để được tư vấn chuyên sâu về <strong><a href="${t.service}">${t.tag}</a></strong>, hãy đến ngay Nha Khoa Kim Dung tại TP. Thái Nguyên.</p>`;
}

function sendPost(payload) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const req = http.request(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${API_KEY}`,
        'Content-Length': Buffer.byteLength(data),
      }
    }, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(body);
          resolve({ status: res.statusCode, data: parsed });
        } catch {
          resolve({ status: res.statusCode, raw: body });
        }
      });
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function main() {
  console.log(`\n=============================================================`);
  console.log(`🚀 BẮT ĐẦU GỬI BATCH 100 BÀI VIẾT QUA API /api/v1/posts`);
  console.log(`🔑 Sử dụng API Key: ${API_KEY}`);
  console.log(`=============================================================\n`);

  const startTime = Date.now();
  let successCount = 0;
  let failCount = 0;
  const createdUrls = [];

  for (let i = 0; i < topics.length; i++) {
    const t = topics[i];
    const index = i + 1;
    const externalId = `batch100_ai_post_${String(index).padStart(3, '0')}`;

    // Distinct dental stock image for thumbnail
    const images = [
      'https://images.unsplash.com/photo-1629909613654-28e377c37b09?w=500&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=500&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1606811841689-23dfddce3e95?w=500&auto=format&fit=crop&q=80',
      'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=500&auto=format&fit=crop&q=80',
      'https://nhakhoakimdung.vn/thumbs/390x300x1/upload/news/bai-dang-instagram-quang-cao-sale-nieng-rang-nha-khoa-tet-hien-dai-do-vang-1772767722.png.webp'
    ];
    const featuredImage = images[i % images.length];

    const payload = {
      title: t.title,
      excerpt: `Chuyên gia Nha Khoa Kim Dung tư vấn toàn diện về ${t.tag}: quy trình, chỉ định y khoa, độ bền và bảng giá ưu đãi mới nhất.`,
      content: makeContent(t, index),
      featured_image: featuredImage,
      status: "published",
      external_id: externalId,
    };

    try {
      const res = await sendPost(payload);
      if (res.status === 201 || res.status === 200) {
        successCount++;
        createdUrls.push(res.data.url);
        if (index % 10 === 0 || index === 1 || index === 100) {
          console.log(`[${String(index).padStart(3, ' ')}/100] ✅ (${res.status}) ${t.title.slice(0, 48)}... -> slug: ${res.data.slug}`);
        }
      } else {
        failCount++;
        console.error(`[${String(index).padStart(3, ' ')}/100] ❌ Failed (${res.status}):`, res.data || res.raw);
      }
    } catch (err) {
      failCount++;
      console.error(`[${String(index).padStart(3, ' ')}/100] ❌ Request Error:`, err.message);
    }
  }

  const duration = ((Date.now() - startTime) / 1000).toFixed(2);
  console.log(`\n=============================================================`);
  console.log(`🎉 HOÀN THÀNH GỬI 100 BÀI VIẾT QUA API`);
  console.log(`⏱️ Thời gian thực thi: ${duration}s (Trung bình: ${(duration / 100 * 1000).toFixed(0)}ms/bài)`);
  console.log(`✅ Thành công: ${successCount} bài`);
  console.log(`❌ Thất bại: ${failCount} bài`);
  console.log(`=============================================================\n`);
}

main();
