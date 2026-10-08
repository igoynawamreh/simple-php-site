<?php

/**
 * Sanitize (transform) field values before validation and storage.
 *
 * Rule syntax:
 *   'no_html'         — strip all HTML tags
 *   'no_newline'      — remove newlines (replaced with a space)
 *   'no_double_space' — collapse double spaces into one
 *   'trim'            — trim leading/trailing whitespace
 *   'lowercase'       — convert to lowercase
 *   'uppercase'       — convert to uppercase
 *   'slug'            — convert to slug format (letters, numbers, hyphens)
 *
 * Null values (empty fields from `clean_value`) are skipped — left unchanged.
 * After sanitizing, empty strings are returned as `null`.
 *
 * @param array $data   Flat key-value data
 * @param array $rules  ['field' => ['rule', ...]]
 * @return array        Sanitized data
 */
function sanitize_fields(array $data, array $rules): array {
    foreach ($rules as $field => $fieldRules) {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            continue;
        }

        $value = (string) $data[$field];

        foreach ($fieldRules as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            $value = match ($name) {
                'no_html'         => strip_tags($value),
                'no_newline'      => preg_replace('/[\r\n]+/', ' ', $value),
                'no_double_space' => preg_replace('/ {2,}/', ' ', $value),
                'trim'            => trim($value),
                'lowercase'       => mb_strtolower($value),
                'uppercase'       => mb_strtoupper($value),
                'slug'            => preg_replace('/-{2,}/', '-',
                                        trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-')
                                    ),
                default           => $value,
            };
        }

        // Empty string after sanitizing → returned as `null` (consistent with `clean_value`)
        $data[$field] = $value === '' ? null : $value;
    }

    return $data;
}
