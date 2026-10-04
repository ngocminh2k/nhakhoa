# Hướng Dẫn Tích Hợp API Đăng Bài Chuyên Khoa Nha Khoa Kim Dung
**API Version:** `v1`  
**Endpoint:** `POST /api/v1/posts`  
**Xác thực:** `Authorization: Bearer <API_KEY>`  
**Định dạng dữ liệu:** `application/json; charset=utf-8`

---

## 1. Tổng Quan Kiến Trúc & Xử Lý Tự Động
Khi một bài viết được gửi qua API:
1. **Xác thực API Key:** Kiểm tra mã băm SHA-256 trong bảng `api_keys`, kiểm tra trạng thái hoạt động (`active = 1`) và cập nhật thời gian thực `last_used_at = NOW()`.
2. **Handle / Slug URL:**
   - Nếu bạn cung cấp `slug`: Hệ thống sử dụng trực tiếp slug này.
   - Nếu bạn để trống `slug`: Hệ thống tự động chuyển `title` tiếng Việt có dấu thành slug không dấu chuẩn SEO (VD: *"Bọc Răng Sứ Cercon"* $\rightarrow$ `"boc-rang-su-cercon"`).
   - Nếu bị trùng slug: Tự động gán thêm timestamp để không đụng độ URL.
3. **Idempotent Upsert (`external_id`):** Nếu gửi kèm `external_id` (ID bài viết từ AI bot hoặc CMS bên ngoài), các lần gọi tiếp theo sẽ **tự động cập nhật bài cũ** thay vì sinh bài trùng lặp.
4. **Tự động điền khung mẫu Designer (`Auto-fill Layout`):** Toàn bộ nội dung bài viết được bọc trong giao diện bài viết chuyên môn đồng bộ nhận diện thương hiệu Kim Dung, tự động sinh meta tag SEO, OpenGraph Facebook/Zalo, và **Widget Đặt Lịch Khám** ở sidebar cột phải.

---

## 2. Quy Chuẩn Định Dạng Nội Dung HTML (Hyperlinks & Bôi Đậm)

Trường `content` nhận chuỗi HTML hợp lệ (đã escape nháy kép `\"` trong JSON).

### 2.1. Bôi Đậm Từ Khóa (Bold)
Sử dụng thẻ `<strong>` hoặc `<b>`:
```html
<p>Công nghệ <strong>cấy ghép Implant All-on-4</strong> giúp phục hồi toàn bộ hàm răng đã mất.</p>
```

### 2.2. Chèn Hyperlink (Liên kết)

#### A. Liên kết nội bộ (Internal Links tới dịch vụ phòng khám)
Khuyên dùng để tối ưu SEO onpage và điều hướng khách hàng:
| Dịch vụ phòng khám | Đường dẫn URL chuẩn | Cú pháp HTML mẫu |
| :--- | :--- | :--- |
| **Trồng răng Implant** | `/website/trong-rang-implant.html` | `<a href="/website/trong-rang-implant.html">cấy ghép Implant</a>` |
| **Bọc răng sứ thẩm mỹ** | `/website/boc-rang-su.html` | `<a href="/website/boc-rang-su.html">bọc răng sứ cao cấp</a>` |
| **Niềng răng trong suốt** | `/website/nieng-rang-tham-my.html` | `<a href="/website/nieng-rang-tham-my.html">niềng răng Invisalign</a>` |
| **Niềng răng mắc cài** | `/website/nieng-rang-mac-cai.html` | `<a href="/website/nieng-rang-mac-cai.html">niềng răng mắc cài kim loại</a>` |
| **Tẩy trắng răng Laser** | `/website/tay-trang-rang.html` | `<a href="/website/tay-trang-rang.html">tẩy trắng răng Laser Whitening</a>` |
| **Nha khoa tổng quát / trám**| `/website/nha-khoa-tong-quat.html` | `<a href="/website/nha-khoa-tong-quat.html">khám nha khoa tổng quát</a>` |
| **Bảng giá dịch vụ** | `/website/bang-gia.html` | `<a href="/website/bang-gia.html">bảng giá niêm yết</a>` |
| **Đội ngũ Bác sĩ** | `/website/bac-si.html` | `<a href="/website/bac-si.html">đội ngũ bác sĩ chuyên khoa</a>` |
| **Đặt lịch khám** | `/website/dat-lich.html` | `<a href="/website/dat-lich.html">đặt lịch hẹn trực tuyến</a>` |

#### B. Vừa bôi đậm vừa gắn liên kết
```html
<p>Khách hàng có thể tham khảo dịch vụ <a href="/website/boc-rang-su.html"><strong>bọc răng sứ thẩm mỹ</strong></a> tại Kim Dung.</p>
```

#### C. Liên kết ra ngoài (External Link)
Thêm thuộc tính `target="_blank" rel="noopener noreferrer"`:
```html
<p>Nghiên cứu lâm sàng được công bố bởi <a href="https://www.ada.org" target="_blank" rel="noopener noreferrer">Hiệp hội Nha khoa Hoa Kỳ (ADA)</a>.</p>
```

