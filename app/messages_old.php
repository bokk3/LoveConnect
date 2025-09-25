<?php
require_once 'db.php';
require_once 'functions.php';

startSecureSession();

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$pdo = getDbConnection();

// Handle AJAX requests for real-time messaging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'send_message':
            $result = sendMessage($pdo, $currentUser['id'], $_POST);
            echo json_encode($result);
            exit;
            
        case 'get_messages':
            $result = getMessages($pdo, $currentUser['id'], $_POST['match_id'] ?? 0);
            echo json_encode($result);
            exit;
            
        case 'mark_read':
            $result = markMessagesRead($pdo, $currentUser['id'], $_POST['match_id'] ?? 0);
            echo json_encode($result);
            exit;
            
        case 'get_conversations':
            $result = getConversations($pdo, $currentUser['id']);
            echo json_encode($result);
            exit;
    }
}

// Get current conversation if match_id is provided
$currentMatchId = isset($_GET['match']) ? (int)$_GET['match'] : null;
$currentConversation = null;

if ($currentMatchId) {
    // Get conversation info from existing messages
    $stmt = $pdo->prepare('
        SELECT 
            m.match_id,
            CASE 
                WHEN m.sender_id = ? THEN u2.username 
                ELSE u1.username 
            END as partner_name,
            CASE 
                WHEN m.sender_id = ? THEN u2.id 
                ELSE u1.id 
            END as partner_id,
            MIN(m.created_at) as created_at
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.recipient_id = u2.id
        WHERE m.match_id = ? AND (m.sender_id = ? OR m.recipient_id = ?)
        GROUP BY m.match_id
    ');
    $stmt->execute([$currentUser['id'], $currentUser['id'], $currentMatchId, $currentUser['id'], $currentUser['id']]);
    $currentConversation = $stmt->fetch();
}

// Functions for message handling
function sendMessage($pdo, $userId, $data) {
    if (!validateCSRFToken($data['csrf_token'] ?? '')) {
        return ['success' => false, 'error' => 'Invalid security token'];
    }
    
    $matchId = (int)($data['match_id'] ?? 0);
    $message = trim($data['message'] ?? '');
    
    if (empty($message)) {
        return ['success' => false, 'error' => 'Message cannot be empty'];
    }
    
    // Verify user is part of this conversation by checking existing messages
    $stmt = $pdo->prepare('
        SELECT DISTINCT sender_id, recipient_id 
        FROM messages 
        WHERE match_id = ? AND (sender_id = ? OR recipient_id = ?)
        LIMIT 1
    ');
    $stmt->execute([$matchId, $userId, $userId]);
    $existing = $stmt->fetch();
    
    if (!$existing) {
        return ['success' => false, 'error' => 'Invalid conversation'];
    }
    
    // Determine recipient ID
    $recipientId = $existing['sender_id'] == $userId ? $existing['recipient_id'] : $existing['sender_id'];
    
    // Insert message
    $stmt = $pdo->prepare('INSERT INTO messages (match_id, sender_id, recipient_id, message) VALUES (?, ?, ?, ?)');
    $success = $stmt->execute([$matchId, $userId, $recipientId, $message]);
    
    if ($success) {
        return ['success' => true, 'message_id' => $pdo->lastInsertId()];
    } else {
        return ['success' => false, 'error' => 'Failed to send message'];
    }
}

function getMessages($pdo, $userId, $matchId) {
    $matchId = (int)$matchId;
    
    // Verify user is part of this conversation by checking existing messages
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM messages 
        WHERE match_id = ? AND (sender_id = ? OR recipient_id = ?)
    ');
    $stmt->execute([$matchId, $userId, $userId]);
    
    if ($stmt->fetchColumn() == 0) {
        return ['success' => false, 'error' => 'Invalid conversation'];
    }
    
    // Get messages
    $stmt = $pdo->prepare('
        SELECT m.id, m.sender_id, m.message, m.created_at, m.is_read,
               u.username as sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.match_id = ?
        ORDER BY m.created_at ASC
    ');
    $stmt->execute([$matchId]);
    $messages = $stmt->fetchAll();
    
    return ['success' => true, 'messages' => $messages];
}

function markMessagesRead($pdo, $userId, $matchId) {
    $matchId = (int)$matchId;
    $stmt = $pdo->prepare('UPDATE messages SET is_read = TRUE WHERE match_id = ? AND recipient_id = ?');
    $stmt->execute([$matchId, $userId]);
    return ['success' => true];
}

function getConversations($pdo, $userId) {
    // Get all messages where the user is involved, grouped by match_id
    $stmt = $pdo->prepare('
        SELECT 
            m.match_id,
            CASE 
                WHEN m.sender_id = ? THEN u2.username 
                ELSE u1.username 
            END as partner_name,
            CASE 
                WHEN m.sender_id = ? THEN u2.id 
                ELSE u1.id 
            END as partner_id,
            MIN(m.created_at) as matched_at,
            (SELECT msg.message FROM messages msg WHERE msg.match_id = m.match_id ORDER BY msg.created_at DESC LIMIT 1) as last_message,
            (SELECT msg.created_at FROM messages msg WHERE msg.match_id = m.match_id ORDER BY msg.created_at DESC LIMIT 1) as last_message_time,
            (SELECT msg.sender_id FROM messages msg WHERE msg.match_id = m.match_id ORDER BY msg.created_at DESC LIMIT 1) as last_sender_id,
            SUM(CASE WHEN m.recipient_id = ? AND m.is_read = FALSE THEN 1 ELSE 0 END) as unread_count
        FROM messages m
        JOIN users u1 ON m.sender_id = u1.id
        JOIN users u2 ON m.recipient_id = u2.id
        WHERE m.sender_id = ? OR m.recipient_id = ?
        GROUP BY m.match_id
        ORDER BY last_message_time DESC
    ');
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

function getCurrentUser() {
    global $pdo;
    if (!$pdo) $pdo = getDbConnection();
    
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - LoveConnect Dating App</title>
    <meta name="csrf-token" content="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .messages-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 60px);
            gap: 0;
            margin-top: 60px;
        }
        
        .conversations-panel {
            background: var(--surface-color);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background-color var(--transition-fast);
            position: relative;
        }
        
        .conversation-item:hover,
        .conversation-item.active {
            background: rgba(255, 107, 122, 0.1);
        }
        
        .conversation-item.active {
            border-right: 3px solid var(--primary-color);
        }
        
        .conversation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-xs);
        }
        
        .partner-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .message-time {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
        }
        
        .last-message {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .unread-badge {
            background: var(--primary-color);
            color: white;
            border-radius: var(--border-radius-full);
            font-size: var(--font-size-xs);
            font-weight: 600;
            padding: 2px 6px;
            min-width: 18px;
            text-align: center;
        }
        
        .chat-panel {
            display: flex;
            flex-direction: column;
            background: var(--bg-color);
        }
        
        .chat-header {
            background: var(--surface-color);
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .chat-partner-info h3 {
            margin: 0;
            font-size: var(--font-size-lg);
            color: var(--text-primary);
        }
        
        .chat-partner-status {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
        }
        
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: var(--spacing-md);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .message {
            max-width: 70%;
            word-wrap: break-word;
        }
        
        .message.sent {
            align-self: flex-end;
        }
        
        .message.received {
            align-self: flex-start;
        }
        
        .message-bubble {
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-lg);
            position: relative;
        }
        
        .message.sent .message-bubble {
            background: var(--primary-color);
            color: white;
            border-bottom-right-radius: var(--border-radius-sm);
        }
        
        .message.received .message-bubble {
            background: var(--surface-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-bottom-left-radius: var(--border-radius-sm);
        }
        
        .message-time {
            font-size: var(--font-size-xs);
            opacity: 0.7;
            margin-top: var(--spacing-xs);
        }
        
        .message-input-area {
            background: var(--surface-color);
            border-top: 1px solid var(--border-color);
            padding: var(--spacing-md);
        }
        
        .message-form {
            display: flex;
            gap: var(--spacing-sm);
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            min-height: 40px;
            max-height: 120px;
            resize: none;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: var(--spacing-sm) var(--spacing-md);
            font-family: inherit;
        }
        
        .send-button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-full);
            width: 40px;
            height: 40px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-fast);
        }
        
        .send-button:hover:not(:disabled) {
            background: var(--primary-dark);
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
            margin-bottom: var(--spacing-lg);
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
                height: calc(100vh - 60px);
            }
            
            .conversations-panel {
                display: none;
            }
            
            .messages-container.show-conversations .conversations-panel {
                display: block;
            }
            
            .messages-container.show-conversations .chat-panel {
                display: none;
            }
        }
        
        .typing-indicator {
            display: none;
            padding: var(--spacing-sm);
            font-style: italic;
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }
        
        .typing-indicator.show {
            display: block;
        }
        
        .typing-dots {
            display: inline-block;
        }
        
        .typing-dots::after {
            content: '';
            animation: dots 1.5s infinite;
        }
        
        @keyframes dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
    </style>
</head>
<body>
    <!-- App Header -->
    <header class="app-header">
        <div class="header-content">
            <h1 class="logo">💕 LoveConnect</h1>
            <nav>
                <ul class="nav-menu">
                    <li><a href="admin.php" class="nav-link">Dashboard</a></li>
                    <li><a href="matches.php" class="nav-link">Discover</a></li>
                    <li><a href="profile.php" class="nav-link">Profile</a></li>
                    <li><a href="messages.php" class="nav-link active">Messages</a></li>
                    <li><a href="logout.php" class="nav-link">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="messages-container">
        <div class="flash-container"></div>
        
        <!-- Conversations Panel -->
        <aside class="conversations-panel">
            <div class="conversations-header" style="padding: var(--spacing-md); border-bottom: 1px solid var(--border-color); background: var(--bg-color);">
                <h2 style="margin: 0; font-size: var(--font-size-lg);">💬 Conversations</h2>
            </div>
            <div id="conversations-list">
                <!-- Conversations will be loaded here -->
            </div>
        </aside>
        
        <!-- Chat Panel -->
        <section class="chat-panel">
            <?php if ($currentConversation): ?>
                <div class="chat-header">
                    <div class="chat-partner-info">
                        <h3><?= htmlspecialchars($currentConversation['partner_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="chat-partner-status">Matched on <?= date('M j, Y', strtotime($currentConversation['created_at'])) ?></div>
                    </div>
                </div>
                
                <div class="messages-area" id="messages-area">
                    <!-- Messages will be loaded here -->
                </div>
                
                <div class="typing-indicator" id="typing-indicator">
                    <span class="typing-dots"><?= htmlspecialchars($currentConversation['partner_name'], ENT_QUOTES, 'UTF-8') ?> is typing</span>
                </div>
                
                <div class="message-input-area">
                    <form class="message-form" id="message-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="match_id" value="<?= $currentMatchId ?>">
                        <textarea 
                            class="message-input" 
                            id="message-input"
                            placeholder="Type a message..." 
                            rows="1"
                            required
                        ></textarea>
                        <button type="submit" class="send-button" id="send-button">
                            ➤
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-chat">
                    <div class="empty-chat-icon">💬</div>
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the left panel to start messaging</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <script src="assets/app.js"></script>
    <script>
        class MessagingSystem {
            constructor() {
                this.currentMatchId = <?= $currentMatchId ? $currentMatchId : 'null' ?>;
                this.currentUserId = <?= $currentUser['id'] ?>;
                this.messagesArea = document.getElementById('messages-area');
                this.messageForm = document.getElementById('message-form');
                this.messageInput = document.getElementById('message-input');
                this.sendButton = document.getElementById('send-button');
                this.conversationsList = document.getElementById('conversations-list');
                
                this.init();
            }
            
            init() {
                this.loadConversations();
                if (this.currentMatchId) {
                    this.loadMessages();
                    this.startPolling();
                }
                this.bindEvents();
            }
            
            bindEvents() {
                if (this.messageForm) {
                    this.messageForm.addEventListener('submit', (e) => this.sendMessage(e));
                }
                
                if (this.messageInput) {
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
                this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 120) + 'px';
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
                        <div style="padding: var(--spacing-lg); text-align: center; color: var(--text-secondary);">
                            <div style="font-size: 2rem; margin-bottom: var(--spacing-md);">😔</div>
                            <p>No conversations yet</p>
                            <p style="font-size: var(--font-size-sm);">Start matching to begin conversations!</p>
                        </div>
                    `;
                    return;
                }
                
                this.conversationsList.innerHTML = conversations.map(conv => `
                    <div class="conversation-item ${conv.match_id == this.currentMatchId ? 'active' : ''}" 
                         onclick="window.location.href='messages.php?match=${conv.match_id}'">
                        <div class="conversation-header">
                            <span class="partner-name">${this.escapeHtml(conv.partner_name)}</span>
                            <div style="display: flex; align-items: center; gap: var(--spacing-xs);">
                                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                                <span class="message-time">${this.formatTime(conv.last_message_time || conv.matched_at)}</span>
                            </div>
                        </div>
                        <div class="last-message">
                            ${conv.last_message ? this.escapeHtml(conv.last_message) : 'Say hello! 👋'}
                        </div>
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
                        this.markAsRead();
                    }
                } catch (error) {
                    console.error('Failed to load messages:', error);
                }
            }
            
            renderMessages(messages) {
                if (!this.messagesArea) return;
                
                this.messagesArea.innerHTML = messages.map(msg => `
                    <div class="message ${msg.sender_id == this.currentUserId ? 'sent' : 'received'}">
                        <div class="message-bubble">
                            ${this.escapeHtml(msg.message)}
                        </div>
                        <div class="message-time">
                            ${this.formatTime(msg.created_at)}
                        </div>
                    </div>
                `).join('');
                
                this.scrollToBottom();
            }
            
            async sendMessage(e) {
                e.preventDefault();
                
                const message = this.messageInput.value.trim();
                if (!message) return;
                
                this.sendButton.disabled = true;
                
                try {
                    const formData = new FormData(this.messageForm);
                    formData.append('action', 'send_message');
                    formData.append('message', message);
                    
                    const response = await fetch('messages.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    if (result.success) {
                        this.messageInput.value = '';
                        this.autoResize();
                        this.loadMessages();
                        this.loadConversations(); // Update conversation list
                        Flash.success('Message sent!');
                    } else {
                        Flash.error(result.error || 'Failed to send message');
                    }
                } catch (error) {
                    Flash.error('Failed to send message');
                    console.error('Send message error:', error);
                } finally {
                    this.sendButton.disabled = false;
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
                // Poll for new messages every 3 seconds
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
            
            formatTime(timestamp) {
                if (!timestamp) return '';
                const date = new Date(timestamp);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) return 'Now';
                if (diffMins < 60) return `${diffMins}m`;
                if (diffHours < 24) return `${diffHours}h`;
                if (diffDays < 7) return `${diffDays}d`;
                return date.toLocaleDateString();
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