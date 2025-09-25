<?php
// Test script for messaging API
session_start();

// Simulate being logged in as alex_tech (user_id 2)
$_SESSION['user_id'] = 2;
$_SESSION['username'] = 'alex_tech';

// Test the conversation API
$_POST['action'] = 'get_conversations';
$_POST['csrf_token'] = 'test_token'; // We'll skip validation for testing

// Include the messages.php file which contains the API
include 'messages.php';
?>