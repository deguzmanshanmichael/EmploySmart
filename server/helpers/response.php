<?php
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sendSuccess($message, $data = null, $statusCode = 200) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) $response['data'] = $data;
    sendResponse($response, $statusCode);
}

function sendError($message, $statusCode = 400, $errors = null) {
    $response = ['success' => false, 'message' => $message];
    if ($errors !== null) $response['errors'] = $errors;
    sendResponse($response, $statusCode);
}

function sendPaginated($data, $total, $page, $limit, $message = 'Success') {
    sendResponse([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'pagination' => [
            'total' => $total,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}