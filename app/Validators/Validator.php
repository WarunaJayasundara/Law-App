<?php

namespace App\Validators;

/**
 * Small server-side validation helper. Frontend validation (Bootstrap
 * `required`/`pattern` attributes) is a convenience only — every rule
 * here is re-checked on the server, which is the actual source of truth.
 */
final class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    private function value(string $field): string
    {
        return trim((string) ($this->data[$field] ?? ''));
    }

    public function required(string $field, string $label): self
    {
        if ($this->value($field) === '') {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->errors[$field] = "{$label} must be {$max} characters or fewer.";
        }
        return $this;
    }

    public function email(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    /** Sri Lankan NIC: 9 digits + V/X (old format) or 12 digits (new format). */
    public function nic(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^(\d{9}[vVxX]|\d{12})$/', $v)) {
            $this->errors[$field] = "{$label} must be a valid NIC number.";
        }
        return $this;
    }

    public function mobile(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^[0-9+\-\s]{7,15}$/', $v)) {
            $this->errors[$field] = "{$label} must be a valid mobile number.";
        }
        return $this;
    }

    /**
     * Letters, digits and the separators real deed numbers use ("/", "-", ".",
     * space). The number is inserted into an SMS, so this also keeps links or
     * other free text out of the message sent to buyers and sellers.
     */
    public function deedNumber(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9\/\-\. ]*$/', $v)) {
            $this->errors[$field] = "{$label} may only contain letters, numbers, spaces and / - .";
        }
        return $this;
    }

    /** bcrypt only uses the first 72 bytes of a password, so longer input is rejected rather than silently truncated. */
    public function maxBytes(string $field, int $max, string $label): self
    {
        if (strlen((string) ($this->data[$field] ?? '')) > $max) {
            $this->errors[$field] = "{$label} must be {$max} bytes or fewer.";
        }
        return $this;
    }

    public function decimal(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
            $this->errors[$field] = "{$label} must be a valid amount.";
        }
        return $this;
    }

    public function date(string $field, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && \DateTime::createFromFormat('Y-m-d', $v) === false) {
            $this->errors[$field] = "{$label} must be a valid date.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $v = $this->value($field);
        if ($v !== '' && !in_array($v, $allowed, true)) {
            $this->errors[$field] = "{$label} is not a recognized value.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label): self
    {
        if (mb_strlen($this->value($field)) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }
}
