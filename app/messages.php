<?php
require_once 'db.php';
require_once 'functions.php';

startSecureSession();

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username']
];

// Handle AJAX requests for messaging
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Allow bypass for debugging - remove in production
    $skipCSRF = isset($_POST['debug']) && $_POST['debug'] === 'true';
    
    if (!$skipCSRF && !validateCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log("CSRF validation failed for user " . $currentUser['id']);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }
    
    try {
        $pdo = getDbConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'get_conversations':
                    error_log("Getting conversations for user: " . $currentUser['id']);
                    // Get all conversations for the current user
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT
                            m.match_id,
                            CASE WHEN m.sender_id = ? THEN u2.username ELSE u1.username END as partner_name,
                            latest.message as last_message,
                            latest.created_at as last_message_time,
                            COALESCE(unread.unread_count, 0) as unread_count
                        FROM messages m
                        JOIN users u1 ON u1.id = m.sender_id
                        JOIN users u2 ON u2.id = m.recipient_id
                        LEFT JOIN (
                            SELECT match_id, message, created_at,
                                   ROW_NUMBER() OVER (PARTITION BY match_id ORDER BY created_at DESC) as rn
                            FROM messages
                        ) latest ON latest.match_id = m.match_id AND latest.rn = 1
                        LEFT JOIN (
                            SELECT match_id, COUNT(*) as unread_count
                            FROM messages
                            WHERE recipient_id = ? AND is_read = FALSE
                            GROUP BY match_id
                        ) unread ON unread.match_id = m.match_id
                        WHERE m.sender_id = ? OR m.recipient_id = ?
                        ORDER BY latest.created_at DESC
                    ");
                    $stmt->execute([$currentUser['id'], $currentUser['id'], $currentUser['id'], $currentUser['id']]);
                    $conversations = $stmt->fetchAll();
                    error_log("Found " . count($conversations) . " conversations");
                    echo json_encode($conversations);
                    exit;
                    
                case 'get_messages':
                    $matchId = (int)($_POST['match_id'] ?? 0);
                    if ($matchId <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Invalid match ID']);
                        exit;
                    }
                    
                    $stmt = $pdo->prepare("
                        SELECT m.*, u.full_name as sender_name,
                               DATE_FORMAT(m.created_at, '%M %e, %l:%i %p') as formatted_time
                        FROM messages m
                        JOIN users u ON u.id = m.sender_id
                        WHERE m.match_id = ?
                        ORDER BY m.created_at ASC
                    ");
                    $stmt->execute([$matchId]);
                    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]);
                    exit;
                    
                case 'send_message':
                    $matchId = (int)($_POST['match_id'] ?? 0);
                    $message = trim($_POST['message'] ?? '');
                    
                    if ($matchId <= 0 || empty($message)) {
                        echo json_encode(['success' => false, 'error' => 'Invalid input']);
                        exit;
                    }
                    
                    // Get the recipient for this match by checking existing messages
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT
                            CASE WHEN sender_id = ? THEN recipient_id ELSE sender_id END as recipient_id
                        FROM messages
                        WHERE match_id = ? AND (sender_id = ? OR recipient_id = ?)
                        LIMIT 1
                    ");
                    $stmt->execute([$currentUser['id'], $matchId, $currentUser['id'], $currentUser['id']]);
                    $result = $stmt->fetch();
                    
                    if (!$result) {
                        echo json_encode(['success' => false, 'error' => 'Invalid match']);
                        exit;
                    }
                    
                    $recipientId = $result['recipient_id'];
                    
                    // Insert the new message
                    $stmt = $pdo->prepare("
                        INSERT INTO messages (match_id, sender_id, recipient_id, message, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$matchId, $currentUser['id'], $recipientId, $message]);
                    
                    echo json_encode(['success' => true]);
                    exit;
                    
                case 'mark_read':
                    $matchId = (int)($_POST['match_id'] ?? 0);
                    if ($matchId <= 0) {
                        echo json_encode(['success' => false]);
                        exit;
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE messages 
                        SET is_read = TRUE 
                        WHERE match_id = ? AND recipient_id = ? AND is_read = FALSE
                    ");
                    $stmt->execute([$matchId, $currentUser['id']]);
                    
                    echo json_encode(['success' => true]);
                    exit;
            }
        }
        
    } catch (Exception $e) {
        error_log("Messages error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'System error']);
        exit;
    }
}

// Get current conversation if match_id is provided
$currentMatchId = isset($_GET['match']) ? (int)$_GET['match'] : null;
$currentConversation = null;

