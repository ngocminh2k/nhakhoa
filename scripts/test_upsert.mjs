// scripts/test_upsert.mjs
import http from 'http';

const API_KEY = 'kd_batch_publisher_secret_key_2026';
const payload = {
  title: "Cấy Ghép Implant Toàn Hàm All-on-4 [ĐÃ CẬP NHẬT 2026]",
  excerpt: "Bài viết đã được bác sĩ cập nhật công nghệ All-on-4 mới nhất 2026.",
  content: "<p>Nội dung mới cập nhật sau khi tái bản.</p>",
  featured_image: "https://nhakhoakimdung.vn/upload/photo/banner-implant-moi.jpg",
  status: "published",
  external_id: "batch100_ai_post_001"
};

const data = JSON.stringify(payload);
const req = http.request('http://127.0.0.1:8080/api/v1/posts', {
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
    console.log('Update Status:', res.statusCode);
    console.log('Update Response:', JSON.parse(body));
  });
});
req.write(data);
req.end();
