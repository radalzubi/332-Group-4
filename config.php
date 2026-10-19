<?php
// Database connection shared by index.php and the AJAX handlers.
//
// Settings come from the DB_HOST, DB_USER, DB_PASSWORD and DB_NAME environment
// variables (for example `SetEnv` in Apache, or
// `DB_HOST=127.0.0.1 DB_USER=... php -S localhost:8000`). The fallbacks are
// placeholders, so no real credentials live in the repo.

// The callers check $conn->connect_error and query results themselves. Since
// PHP 8.1, mysqli throws exceptions by default instead, so turn that off.
mysqli_report(MYSQLI_REPORT_OFF);

function db_setting(string $name, string $placeholder): string
{
    $value = getenv($name);
    return ($value === false || $value === '') ? $placeholder : $value;
}

$conn = @new mysqli(
    db_setting('DB_HOST', 'localhost'),
    db_setting('DB_USER', 'your_db_user'),
    db_setting('DB_PASSWORD', 'your_db_password'),
    db_setting('DB_NAME', 'your_db_name')
);
