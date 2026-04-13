<?php

declare(strict_types=1);

namespace Libxa\Validation;

/**
 * Validator
 *
 * Validates an array of data against a set of rules.
 *
 * Rules:
 *   required | string | integer | numeric | email | url | min:3 | max:255
 *   in:a,b,c | not_in:x,y | regex:/^[A-Z]+$/
 *   confirmed | same:other_field | different:other_field
 *   unique:table,column | exists:table,column
 *   date | date_format:Y-m-d | before:tomorrow | after:yesterday
 *   file | image | mimes:jpg,png | max_size:2048
 *   boolean | array | json
 *   nullable | sometimes
 *
 * Usage:
 *   $v = new Validator($request->all(), [
 *       'email'    => 'required|email',
 *       'password' => 'required|string|min:8',
 *       'age'      => 'nullable|integer|min:18',
 *   ]);
 *
 *   if ($v->fails()) {
 *       return json($v->errors(), 422);
 *   }
 */
class Validator
{
    protected array $errors   = [];
    protected array $validated = [];

    public function __construct(
        protected array $data,
        protected array $rules,
        protected array $messages = [],
    ) {
        $this->run();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Run validation
    // ─────────────────────────────────────────────────────────────────

    protected function run(): void
    {
        foreach ($this->rules as $field => $ruleset) {
            $rules    = is_string($ruleset) ? explode('|', $ruleset) : $ruleset;
            $value    = $this->data[$field] ?? null;
            $nullable = in_array('nullable', $rules);
            $sometimes = in_array('sometimes', $rules);

            // Skip if 'sometimes' and not present
            if ($sometimes && ! array_key_exists($field, $this->data)) {
                continue;
            }

            // Skip null-able empty values except 'required'
            if ($nullable && ($value === null || $value === '')) {
                $this->validated[$field] = null;
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['nullable', 'sometimes'])) continue;

                $this->applyRule($field, $value, $rule);
            }

            if (! isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    protected function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        match ($name) {
            'required'   => $this->validateRequired($field, $value),
            'string'     => $this->validateString($field, $value),
            'integer'    => $this->validateInteger($field, $value),
            'numeric'    => $this->validateNumeric($field, $value),
            'boolean'    => $this->validateBoolean($field, $value),
            'array'      => $this->validateArray($field, $value),
            'email'      => $this->validateEmail($field, $value),
            'url'        => $this->validateUrl($field, $value),
            'json'       => $this->validateJson($field, $value),
            'min'        => $this->validateMin($field, $value, (int) $param),
            'max'        => $this->validateMax($field, $value, (int) $param),
            'between'    => $this->validateBetween($field, $value, $param),
            'in'         => $this->validateIn($field, $value, explode(',', $param)),
            'not_in'     => $this->validateNotIn($field, $value, explode(',', $param)),
            'regex'      => $this->validateRegex($field, $value, $param),
            'confirmed'  => $this->validateConfirmed($field, $value),
            'same'       => $this->validateSame($field, $value, $param),
            'different'  => $this->validateDifferent($field, $value, $param),
            'date'       => $this->validateDate($field, $value),
            'after'      => $this->validateAfter($field, $value, $param),
            'before'     => $this->validateBefore($field, $value, $param),
            'unique'     => $this->validateUnique($field, $value, $param),
            'exists'     => $this->validateExists($field, $value, $param),
            'size'       => $this->validateSize($field, $value, (int) $param),
            'alpha'      => $this->validateAlpha($field, $value),
            'alpha_num'  => $this->validateAlphaNum($field, $value),
            'alpha_dash' => $this->validateAlphaDash($field, $value),
            'uuid'       => $this->validateUuid($field, $value),
            'ip'         => $this->validateIp($field, $value),
            'starts_with'=> $this->validateStartsWith($field, $value, $param),
            'ends_with'  => $this->validateEndsWith($field, $value, $param),
            'file'       => $this->validateFile($field, $value),
            'image'      => $this->validateImage($field, $value),
            'mimes'      => $this->validateMimes($field, $value, $param),
            'max_size'   => $this->validateMaxSize($field, $value, (int) $param),
            'dimensions' => $this->validateDimensions($field, $value, $param),
            default      => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────
    //  Validators
    // ─────────────────────────────────────────────────────────────────

    protected function validateRequired(string $f, mixed $v): void
    {
        if ($v === null || $v === '' || (is_array($v) && empty($v))) {
            $this->fail($f, 'required', "The $f field is required.");
        }
    }

    protected function validateString(string $f, mixed $v): void
    {
        if ($v !== null && ! is_string($v)) $this->fail($f, 'string', "The $f must be a string.");
    }

    protected function validateInteger(string $f, mixed $v): void
    {
        if ($v !== null && ! filter_var($v, FILTER_VALIDATE_INT)) $this->fail($f, 'integer', "The $f must be an integer.");
    }

    protected function validateNumeric(string $f, mixed $v): void
    {
        if ($v !== null && ! is_numeric($v)) $this->fail($f, 'numeric', "The $f must be a number.");
    }

    protected function validateBoolean(string $f, mixed $v): void
    {
        if ($v !== null && ! in_array($v, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
            $this->fail($f, 'boolean', "The $f must be a boolean.");
        }
    }

    protected function validateArray(string $f, mixed $v): void
    {
        if ($v !== null && ! is_array($v)) $this->fail($f, 'array', "The $f must be an array.");
    }

    protected function validateEmail(string $f, mixed $v): void
    {
        if ($v && ! filter_var($v, FILTER_VALIDATE_EMAIL)) $this->fail($f, 'email', "The $f must be a valid email address.");
    }

    protected function validateUrl(string $f, mixed $v): void
    {
        if ($v && ! filter_var($v, FILTER_VALIDATE_URL)) $this->fail($f, 'url', "The $f must be a valid URL.");
    }

    protected function validateJson(string $f, mixed $v): void
    {
        if ($v === null) return;
        json_decode((string) $v);
        if (json_last_error() !== JSON_ERROR_NONE) $this->fail($f, 'json', "The $f must be valid JSON.");
    }

    protected function validateMin(string $f, mixed $v, int $min): void
    {
        if ($v === null) return;
        $len = is_string($v) ? mb_strlen($v) : (is_array($v) ? count($v) : (float) $v);
        if ($len < $min) $this->fail($f, 'min', "The $f must be at least $min.");
    }

    protected function validateMax(string $f, mixed $v, int $max): void
    {
        if ($v === null) return;
        $len = is_string($v) ? mb_strlen($v) : (is_array($v) ? count($v) : (float) $v);
        if ($len > $max) $this->fail($f, 'max', "The $f may not be greater than $max.");
    }

    protected function validateBetween(string $f, mixed $v, string $param): void
    {
        [$min, $max] = explode(',', $param);
        $this->validateMin($f, $v, (int) $min);
        $this->validateMax($f, $v, (int) $max);
    }

    protected function validateIn(string $f, mixed $v, array $values): void
    {
        if ($v !== null && ! in_array($v, $values)) $this->fail($f, 'in', "The selected $f is invalid.");
    }

    protected function validateNotIn(string $f, mixed $v, array $values): void
    {
        if ($v !== null && in_array($v, $values)) $this->fail($f, 'not_in', "The selected $f is invalid.");
    }

    protected function validateRegex(string $f, mixed $v, string $pattern): void
    {
        if ($v !== null && ! preg_match($pattern, (string) $v)) $this->fail($f, 'regex', "The $f format is invalid.");
    }

    protected function validateConfirmed(string $f, mixed $v): void
    {
        if ($v !== ($this->data[$f . '_confirmation'] ?? null)) $this->fail($f, 'confirmed', "The $f confirmation does not match.");
    }

    protected function validateSame(string $f, mixed $v, string $other): void
    {
        if ($v !== ($this->data[$other] ?? null)) $this->fail($f, 'same', "The $f and $other must match.");
    }

    protected function validateDifferent(string $f, mixed $v, string $other): void
    {
        if ($v === ($this->data[$other] ?? null)) $this->fail($f, 'different', "The $f and $other must be different.");
    }

    protected function validateDate(string $f, mixed $v): void
    {
        if ($v && strtotime((string) $v) === false) $this->fail($f, 'date', "The $f is not a valid date.");
    }

    protected function validateAfter(string $f, mixed $v, string $date): void
    {
        if ($v && strtotime((string) $v) <= strtotime($date)) $this->fail($f, 'after', "The $f must be a date after $date.");
    }

    protected function validateBefore(string $f, mixed $v, string $date): void
    {
        if ($v && strtotime((string) $v) >= strtotime($date)) $this->fail($f, 'before', "The $f must be a date before $date.");
    }

    protected function validateUnique(string $f, mixed $v, string $param): void
    {
        [$table, $column] = array_pad(explode(',', $param), 2, $f);
        try {
            $pdo  = \Libxa\Atlas\Connection\ConnectionPool::getInstance()->get();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
            $stmt->execute([$v]);
            if ($stmt->fetchColumn() > 0) $this->fail($f, 'unique', "The $f has already been taken.");
        } catch (\Throwable) {
            // Silently fail if DB not available during validation
        }
    }

    protected function validateExists(string $f, mixed $v, string $param): void
    {
        [$table, $column] = array_pad(explode(',', $param), 2, $f);
        try {
            $pdo  = \Libxa\Atlas\Connection\ConnectionPool::getInstance()->get();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` = ?");
            $stmt->execute([$v]);
            if ($stmt->fetchColumn() == 0) $this->fail($f, 'exists', "The selected $f is invalid.");
        } catch (\Throwable) {}
    }

    protected function validateSize(string $f, mixed $v, int $size): void
    {
        $len = is_string($v) ? mb_strlen($v) : (is_array($v) ? count($v) : (float) $v);
        if ($len !== $size) $this->fail($f, 'size', "The $f must be $size.");
    }

    protected function validateAlpha(string $f, mixed $v): void
    {
        if ($v && ! preg_match('/^[\pL\pM]+$/u', (string) $v)) $this->fail($f, 'alpha', "The $f may only contain letters.");
    }

    protected function validateAlphaNum(string $f, mixed $v): void
    {
        if ($v && ! preg_match('/^[\pL\pM\pN]+$/u', (string) $v)) $this->fail($f, 'alpha_num', "The $f may only contain letters and numbers.");
    }

    protected function validateAlphaDash(string $f, mixed $v): void
    {
        if ($v && ! preg_match('/^[\pL\pM\pN_-]+$/u', (string) $v)) $this->fail($f, 'alpha_dash', "The $f may only contain letters, numbers, dashes and underscores.");
    }

    protected function validateUuid(string $f, mixed $v): void
    {
        if ($v && ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $v)) {
            $this->fail($f, 'uuid', "The $f must be a valid UUID.");
        }
    }

    protected function validateIp(string $f, mixed $v): void
    {
        if ($v && ! filter_var($v, FILTER_VALIDATE_IP)) $this->fail($f, 'ip', "The $f must be a valid IP address.");
    }

    protected function validateStartsWith(string $f, mixed $v, string $prefix): void
    {
        if ($v && ! str_starts_with((string) $v, $prefix)) $this->fail($f, 'starts_with', "The $f must start with $prefix.");
    }

    protected function validateEndsWith(string $f, mixed $v, string $suffix): void
    {
        if ($v && ! str_ends_with((string) $v, $suffix)) $this->fail($f, 'ends_with', "The $f must end with $suffix.");
    }

    protected function validateFile(string $f, mixed $v): void
    {
        $type = is_object($v) ? get_class($v) : gettype($v);
        logger("VALIDATING FILE for field [$f]. Type: " . $type, [], 'info');

        if ($v instanceof \Libxa\Http\UploadedFile) {
            if (!$v->isValid()) {
                logger("FILE INVALID for field [$f]. Status: " . ($v->isValid() ? 'valid' : 'invalid'), [], 'warning');
                $this->fail($f, 'file', "The $f must be a valid uploaded file.");
            }
            return;
        }

        if ($v !== null) {
            logger("FILE VALIDATION FAILED for field [$f]. Value is not an UploadedFile instance.", [], 'error');
            $this->fail($f, 'file', "The $f must be a file.");
        }
    }

    protected function validateImage(string $f, mixed $v): void
    {
        if ($v instanceof \Libxa\Http\UploadedFile) {
            $mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            if (!in_array($v->getMimeType(), $mimes)) {
                $this->fail($f, 'image', "The $f must be an image (jpeg, png, gif, webp, svg).");
            }
            return;
        }
        $this->validateFile($f, $v);
    }

    protected function validateMimes(string $f, mixed $v, ?string $param): void
    {
        if (!$v instanceof \Libxa\Http\UploadedFile || !$param) return;
        $allowed = explode(',', $param);
        $ext = $v->getClientOriginalExtension();
        if (!in_array(strtolower($ext), $allowed)) {
            $this->fail($f, 'mimes', "The $f must be a file of type: $param.");
        }
    }

    protected function validateMaxSize(string $f, mixed $v, int $maxKb): void
    {
        if (!$v instanceof \Libxa\Http\UploadedFile) return;
        if ($v->getSize() > ($maxKb * 1024)) {
            $this->fail($f, 'max_size', "The $f may not be greater than $maxKb kilobytes.");
        }
    }

    protected function validateDimensions(string $f, mixed $v, ?string $param): void
    {
        // Basic stub for now, would usually require getimagesize()
        if (!$v instanceof \Libxa\Http\UploadedFile) return;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Error handling
    // ─────────────────────────────────────────────────────────────────

    protected function fail(string $field, string $rule, string $message): void
    {
        // Check custom message override
        $key = "$field.$rule";
        $msg = $this->messages[$key] ?? $this->messages[$field] ?? $message;
        $this->errors[$field][] = $msg;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Results
    // ─────────────────────────────────────────────────────────────────

    public function fails(): bool   { return ! empty($this->errors); }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }

    public function firstError(?string $field = null): ?string
    {
        if ($field) return $this->errors[$field][0] ?? null;
        $first = reset($this->errors);
        return $first ? $first[0] : null;
    }

    public function validated(): array { return $this->validated; }
}

/**
 * Validation Exception — thrown when $request->validate() fails.
 */
class ValidationException extends \RuntimeException
{
    public function __construct(
        protected array $errors,
        string $message = 'The given data was invalid.',
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }

    public function errors(): array { return $this->errors; }
    public function status(): int   { return 422; }

    public function toResponse(): \Libxa\Http\JsonResponse
    {
        return \Libxa\Http\JsonResponse::error('The given data was invalid.', 422, $this->errors);
    }
}
