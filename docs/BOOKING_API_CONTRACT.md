# 🦷 Nha Khoa Kim Dung — Booking & Tracking API Contract v1.0

> **Dành cho:** Frontend Designer / Developer phụ trách tích hợp giao diện đặt lịch khám và đo lường chuyển đổi.  
> **Backend Host:** Mắt Bão Cloud Hosting (PHP 8.x + MariaDB)  
> **Base URL:** `https://nhakhoakimdung.vn` (hoặc domain môi trường staging)

---

## 📌 1. Danh Sách Endpoint

| Phương thức | Endpoint | Chức năng | Quyền truy cập |
|---|---|---|---|
| `GET` | `/api/v1/services.php` | Lấy danh sách dịch vụ nha khoa đang kích hoạt | Public |
| `GET` | `/api/v1/availability.php?service_id={id}&date={YYYY-MM-DD}` | Lấy danh sách khung giờ còn trống (30 phút/slot) | Public |
| `POST` | `/api/v1/bookings.php` | Gửi đơn đăng ký đặt lịch hẹn khám | Public (Rate limit: 10/h/IP) |
| `POST` | `/api/v1/track.php` | Beacon ghi nhận phiên truy cập & UTM | Public (Khuyên dùng) |

---

## 🔄 2. Chu Trình Đặt Lịch Chuẩn (Standard Booking Flow)

```
[1. Load Trang]
      │
      ▼
[GET /api/v1/services.php] ──► Đổ dữ liệu vào <select name="service_id">
      │
      ▼
[Khách chọn Dịch vụ & Ngày (date)]
      │
      ▼
[GET /api/v1/availability.php?service_id=X&date=YYYY-MM-DD]
      │
      ▼
[Render Khung Giờ (Slots)] ──► Giờ trống cho phép click; Giờ đã kín bị disabled
      │
      ▼
[Khách điền Tên, SĐT, Giờ & Bấm Đặt Lịch]
      │
      ▼
[POST /api/v1/bookings.php] (Kèm honeypot ẩn & UTM)
      │
      ├───► 201 Created: Thành công! Hiển thị mã hẹn (VD: KD-20261004-9842)
      │
      └───► 409 Conflict: Trùng lịch vừa có người đặt. Yêu cầu chọn giờ khác.
```

---

## 📋 3. Chi Tiết Các Endpoint

### 3.1. Lấy danh sách dịch vụ
- **Endpoint:** `GET /api/v1/services.php`
- **Response Success (200 OK):**
```json
{
  "success": true,
  "data": {
    "services": [
      {
        "id": 1,
        "name": "Trồng răng Implant",
        "slug": "trong-rang-implant",
        "description": "Cấy ghép trụ Implant phục hồi răng đã mất vĩnh viễn, ăn nhai tự nhiên.",
        "duration_minutes": 45,
        "price": 0
      },
      {
        "id": 4,
        "name": "Nha khoa tổng quát / Khám & Tư vấn",
        "slug": "nha-khoa-tong-quat",
        "description": "Khám răng tổng quát, chụp phim và tư vấn kế hoạch điều trị chi tiết.",
        "duration_minutes": 30,
        "price": 0
      }
    ]
  }
}
```

---

### 3.2. Kiểm tra khung giờ còn trống (Availability Engine)
- **Endpoint:** `GET /api/v1/availability.php`
- **Query Params:**
  - `service_id` *(int, bắt buộc)*: ID dịch vụ được chọn.
  - `date` *(string, bắt buộc, định dạng YYYY-MM-DD)*: Ngày khách muốn đặt.
- **Response Success (200 OK):**
```json
{
  "success": true,
  "data": {
    "date": "2026-10-05",
    "slots": [
      "08:00",
      "08:30",
      "09:00",
      "09:30",
      "10:00",
      "10:30",
      "11:00",
      "14:00",
      "14:30",
      "15:00",
      "15:30",
      "16:00",
      "16:30",
      "17:00",
      "17:30",
      "18:00",
      "18:30",
      "19:00"
    ]
  }
}
```
*Lưu ý: Danh sách `slots` trả về toàn bộ các mốc giờ 30 phút bắt đầu hợp lệ mà phòng khám đang mở cửa và chưa bị khách khác đặt trước.*

---