### 2.3. Hộp Lưu Ý Lời Khuyên Của Bác Sĩ (Luxury Brand Callout)
```html
<div style="background:#FFFDF6; border-left:4px solid #EABF0E; padding:14px 18px; margin:20px 0; border-radius:4px; color:#18181b;">
  <strong style="color:#005A36;">💡 Lời khuyên từ Bác sĩ Kim Dung:</strong>
  <p style="margin:6px 0 0 0;">Nên kiêng các thực phẩm quá nóng hoặc quá cứng trong 48 giờ đầu tiên sau khi lắp răng sứ.</p>
</div>
```

---

## 3. Cấu Trúc Request Body (JSON)

```json
{
  "title": "Bọc Răng Sứ Cercon HT Có Bền Không? Giá Bao Nhiêu?",
  "slug": "boc-rang-su-cercon-ht-co-ben-khong",
  "excerpt": "Đánh giá chi tiết độ bền trên 15 năm của dòng răng toàn sứ Cercon HT chính hãng tại Nha Khoa Kim Dung.",
  "content": "<h2>1. Răng sứ Cercon HT là gì?</h2>\n<p>Cercon HT là dòng răng toàn sứ hàng đầu của Đức. Tại Kim Dung, dịch vụ <strong><a href=\"/website/boc-rang-su.html\">bọc răng sứ thẩm mỹ</a></strong> được thực hiện bởi đội ngũ bác sĩ tay nghề cao.</p>\n\n<h2>2. Ưu điểm vượt trội</h2>\n<ul>\n  <li>Độ chịu lực lên tới <strong>1200 MPa</strong> (gấp 4 lần răng thật).</li>\n  <li>Bảo hành chính hãng <strong>10 năm</strong>.</li>\n</ul>\n\n<div style=\"background:#FFFDF6; border-left:4px solid #EABF0E; padding:14px 18px; margin:20px 0; border-radius:4px;\">\n  <strong style=\"color:#005A36;\">💡 Lời khuyên từ Bác sĩ Kim Dung:</strong>\n  <p style=\"margin:6px 0 0 0;\">Quý khách nên tham khảo <a href=\"/website/bang-gia.html\">bảng giá chi tiết</a> trước khi lựa chọn dòng sứ.</p>\n</div>",
  "featured_image": "https://nhakhoakimdung.vn/upload/photo/banner-rang-su.jpg",
  "status": "published",
  "external_id": "n8n_post_cercon_2026"
}
```

---

## 4. Response Trả Về

### 4.1. Tạo bài mới thành công (HTTP 201 Created)
```json
{
  "success": true,
  "post_id": 16,
  "slug": "boc-rang-su-cercon-ht-co-ben-khong",
  "action": "created",
  "url": "https://nhakhoakimdung.vn/tin-tuc/boc-rang-su-cercon-ht-co-ben-khong"
}
```

### 4.2. Cập nhật bài cũ (HTTP 200 OK)
```json
{
  "success": true,
  "post_id": 16,
  "slug": "boc-rang-su-cercon-ht-co-ben-khong",
  "action": "updated",
  "url": "https://nhakhoakimdung.vn/tin-tuc/boc-rang-su-cercon-ht-co-ben-khong"
}
```

### 4.3. Các mã lỗi thường gặp
* **401 Unauthorized:** API Key bị thiếu, sai hoặc đã bị Vô hiệu hóa trên trang Admin.
* **422 Unprocessable Entity:** Thiếu trường bắt buộc (`title` hoặc `content`).
* **405 Method Not Allowed:** Gọi sai method (phải dùng `POST`).

---

## 5. Ví Dụ Triển Khai Thực Tế

### cURL
```bash
curl -X POST https://nhakhoakimdung.vn/api/v1/posts \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer kd_your_api_key_here" \
  -d '{
    "title": "Kỹ Thuật Cấy Ghép Răng Implant Chuẩn Y Khoa",
    "content": "<p>Tham khảo dịch vụ <strong><a href=\"/website/trong-rang-implant.html\">cấy ghép răng Implant</a></strong> tại Nha Khoa Kim Dung.</p>",
    "status": "published"
  }'
```

### Node.js (Fetch API)
```javascript
const response = await fetch('https://nhakhoakimdung.vn/api/v1/posts', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': 'Bearer kd_your_api_key_here'
  },
  body: JSON.stringify({
    title: 'Kỹ Thuật Cấy Ghép Răng Implant Chuẩn Y Khoa',
    content: '<p>Tham khảo dịch vụ <strong><a href="/website/trong-rang-implant.html">cấy ghép răng Implant</a></strong> tại Nha Khoa Kim Dung.</p>',
    status: 'published'
  })
});
const result = await response.json();
console.log('Xem bài viết tại:', result.url);
```

### Python (requests)
```python
import requests

url = "https://nhakhoakimdung.vn/api/v1/posts"
headers = {
    "Content-Type": "application/json",
    "Authorization": "Bearer kd_your_api_key_here"
}
payload = {
    "title": "Kỹ Thuật Cấy Ghép Răng Implant Chuẩn Y Khoa",
    "content": '<p>Tham khảo dịch vụ <strong><a href="/website/trong-rang-implant.html">cấy ghép răng Implant</a></strong> tại Nha Khoa Kim Dung.</p>',
    "status": "published"
}

res = requests.post(url, json=payload, headers=headers)
print("Kết quả:", res.json())
```
