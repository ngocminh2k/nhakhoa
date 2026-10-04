# 🚀 Hướng Dẫn Triển Khai Lên Mắt Bão Cloud Hosting (cPanel / LiteSpeed)
### Dự án: Hệ Thống Backend & Quản Trị Nha Khoa Kim Dung

Tài liệu này hướng dẫn chi tiết quy trình đưa mã nguồn Backend (Zone B) lên gói **Cloud Hosting Mắt Bão** mà **hoàn toàn không làm ảnh hưởng đến 79 trang HTML tĩnh hiện tại (Zone A)**.

---

## 📌 1. Yêu Cầu Môi Trường Hosting Mắt Bão

Hệ thống được thiết kế theo tiêu chuẩn **Zero Dependency (100% Native PHP 8.x + MariaDB PDO)**, không sử dụng Composer, không cần Node.js runtime trên server, tương thích hoàn toàn với Cloud Hosting Mắt Bão:

- **Web Server:** LiteSpeed Web Server / Apache 2.4 (hỗ trợ đọc trực tiếp `.htaccess`)
- **PHP Version:** **PHP 8.1, 8.2 hoặc 8.3**
- **PHP Extensions yêu cầu:**
  - `pdo_mysql` (Kết nối database MariaDB qua chuẩn PDO)
  - `mbstring` (Xử lý chuỗi tiếng Việt có dấu, tạo slug chuẩn SEO)
  - `openssl` (Mã hóa, băm API Key SHA-256)
  - `curl` (Tùy chọn: đồng bộ Google Sheets webhook)
  - `json` & `session` (Core mặc định)
- **Database:** MariaDB 10.5+ hoặc MySQL 8.0+

---

## ⚙️ 2. Quy Trình Cài Đặt 6 Bước Chi Tiết

### Bước 1: Kiểm Tra & Bật PHP 8.2 trên cPanel Mắt Bão
1. Đăng nhập vào cPanel Mắt Bão (thường là `https://yourdomain:2083`).
2. Tìm mục **Software** -> Click **Select PHP Version**.
3. Chọn phiên bản **PHP 8.2** (hoặc 8.1/8.3) và nhấn **Set as current**.
4. Chuyển sang tab **Extensions**: Đảm bảo các module sau đã được tích chọn:
   - `pdo_mysql`
   - `mbstring`
   - `openssl`
   - `curl`

---

### Bước 2: Tạo Cơ Sở Dữ Liệu MariaDB
1. Trong cPanel, vào mục **Databases** -> Click **MySQL Databases**.
2. **Create New Database**:
   - Nhập tên: `nhakhoa` (Tên đầy đủ sẽ có tiền tố user hosting, ví dụ: `matbao_nhakhoa`).
   - Click **Create Database**.
3. **Add New User**:
   - Username: `kd_user` (Tên đầy đủ: `matbao_kd_user`).
   - Mật khẩu: Tạo mật khẩu mạnh (ví dụ: `KimDung@2026!Secured#`).
   - Click **Create User**.
4. **Add User To Database**:
   - Chọn User vừa tạo và Database vừa tạo.
   - Click **Add**.
   - Tích chọn **ALL PRIVILEGES** -> Click **Make Changes**.

---

### Bước 3: Khởi Tạo Cấu Trúc Bảng (Import Schema)
1. Trong cPanel, vào mục **Databases** -> Click **phpMyAdmin**.
2. Chọn cơ sở dữ liệu vừa tạo (`matbao_nhakhoa`) ở danh sách cột bên trái.
3. Click tab **Import** ở thanh menu trên cùng.
4. Tại mục **File to import**, chọn file `database/schema.sql` từ mã nguồn dự án.
5. Click nút **Import** ở cuối trang.
6. Hệ thống sẽ tạo thành công 10 bảng tối ưu hóa InnoDB với collation `utf8mb4_unicode_ci`:
   - `admins`: Tài khoản quản trị viên
   - `services`: 9 danh mục dịch vụ nha khoa
   - `business_hours`: Giờ làm việc phòng khám (8:00 - 18:00)
   - `schedule_blocks`: Lịch nghỉ lễ, bác sĩ hội chẩn
   - `bookings`: Đơn hẹn khám với khóa chống trùng lịch
   - `posts`: Bài viết chuyên đề nha khoa (hỗ trợ API tự động đăng bài)
   - `sessions`, `page_views`, `daily_analytics`: Phân tích chuyển đổi & UTM
   - `api_keys`: Khóa xác thực API đăng bài

---

### Bước 4: Tải Mã Nguồn Lên `public_html`
Bạn có thể sử dụng **File Manager** trên cPanel hoặc **FTP (FileZilla)**:
1. Thư mục gốc trên hosting là `/home/username/public_html/`.
2. Giữ nguyên toàn bộ các file giao diện tĩnh hiện có (Zone A):
   - `index.html`
   - `website/` (toàn bộ CSS, JS, hình ảnh, trang dịch vụ tĩnh)
3. Upload các thư mục và file Backend mới (Zone B) vào đúng vị trí:
   ```text
   public_html/
   ├── index.html                   (Zone A - Giữ nguyên)
   ├── website/                     (Zone A - Giữ nguyên)
   │
   ├── .htaccess                    (Zone B - Điều hướng LiteSpeed / Apache)
   ├── .env                         (Zone B - Cấu hình mật khẩu & DB)
   ├── api/
   │   └── v1/                      (API đặt lịch, lấy slot, nhận bài viết)
   ├── admin/                       (Cổng quản trị Portal)
   ├── backend/                     (Core, Repositories, Security, Templates)
   └── database/
       └── schema.sql
   ```

