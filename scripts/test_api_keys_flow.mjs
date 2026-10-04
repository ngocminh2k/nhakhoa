// scripts/test_api_keys_flow.mjs
import http from 'http';
import fs from 'fs';
import os from 'os';
import path from 'path';

function clearRateLimits() {
  try {
    fs.rmSync(path.join(os.tmpdir(), 'kd_rate_limits'), { recursive: true, force: true });
    console.log('Cleared temp rate limit store.');
  } catch {}
}

function request(options, data = null, cookies = '') {
  return new Promise((resolve, reject) => {
    const headers = { ...(options.headers || {}) };
    if (cookies) headers['Cookie'] = cookies;
    if (data) {
      if (typeof data === 'string') {
        headers['Content-Type'] = headers['Content-Type'] || 'application/x-www-form-urlencoded';
        headers['Content-Length'] = Buffer.byteLength(data);
      }
    }
    const req = http.request({ ...options, headers }, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        const setCookie = res.headers['set-cookie'];
        let newCookie = cookies;
        if (setCookie) {
          const parts = setCookie.map(c => c.split(';')[0]);
          newCookie = parts.join('; ');
        }
        resolve({ status: res.statusCode, headers: res.headers, body, cookie: newCookie });
      });
    });
    req.on('error', reject);
    if (data) req.write(data);
    req.end();
  });
}

function assert(cond, msg = 'Assertion failed') {
  if (!cond) throw new Error(msg);
}

