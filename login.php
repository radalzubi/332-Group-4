<?php
session_start();
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab 3 Form</title>
</head>
<body>
<?php
$servername = "localhost";
$username = "your_db_user";
$password = "your_db_password";
$database = $username;

$conn = new mysqli ($servername, $username, $password, $database);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

//$sql = "SELECT track_name, time from track where time < (select avg(time)
//from track)";
//$result = mysqli_query($conn, $sql);

foreach ($result as $row) {
echo $row ['column_name'];
echo print_r ($row);
}

$conn->close();
?>

</body>
</html>

// --- SIMPLE ROUTER ---
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// --- HANDLE LOGIN ---
if ($page === "do_login") {

    $username = $_POST['username'] ?? "";
    $password = $_POST['password'] ?? "";

    // Example hardcoded demo roles
    if ($username === "manager" && $password === "123") {
        $_SESSION['role'] = "senior";
        header("Location: index.php?page=erp");
        exit();
    }

    if ($username === "supply" && $password === "123") {
        $_SESSION['role'] = "scm";
        header("Location: index.php?page=scm");
        exit();
    }

    header("Location: index.php?page=login&error=1");
    exit();
}

// --- HANDLE LOGOUT ---
if ($page === "logout") {
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>ERP System</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>
</head>
<body>

<?php
// --- LOGIN PAGE ---
if ($page === "login") {
?>
    <h1>Welcome to Our ERP</h1>

    <div class="members">
        <div class="member">
            <img src="member1.jpg" class="pfp">
            <p>Member 1</p>
        </div>
        <div class="member">
            <img src="member2.jpg" class="pfp">
            <p>Member 2</p>
        </div>
        <div class="member">
            <img src="member3.jpg" class="pfp">
            <p>Member 3</p>
        </div>
        <div class="member">
            <img src="member4.jpg" class="pfp">
            <p>Member 4</p>
        </div>
        <div class="member">
            <img src="member5.jpg" class="pfp">
            <p>Member 5</p>
        </div>
        <div class="member">
            <img src="member6.jpg" class="pfp">
            <p>Member 6</p>
        </div>
    </div>

    <div class="login-box">
        <?php if (isset($_GET['error'])) echo "<p class='error-msg'>Incorrect login.</p>"; ?>
        <form action="index.php?page=do_login" method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <button type="submit">login</button>
        </form>
    </div>

<?php
}

// --- SENIOR MANAGER PAGE ---
if ($page === "erp" && ($_SESSION['role'] ?? '') === "senior") {
?>
    <h1>Senior Manager Dashboard</h1>

    <p>This is where financial health, top distributors, regional disruptions, etc. will go.</p>

    <a href="index.php?page=logout">Logout</a>

<?php
}

// --- SUPPLY CHAIN MANAGER PAGE ---
if ($page === "scm" && ($_SESSION['role'] ?? '') === "scm") {
?>
    <h1>Supply Chain Manager Dashboard</h1>

    <p>This is where KPIs, disruption metrics, company info, and transactions will go.</p>

    <a href="index.php?page=logout">Logout</a>

<?php
}
?>

</body>
</html>
