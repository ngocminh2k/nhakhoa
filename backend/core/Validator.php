<?php
// backend/core/Validator.php

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: $field;
        $val = $this->data[$field] ?? null;
        if ($val === null || (is_string($val) && trim($val) === '')) {
            $this->errors[$field] = "$label không được để trống.";
        }
        return $this;
    }

    public function phone(string $field, string $label = 'Số điện thoại'): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '' && !preg_match('/^(0|\+84)[35789][0-9]{8}$/', $val)) {
            $this->errors[$field] = "$label không hợp lệ (cần 10 số, đầu số Việt Nam).";
        }
        return $this;
    }

    public function date(string $field, string $label = 'Ngày'): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $val);
            if (!$d || $d->format('Y-m-d') !== $val) {
                $this->errors[$field] = "$label định dạng không đúng (YYYY-MM-DD).";
            }
        }
        return $this;
    }

    public function time(string $field, string $label = 'Giờ'): self {
        $val = trim($this->data[$field] ?? '');
        if ($val !== '') {
            $t = DateTime::createFromFormat('H:i', $val);
            $isValid = ($t && $t->format('H:i') === $val);
            if (!$isValid) {
                $tSec = DateTime::createFromFormat('H:i:s', $val);
                $isValid = ($tSec && $tSec->format('H:i:s') === $val);
            }
            if (!$isValid) {
                $this->errors[$field] = "$label định dạng không đúng (HH:MM).";
            }
        }
        return $this;
    }

    public function integer(string $field, string $label = ''): self {
        $label = $label ?: $field;
        $val = $this->data[$field] ?? null;
        if ($val !== null && (is_bool($val) || filter_var($val, FILTER_VALIDATE_INT) === false)) {
            $this->errors[$field] = "$label phải là số nguyên.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): self {
        $label = $label ?: $field;
        $val = $this->data[$field] ?? '';
        if (mb_strlen((string)$val) > $max) {
            $this->errors[$field] = "$label không được vượt quá $max ký tự.";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        return reset($this->errors) ?: '';
    }
}