### 3.3. Gửi đặt lịch khám
- **Endpoint:** `POST /api/v1/bookings.php`
- **Headers:** `Content-Type: application/json` (hoặc form URL-encoded)
- **Request Body Payload:**
```json
{
  "name": "Nguyễn Văn An",
  "phone": "0912345678",
  "service_id": 4,
  "date": "2026-10-05",
  "time": "09:00",
  "notes": "Răng hàm dưới hơi ê buốt khi uống nước lạnh",
  "hp_field": "",
  "source_page": "/website/nha-khoa-tong-quat.html",
  "referrer": "https://google.com/",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "kham_tong_quat",
  "session_id": "8f9a2b..."
}
```

#### Bảng trường dữ liệu (Fields Specification):
| Trường | Kiểu | Bắt buộc | Mô tả |
|---|---|---|---|
| `name` | string (max 100) | **Có** | Họ và tên khách hàng |
| `phone` | string (10 số) | **Có** | Số điện thoại VN hợp lệ (03x, 05x, 07x, 08x, 09x hoặc +84) |
| `service_id` | int | **Có** | ID dịch vụ từ `/api/v1/services.php` |
| `date` | string (YYYY-MM-DD) | **Có** | Ngày hẹn |
| `time` | string (HH:MM) | **Có** | Giờ bắt đầu hẹn (VD: `"09:00"`) |
| `notes` | string (max 1000) | Không | Ghi chú tình trạng răng miệng |
| `hp_field` | string | **Bẫy Bot** | **BẮT BUỘC ĐỂ TRỐNG**. Ẩn với người dùng bằng CSS `style="display:none;"`. Bot tự động điền vào sẽ bị chặn. |
| `source_page` | string | Không | Đường dẫn URL trang mà khách đang mở form |
| `referrer` | string | Không | `document.referrer` |
| `utm_*` | string | Không | Các thông số UTM lấy từ URL (source, medium, campaign, content, term) |
| `session_id` | string | Không | Đọc từ cookie `kd_sid` |

#### Response Success (201 Created):
```json
{
  "success": true,
  "data": {
    "booking_code": "KD-20261005-4819",
    "status": "new",
    "service": "Nha khoa tổng quát / Khám & Tư vấn",
    "date": "2026-10-05",
    "time": "09:00"
  }
}
```

#### Response Error Slot Unavailable (409 Conflict):
```json
{
  "success": false,
  "error": {
    "code": "SLOT_UNAVAILABLE",
    "message": "Khung giờ này vừa có khách khác đặt trước. Vui lòng chọn một khung giờ khác."
  }
}
```

---

### 3.4. Beacon Ghi Nhận Phiên & Traffic (Track)
Nhúng script nhỏ ở đầu trang hoặc footer để tự động ghi nhận phiên, hỗ trợ tính tỉ lệ chuyển đổi chính xác.
- **Endpoint:** `POST /api/v1/track.php`
- **Payload:**
```json
{
  "path": window.location.pathname,
  "referrer": document.referrer,
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "brand_search"
}
```
Endpoint tự động quản lý cookie ẩn danh `kd_sid` (thời hạn 1 năm).

---

## 🛠️ 4. Code JavaScript Mẫu Tích Hợp (Vanilla JS, Zero Dependencies)

Dưới đây là đoạn code hoàn chỉnh, nhẹ, không phụ thuộc jQuery hay React, có thể chèn trực tiếp vào trang:

```html
<!-- Bẫy bot (Honeypot) - Giữ ẩn hoàn toàn -->
<input type="text" name="hp_field" id="kd_hp_field" style="display:none !important;" tabindex="-1" autocomplete="off">

<script>
// 1. Tự động lấy tham số URL (UTM params)
function getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    return {
        utm_source: params.get('utm_source') || '',
        utm_medium: params.get('utm_medium') || '',
        utm_campaign: params.get('utm_campaign') || '',
        utm_content: params.get('utm_content') || '',
        utm_term: params.get('utm_term') || ''
    };
}

// 2. Gửi beacon đo lường truy cập khi tải trang
(function trackVisit() {
    const utm = getUrlParams();
    fetch('/api/v1/track.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            path: window.location.pathname,
            referrer: document.referrer,
            ...utm
        })
    }).catch(() => {});
})();

// 3. Tải danh sách dịch vụ vào select box
async function loadServices(selectElementId) {
    try {
        const res = await fetch('/api/v1/services.php');
        const json = await res.json();
        if (json.success) {
            const el = document.getElementById(selectElementId);
            el.innerHTML = '<option value="">-- Chọn dịch vụ khám --</option>';
            json.data.services.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = `${s.name} (${s.duration_minutes} phút)`;
                el.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Không tải được danh mục dịch vụ', e);
    }
}

// 4. Lấy danh sách khung giờ trống theo dịch vụ và ngày
async function checkAvailableSlots(serviceId, dateStr, containerElementId) {
    const container = document.getElementById(containerElementId);
    container.innerHTML = '<span class="loading">Đang tải lịch trống...</span>';
    
    try {
        const res = await fetch(`/api/v1/availability.php?service_id=${serviceId}&date=${dateStr}`);
        const json = await res.json();
        if (!json.success || !json.data.slots.length) {
            container.innerHTML = '<span class="closed">Phòng khám nghỉ hoặc ngày đã qua.</span>';
            return;
        }

        container.innerHTML = '';
        json.data.slots.forEach(slotTime => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'slot-btn available';
            btn.textContent = slotTime;
            btn.onclick = () => {
                document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                document.getElementById('selected_time_input').value = slotTime;
            };
            container.appendChild(btn);
        });
    } catch (e) {
        container.innerHTML = '<span class="error">Lỗi kiểm tra lịch trống.</span>';
    }
}

// 5. Submit Form Đặt Lịch
async function submitBooking(formElement) {
    const submitBtn = formElement.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerText = 'Đang xử lý...';

    const utm = getUrlParams();
    const payload = {
        name: formElement.elements['name'].value.trim(),
        phone: formElement.elements['phone'].value.trim(),
        service_id: parseInt(formElement.elements['service_id'].value, 10),
        date: formElement.elements['date'].value,
        time: formElement.elements['time'].value,
        notes: formElement.elements['notes'] ? formElement.elements['notes'].value.trim() : '',
        hp_field: document.getElementById('kd_hp_field').value,
        source_page: window.location.pathname,
        referrer: document.referrer,
        ...utm
    };

    try {
        const response = await fetch('/api/v1/bookings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();

        if (response.status === 201 && result.success) {
            alert(`🎉 ĐẶT LỊCH THÀNH CÔNG!\n\nMã lịch hẹn: ${result.data.booking_code}\nDịch vụ: ${result.data.service}\nNgày hẹn: ${result.data.date} lúc ${result.data.time}\n\nNha Khoa Kim Dung sẽ liên hệ xác nhận sớm nhất!`);
            formElement.reset();
        } else if (response.status === 409) {
            alert('⚠️ ' + result.error.message);
            // Refresh slots
            checkAvailableSlots(payload.service_id, payload.date, 'slots_container');
        } else {
            alert('❌ ' + (result.error ? result.error.message : 'Đặt lịch thất bại. Vui lòng thử lại sau.'));
        }
    } catch (err) {
        alert('❌ Có lỗi kết nối máy chủ. Vui lòng liên hệ Hotline 0989.123.456 để đặt trực tiếp.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Đăng Ký Đặt Lịch';
    }
}
</script>
```

---

## ⚠️ 5. Bảng Mã Lỗi & Thông Báo Khuyên Dùng Cho Người Dùng

| HTTP Status | Error Code | Nguyên nhân | Thông báo gợi ý hiển thị người dùng |
|---|---|---|---|
| `400` | `INVALID_INPUT` | Thiếu trường dữ liệu hoặc sai kiểu | "Vui lòng kiểm tra lại thông tin đã điền." |
| `422` | `VALIDATION_ERROR` | Số điện thoại không đúng chuẩn VN hoặc ngày giờ không hợp lệ | "Số điện thoại không đúng định dạng. Vui lòng nhập số điện thoại 10 chữ số." |
| `409` | `SLOT_UNAVAILABLE` | Khung giờ vừa bị người khác đặt trước | "Khung giờ này vừa có khách khác đặt. Vui lòng chọn giờ hẹn khác." |
| `429` | `RATE_LIMITED` | Gửi quá 10 lần trong 1 giờ từ cùng một IP | "Bạn đã gửi yêu cầu quá nhiều lần. Vui lòng thử lại sau hoặc liên hệ Hotline." |
| `500` | `SERVER_ERROR` | Lỗi kết nối cơ sở dữ liệu | "Hệ thống đang bảo trì giây lát. Quý khách vui lòng gọi Hotline để được hỗ trợ ngay." |
