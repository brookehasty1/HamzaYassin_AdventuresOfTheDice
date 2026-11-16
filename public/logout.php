<?php
declare(strict_types = 1);
require_once __DIR__ . '/../src/bootstrap.php';

/** Makes sure submit was clicked before logging out user */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && csrf_valid()) {
    logout_user();
    flash('success', 'You have been logged out.');
}
redirect('login.php');