<?php
// tests/run.php
// Master test suite runner — executes all test suites and exits with 0 (all pass) or 1 (any fail)

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/ValidatorTest.php';
require_once __DIR__ . '/SanitizerTest.php';
require_once __DIR__ . '/RateLimiterTest.php';
require_once __DIR__ . '/CsrfTest.php';
require_once __DIR__ . '/AvailabilityServiceLogicTest.php';

echo "=========================================\n";
echo "Nha Khoa Kim Dung - Test Suite Runner\n";
echo "=========================================\n";

runValidatorTests();
runSanitizerTests();
runRateLimiterTests();
runCsrfTests();
runAvailabilityServiceLogicTests();

$exitCode = TestRunner::report();
exit($exitCode);
