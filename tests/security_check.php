<?php
// tests/security_check.php — Native assert self-check for patched security issues
ini_set('zend.assertions', 1);

require_once __DIR__ . '/../backend/security/Sanitizer.php';

// 1. Verify XSS filter blocks solidus and tag-based payloads
$svgPayload = '<svg/onload=alert(1)>';
assert(Sanitizer::html($svgPayload) === '', 'SVG solidus payload must be stripped');

$imgPayload = '<img src="x"/onerror=alert(1)>';
$imgClean = Sanitizer::html($imgPayload);
assert(!str_contains($imgClean, 'onerror'), 'Image solidus onerror must be stripped');
assert(str_contains($imgClean, '<img src="x">'), 'Safe img tag must be preserved');

$scriptPayload = '<script>alert(document.cookie)</script>';
assert(Sanitizer::html($scriptPayload) === '', 'Script blocks must be completely stripped');

$safeHtml = '<p>Xin chao <b>Nha Khoa Kim Dung</b></p>';
assert(Sanitizer::html($safeHtml) === $safeHtml, 'Safe markup must remain intact');

// 2. Verify Sanitizer::text
assert(Sanitizer::text('<script>') === '&lt;script&gt;', 'Text sanitizer must encode HTML special chars');

// 3. Verify Sanitizer::phone
assert(Sanitizer::phone('090-123.4567') === '0901234567', 'Phone sanitizer must strip formatting chars');

echo "ALL 6 SECURITY ASSERTS PASSED\n";