if ($currentMatchId && $currentMatchId > 0) {
    try {
        $pdo = getDbConnection();
        
        // Get conversation partner info from messages
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                ? as match_id,
                CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END as partner_id,
                CASE WHEN m.sender_id = ? THEN u2.username ELSE u1.username END as partner_name
            FROM messages m
            JOIN users u1 ON u1.id = m.sender_id
            JOIN users u2 ON u2.id = m.recipient_id
            WHERE m.match_id = ? AND (m.sender_id = ? OR m.recipient_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$currentMatchId, $currentUser['id'], $currentUser['id'], $currentMatchId, $currentUser['id'], $currentUser['id']]);
        $currentConversation = $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Conversation fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - 💕 LoveConnect</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken()) ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        :root {
            --primary-pink: #ff6b9d;
            --primary-pink-dark: #e55a8a;
            --secondary-purple: #a8e6cf;
            --background: #fafafa;
            --surface: #ffffff;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --border: #e2e8f0;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        body {
            background: var(--background);
            font-family: 'Inter', system-ui, sans-serif;
            margin: 0;
            padding: 0;
        }

        .header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            box-shadow: var(--shadow);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-pink);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover, .nav-links a.active {
            color: var(--primary-pink);
        }

        .messages-container {
            max-width: 1200px;
            margin: 2rem auto;
            background: var(--surface);
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: grid;
            grid-template-columns: 350px 1fr;
            height: calc(100vh - 200px);
            overflow: hidden;
        }

        .conversations-panel {
            border-right: 1px solid var(--border);
            background: var(--surface);
        }

        .conversations-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, var(--primary-pink), var(--primary-pink-dark));
            color: white;
        }

        .conversations-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }

        .conversations-list {
            height: calc(100% - 80px);
            overflow-y: auto;
        }

        .conversation-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .conversation-item:hover {
            background: #f7fafc;
        }

        .conversation-item.active {
            background: var(--primary-pink);
            color: white;
        }

        .conversation-info {
            flex: 1;
        }

        .partner-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .last-message {
            font-size: 0.875rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 200px;
        }

        .conversation-item.active .last-message {
            color: rgba(255, 255, 255, 0.8);
        }

        .unread-badge {
            background: var(--primary-pink);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .conversation-item.active .unread-badge {
            background: rgba(255, 255, 255, 0.3);
        }

        .chat-panel {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        .chat-partner-info h3 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-primary);
        }

        .messages-area {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .message {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            word-wrap: break-word;
            position: relative;
        }

        .message.sent {
            align-self: flex-end;
            background: var(--primary-pink);
            color: white;
            border-bottom-right-radius: 0.5rem;
        }

        .message.received {
            align-self: flex-start;
            background: #f7fafc;
            color: var(--text-primary);
            border-bottom-left-radius: 0.5rem;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }

        .message-input-container {
            padding: 1rem;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }

        .message-form {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 20px;
            resize: none;
            min-height: 20px;
            max-height: 100px;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .message-input:focus {
            outline: none;
            border-color: var(--primary-pink);
        }

        .send-button {
            background: var(--primary-pink);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .send-button:hover {
            background: var(--primary-pink-dark);
            transform: scale(1.05);
        }

        .send-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
            text-align: center;
        }

        .empty-chat-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
                margin: 1rem;
            }
            
            .conversations-panel {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- App Header -->
    <header class="header">
        <div class="header-content">
            <a href="admin.php" class="logo">💕 LoveConnect</a>
            <nav class="nav-links">
                <a href="admin.php">Dashboard</a>
                <a href="matches.php">Discover</a>
                <a href="profile.php">Profile</a>
                <a href="messages.php" class="active">Messages</a>
                <a href="logout.php">Logout</a>
            </nav>
        </div>
    </header>

    <main class="messages-container">
        <!-- Conversations Panel -->
        <aside class="conversations-panel">
            <div class="conversations-header">
                <h2>💬 Conversations</h2>
            </div>
            <div id="conversations-list" class="conversations-list">
                <!-- Conversations will be loaded here -->
            </div>
        </aside>
        
        <!-- Chat Panel -->
        <section class="chat-panel">
            <?php if ($currentConversation): ?>
                <div class="chat-header">
                    <div class="chat-partner-info">
                        <h3><?= htmlspecialchars($currentConversation['partner_name']) ?></h3>
                    </div>
                </div>
                
                <div id="messages-area" class="messages-area">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div class="message-input-container">
                    <form id="message-form" class="message-form">
                        <textarea 
                            id="message-input" 
                            class="message-input" 
                            placeholder="Type your message..."
                            rows="1"
                        ></textarea>
                        <button type="submit" class="send-button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <div class="empty-chat-icon">💕</div>
                    <h2>Select a conversation</h2>
                    <p>Choose someone from your conversations to start messaging</p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        class MessagingSystem {
            constructor() {
                this.currentMatchId = <?= $currentMatchId ?? 'null' ?>;
                this.currentUserId = <?= $currentUser['id'] ?>;
                this.conversationsList = document.getElementById('conversations-list');
                this.messagesArea = document.getElementById('messages-area');
                this.messageForm = document.getElementById('message-form');
                this.messageInput = document.getElementById('message-input');
                
                this.init();
            }
            
            init() {
                this.loadConversations();
                
                if (this.currentMatchId) {
                    this.loadMessages();
                    this.markAsRead();
                    this.startPolling();
                }
                
                if (this.messageForm) {
                    this.messageForm.addEventListener('submit', (e) => this.sendMessage(e));
                    this.messageInput.addEventListener('input', () => this.autoResize());
                    this.messageInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            this.sendMessage(e);
                        }
                    });
                }
            }
            
            autoResize() {
                this.messageInput.style.height = 'auto';
                this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 100) + 'px';
            }
            
            async loadConversations() {
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_conversations');
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                    
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const conversations = await response.json();
                    this.renderConversations(conversations);
                } catch (error) {
                    console.error('Failed to load conversations:', error);
                }
            }
            
            renderConversations(conversations) {
                if (!this.conversationsList) return;
                
                if (conversations.length === 0) {
                    this.conversationsList.innerHTML = `
                        <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                            <div style="font-size: 2rem; margin-bottom: 1rem;">😔</div>
                            <p>No conversations yet</p>
                            <p style="font-size: 0.875rem;">Start matching to begin conversations!</p>
                        </div>
                    `;
                    return;
                }
                
                this.conversationsList.innerHTML = conversations.map(conv => `
                    <div class="conversation-item ${conv.match_id == this.currentMatchId ? 'active' : ''}" 
                         onclick="window.location.href='messages.php?match=${conv.match_id}'">
                        <div class="conversation-info">
                            <div class="partner-name">${this.escapeHtml(conv.partner_name)}</div>
                            <div class="last-message">${this.escapeHtml(conv.last_message || 'Say hello! 👋')}</div>
                        </div>
                        ${conv.unread_count > 0 ? `<div class="unread-badge">${conv.unread_count}</div>` : ''}
                    </div>
                `).join('');
            }
            
            async loadMessages() {
                if (!this.currentMatchId || !this.messagesArea) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'get_messages');
                    formData.append('match_id', this.currentMatchId);
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                    
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        this.renderMessages(result.messages);
                    }
                } catch (error) {
                    console.error('Failed to load messages:', error);
                }
            }
            
            renderMessages(messages) {
                if (!this.messagesArea) return;
                
                this.messagesArea.innerHTML = messages.map(msg => `
                    <div class="message ${msg.sender_id == this.currentUserId ? 'sent' : 'received'}">
                        <div>${this.escapeHtml(msg.message)}</div>
                        <div class="message-time">${msg.formatted_time}</div>
                    </div>
                `).join('');
                
                this.scrollToBottom();
            }
            
            async sendMessage(e) {
                e.preventDefault();
                
                const message = this.messageInput.value.trim();
                if (!message || !this.currentMatchId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('match_id', this.currentMatchId);
                    formData.append('message', message);
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                    
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        this.messageInput.value = '';
                        this.autoResize();
                        this.loadMessages();
                        this.loadConversations();
                    }
                } catch (error) {
                    console.error('Failed to send message:', error);
                }
            }
            
            async markAsRead() {
                if (!this.currentMatchId) return;
                
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_read');
                    formData.append('match_id', this.currentMatchId);
                    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
                    
                    await fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    });
                } catch (error) {
                    console.error('Failed to mark messages as read:', error);
                }
            }
            
            startPolling() {
                this.pollInterval = setInterval(() => {
                    this.loadMessages();
                    this.loadConversations();
                }, 3000);
            }
            
            scrollToBottom() {
                if (this.messagesArea) {
                    this.messagesArea.scrollTop = this.messagesArea.scrollHeight;
                }
            }
            
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }
        
        // Initialize messaging system
        document.addEventListener('DOMContentLoaded', () => {
            window.messaging = new MessagingSystem();
        });
        
        // Clean up on page unload
        window.addEventListener('beforeunload', () => {
            if (window.messaging && window.messaging.pollInterval) {
                clearInterval(window.messaging.pollInterval);
            }
        });
    </script>
</body>
</html>