---

### Bước 5: Cấu Hình File `.env`
Trong thư mục `public_html/`, tạo hoặc chỉnh sửa file `.env` với thông tin database thực tế:

```env
# Mắt Bão Production Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=matbao_nhakhoa
DB_USERNAME=matbao_kd_user
DB_PASSWORD=KimDung@2026!Secured#
DB_CHARSET=utf8mb4

# Ứng Dụng
APP_ENV=production
APP_DEBUG=false
BASE_URL=https://nhakhoakimdung.vn

# API Key cho hệ thống tự động đăng bài (Tạo chuỗi ngẫu nhiên bí mật)
EXTERNAL_POST_API_KEY=kd_db6742ceea37b12d20c902eed5d9398e2a76628a51b8fcbb

# Đồng bộ Google Sheets (Tùy chọn)
GOOGLE_SHEET_WEBHOOK_URL=
```

---

### Bước 6: Chạy Bộ Khởi Tạo Dữ Liệu Mẫu (Installer)
1. Mở trình duyệt và truy cập:
   `https://nhakhoakimdung.vn/backend/install.php`
2. Trình duyệt sẽ hiển thị:
   - ✅ Kết nối Database thành công
   - ✅ Nạp 9 dịch vụ khám răng chuẩn
   - ✅ Thiết lập khung giờ hoạt động cả tuần
   - ✅ Tạo tài khoản Quản trị viên mặc định:
     - **Email:** `admin@nhakhoakimdung.vn`
     - **Mật khẩu khởi tạo:** `AdminKimDung@2026!`
   - ✅ Cấp phép API Key đăng bài bên ngoài
3. **BẢO MẬT QUAN TRỌNG:**
   Sau khi màn hình báo thành công, hãy xóa file `backend/install.php` hoặc đổi tên thành `install.php.bak` để ngăn người ngoài gọi lại.

---

## 🔒 3. Thiết Lập Cron Job Tự Động Hóa (cPanel)

Để đồng bộ các đơn hẹn mới vào Google Sheets của phòng khám (nếu sử dụng Google Sheet):
1. Trong cPanel, vào mục **Advanced** -> Click **Cron Jobs**.
2. Mục **Add New Cron Job**:
   - **Common Settings:** `Once Per 5 Minutes (*/5 * * * *)`
   - **Command:**
     ```bash
     /usr/local/bin/php /home/username/public_html/backend/cron/sync_sheets.php >/dev/null 2>&1
     ```
     *(Thay `/home/username/` bằng đường dẫn thư mục gốc hosting của bạn, xem ở cột bên phải cPanel)*
3. Click **Add New Cron Job**.

---

## 🧪 4. Danh Sách Kiểm Tra Vận Hành (Verification Checklist)

| Hạng mục | Đường dẫn kiểm tra | Kết quả mong đợi |
|---|---|---|
| **Trang chủ tĩnh** | `https://nhakhoakimdung.vn/` | Tải trang gốc bình thường, không lỗi CSS/JS |
| **Cổng Quản Trị** | `https://nhakhoakimdung.vn/admin/login.php` | Giao diện đăng nhập Admin hiển thị rõ ràng |
| **API Danh mục dịch vụ** | `GET /api/v1/services` | Trả về JSON 9 dịch vụ với HTTP 200 |
| **API Giờ trống** | `GET /api/v1/availability?service_id=1&date=YYYY-MM-DD` | Trả về danh sách khung giờ 30 phút/slot |
| **Đăng bài chuyên đề** | `POST /api/v1/posts` (Kèm Header Bearer Token) | Tạo bài viết thành công, trả về HTTP 201 |
| **Xem bài viết mới** | `https://nhakhoakimdung.vn/tin-tuc/:slug` | Hiển thị bài viết chuẩn y khoa, responsive, SEO OpenGraph |

---

## 🛡️ 5. Tính Năng Bảo Mật Đã Kích Hoạt Sẵn Trên Mắt Bão

1. **Chống dò quét trực tiếp (`.htaccess`):**
   Mọi truy cập trực tiếp vào thư mục `backend/core/`, `backend/config/`, `backend/repositories/`, file `.env`, `.sql`, `.log` đều bị chặn với mã lỗi `403 Forbidden`.
2. **Khóa chống đua lịch (MySQL Advisory Lock):**
   `SELECT GET_LOCK('kd_date_time', 5)` bảo vệ tuyệt đối không bao giờ xảy ra tình trạng 2 khách đặt trùng 1 khung giờ khám.
3. **Chống Spam Bot (Honeypot + Rate Limiting):**
   - Trường ẩn `hp_field` bẫy bot tự động điền form, trả về mã giả mà không ghi DB.
   - Giới hạn 10 đơn hẹn/giờ theo IP `REMOTE_ADDR` (không thể giả mạo qua header trên Mắt Bão).
4. **Phòng chống XSS & SQL Injection:**
   - 100% câu lệnh SQL sử dụng PDO Prepared Statements native với tên tham số phân tách riêng biệt.
   - Nội dung bài viết được làm sạch qua `Sanitizer::html()` loại bỏ triệt để thẻ script, iframe lậu và các thuộc tính javascript độc hại.
