<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

header('Content-Type: application/json');

if ($uri === '/api/status') {
    if ($auth !== 'Bearer test-token') {
        http_response_code(401);
        echo json_encode(['error' => 'unauthenticated']);

        return;
    }
    echo json_encode(['data' => ['ok' => true, 'method' => $method]]);

    return;
}

if ($uri === '/api/apps') {
    header('Location: /api/followed', true, 302);
    echo json_encode(['error' => 'redirect']);

    return;
}

if ($uri === '/api/followed') {
    echo json_encode(['followed' => true]);

    return;
}

http_response_code(404);
echo json_encode(['error' => 'missing']);
