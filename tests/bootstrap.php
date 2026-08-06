<?php
/**
 * SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Find Nextcloud's base directory
$baseDir = __DIR__ . '/../../../../';
if (!file_exists($baseDir . 'lib/base.php')) {
    // Try alternative path when running in container
    $baseDir = '/var/www/html/';
}

require_once $baseDir . 'lib/base.php';

// Set up test environment
\OC::$CLI = true;

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}
