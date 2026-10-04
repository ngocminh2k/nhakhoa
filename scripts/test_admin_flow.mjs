// scripts/test_admin_flow.mjs
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
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      headers['Content-Length'] = Buffer.byteLength(data);
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

async function run() {
  clearRateLimits();
  console.log('--- 1. GET /admin/login.php ---');
  let res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/login.php', method: 'GET' });
  let cookies = res.cookie;
  const matchCsrf = res.body.match(/name="csrf_token"\s+value="([^"]+)"/);
  const loginCsrf = matchCsrf ? matchCsrf[1] : '';
  console.log('Login CSRF:', loginCsrf, 'Cookie:', cookies);

  console.log('\n--- 2. POST /admin/login.php ---');
  const loginPayload = `csrf_token=${encodeURIComponent(loginCsrf)}&email=admin%40nhakhoakimdung.com&password=AdminKimDung%402026%21`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/login.php', method: 'POST' }, loginPayload, cookies);
  console.log('Login Status:', res.status, 'Location:', res.headers.location);
  const errMatch = res.body.match(/<div class="alert-error">([\s\S]*?)<\/div>/);
  if (errMatch) console.log('Login Error on page:', errMatch[1].trim());
  if (res.cookie) cookies = res.cookie;
  console.log('Updated Cookie after login:', cookies);

  console.log('\n--- 3. GET /admin/index.php ---');
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/index.php', method: 'GET' }, null, cookies);
  console.log('Dashboard Status:', res.status, 'Body Length:', res.body.length);
  console.log('Contains "Trung Tâm Điều Hành":', res.body.includes('Trung Tâm Điều Hành'));
  console.log('Contains "Be Vietnam Pro":', res.body.includes('Be Vietnam Pro'));
  const warnMatches = res.body.match(/(?:Warning|Notice|Deprecated|Fatal error):[\s\S]*?on line \d+/g);
  if (warnMatches) {
    console.log('Found Warnings in index.php:\n', warnMatches.join('\n'));
  }

  const dashCsrfMatch = res.body.match(/name="csrf_token"\s+value="([^"]+)"/);
  const dashCsrf = dashCsrfMatch ? dashCsrfMatch[1] : '';

  console.log('\n--- 4. POST Quick Booking Intake ---');
  console.log('Using Dashboard CSRF:', dashCsrf);
  const intakePayload = `csrf_token=${encodeURIComponent(dashCsrf)}&action=quick_booking&customer_name=${encodeURIComponent('Trần Thị Bích Ngọc')}&phone=0912345678&service_id=2&booking_date=2026-10-05&booking_time=10%3A30&notes=${encodeURIComponent('Khách gọi qua hotline tư vấn sứ')}`;
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/index.php', method: 'POST' }, intakePayload, cookies);
  console.log('Intake Post Status:', res.status);
  const alertMatch = res.body.match(/<div class="alert alert-([^"]+)">([\s\S]*?)<\/div>/);
  if (alertMatch) {
    console.log('Intake Alert type:', alertMatch[1], 'text:', alertMatch[2].trim());
  } else {
    console.log('No alert tag found. Response body:\n', res.body);
  }
  console.log('Contains Patient "Trần Thị Bích Ngọc":', res.body.includes('Trần Thị Bích Ngọc'));

  // Extract booking id from row to test inline status update
  const bookingIdMatch = res.body.match(/name="booking_id"\s+value="(\d+)"/);
  if (bookingIdMatch) {
    const bookingId = bookingIdMatch[1];
    console.log('\n--- 4.2. POST In-place Status Update for Booking #' + bookingId + ' ---');
    const updatePayload = `csrf_token=${encodeURIComponent(dashCsrf)}&action=update_booking_status&booking_id=${bookingId}&status=confirmed`;
    const updateRes = await request({ host: '127.0.0.1', port: 8080, path: '/admin/index.php', method: 'POST' }, updatePayload, cookies);
    const updateAlertMatch = updateRes.body.match(/<div class="alert alert-([^"]+)">([\s\S]*?)<\/div>/);
    console.log('Update Status Result:', updateAlertMatch ? updateAlertMatch[2].trim() : 'No alert');
  }

  console.log('\n--- 5. GET /admin/analytics.php ---');
  res = await request({ host: '127.0.0.1', port: 8080, path: '/admin/analytics.php', method: 'GET' }, null, cookies);
  console.log('Analytics Status:', res.status);
  const warnAnalytics = res.body.match(/(?:Warning|Notice|Deprecated|Fatal error):[\s\S]*?on line \d+/g);
  if (warnAnalytics) {
    console.log('Found Warnings in analytics.php:\n', warnAnalytics.join('\n'));
  } else {
    console.log('Zero warnings in analytics.php!');
  }
}

run().catch(console.error);
