<?php
/**
 * Quick test for reset functionality
 */

require_once 'db.php';
require_once 'functions.php';

function resetMatchesAndSwipes(): array {
    try {
        $pdo = getDbConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Count records before deletion
        $matchCount = $pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
        $messageCount = $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
        
        echo "Found $matchCount matches and $messageCount messages to delete.\n";
        
        // Clear all matches and swipes
        $pdo->exec('DELETE FROM messages');
        $pdo->exec('DELETE FROM matches');
        
        // Reset auto increment
        $pdo->exec('ALTER TABLE matches AUTO_INCREMENT = 1');
        $pdo->exec('ALTER TABLE messages AUTO_INCREMENT = 1');
        
        // Commit transaction
        $pdo->commit();
        
        echo "✅ Reset completed successfully!\n";
        
        return [
            'success' => true,
            'message' => "Successfully reset! Cleared {$matchCount} matches and {$messageCount} messages."
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ Error: " . $e->getMessage() . "\n";
        return [
            'success' => false,
            'message' => 'Database error occurred during reset.'
        ];
    }
}

echo "🔄 Testing reset functionality...\n";
$result = resetMatchesAndSwipes();

if ($result['success']) {
    echo "✅ Test passed: " . $result['message'] . "\n";
} else {
    echo "❌ Test failed: " . $result['message'] . "\n";
}

echo "\n📊 Current database state:\n";
try {
    $pdo = getDbConnection();
    $matchCount = $pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
    $messageCount = $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
    $userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    
    echo "- Users: $userCount\n";
    echo "- Matches: $matchCount\n";
    echo "- Messages: $messageCount\n";
} catch (Exception $e) {
    echo "Error checking database state: " . $e->getMessage() . "\n";
}