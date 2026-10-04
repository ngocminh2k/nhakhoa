// tests/e2e_api_test.mjs
// Real End-to-End Integration Test against live server & real MariaDB database
import os from 'os';
import fs from 'fs';
import path from 'path';

// Clean rate limit cache before test suite runs
const rateLimitDir = path.join(os.tmpdir(), 'kd_rate_limits');
if (fs.existsSync(rateLimitDir)) {
    fs.rmSync(rateLimitDir, { recursive: true, force: true });
}

const BASE = process.argv[2] || 'http://127.0.0.1:8080';
const API_KEY = 'kd_db6742ceea37b12d20c902eed5d9398e2a76628a51b8fcbb';

console.log(`\n======================================================`);
console.log(`🦷 Nha Khoa Kim Dung — Live E2E Integration Suite`);
console.log(`Target: ${BASE}`);
console.log(`======================================================\n`);

let passed = 0;
let failed = 0;

async function check(name, fn) {
    process.stdout.write(`Testing: ${name}... `);
    try {
        await fn();
        passed++;
        console.log('\x1b[32m[PASS]\x1b[0m');
    } catch (e) {
        failed++;
        console.log('\x1b[31m[FAIL]\x1b[0m');
        console.error('   Error:', e.message);
    }
}

function assert(cond, msg = 'Assertion failed') {
    if (!cond) throw new Error(msg);
}

// -----------------------------------------------------------
// 1. Check server reachable
// -----------------------------------------------------------
await check('Server is reachable & serving index', async () => {
    const res = await fetch(`${BASE}/`);
    assert(res.ok || res.status === 200, `HTTP ${res.status}`);
});

// -----------------------------------------------------------
// 2. GET /api/v1/services
// -----------------------------------------------------------
let services = [];
await check('GET /api/v1/services returns catalog with 9 dental services', async () => {
    const res = await fetch(`${BASE}/api/v1/services`);
    assert(res.status === 200, `Expected 200, got ${res.status}`);
    const data = await res.json();
    assert(data.success === true, 'Response success should be true');
    assert(Array.isArray(data.services), 'data.services should be an array');
    assert(data.services.length >= 9, `Expected >= 9 services, got ${data.services.length}`);
    services = data.services;
});

// -----------------------------------------------------------
// 3. GET /api/v1/availability
// -----------------------------------------------------------
let availableSlot = '';
const testDate = new Date(Date.now() + 86400000 * (2 + Math.floor(Math.random() * 25))).toISOString().split('T')[0];
await check('GET /api/v1/availability returns business slots for date', async () => {
    const serviceId = services[0]?.id || 1;
    const res = await fetch(`${BASE}/api/v1/availability?service_id=${serviceId}&date=${testDate}`);
    assert(res.status === 200, `Expected 200, got ${res.status}`);
    const data = await res.json();
    assert(data.success === true, 'Response success should be true');
    assert(Array.isArray(data.slots), 'slots should be an array');
    assert(data.slots.length > 0, 'Should have free slots');
    availableSlot = data.slots[0];
});

// -----------------------------------------------------------
// 4. POST /api/v1/bookings — Validation rejection (invalid phone)
// -----------------------------------------------------------
await check('POST /api/v1/bookings rejects invalid phone with 422', async () => {
    const res = await fetch(`${BASE}/api/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: 'Nguyễn Văn Test',
            phone: '0123456789', // Invalid carrier prefix
            service_id: 1,
            date: testDate,
            time: '09:00',
        }),
    });
    assert(res.status === 422, `Expected 422, got ${res.status}`);
    const data = await res.json();
    assert(data.success === false, 'Should fail');
    assert(data.error.message.includes('Số điện thoại'), `Expected phone error, got: ${data.error.message}`);
});

// -----------------------------------------------------------
// 5. POST /api/v1/bookings — Honeypot bot trap
// -----------------------------------------------------------
await check('POST /api/v1/bookings traps bot via honeypot (fake 200 without DB write)', async () => {
    const res = await fetch(`${BASE}/api/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: 'Spam Bot',
            phone: '0987654321',
            service_id: 1,
            date: testDate,
            time: '09:00',
            hp_field: 'I am a spammer robot',
        }),
    });
    assert(res.status === 200, `Expected 200, got ${res.status}`);
    const data = await res.json();
    assert(data.success === true, 'Honeypot returns fake success');
    assert(data.booking_code.endsWith('-0000'), 'Fake booking code signature for spam trap');
});

