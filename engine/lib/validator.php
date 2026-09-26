<?php

/**
 * Validate $data against $rules.
 *
 * Rule syntax:
 *   'required'    — must not be empty (after trim)
 *   'max:N'       — max N characters
 *   'min:N'       — min N characters
 *   'date'        — valid YYYY-MM-DD format
 *   'time'        — HH:MM format
 *   'url'         — valid URL (http/https)
 *
 * @param array $data   Flat key-value data from the request
 * @param array $rules  ['field' => ['rule', 'rule:param', ...]]
 * @return array        ['field' => 'error message'] — empty if all valid
 */
function validate_fields(array $data, array $rules): array {
    $errors = [];

    foreach ($rules as $field => $fieldRules) {
        // array_key_exists so fields with null values (from clean_value) are still detected.
        // null is treated the same as an empty string.
        $raw   = array_key_exists($field, $data) ? $data[$field] : null;
        $value = ($raw === null) ? '' : trim((string) $raw);

        foreach ($fieldRules as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $err = null;

            if ($name === 'required' && $value === '') {
                $err = 'This field is required.';

            } elseif ($name === 'max' && $value !== '' && mb_strlen($value) > (int) $param) {
                $err = "Maximum {$param} characters.";

            } elseif ($name === 'min' && $value !== '' && mb_strlen($value) < (int) $param) {
                $err = "Minimum {$param} characters.";

            } elseif ($name === 'date' && $value !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $value);
                if (!$d || $d->format('Y-m-d') !== $value) {
                    $err = 'Invalid date format (YYYY-MM-DD).';
                }

            } elseif ($name === 'time' && $value !== '') {
                if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
                    $err = 'Invalid time format (HH:MM).';
                }

            } elseif ($name === 'url' && $value !== '') {
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $err = 'Invalid URL.';
                }
            }

            if ($err !== null) {
                $errors[$field] = $err;
                break; // one error per field, move on to the next field
            }
        }
    }

    return $errors;
}
