<?php
require_once 'db.php';

// Simulate being logged in as user 2 (alex_tech)
$currentUser = ['id' => 2, 'username' => 'alex_tech'];

// Test getConversations function
echo "Testing getConversations for user " . $currentUser['id'] . ":\n";

try {
    $stmt = $pdo->prepare('
        SELECT DISTINCT
            m.match_id,
            CASE 
                WHEN m.sender_id = ? THEN u2.username 
                ELSE u1.username 
            END as partner_name,
            MAX(m.created_at) as last_message_time,
            (SELECT message FROM messages m2 WHERE m2.match_id = m.match_id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
            COUNT(CASE WHEN m.recipient_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.recipient_id = u2.id
        WHERE m.sender_id = ? OR m.recipient_id = ?
        GROUP BY m.match_id
        ORDER BY last_message_time DESC
    ');
    
    $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
    $conversations = $stmt->fetchAll();
    
    echo "Found " . count($conversations) . " conversations:\n";
    foreach ($conversations as $conv) {
        echo "- Match ID: " . $conv['match_id'] . ", Partner: " . $conv['partner_name'] . ", Last: " . $conv['last_message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test specific conversation for match_id 1
echo "\nTesting messages for match_id 1:\n";

try {
    $stmt = $pdo->prepare('
        SELECT 
            m.*,
            u.username as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.match_id = ?
        ORDER BY m.created_at ASC
    ');
    
    $stmt->execute([1]);
    $messages = $stmt->fetchAll();
    
    echo "Found " . count($messages) . " messages:\n";
    foreach ($messages as $msg) {
        echo "- " . $msg['sender_name'] . ": " . substr($msg['message'], 0, 50) . "...\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>