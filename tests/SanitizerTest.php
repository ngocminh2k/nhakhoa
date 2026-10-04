<?php
// tests/SanitizerTest.php

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../backend/security/Sanitizer.php';

function runSanitizerTests(): void {
    echo "\n--- [Suite: SanitizerTest] ---\n";

    TestRunner::test('Sanitizer::text escapes dangerous HTML characters and handles null', function() {
        $raw = '<script>alert("xss")</script> & "quotes" \'single\' < >';
        $cleaned = Sanitizer::text($raw);
        TestRunner::assertEquals(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; &amp; &quot;quotes&quot; &#039;single&#039; &lt; &gt;',
            $cleaned
        );

        // Null input returns empty string
        TestRunner::assertEquals('', Sanitizer::text(null));

        // Surrounding whitespace is trimmed
        TestRunner::assertEquals('Nguyễn Văn A', Sanitizer::text("   Nguyễn Văn A   \n"));
    });

    TestRunner::test('Sanitizer::slug converts all Vietnamese accents to ASCII slug', function() {
        $title = 'Cấy Ghép Răng Implant Chuẩn Quốc Tế Tại Thái Nguyên!';
        $slug = Sanitizer::slug($title);
        TestRunner::assertEquals('cay-ghep-rang-implant-chuan-quoc-te-tai-thai-nguyen', $slug);

        $allVnChars = 'á à ả ã ạ ă ắ ặ ằ ẳ ẵ â ấn ầ ẩ ẫ ậ đ é è ẻ ẽ ẹ ê ế ề ể ễ ệ í ì ỉ ĩ ị ó ò ỏ õ ọ ô ố ồ ổ ỗ ộ ơ ớ ờ ở ỡ ợ ú ù ủ ũ ụ ư ứ ừ ử ữ ự ý kỳ';
        $slugVn = Sanitizer::slug($allVnChars);
        // Ensure no Vietnamese accented letters remain
        TestRunner::assertMatches('/^[a-z0-9-]+$/', $slugVn, 'Slug must contain only lowercase ASCII alphanumeric and hyphens');
    });

    TestRunner::test('Sanitizer::slug handles symbols, repeated separators and leading/trailing dashes', function() {
        $test1 = '---Khám & Tư Vấn: Niềng Răng (2026)???---';
        TestRunner::assertEquals('kham-tu-van-nieng-rang-2026', Sanitizer::slug($test1));

        $test2 = 'Dịch Vụ @ Nha Khoa #1 $100% * Kim Dung';
        TestRunner::assertEquals('dich-vu-nha-khoa-1-100-kim-dung', Sanitizer::slug($test2));
    });

    TestRunner::test('Sanitizer::html strips script, iframe, style, object, embed tags while preserving safe HTML', function() {
        $html = '<p>Chào mừng bạn!</p><script>evil()</script><iframe src="malicious.com"></iframe><style>body{display:none}</style><object data="exploit.swf"></object><embed src="bad.mov"><b>Răng đẹp</b>';
        $cleaned = Sanitizer::html($html);

        TestRunner::assertNotContains('<script', $cleaned);
        TestRunner::assertNotContains('evil()', $cleaned);
        TestRunner::assertNotContains('<iframe', $cleaned);
        TestRunner::assertNotContains('<style', $cleaned);
        TestRunner::assertNotContains('<object', $cleaned);
        TestRunner::assertNotContains('<embed', $cleaned);

        TestRunner::assertContains('<p>Chào mừng bạn!</p>', $cleaned);
        TestRunner::assertContains('<b>Răng đẹp</b>', $cleaned);
    });

    TestRunner::test('Sanitizer::html handles case insensitivity and self-closing tags', function() {
        $html = '<SCRIPT type="text/javascript">alert(1)</SCRIPT><IFRAME src="x"/>Safe';
        $cleaned = Sanitizer::html($html);

        TestRunner::assertNotContains('<SCRIPT', $cleaned);
        TestRunner::assertNotContains('<IFRAME', $cleaned);
        TestRunner::assertContains('Safe', $cleaned);
    });

    TestRunner::test('Sanitizer::html strips event handlers (onclick, onload, onerror, onmouseover)', function() {
        $malicious = '<button onclick="evil()" onmouseover="steal()" onfocus="log()">Click</button><img src="pic.jpg" onload="hack()" onerror="bad()"/>';
        $cleaned = Sanitizer::html($malicious);

        TestRunner::assertNotContains('onclick', $cleaned);
        TestRunner::assertNotContains('onmouseover', $cleaned);
        TestRunner::assertNotContains('onfocus', $cleaned);
        TestRunner::assertNotContains('onload', $cleaned);
        TestRunner::assertNotContains('onerror', $cleaned);
        TestRunner::assertContains('Click', $cleaned);
    });

    TestRunner::test('Sanitizer::html strips javascript:, vbscript: and data: URIs', function() {
        $malicious = '<a href="javascript:alert(1)">Link 1</a><a href=\'vbscript:msgbox(1)\'>Link 2</a><a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">Link 3</a><a href="https://nhakhoakimdung.com">Safe Link</a>';
        $cleaned = Sanitizer::html($malicious);

        TestRunner::assertNotContains('javascript:', $cleaned);
        TestRunner::assertNotContains('vbscript:', $cleaned);
        TestRunner::assertNotContains('data:', $cleaned);
        TestRunner::assertContains('https://nhakhoakimdung.com', $cleaned);
    });

    TestRunner::test('Sanitizer::cleanHtml alias works identically to Sanitizer::html', function() {
        $html = '<p>Thử nghiệm</p><script>alert(1)</script>';
        TestRunner::assertEquals(Sanitizer::html($html), Sanitizer::cleanHtml($html));
        TestRunner::assertEquals('', Sanitizer::cleanHtml(null));
    });
}
