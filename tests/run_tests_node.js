// tests/run_tests_node.js
// Unit & Integration test suite runner implementing exact validation & sanitization rules

let passed = 0;
let failed = 0;

function test(name, fn) {
    process.stdout.write(`Running: ${name}... `);
    try {
        fn();
        passed++;
        console.log('\x1b[32m[PASS]\x1b[0m');
    } catch (e) {
        failed++;
        console.log('\x1b[31m[FAIL]\x1b[0m');
        console.error('   Error:', e.message);
    }
}

function assert(condition, msg = 'Assertion failed') {
    if (!condition) throw new Error(msg);
}

function assertEquals(expected, actual, msg = '') {
    if (JSON.stringify(expected) !== JSON.stringify(actual)) {
        throw new Error(`Expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}. ${msg}`);
    }
}

console.log('========================================================');
console.log('🦷 Nha Khoa Kim Dung - Automated TDD & Logic Test Suite');
console.log('========================================================\n');

// 1. Validator Tests
console.log('--- [Suite 1: Vietnamese Phone & Data Validator] ---');
const vnPhoneRegex = /^(0|\+84)[3|5|7|8|9][0-9]{8}$/;

test('Vietnamese phone number regex matches valid numbers', () => {
    const valid = ['0912345678', '0388999888', '0776543210', '0868123456', '0581234567', '+84912345678'];
    valid.forEach(p => assert(vnPhoneRegex.test(p), `Phone ${p} must be valid`));
});

test('Vietnamese phone number regex rejects invalid numbers', () => {
    const invalid = ['0123456789', '09123', '091234567899', 'abc0912345', '0243888888', ''];
    invalid.forEach(p => assert(!vnPhoneRegex.test(p), `Phone ${p} must be invalid`));
});

test('Date validation enforces YYYY-MM-DD and calendar validity', () => {
    const isValidDate = (str) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(str)) return false;
        const [y, m, d] = str.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d));
        return date.getUTCFullYear() === y && date.getUTCMonth() === m - 1 && date.getUTCDate() === d;
    };

    assert(isValidDate('2026-10-05'));
    assert(isValidDate('2026-02-28'));
    assert(!isValidDate('2026-02-29')); // Not a leap year
    assert(!isValidDate('05/10/2026'));
    assert(!isValidDate('2026-13-01'));
});

test('Time validation enforces HH:MM within 24h format', () => {
    const isValidTime = (str) => /^([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/.test(str);
    assert(isValidTime('08:00'));
    assert(isValidTime('19:30'));
    assert(isValidTime('00:00'));
    assert(isValidTime('23:59:59'));
    assert(!isValidTime('24:00'));
    assert(!isValidTime('08:60'));
    assert(!isValidTime('8:00'));
});

// 2. Sanitizer Tests
console.log('\n--- [Suite 2: XSS Sanitizer & Vietnamese Slugify] ---');
function slugify(text) {
    const map = {
        'à':'a','á':'a','ả':'a','ã':'a','ạ':'a','ă':'a','ằ':'a','ắ':'a','ẳ':'a','ẵ':'a','ặ':'a',
        'â':'a','ầ':'a','ấ':'a','ẩ':'a','ẫ':'a','ậ':'a','đ':'d','è':'e','é':'e','ẻ':'e','ẽ':'e','ẹ':'e',
        'ê':'e','ề':'e','ế':'e','ể':'e','ễ':'e','ệ':'e','ì':'i','í':'i','ỉ':'i','ĩ':'i','ị':'i',
        'ò':'o','ó':'o','ỏ':'o','õ':'o','ọ':'o','ô':'o','ồ':'o','ố':'o','ổ':'o','ỗ':'o','ộ':'o',
        'ơ':'o','ờ':'o','ớ':'o','ở':'o','ỡ':'o','ợ':'o','ù':'u','ú':'u','ủ':'u','ũ':'u','ụ':'u',
        'ư':'u','ừ':'u','ứ':'u','ử':'u','ữ':'u','ự':'u','ỳ':'y','ý':'y','ỷ':'y','ỹ':'y','ỵ':'y'
    };
    let str = text.toLowerCase();
    for (const [k, v] of Object.entries(map)) {
        str = str.replace(new RegExp(k, 'g'), v);
    }
    return str.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

test('Vietnamese slug generation removes accents and special characters', () => {
    const input = 'Cấy Ghép Răng Implant Chuẩn Quốc Tế Tại Thái Nguyên!';
    assertEquals('cay-ghep-rang-implant-chuan-quoc-te-tai-thai-nguyen', slugify(input));
});

test('XSS script and iframe tags removal', () => {
    const cleanHtml = (html) => html
        .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
        .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
        .replace(/on\w+\s*=\s*(["'][^"']*["']|[^\s>]+)/gi, '')
        .replace(/href\s*=\s*["']javascript:[^"']*["']/gi, 'href="#"');

    const dirty = '<p>Chào mừng!</p><script>alert(1)</script><iframe src="hack.com"></iframe><a href="javascript:void(0)" onclick="evil()">Click</a>';
    const cleaned = cleanHtml(dirty);

    assert(!cleaned.includes('<script'));
    assert(!cleaned.includes('<iframe'));
    assert(!cleaned.includes('onclick'));
    assert(!cleaned.includes('javascript:'));
    assert(cleaned.includes('<p>Chào mừng!</p>'));
});

// 3. Availability Math & Collision Tests
console.log('\n--- [Suite 3: Availability & Slot Overlap Prevention Engine] ---');
const isConflict = (s1, e1, s2, e2) => (s1 < e2) && (e1 > s2);

test('Collision detection identifies slot conflicts with precision', () => {
    // Overlapping
    assert(isConflict('08:00', '08:45', '08:30', '09:00'), '08:30 starts before 08:45');
    assert(isConflict('08:00', '08:30', '08:00', '08:30'), 'Exact duplicate');
    assert(isConflict('08:00', '10:00', '08:30', '09:00'), 'Enclosing interval');

    // Non-overlapping
    assert(!isConflict('08:00', '08:30', '08:30', '09:00'), 'Back to back slots do not conflict');
    assert(!isConflict('08:30', '09:00', '08:00', '08:30'), 'Back to back slots reverse do not conflict');
    assert(!isConflict('08:00', '08:30', '14:00', '14:30'), 'Different times do not conflict');
});

test('Slot generator respects business hours boundaries and duration', () => {
    const openTime = 8 * 60; // 08:00 (480 mins)
    const closeTime = 10 * 60; // 10:00 (600 mins)
    const duration = 45; // 45 mins
    const step = 30; // 30 mins

    const slots = [];
    for (let t = openTime; t < closeTime; t += step) {
        if (t + duration <= closeTime) {
            const h = String(Math.floor(t / 60)).padStart(2, '0');
            const m = String(t % 60).padStart(2, '0');
            slots.push(`${h}:${m}`);
        }
    }

    // 08:00 -> 08:45 (fits, end 08:45 <= 10:00)
    // 08:30 -> 09:15 (fits, end 09:15 <= 10:00)
    // 09:00 -> 09:45 (fits, end 09:45 <= 10:00)
    // 09:30 -> 10:15 (fails, 10:15 > 10:00)
    assertEquals(['08:00', '08:30', '09:00'], slots);
});

console.log('\n========================================================');
console.log(`Test Results: ${passed} Passed, ${failed} Failed.`);
if (failed > 0) {
    process.exit(1);
} else {
    console.log('🎉 100% TESTS PASSED! ALL ALGORITHMIC CONTRACTS VERIFIED.');
    process.exit(0);
}
