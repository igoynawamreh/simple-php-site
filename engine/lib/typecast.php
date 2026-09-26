<?php

function cast_value($value, array $lot = []) {
    if (is_string($value)) {
        if ($value === "") {
            return $value;
        }
        if (is_numeric($value)) {
            if (
                strlen($value) > 1 &&
                $value[0] === "0" &&
                strpos($value, ".") === false
            ) {
                return $value; // Preserve as-is!
            }
            return strpos($value, ".") !== false
                ? (float) $value
                : (int) $value;
        }
        if (
            array_key_exists(
                $value,
                $lot = array_replace(
                    [
                        "FALSE" => false,
                        "NULL" => null,
                        "TRUE" => true,
                        "false" => false,
                        "null" => null,
                        "true" => true,
                    ],
                    $lot,
                ),
            )
        ) {
            return $lot[$value];
        }
        return $value;
    }
    if (is_array($value)) {
        foreach ($value as &$v) {
            $v = cast_value($v, $lot);
        }
        unset($v);
    }
    return $value;
}

function clean_value($value) {
    if (is_array($value)) {
        foreach ($value as &$v) {
            $v = clean_value($v);
        }
        unset($v);
    } else {
        // Trim white-space
        $value = trim($value ?? "");
        // Normalize line-break
        $value = strtr($value, ['\r\n' => '\n', '\r' => '\n']);
        // Auto-decode JSON object/array strings, then recursively normalize their contents
        if ($value !== "" && ($value[0] === "{" || $value[0] === "[")) {
            try {
                return clean_value(
                    json_decode($value, true, 512, JSON_THROW_ON_ERROR),
                );
            } catch (\JsonException $e) {
                // Not valid JSON, continue normal processing
            }
        }
        // Replace all empty value with `null`
        $value = $value === "" ? null : $value;
        // Evaluate other(s)
        if (is_string($value)) {
            $value = cast_value($value);
        }
    }
    return $value;
}
