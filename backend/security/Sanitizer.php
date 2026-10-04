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
     * Removes dangerous tags (script, iframe, object, embed, style, applet)
     * and attributes (onload, onerror, onclick, javascript: links).
     */
    public static function html(?string $html): string {
        if ($html === null) return '';

        // Remove script, iframe, style, object, embed tags and their contents
        $clean = preg_replace('#<(script|iframe|style|object|embed|applet)[^>]*>.*?</\1>#is', '', $html);

        // Remove self-closing dangerous tags
        $clean = preg_replace('#<(script|iframe|style|object|embed|applet)[^>]*>#is', '', $clean);

        // Remove javascript: and vbscript: URIs
        $clean = preg_replace('#(href|src)\s*=\s*["\']\s*(javascript|vbscript|data):[^"\']*["\']#is', '', $clean);

        // Remove inline on* event handlers (onclick, onload, onerror, onmouseover, etc.)
        $clean = preg_replace('#\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)#is', '', $clean);

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
