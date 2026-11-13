<?php
require __DIR__ . '/includes/bootstrap.php';
require_post_with_csrf();
logout_user();
session_start();
flash('success', 'You have been logged out. See you soon!');
redirect('login.php');
