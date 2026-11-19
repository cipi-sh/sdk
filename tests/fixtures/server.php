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

// What arrived: the method, the content type and the body, as sent.
if ($uri === '/api/echo') {
    echo json_encode(['data' => [
        'method' => $method,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
        'body' => file_get_contents('php://input'),
    ]]);

    return;
}

// A chunk of a database dump: what PHP itself parsed out of the multipart body.
if ($method === 'POST' && preg_match('#^/api/dbs/uploads/[0-9a-f-]{36}/chunks$#', $uri) === 1) {
    $file = $_FILES['chunk'] ?? null;
    echo json_encode(['data' => [
        'index' => $_POST['index'] ?? null,
        'error' => $file['error'] ?? null,
        'filename' => $file['name'] ?? null,
        'size' => $file['size'] ?? null,
        'sha1' => isset($file['tmp_name']) && is_uploaded_file($file['tmp_name']) ? sha1_file($file['tmp_name']) : null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? null,
    ]]);

    return;
}

http_response_code(404);
echo json_encode(['error' => 'missing']);
