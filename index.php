<?php
require __DIR__ . '/includes/bootstrap.php';
redirect(current_user() ? 'board.php' : 'login.php');
