<?php
// backend/security/Sanitizer.php

class Sanitizer {
    /**
     * Clean plain text input (e.g. name, phone, notes)
     */
    public static function text(?string $input): string {
        if ($input === null) return '';
        return trim(htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    /**
     * Clean rich HTML content (e.g. from blog automation) to prevent XSS.
     * Strips dangerous tags (script, iframe, style, svg, math, object, embed, etc.)
     * and event handlers (onload, onerror, onclick...) regardless of delimiters.
     */
    public static function html(?string $html): string {
        if ($html === null) return '';

        // ponytail: upgrade to HTMLPurifier if user editors need arbitrary inline styles/classes. Current strip_tags whitelist is zero-dependency.
        // 1. Remove dangerous blocks and their contents completely
        $clean = preg_replace('#<(script|iframe|style|object|embed|applet|svg|math)[^>]*>.*?</\1>#is', '', $html);
        $clean = preg_replace('#<(script|iframe|style|object|embed|applet|svg|math)[^>]*>#is', '', $clean);

        // 2. Whitelist safe formatting tags only
        $allowedTags = '<p><br><strong><b><em><i><u><h2><h3><h4><h5><h6><ul><ol><li><a><img><div><span><blockquote><table><thead><tbody><tr><th><td><hr><figure><figcaption>';
        $clean = strip_tags($clean, $allowedTags);

        // 3. Remove javascript:, vbscript:, data: URIs in attributes
        $clean = preg_replace('#(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data):.*?\2#is', '', $clean);
        $clean = preg_replace('#(href|src)\s*=\s*(javascript|vbscript|data):[^\s>]*#is', '', $clean);

        // 4. Remove all on* event handlers (support whitespace, slash, or tag-boundary preceding the event handler)
        $clean = preg_replace('#[\s/]+on[a-z0-9_-]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)#is', '', $clean);

        return trim($clean);
    }

    /**
     * Alias for html()
     */
    public static function cleanHtml(?string $html): string {
        return self::html($html);
    }

    /**
     * Generate URL-safe slug from UTF-8 Vietnamese string
     */
    public static function slug(string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        // Convert Vietnamese accented chars
        $unicode = [
            'a' => 'á|à|ả|ã|ạ|ă|ắ|ặ|ằ|ẳ|ẵ|â|ấ|ầ|ẩ|ẫ|ậ',
            'd' => 'đ',
            'e' => 'é|è|ẻ|ẽ|ẹ|ê|ế|ề|ể|ễ|ệ',
            'i' => 'í|ì|ỉ|ĩ|ị',
            'o' => 'ó|ò|ỏ|õ|ọ|ô|ố|ồ|ổ|ỗ|ộ|ơ|ớ|ờ|ở|ỡ|ợ',
            'u' => 'ú|ù|ủ|ũ|ụ|ư|ứ|ừ|ử|ữ|ự',
            'y' => 'ý|ỳ|ỷ|ỹ|ỵ',
        ];
        foreach ($unicode as $nonAccent => $accent) {
            $str = preg_replace("/($accent)/i", $nonAccent, $str);
        }
        $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
        $str = preg_replace('/[\s-]+/', '-', $str);
        return trim($str, '-');
    }

    /**
     * Clean phone number input
     */
    public static function phone(?string $input): string {
        if ($input === null) return '';
        return preg_replace('/[^0-9+]/', '', trim($input));
    }
}