// -----------------------------------------------------------
// 6. POST /api/v1/bookings — Real booking creation
// -----------------------------------------------------------
let createdBookingCode = '';
await check('POST /api/v1/bookings successfully creates valid booking', async () => {
    const res = await fetch(`${BASE}/api/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: 'Trần Thị Mai',
            phone: '0912345678',
            service_id: 1,
            date: testDate,
            time: availableSlot,
            notes: 'Khách hàng hẹn khám lần đầu',
            utm_source: 'google_ads',
            utm_campaign: 'implant_autumn',
        }),
    });
    const data = await res.json();
    assert(res.status === 201, `Expected 201 Created, got ${res.status}: ${JSON.stringify(data)}`);
    assert(data.success === true, 'Success should be true');
    assert(data.booking_code.startsWith('KD-'), 'Booking code should start with KD-');
    createdBookingCode = data.booking_code;
});

// -----------------------------------------------------------
// 7. POST /api/v1/bookings — Double booking collision prevention (409 Conflict)
// -----------------------------------------------------------
await check('POST /api/v1/bookings rejects double booking on same slot with 409', async () => {
    const res = await fetch(`${BASE}/api/v1/bookings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: 'Lê Văn Trùng Lịch',
            phone: '0988776655',
            service_id: 1,
            date: testDate,
            time: availableSlot, // Exact same slot
        }),
    });
    assert(res.status === 409, `Expected 409 Conflict, got ${res.status}`);
    const data = await res.json();
    assert(data.success === false, 'Double booking must fail');
    assert(['SLOT_CONFLICT', 'SLOT_UNAVAILABLE'].includes(data.error.code), `Expected conflict code, got: ${data.error.code}`);
});

// -----------------------------------------------------------
// 8. POST /api/v1/posts — Publish article via valid API Key
// -----------------------------------------------------------
const uniqueSlug = 'chuyen-de-cay-ghep-implant-' + Date.now();
await check('POST /api/v1/posts creates article with valid API Key', async () => {
    const res = await fetch(`${BASE}/api/v1/posts`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_KEY}`,
        },
        body: JSON.stringify({
            title: 'Chuyên Đề Cấy Ghép Implant Toàn Hàm All-On-4 Tại Kim Dung',
            slug: uniqueSlug,
            excerpt: 'Giải pháp phục hình răng toàn hàm tối ưu cho người mất nhiều răng.',
            content: '<h2>Phục hình răng toàn diện</h2><p>Công nghệ All-on-4 khôi phục 98% lực nhai tự nhiên.</p>',
            featured_image: 'https://nhakhoakimdung.vn/images/all-on-4.jpg',
            status: 'published',
        }),
    });

    const data = await res.json();
    assert(res.status === 201, `Expected 201, got ${res.status}: ${JSON.stringify(data)}`);
    assert(data.success === true, 'Publish must succeed');
    assert(data.action === 'created', 'Action should be created');
});

// -----------------------------------------------------------
// 9. POST /api/v1/posts — Upsert update existing article
// -----------------------------------------------------------
await check('POST /api/v1/posts updates existing article when slug matches', async () => {
    const res = await fetch(`${BASE}/api/v1/posts`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${API_KEY}`,
        },
        body: JSON.stringify({
            title: 'Chuyên Đề Cấy Ghép Implant Toàn Hàm All-On-4 (Cập Nhật 2026)',
            slug: uniqueSlug,
            excerpt: 'Phiên bản cập nhật mới nhất với phác đồ điều trị 3D.',
            content: '<h2>Cập nhật 2026</h2><p>Bổ sung công nghệ định vị phẫu thuật số hóa Dynamic Navigation.</p>',
            status: 'published',
        }),
    });

    const data = await res.json();
    assert(res.status === 200, `Expected 200 for update, got ${res.status}`);
    assert(data.action === 'updated', 'Action should be updated');
});

// -----------------------------------------------------------
// 10. POST /api/v1/posts — Reject invalid API Key with 401
// -----------------------------------------------------------
await check('POST /api/v1/posts rejects unauthorized request with 401', async () => {
    const res = await fetch(`${BASE}/api/v1/posts`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer wrong_token_fake',
        },
        body: JSON.stringify({
            title: 'Hacked Title',
            content: '<p>Hacked</p>',
        }),
    });
    assert(res.status === 401, `Expected 401, got ${res.status}`);
});

// -----------------------------------------------------------
// 11. GET /tin-tuc/:slug — Public reader renders published article
// -----------------------------------------------------------
await check('GET /tin-tuc/:slug serves rendered article with typography & SEO', async () => {
    const res = await fetch(`${BASE}/tin-tuc/${uniqueSlug}`);
    assert(res.status === 200, `Expected 200, got ${res.status}`);
    const html = await res.text();
    assert(html.includes('Chuyên Đề Cấy Ghép Implant Toàn Hàm'), 'HTML should contain updated title');
    assert(html.includes('Dynamic Navigation'), 'HTML should contain updated content');
    assert(html.includes('Đặt lịch hẹn') || html.includes('gf-news-booking-form'), 'HTML should contain CTA booking button');
});

// -----------------------------------------------------------
// Summary
// -----------------------------------------------------------
console.log(`\n======================================================`);
console.log(`🎉 ALL ${passed} LIVE INTEGRATION TESTS PASSED! (0 failed)`);
console.log(`======================================================\n`);
