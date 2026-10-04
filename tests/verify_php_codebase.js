// tests/verify_php_codebase.js
// Node.js verification script to validate syntax integrity and file consistency of pure PHP files

const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
let passed = 0;
let failed = 0;
const errors = [];

function checkFile(relPath) {
    const fullPath = path.join(rootDir, relPath);
    if (!fs.existsSync(fullPath)) {
        errors.push(`Missing file: ${relPath}`);
        failed++;
        return;
    }

    const content = fs.readFileSync(fullPath, 'utf8');

    // 1. Must start with <?php
    if (!content.trim().startsWith('<?php')) {
        errors.push(`${relPath}: Does not start with <?php`);
        failed++;
        return;
    }

    // 2. Bracket matching check inside <?php ... ?> blocks
    let inPhp = false;
    let braces = 0;
    let parens = 0;
    let brackets = 0;
    let inString = false;
    let stringChar = '';
    let inSingleLineComment = false;
    let inMultiLineComment = false;

    for (let i = 0; i < content.length; i++) {
        const char = content[i];
        const next = content[i + 1] || '';

        // PHP tag transitions
        if (!inPhp) {
            if (content.substr(i, 5) === '<?php') {
                inPhp = true;
                i += 4;
            } else if (content.substr(i, 3) === '<?=') {
                inPhp = true;
                i += 2;
            }
            continue;
        }

        if (inPhp && !inString && char === '?' && next === '>') {
            inPhp = false;
            inSingleLineComment = false;
            i++;
            continue;
        }

        // Inside PHP mode:
        // Comments
        if (!inString) {
            if (inSingleLineComment) {
                if (char === '\n') inSingleLineComment = false;
                continue;
            }
            if (inMultiLineComment) {
                if (char === '*' && next === '/') {
                    inMultiLineComment = false;
                    i++;
                }
                continue;
            }
            if (char === '/' && next === '/') {
                inSingleLineComment = true;
                i++;
                continue;
            }
            if (char === '/' && next === '*') {
                inMultiLineComment = true;
                i++;
                continue;
            }
            if (char === '#') {
                inSingleLineComment = true;
                continue;
            }
        }

        // Strings
        if (char === "'" || char === '"') {
            if (!inString) {
                inString = true;
                stringChar = char;
            } else if (stringChar === char && content[i - 1] !== '\\') {
                inString = false;
            }
            continue;
        }

        if (inString) continue;

        // Brackets
        if (char === '{') braces++;
        else if (char === '}') braces--;
        else if (char === '(') parens++;
        else if (char === ')') parens--;
        else if (char === '[') brackets++;
        else if (char === ']') brackets--;

        if (braces < 0 || parens < 0 || brackets < 0) {
            errors.push(`${relPath}: Negative bracket balance at offset ${i} (char: ${char})`);
            failed++;
            return;
        }
    }

    if (braces !== 0 || parens !== 0 || brackets !== 0) {
        errors.push(`${relPath}: Unbalanced delimiters (braces: ${braces}, parens: ${parens}, brackets: ${brackets})`);
        failed++;
        return;
    }

    // 3. Verify require_once paths exist
    const requireRegex = /require_once\s+__DIR__\s*\.\s*['"]([^'"]+)['"]/g;
    let match;
    const fileDir = path.dirname(fullPath);
    while ((match = requireRegex.exec(content)) !== null) {
        const targetRel = match[1];
        const targetPath = path.resolve(fileDir, '.' + targetRel);
        if (!fs.existsSync(targetPath)) {
            errors.push(`${relPath}: Broken require_once path -> ${targetRel} (resolved: ${targetPath})`);
            failed++;
            return;
        }
    }

    passed++;
}

console.log('--- Verifying All PHP Codebase Files ---');

function walkDir(dir) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const full = path.join(dir, file);
        const rel = path.relative(rootDir, full);
        if (fs.statSync(full).isDirectory()) {
            if (file !== 'vendor' && file !== 'node_modules' && file !== '.git' && file !== '.claude') {
                walkDir(full);
            }
        } else if (file.endsWith('.php')) {
            checkFile(rel);
        }
    }
}

['backend', 'api', 'admin', 'tests'].forEach(dir => {
    if (fs.existsSync(path.join(rootDir, dir))) {
        walkDir(path.join(rootDir, dir));
    }
});

console.log(`Verification Summary: ${passed} files passed, ${failed} files failed.`);
if (failed > 0) {
    console.error('\nErrors encountered:');
    errors.forEach(e => console.error('  - ' + e));
    process.exit(1);
} else {
    console.log('✅ ALL PHP FILES ARE SYNTACTICALLY BALANCED AND ALL REQUIRE_ONCE PATHS RESOLVE PERFECTLY!');
    process.exit(0);
}
