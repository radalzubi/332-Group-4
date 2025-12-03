<?php
// ajax_handler_supply_chain_managers.php
session_start();

// Database Connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);
header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';