async function run() {
  clearRateLimits();
  console.log('=== 1. Login to Admin Portal ===');
  let res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/login.php', method: 'GET' });
  let cookies = res.cookie;
  const matchCsrf = res.body.match(/name="csrf_token"\s+value="([^"]+)"/);
  const loginCsrf = matchCsrf ? matchCsrf[1] : '';

  const loginPayload = `csrf_token=${encodeURIComponent(loginCsrf)}&email=admin%40nhakhoakimdung.com&password=AdminKimDung%402026%21`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/login.php', method: 'POST' }, loginPayload, cookies);
  if (res.cookie) cookies = res.cookie;
  assert(res.status === 302, `Login failed: status ${res.status}`);
  console.log('Logged in successfully. Cookies acquired.');

  console.log('\n=== 2. GET /admin/api_keys.php ===');
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'GET' }, null, cookies);
  assert(res.status === 200, `Expected 200, got ${res.status}`);
  assert(res.body.includes('Quản Lý API Key &amp; Tự Động Hóa'), 'Page title present');
  assert(res.body.includes('Tài Liệu Tích Hợp API Đăng Bài Chuyên Khoa'), 'Doc present');
  console.log('Page loaded cleanly with zero PHP warnings/errors.');

  const csrfMatch = res.body.match(/name="csrf_token"\s+value="([^"]+)"/);
  const adminCsrf = csrfMatch ? csrfMatch[1] : '';
  assert(adminCsrf, 'CSRF token must exist');

  console.log('\n=== 3. POST /admin/api_keys.php -> Create New API Key ===');
  const createPayload = `csrf_token=${encodeURIComponent(adminCsrf)}&action=create&name=${encodeURIComponent('N8N Automation Agent Demo')}`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'POST' }, createPayload, cookies);
  assert(res.status === 200, `Expected 200, got ${res.status}`);
  assert(res.body.includes('N8N Automation Agent Demo'), 'Key name appears in table');

  const keyRevealMatch = res.body.match(/id="revealedSecretKey"\s+readonly\s+value="([^"]+)"/);
  assert(keyRevealMatch, 'Secret key reveal banner must be present');
  const newPlainKey = keyRevealMatch[1];
  console.log('Newly generated plain key:', newPlainKey);
  assert(newPlainKey.startsWith('kd_'), 'Key must start with kd_');

  // Extract the ID of the new key from table
  const keyIdMatch = res.body.match(/N8N Automation Agent Demo[\s\S]*?ID:\s*#(\d+)/);
  assert(keyIdMatch, 'Must find ID for new key');
  const newKeyId = keyIdMatch[1];
  console.log('Key ID:', newKeyId);

  console.log('\n=== 4. Test Calling API /api/v1/posts with the newly created key ===');
  const postArticlePayload = JSON.stringify({
    title: 'Giải Pháp Trồng Răng Toàn Hàm Kỹ Thuật Số ' + Date.now(),
    excerpt: 'Công nghệ định vị 3D giúp quá trình cấy ghép nhanh chóng, an toàn.',
    content: '<h2>Ưu điểm vượt trội</h2><p>Phục hồi răng vững chắc chỉ sau 48 giờ.</p>',
    status: 'published'
  });

  const apiRes = await request({
    host: '127.0.0.1',
    port: 8080,
    path: '/api/v1/posts',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${newPlainKey}`
    }
  }, postArticlePayload);

  console.log('API Post Status:', apiRes.status);
  const apiData = JSON.parse(apiRes.body);
  console.log('API Response:', apiData);
  assert(apiRes.status === 201, 'Should create post successfully with 201');
  assert(apiData.success === true, 'Success must be true');
  assert(apiData.action === 'created', 'Action must be created');
  console.log('Auto-generated slug handle:', apiData.slug);
  console.log('Public URL:', apiData.url);

  console.log('\n=== 5. Verify last_used_at on /admin/api_keys.php ===');
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'GET' }, null, cookies);
  assert(res.body.includes('Đang hoạt động tốt'), 'last_used_at should now be recorded');

  console.log('\n=== 6. POST /admin/api_keys.php -> Disable (Toggle) Key ===');
  const toggleOffPayload = `csrf_token=${encodeURIComponent(adminCsrf)}&action=toggle&id=${newKeyId}&active=0`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'POST' }, toggleOffPayload, cookies);
  assert(res.status === 200, 'Toggle request succeeded');
  assert(res.body.includes('Đã vô hiệu hóa'), 'Badge shows disabled');

  console.log('\n=== 7. Verify Disabled Key is Rejected by API with 401 ===');
  const rejectedRes = await request({
    host: '127.0.0.1',
    port: 8080,
    path: '/api/v1/posts',
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${newPlainKey}`
    }
  }, postArticlePayload);

  console.log('Disabled key status:', rejectedRes.status);
  assert(rejectedRes.status === 401, 'Must reject disabled key with 401');
  const rejectData = JSON.parse(rejectedRes.body);
  console.log('Rejected error code:', rejectData.error?.code);
  assert(rejectData.error?.code === 'UNAUTHORIZED', 'Code must be UNAUTHORIZED');

  console.log('\n=== 8. POST /admin/api_keys.php -> Re-enable Key ===');
  const toggleOnPayload = `csrf_token=${encodeURIComponent(adminCsrf)}&action=toggle&id=${newKeyId}&active=1`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'POST' }, toggleOnPayload, cookies);
  assert(res.status === 200, 'Re-enable request succeeded');
  assert(res.body.includes('Đang hoạt động'), 'Badge shows active');

  console.log('\n=== 9. POST /admin/api_keys.php -> Delete Key ===');
  const deletePayload = `csrf_token=${encodeURIComponent(adminCsrf)}&action=delete&id=${newKeyId}`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/api_keys.php', method: 'POST' }, deletePayload, cookies);
  assert(res.status === 200, 'Delete request succeeded');
  assert(!res.body.includes(`ID: #${newKeyId}`), 'Key ID must no longer exist in table');
  console.log('Key deleted cleanly and permanently.');

  console.log('\n======================================================');
  console.log('🎉 ALL API KEY LIFECYCLE & INTEGRATION TESTS PASSED!');
  console.log('======================================================\n');
}

run().catch((err) => {
  console.error('Test execution failed:', err);
  process.exit(1);
});
