<?php
// tests/ValidatorTest.php

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../backend/core/Validator.php';

function runValidatorTests(): void {
    echo "\n--- [Suite: ValidatorTest] ---\n";

    TestRunner::test('Validator passes on completely valid input dataset', function() {
        $data = [
            'name'       => 'Nguyễn Văn A',
            'phone'      => '0912345678',
            'service_id' => 1,
            'date'       => '2026-10-15',
            'time'       => '09:30',
            'note'       => 'Khám răng hàm mặt tổng quát',
        ];
        $v = new Validator($data);
        $v->required('name', 'Tên')
          ->maxLength('name', 50, 'Tên')
          ->required('phone', 'SĐT')
          ->phone('phone', 'SĐT')
          ->required('service_id', 'Dịch vụ')
          ->integer('service_id', 'Dịch vụ')
          ->required('date', 'Ngày')
          ->date('date', 'Ngày')
          ->required('time', 'Giờ')
          ->time('time', 'Giờ')
          ->maxLength('note', 500, 'Ghi chú');

        TestRunner::assertTrue($v->passes(), 'All valid fields must pass');
        TestRunner::assertCount(0, $v->getErrors());
        TestRunner::assertEquals('', $v->getFirstError());
    });

    TestRunner::test('Validator::required catches null, empty, whitespace and missing fields', function() {
        $data = [
            'empty_str'  => '',
            'spaces_str' => "   \t  \n ",
            'null_val'   => null,
            // 'missing' is not set
        ];
        $v = new Validator($data);
        $v->required('empty_str', 'Trường rỗng')
          ->required('spaces_str', 'Trường khoảng trắng')
          ->required('null_val', 'Trường null')
          ->required('missing', 'Trường thiếu');

        TestRunner::assertFalse($v->passes());
        $errors = $v->getErrors();
        TestRunner::assertCount(4, $errors);
        TestRunner::assertEquals('Trường rỗng không được để trống.', $errors['empty_str']);
        TestRunner::assertEquals('Trường khoảng trắng không được để trống.', $errors['spaces_str']);
        TestRunner::assertEquals('Trường null không được để trống.', $errors['null_val']);
        TestRunner::assertEquals('Trường thiếu không được để trống.', $errors['missing']);
        TestRunner::assertEquals('Trường rỗng không được để trống.', $v->getFirstError());
    });

    TestRunner::test('Validator::required allows zero as valid value', function() {
        $v = new Validator(['zero_int' => 0, 'zero_str' => '0']);
        $v->required('zero_int', 'Zero Int')
          ->required('zero_str', 'Zero Str');

        TestRunner::assertTrue($v->passes(), 'Zero (integer or string) should be accepted by required');
    });

    TestRunner::test('Validator::phone validates all Vietnamese mobile prefixes (03x, 05x, 07x, 08x, 09x)', function() {
        $validPhones = [
            '0912345678', // VinaPhone
            '0909876543', // MobiFone
            '0987654321', // Viettel
            '0868123456', // Viettel 086
            '0888123456', // VinaPhone 088
            '0898123456', // MobiFone 089
            '0776543210', // MobiFone 077
            '0701234567', // MobiFone 070
            '0388999888', // Viettel 038
            '0321234567', // Viettel 032
            '0581234567', // Vietnamobile 058
            '0561234567', // Vietnamobile 056
            '0591234567', // Gmobile 059
        ];
        foreach ($validPhones as $phone) {
            $v = new Validator(['phone' => $phone]);
            $v->phone('phone', 'SĐT');
            TestRunner::assertTrue($v->passes(), "VN mobile phone {$phone} must pass");
        }
    });

    TestRunner::test('Validator::phone validates +84 international format', function() {
        $validInternational = [
            '+84912345678',
            '+84388999888',
            '+84776543210',
            '+84868123456',
            '+84581234567',
        ];
        foreach ($validInternational as $phone) {
            $v = new Validator(['phone' => $phone]);
            $v->phone('phone', 'SĐT');
            TestRunner::assertTrue($v->passes(), "+84 phone {$phone} must pass");
        }
    });

    TestRunner::test('Validator::phone rejects invalid prefixes, wrong lengths, letters, symbols and injection', function() {
        $invalidPhones = [
            '0123456789',            // 01x is old 11-digit prefix, not valid 10-digit
            '0243888888',            // Landline Hanoi (024)
            '0283888888',            // Landline HCMC (028)
            '0412345678',            // Invalid prefix 04
            '0612345678',            // Invalid prefix 06
            '09123',                 // Too short (5 digits)
            '091234567',             // Too short (9 digits)
            '091234567899',          // Too long (12 digits)
            '+8491234567',           // +84 too short
            '+8491234567899',        // +84 too long
            '091234567a',            // Contains letter
            'abc0912345',            // Leading letters
            '0912-345-678',          // Contains dashes
            '0912.345.678',          // Contains dots
            '(091)2345678',          // Contains parentheses
            "0912345678' OR '1'='1", // SQL injection
            '<script>0912345678',    // XSS attempt
            '0|12345678',            // Literal pipe
        ];
        foreach ($invalidPhones as $phone) {
            $v = new Validator(['phone' => $phone]);
            $v->phone('phone', 'SĐT');
            TestRunner::assertFalse($v->passes(), "Invalid phone '{$phone}' must be rejected");
            TestRunner::assertNotEmpty($v->getFirstError());
        }
    });

    TestRunner::test('Validator::phone handles optional empty field vs required phone', function() {
        // Standalone phone on empty string is treated as optional (no format error)
        $v1 = new Validator(['phone' => '']);
        $v1->phone('phone', 'SĐT');
        TestRunner::assertTrue($v1->passes(), 'Empty optional phone field passes format check');

        // Missing field passes optional check
        $v2 = new Validator([]);
        $v2->phone('phone', 'SĐT');
        TestRunner::assertTrue($v2->passes(), 'Missing optional phone field passes format check');

        // Chaining required + phone properly rejects empty string
        $v3 = new Validator(['phone' => '   ']);
        $v3->required('phone', 'SĐT')->phone('phone', 'SĐT');
        TestRunner::assertFalse($v3->passes(), 'Required phone with empty string must fail');

        // Chaining required + phone properly rejects null
        $v4 = new Validator(['phone' => null]);
        $v4->required('phone', 'SĐT')->phone('phone', 'SĐT');
        TestRunner::assertFalse($v4->passes(), 'Required phone with null must fail');
    });

    TestRunner::test('Validator::date accepts valid calendar dates in Y-m-d format', function() {
        $validDates = [
            '2026-10-15',
            '2024-02-29', // 2024 is a leap year
            '2026-12-31',
            '2026-01-01',
        ];
        foreach ($validDates as $d) {
            $v = new Validator(['date' => $d]);
            $v->date('date', 'Ngày');
            TestRunner::assertTrue($v->passes(), "Date {$d} should be valid");
        }
    });

    TestRunner::test('Validator::date rejects invalid leap years, out-of-range dates and malformed strings', function() {
        $invalidDates = [
            '2026-02-29',             // 2026 is NOT a leap year
            '2026-04-31',             // April has only 30 days
            '2026-13-01',             // Month 13 does not exist
            '2026-00-15',             // Month 0 does not exist
            '2026-10-32',             // Day 32 does not exist
            '15/10/2026',             // Wrong format (d/m/Y)
            '2026/10/15',             // Wrong separator
            '2026-1-1',               // Missing leading zero
            'today',                  // Plain text
            '2026-10-15; DROP TABLE', // SQL injection attempt
        ];
        foreach ($invalidDates as $d) {
            $v = new Validator(['date' => $d]);
            $v->date('date', 'Ngày');
            TestRunner::assertFalse($v->passes(), "Date '{$d}' must be rejected");
        }
    });

    TestRunner::test('Validator::time accepts valid 24h formats HH:MM and HH:MM:SS', function() {
        $validTimes = [
            '00:00',
            '08:00',
            '08:30',
            '12:00',
            '19:30',
            '23:59',
            '08:30:00',
            '19:30:45',
            '00:00:00',
            '23:59:59',
        ];
        foreach ($validTimes as $t) {
            $v = new Validator(['time' => $t]);
            $v->time('time', 'Giờ');
            TestRunner::assertTrue($v->passes(), "Time {$t} should be valid");
        }
    });

    TestRunner::test('Validator::time rejects invalid hours, minutes, seconds and malformed times', function() {
        $invalidTimes = [
            '24:00',                  // 24:00 is invalid in 24h format (must be 00:00)
            '24:01',                  // Hour 24 invalid
            '25:00',                  // Hour 25 invalid
            '08:60',                  // Minute 60 invalid
            '08:99',                  // Minute 99 invalid
            '8:30',                   // Single digit hour invalid without leading zero
            '-01:00',                 // Negative time
            '12:00 PM',               // 12h AM/PM format invalid
            'noon',                   // Plain text
            '08:30:60',               // Second 60 invalid
        ];
        foreach ($invalidTimes as $t) {
            $v = new Validator(['time' => $t]);
            $v->time('time', 'Giờ');
            TestRunner::assertFalse($v->passes(), "Time '{$t}' must be rejected");
        }
    });

    TestRunner::test('Validator::integer accepts valid integers including 0 and negative numbers', function() {
        $validInts = [
            0,
            '0',
            1,
            '1',
            42,
            '42',
            -10,
            '-10',
            999999,
            '999999',
        ];
        foreach ($validInts as $num) {
            $v = new Validator(['val' => $num]);
            $v->integer('val', 'Giá trị');
            TestRunner::assertTrue($v->passes(), "Value " . var_export($num, true) . " should pass as integer");
        }
    });

    TestRunner::test('Validator::integer rejects floats, strings, booleans, and arrays', function() {
        $invalidInts = [
            'abc',
            '1.5',
            1.5,
            '10a',
            '10 20',
            true,
            false,
            [],
            ['a' => 1],
            '--1',
        ];
        foreach ($invalidInts as $num) {
            $v = new Validator(['val' => $num]);
            $v->integer('val', 'Giá trị');
            TestRunner::assertFalse($v->passes(), "Value " . var_export($num, true) . " must not pass as integer");
        }
    });

    TestRunner::test('Validator::maxLength correctly measures ASCII and UTF-8 Vietnamese strings', function() {
        // ASCII string: exactly 10 characters
        $v1 = new Validator(['text' => '1234567890']);
        $v1->maxLength('text', 10, 'Độ dài');
        TestRunner::assertTrue($v1->passes(), '10 chars should pass maxLength 10');

        // ASCII string: 11 characters
        $v2 = new Validator(['text' => '12345678901']);
        $v2->maxLength('text', 10, 'Độ dài');
        TestRunner::assertFalse($v2->passes(), '11 chars should fail maxLength 10');

        // Vietnamese UTF-8 multibyte: 'Nguyễn Văn A' has 12 characters (but 17 bytes)
        $vnName = 'Nguyễn Văn A';
        $v3 = new Validator(['name' => $vnName]);
        $v3->maxLength('name', 12, 'Tên');
        TestRunner::assertTrue($v3->passes(), '12 UTF-8 chars should pass maxLength 12');

        $v4 = new Validator(['name' => $vnName]);
        $v4->maxLength('name', 11, 'Tên');
        TestRunner::assertFalse($v4->passes(), '12 UTF-8 chars should fail maxLength 11');

        // Empty string and null handling
        $v5 = new Validator(['name' => '']);
        $v5->maxLength('name', 10, 'Tên');
        TestRunner::assertTrue($v5->passes(), 'Empty string passes maxLength');

        $v6 = new Validator(['name' => null]);
        $v6->maxLength('name', 10, 'Tên');
        TestRunner::assertTrue($v6->passes(), 'Null value passes maxLength');
    });

    TestRunner::test('Validator accumulates multiple errors and formats labels', function() {
        $v = new Validator([
            'phone' => 'invalid_phone',
            'date'  => 'invalid_date',
        ]);
        // Test default labels
        $v->phone('phone')
          ->date('date');

        TestRunner::assertFalse($v->passes());
        $errors = $v->getErrors();
        TestRunner::assertCount(2, $errors);
        TestRunner::assertContains('Số điện thoại không hợp lệ', $errors['phone']);
        TestRunner::assertContains('Ngày định dạng không đúng', $errors['date']);

        // Test custom labels
        $v2 = new Validator(['code' => 'abc']);
        $v2->integer('code', 'Mã đặt lịch');
        TestRunner::assertFalse($v2->passes());
        TestRunner::assertContains('Mã đặt lịch phải là số nguyên', $v2->getFirstError());
    });
}
