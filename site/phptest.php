<?php
// Temporary PHP check. Delete this file after testing.
header('Content-Type: text/plain; charset=utf-8');
echo "php-ok\n";
echo 'version: ' . PHP_VERSION . "\n";
echo 'sapi: ' . PHP_SAPI . "\n";
echo 'mysqli: ' . (extension_loaded('mysqli') ? 'yes' : 'no') . "\n";
echo 'pdo_mysql: ' . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
echo 'curl: ' . (extension_loaded('curl') ? 'yes' : 'no') . "\n";
