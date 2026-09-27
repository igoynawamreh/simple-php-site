<?php

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_validation_error(array $errors, string $message = 'Validation failed.'): void {
    json_response(['success' => false, 'message' => $message, 'errors' => $errors], 422);
}

function json_error(string $message, int $status = 400): void {
    json_response(['success' => false, 'message' => $message], $status);
}

function json_success(array $data = []): void {
    json_response(array_merge(['success' => true], $data));
}
