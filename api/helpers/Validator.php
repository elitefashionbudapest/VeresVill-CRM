<?php
/**
 * Input validáció helper
 */
class Validator {

    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: $field;
        if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
            $this->errors[$field] = "{$label} megadása kötelező.";
        }
        return $this;
    }

    public function email(string $field, string $label = ''): self {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Érvényes {$label} megadása kötelező.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): self {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = "{$label} számnak kell lennie.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!empty($value) && mb_strlen($value) < $min) {
            $this->errors[$field] = "{$label} legalább {$min} karakter kell legyen.";
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $label = ''): self {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!empty($value) && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "Érvénytelen {$label}.";
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d', string $label = ''): self {
        $label = $label ?: $field;
        $value = $this->data[$field] ?? '';
        if (!empty($value)) {
            $d = DateTime::createFromFormat($format, $value);
            if (!$d || $d->format($format) !== $value) {
                $this->errors[$field] = "Érvénytelen {$label} formátum.";
            }
        }
        return $this;
    }

    public function time(string $field, string $label = ''): self {
        return $this->date($field, 'H:i', $label);
    }

    public function fails(): bool {
        return !empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): string {
        return reset($this->errors) ?: '';
    }

    public function get(string $field, $default = null) {
        return isset($this->data[$field]) ? trim((string) $this->data[$field]) : $default;
    }

    public function getInt(string $field, int $default = 0): int {
        return isset($this->data[$field]) ? (int) $this->data[$field] : $default;
    }

    public function all(): array {
        return $this->data;
    }
}
