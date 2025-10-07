<?php
// API endpoint for wallet balance
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Include necessary files
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/auth.php';
require_once BASE_PATH . '/src/Utils/db.php';

try {
    // Start session and check authentication
    Auth::startSession();
    
    if (!Auth::is_logged_in()) {
        echo json_encode(['balance' => 0, 'logged_in' => false]);
        exit;
    }
    
    $user = Auth::currentUser();
    if (!$user) {
        echo json_encode(['balance' => 0, 'error' => 'User not found']);
        exit;
    }
    
    $userId = $user['id'];
    
    // Get wallet balance from database
    $balance = Database::fetchOne(
        "SELECT COALESCE(balance, 0) as balance FROM wallets WHERE user_id = ?",
        [$userId]
    );
    
    // If no wallet exists, create one with zero balance
    if (!$balance) {
        try {
            Database::query(
                "INSERT INTO wallets (user_id, balance, created_at, updated_at) VALUES (?, 0, NOW(), NOW())",
                [$userId]
            );
            $balanceAmount = 0;
        } catch (Exception $e) {
            error_log("Failed to create wallet for user $userId: " . $e->getMessage());
            $balanceAmount = 0;
        }
    } else {
        $balanceAmount = floatval($balance['balance']);
    }
    
    echo json_encode([
        'balance' => $balanceAmount,
        'balance_lyd' => $balanceAmount,
        'wallet_balance' => $balanceAmount,
        'amount' => $balanceAmount,
        'value' => $balanceAmount,
        'logged_in' => true,
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Wallet balance API error: " . $e->getMessage());
    error_log("Wallet balance API stack trace: " . $e->getTraceAsString());
    
    // Return zero balance on error
    echo json_encode([
        'balance' => 0, 
        'error' => 'Internal server error',
        'logged_in' => Auth::is_logged_in(),
        'debug' => $e->getMessage()
    ]);
}
?>
