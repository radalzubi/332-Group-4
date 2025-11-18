<?php
// -----------------------------------------
// START SESSION
// -----------------------------------------
session_start();

// -----------------------------------------
// DATABASE CONNECTION (for non-AJAX content)
// -----------------------------------------
$servername = "localhost";
$db_user    = "your_db_user";     // Your working username
$db_pass    = "your_db_password";  // Update with your password
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

$db_connected = true;
if (!$conn || $conn->connect_error) {
    // Don't die - just note that DB is not connected
    $db_connected = false;
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
}

// -----------------------------------------
// ROUTER - FIXED FOR PHP 5.x
// -----------------------------------------
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// -----------------------------------------
// HANDLE LOGIN SUBMIT
// -----------------------------------------
if ($page === 'do_login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Demo login logic
    if ($username === 'manager' && $password === '123') {
        $_SESSION['role'] = 'senior';
        header("Location: index.php?page=erp");
        exit();
    }
    if ($username === 'supply' && $password === '123') {
        $_SESSION['role'] = 'scm';
        header("Location: index.php?page=scm");
        exit();
    }

    header("Location: index.php?page=login&error=1");
    exit();
}

// -----------------------------------------
// HANDLE LOGOUT
// -----------------------------------------
if ($page === 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles for AJAX KPI components */
        .kpi-section {
            margin-top: 20px;
        }
        
        .date-range-controls {
            background: rgba(31, 41, 55, 0.5);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .date-range-controls label {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .date-range-controls input[type="date"],
        .date-range-controls input[type="text"] {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #4b5563;
            background: #111827;
            color: #f9fafb;
            font-size: 0.9rem;
        }
        
        .btn-load {
            padding: 8px 20px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-load:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .kpi-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.2s;
        }
        
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.2);
        }
        
        .kpi-card h4 {
            margin: 0 0 10px 0;
            color: #e5e7eb;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .kpi-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #60a5fa;
            margin: 10px 0;
        }
        
        .kpi-subtitle {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .loading {
            text-align: center;
            color: #9ca3af;
            padding: 20px;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        /* Company search styles */
        .search-section {
            background: rgba(31, 41, 55, 0.5);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #4b5563;
            background: #111827;
            color: #f9fafb;
        }
        
        .btn-search {
            padding: 10px 20px;
            background: #4b5563;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-search:hover {
            background: #6b7280;
        }
        
        .company-results {
            margin-top: 20px;
        }
        
        .company-card {
            background: rgba(17, 24, 39, 0.9);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #374151;
            margin-top: 10px;
        }
        
        .company-card h3 {
            color: #60a5fa;
            margin: 0 0 10px 0;
        }
        
        .company-info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 0.9rem;
        }
        
        .company-info-label {
            color: #9ca3af;
        }
        
        .company-info-value {
            color: #e5e7eb;
            font-weight: 500;
        }

        /* Alerts styles */
        .alerts-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .alert-item {
            display: flex;
            gap: 10px;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .alert-high {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
        }
        
        .alert-medium {
            background: rgba(245, 158, 11, 0.2);
            border-left: 4px solid #f59e0b;
        }
        
        .alert-low {
            background: rgba(34, 197, 94, 0.2);
            border-left: 4px solid #22c55e;
        }
        
        .alert-icon {
            font-size: 1.5em;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-content strong {
            color: #f3f4f6;
            display: block;
            margin-bottom: 5px;
        }
        
        .alert-content p {
            color: #d1d5db;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .alert-content small {
            color: #9ca3af;
            font-size: 0.8rem;
        }
    </style>
    <script src="script.js" defer></script>
</head>
<body class="app-body">
<div class="app-shell">

<?php if ($page === 'login'): ?>

    <!-- LOGIN PAGE -->
    <div class="card" id="login-card">
        <header class="card-header">
            <h1 class="app-title">Group ERP Portal</h1>
            <p class="app-subtitle">Sign in to access your dashboard</p>
        </header>

        <!-- team members -->
        <section class="members-row">
            <div class="member">
                <img src="rad.jpg" alt="Rad AlZubi" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Rad AlZubi</span>
            </div>
            <div class="member">
                <img src="ryder.jpg" alt="Ryder Aldrich" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Ryder Aldrich</span>
            </div>
            <div class="member">
                <img src="natalie.jpg" alt="Natalie Anderson" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Natalie Anderson</span>
            </div>
            <div class="member">
                <img src="tani.jpg" alt="Tanitoluwa Adeleye" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Tanitoluwa Adeleye</span>
            </div>
            <div class="member">
                <img src="anushka.jpg" alt="Anushka Heda" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Anushka Heda</span>
            </div>
            <div class="member">
                <img src="jian.jpg" alt="Jian Lim" class="pfp" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22%234b5563%22/></svg>'">
                <span>Jian Lim</span>
            </div>
        </section>

        <!-- client-side error (JS) -->
        <p id="clientError" class="error-msg hidden"></p>

        <!-- simple PHP error (invalid login) -->
        <?php if (isset($_GET['error'])): ?>
            <p class="error-msg">Incorrect username or password.</p>
        <?php endif; ?>

        <form id="login-form" class="form" action="index.php?page=do_login" method="POST">
            <div class="form-field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="e.g. manager"
                    autocomplete="username"
                >
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Your password"
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-primary">Login</button>

            <p class="hint">
                Demo: <code>manager / 123</code> (Senior) • <code>supply / 123</code> (SCM)
            </p>
        </form>
    </div>

<?php elseif ($page === 'erp' && $role === 'senior'): ?>

    <!-- SENIOR MANAGER DASHBOARD -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Senior Manager Dashboard</h1>
                <p class="app-subtitle">
                    Financial health, disruptions, and network overview.
                </p>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=erp" class="nav-link active">Overview</a>
                <a href="#" class="nav-link">Financials</a>
                <a href="#" class="nav-link">Disruptions</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
            <div class="panel">
                <h2>Financial Health</h2>
                <p>SQL + charts for average financial health by company and time range.</p>
            </div>
            <div class="panel">
                <h2>Regional Disruptions</h2>
                <p>Disruptions per region, total vs high-impact (heatmap / stacked bar).</p>
            </div>
            <div class="panel">
                <h2>Critical Companies</h2>
                <p>Sort by <code>Criticality = #Downstream × HighImpactCount</code>.</p>
            </div>
            <div class="panel">
                <h2>Disruption Over Time</h2>
                <p>Time-series of disruption counts with a selectable window.</p>
            </div>
        </section>
    </div>

<?php elseif ($page === 'scm' && $role === 'scm'): ?>

    <!-- SUPPLY CHAIN MANAGER DASHBOARD -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Supply Chain Manager Dashboard</h1>
                <p class="app-subtitle">
                    Company info, KPIs, and disruption metrics.
                </p>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=scm" class="nav-link active">Overview</a>
                <a href="#" class="nav-link">Companies</a>
                <a href="#" class="nav-link">Transactions</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
            <!-- Company Info Panel -->
            <div class="panel">
                <h2>Company Info</h2>
                <div class="search-section" style="background: transparent; padding: 10px 0;">
                    <div class="search-box">
                        <input type="text" id="company-search" placeholder="Enter company name...">
                        <button onclick="searchCompany()" class="btn-search">Search</button>
                    </div>
                    <div id="company-results"></div>
                </div>
            </div>

            <!-- Key KPIs Panel - ENHANCED WITH AJAX -->
            <div class="panel">
                <h2>Key KPIs</h2>
                <div class="kpi-section">
                    <div class="date-range-controls">
                        <label>Company (optional):</label>
                        <input type="text" id="kpi-company" placeholder="All companies">
                        <label>From:</label>
                        <input type="date" id="kpi-start-date" value="2024-01-01">
                        <label>To:</label>
                        <input type="date" id="kpi-end-date" value="2024-12-31">
                        <button onclick="loadKPIs()" class="btn-load">Load KPIs</button>
                    </div>
                    <div id="kpi-loading" class="loading" style="display: none;">Loading KPIs...</div>
                    <div id="kpi-error" class="error-message" style="display: none;"></div>
                    <div id="kpi-display"></div>
                </div>
            </div>

            <!-- Disruption Metrics Panel -->
            <div class="panel">
                <h2>Disruption Metrics</h2>
                <div class="date-range-controls">
                    <label>From:</label>
                    <input type="date" id="metric-start-date" value="2024-01-01">
                    <label>To:</label>
                    <input type="date" id="metric-end-date" value="2024-12-31">
                    <button onclick="loadDisruptionMetrics()" class="btn-load">Calculate</button>
                </div>
                <div id="metrics-loading" class="loading" style="display: none;">Calculating metrics...</div>
                <div id="metrics-display"></div>
            </div>

            <!-- Alerts Panel -->
            <div class="panel">
                <h2>Alerts</h2>
                <div id="alerts-container" class="alerts-container">
                    <div class="loading">Loading alerts...</div>
                </div>
            </div>
        </section>
    </div>

<?php else: ?>

    <!-- FALLBACK -->
    <div class="card">
        <h1 class="app-title">Session expired or unauthorized</h1>
        <p class="app-subtitle">Please log in again.</p>
        <a href="index.php?page=login" class="btn-primary">Back to login</a>
    </div>

<?php endif; ?>

</div>
</body>
</html>
<?php
if ($db_connected && $conn && !$conn->connect_error) {
    $conn->close();
}
?>
