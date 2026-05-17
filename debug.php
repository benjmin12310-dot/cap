<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<h2>PHP ' . PHP_VERSION . '</h2>';
echo '<b>ENV vars via getenv():</b><pre>';
echo 'DB_HOST: ' . var_export(getenv('DB_HOST'), true) . "\n";
echo 'DB_USER: ' . var_export(getenv('DB_USER'), true) . "\n";
echo 'DB_NAME: ' . var_export(getenv('DB_NAME'), true) . "\n";
echo 'DB_PORT: ' . var_export(getenv('DB_PORT'), true) . "\n";
echo '$_ENV count: ' . count($_ENV) . "\n";
echo '</pre>';

echo '<b>DB connection test:</b><br>';
try {
    $c = new mysqli(
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_USER') ?: 'root',
        getenv('DB_PASS') ?: '',
        getenv('DB_NAME') ?: 'railway',
        (int)(getenv('DB_PORT') ?: 3306)
    );
    echo '<span style="color:green">✓ Connected!</span><br>';
    $r = $c->query("SHOW TABLES");
    echo 'Tables: ';
    while($row = $r->fetch_row()) echo $row[0] . ', ';
    $c->close();
} catch(Exception $e) {
    echo '<span style="color:red">✗ ' . htmlspecialchars($e->getMessage()) . '</span><br>';
}

echo '<br><b>Loading includes:</b><br>';
try {
    require_once 'includes/config.php';
    echo 'config.php ✓<br>';
    echo 'APP_DEBUG=' . var_export(APP_DEBUG, true) . '<br>';
    require_once 'includes/db.php';
    echo 'db.php ✓<br>';
} catch(Throwable $e) {
    echo '<span style="color:red">CRASH: ' . htmlspecialchars($e->getMessage()) . '<br>';
    echo 'In: ' . $e->getFile() . ':' . $e->getLine() . '</span><br>';
}
