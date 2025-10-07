<?php
require_once __DIR__ . '/../src/Utils/auth.php';

// Set headers for fast, small response
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// Close session early to avoid lock
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    $user = Auth::currentUser();

    // Minimal response - only essential data
    $response = [
        'logged_in' => !!$user,
        'user_id' => $user['id'] ?? null,
        'role' => $user ? 'user' : 'guest',
        'ts' => time()
    ];

    // Add name only if user is logged in (optional)
    if ($user && isset($user['name'])) {
        $response['name'] = $user['name'];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Error response - still HTTP 200 to avoid retry storms
    echo json_encode([
        'ok' => false,
        'message' => 'خطأ في الخادم',
        'logged_in' => false,
        'ts' => time()
    ], JSON_UNESCAPED_UNICODE);
}
