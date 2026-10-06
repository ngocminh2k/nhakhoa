// scripts/attack_test.mjs — Live concurrent penetration & stability test suite
import http from 'http';
import { execSync } from 'child_process';

const BASE_HOST = '127.0.0.1';
const BASE_PORT = 8080;
const API_KEY = 'kd_live_sec_7a8f9b2c3d4e5f6a1b2c3d4e5f6a7b8c';

function resetLimits() {
  try { execSync('docker exec nhakhoa_web rm -rf /tmp/kd_rate_limits'); } catch {}
}

function req(options, data = null) {
  return new Promise((resolve, reject) => {
    const opts = { host: BASE_HOST, port: BASE_PORT, ...options };
    const request = http.request(opts, res => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body }));
    });
    request.on('error', reject);
    if (data) request.write(typeof data === 'string' ? data : JSON.stringify(data));
    request.end();
  });
}

const results = [];

function record(vector, testName, passed, detail) {
  results.push({ vector, testName, passed, detail });
  const icon = passed ? '✅' : '❌';
  console.log(`  ${icon} [${vector}] ${testName}: ${detail}`);
}

async function run() {
  console.log('═══════════════════════════════════════════════════════════════════');
  console.log('🚀 BẮT ĐẦU TẤN CÔNG ĐỒNG LOẠT 10 VÉC-TƠ VÀO http://127.0.0.1:8080');
  console.log('═══════════════════════════════════════════════════════════════════\n');

  resetLimits();

  // 1. SQL Injection
  {
    const res = await req({
      path: '/api/v1/bookings',
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    }, {
      name: "' OR 1=1--",
      phone: "0901234567",
      service_id: "1 UNION SELECT 1,2,3--",
      date: "2026-11-11",
      time: "10:00"
    });
    const isSafe = res.status === 422 && !res.body.includes('SQLSTATE');
    record('V1-SQLi', 'Chặn payload SQLi trên booking', isSafe, `HTTP ${res.status}`);
  }

  // 2. Business Logic: Service không tồn tại
  {
    const res = await req({
      path: '/api/v1/bookings',
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }
    }, {
      name: 'Nguyen Test',
      phone: '0901234567',
      service_id: 99999,
      date: '2026-11-20',
      time: '14:00'
    });
    const parsed = JSON.parse(res.body || '{}');
    const correct = res.status === 422 && parsed?.error?.code === 'VALIDATION_ERROR';
    record('V2-Logic', 'Service_id=99999 trả về HTTP 422 (không văng 500)', correct, `HTTP ${res.status} [${parsed?.error?.code}]`);
  }

  // 3. Stored XSS Verification
  {
    const slug = `xss-check-${Date.now()}`;
    const postRes = await req({
      path: '/api/v1/posts',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${API_KEY}`
      }
    }, {
      title: 'XSS Attack Verification',
      content: '<p>Nội dung sạch</p><svg/onload=alert(1)><script>alert(2)</script><img src="x"/onerror=alert(3)>',
      slug: slug
    });

    if (postRes.status === 200 || postRes.status === 201) {
      const articleRes = await req({ path: `/tin-tuc/${slug}`, method: 'GET' });
      const hasSvgOnload = articleRes.body.includes('onload=') || articleRes.body.includes('alert(1)');
      const hasScriptAlert = articleRes.body.includes('alert(2)');
      const hasOnerror = articleRes.body.includes('onerror=');
      const isClean = !hasSvgOnload && !hasScriptAlert && !hasOnerror;
      record('V3-XSS', 'Loại bỏ hoàn toàn Stored XSS (svg/onload, script, onerror)', isClean, isClean ? 'Payload bị lọc sạch' : 'XSS lọt qua!');
    } else {
      record('V3-XSS', 'Tạo bài test XSS', false, `Status ${postRes.status}`);
    }
  }

  // 4. CORS Check với Origin độc hại
  {
    const res = await req({
      path: '/api/v1/services',
      method: 'GET',
      headers: { 'Origin': 'https://evil-hacker.com' }
    });
    const acao = res.headers['access-control-allow-origin'];
    record('V4-CORS', 'Từ chối cấp CORS cho domain lạ https://evil-hacker.com', !acao, acao ? `Lộ origin: ${acao}` : 'Không cấp header ACAO');
  }

  // 5. Path Traversal & FilesMatch
  {
    const envRes = await req({ path: '/.env', method: 'GET' });
    const gitRes = await req({ path: '/.git/config', method: 'GET' });
    const coreRes = await req({ path: '/backend/core/Database.php', method: 'GET' });
    const safe = (envRes.status === 403 || envRes.status === 404) &&
                 (gitRes.status === 403 || gitRes.status === 404) &&
                 (coreRes.status === 403 || coreRes.status === 404);
    record('V5-PathTraversal', 'Chặn truy cập trực tiếp .env, .git, backend core (403/404)', safe, `.env:${envRes.status}, .git:${gitRes.status}, core:${coreRes.status}`);
  }

  // 6. Error Message Leakage Check
  {
    const res = await req({ path: '/api/v1/bookings', method: 'GET' });
    const hasSqlError = res.body.includes('SQLSTATE') || res.body.includes('Fatal error') || res.body.includes('Stack trace');
    record('V6-InfoLeak', 'Không lộ Stack Trace hay SQL Error khi gọi sai method GET', !hasSqlError && res.status === 405, `HTTP ${res.status}`);
  }

  // 7. CSRF Protection
  {
    const res = await req({
      path: '/admin/login.php',
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    }, 'email=admin@nhakhoakimdung.com&password=AdminKimDung@2026!&csrf_token=INVALID_TOKEN');
    const safe = res.body.includes('Phiên bảo mật đã hết hạn') || res.status === 403;
    record('V7-CSRF', 'Chặn form submit với CSRF token giả', safe, safe ? 'Phát hiện token giả' : 'Bị lọt qua');
  }

  // 8. Auth Brute Force & Rate Limit Lockout
  {
    let blocked429 = false;
    for (let i = 0; i < 7; i++) {
      const res = await req({
        path: '/admin/login.php',
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
      }, `email=admin@test.com&password=wrong${i}&csrf_token=fake`);
      if (res.body.includes('thử đăng nhập thất bại quá nhiều lần') || res.status === 429) {
        blocked429 = true;
        break;
      }
    }
    record('V8-AuthBruteForce', 'Rate limiter khóa brute-force login sau 5 lần', blocked429, blocked429 ? 'Bị chặn thành công' : 'Chưa bị chặn');
  }

  // 9. Concurrency / Race Condition on Booking Slot (2 simultaneous requests)
  resetLimits();
  {
    const randomDay = String(10 + Math.floor(Math.random() * 18)).padStart(2, '0');
    const randomMonth = String(1 + Math.floor(Math.random() * 12)).padStart(2, '0');
    const date = `2027-${randomMonth}-${randomDay}`;
    const time = '09:00';
    const [res1, res2] = await Promise.all([
      req({
        path: '/api/v1/bookings',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
      }, { name: 'Khach A', phone: '0901111111', service_id: 1, date, time }),
      req({
        path: '/api/v1/bookings',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
      }, { name: 'Khach B', phone: '0902222222', service_id: 1, date, time })
    ]);
    const statuses = [res1.status, res2.status].sort();
    const raceSafe = statuses[0] === 201 && statuses[1] === 409;
    record('V9-RaceCondition', 'GET_LOCK chặn double-booking (1 thành công 201, 1 xung đột 409)', raceSafe, `Req1: HTTP ${res1.status}, Req2: HTTP ${res2.status}`);
  }

  // 10. Rate Limiting Booking API Flood (35 rapid requests vượt ngưỡng 30/15m)
  {
    const floodRequests = Array.from({ length: 35 }, (_, i) =>
      req({
        path: '/api/v1/bookings',
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
      }, { name: `Spam ${i}`, phone: `090000000${i % 10}`, service_id: 1, date: '2028-01-01', time: '15:00' })
    );
    const responses = await Promise.all(floodRequests);
    const has429 = responses.some(r => r.status === 429);
    record('V10-RateLimit', 'API Rate Limiting kích hoạt khi 1 IP gửi dồn dập >30 lần (HTTP 429)', has429, `Đã ghi nhận HTTP 429 bảo vệ hệ thống`);
  }

  console.log('\n═══════════════════════════════════════════════════════════════════');
  const total = results.length;
  const passedCount = results.filter(r => r.passed).length;
  console.log(`KẾT QUẢ: ${passedCount}/${total} VÉC-TƠ ĐÃ CHẶN THÀNH CÔNG (${Math.round((passedCount/total)*100)}%)`);
  console.log('═══════════════════════════════════════════════════════════════════');
}

run().catch(err => {
  console.error('Lỗi khi chạy tấn công:', err);
  process.exit(1);
});
