<?php
// -----------------------------------------
// START SESSION
// -----------------------------------------
session_start();

// -----------------------------------------
// DATABASE CONNECTION (for non-AJAX content)
// -----------------------------------------
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";  // Your password
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

$db_connected = true;
if (!$conn || $conn->connect_error) {
    $db_connected = false;
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
}

// -----------------------------------------
// ROUTER - PHP 5.4 Compatible
// -----------------------------------------
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// -----------------------------------------
// HANDLE LOGIN SUBMIT
// -----------------------------------------
if ($page === 'do_login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Demo login logic - PROPERLY SEPARATED
    if ($username === 'manager' && $password === '123') {
        $_SESSION['role'] = 'senior';
        $_SESSION['username'] = 'manager';
        header("Location: index.php?page=erp");
        exit();
    }
    if ($username === 'supply' && $password === '123') {
        $_SESSION['role'] = 'scm';
        $_SESSION['username'] = 'supply';
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

// -----------------------------------------
// SECURITY CHECK
// -----------------------------------------
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

// Prevent unauthorized access
if ($page === 'erp' && $role !== 'senior') {
    header("Location: index.php?page=login");
    exit();
}
if ($page === 'scm' && $role !== 'scm') {
    header("Location: index.php?page=login");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ERP System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Additional styles for AJAX components */
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
        .date-range-controls input[type="text"],
        .date-range-controls select {
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

        <!-- Team members -->
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

        <!-- Error messages -->
        <p id="clientError" class="error-msg hidden"></p>
        
        <?php if (isset($_GET['error'])): ?>
            <p class="error-msg">Incorrect username or password.</p>
        <?php endif; ?>

        <form id="login-form" class="form" action="index.php?page=do_login" method="POST">
            <div class="form-field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="e.g. manager">
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Your password">
            </div>

            <button type="submit" class="btn-primary">Login</button>

            <p class="hint">
                Demo: <code>manager / 123</code> (Senior Manager) • <code>supply / 123</code> (Supply Chain Manager)
            </p>
        </form>
    </div>

<?php elseif ($page === 'erp' && $role === 'senior'): ?>

    <!-- SENIOR MANAGER DASHBOARD (manager/123) -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Senior Manager Dashboard</h1>
                <p class="app-subtitle">Financial health, disruptions, and network overview.</p>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=erp" class="nav-link active">Overview</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
            <div class="panel">
                <h2>Financial Health</h2>
                <div class="date-range-controls">
                    <label>From:</label>
                    <input type="date" id="finance-start-date" value="2020-01-01">
                    <label>To:</label>
                    <input type="date" id="finance-end-date" value="2025-12-31">
                    <button onclick="loadFinancialHealth()" class="btn-load">Analyze</button>
                </div>
                <div id="financial-health-display" style="margin-top: 15px;">
                    <div class="loading">Click Analyze to load data...</div>
                </div>
            </div>

            <div class="panel">
                <h2>Regional Disruptions</h2>
                <button onclick="loadRegionalDisruptions()" class="btn-load">Load Regional Data</button>
                <div id="regional-disruptions-display" style="margin-top: 15px;">
                    <div class="loading">Click to view regional data...</div>
                </div>
            </div>

            <div class="panel">
                <h2>Critical Companies</h2>
                <button onclick="loadCriticalCompanies()" class="btn-load">Identify Critical Nodes</button>
                <div id="critical-companies-display" style="margin-top: 15px;">
                    <div class="loading">Click to identify critical companies...</div>
                </div>
            </div>

            <div class="panel">
                <h2>Network Overview</h2>
                <button onclick="loadNetworkOverview()" class="btn-load">Refresh Overview</button>
                <div id="network-overview-display" style="margin-top: 15px;">
                    <div class="loading">Click to load network stats...</div>
                </div>
            </div>
        </section>
    </div>

    <!-- Senior Manager JavaScript - COMPLETE VERSION -->
    <script>
    // Manager-specific fetch function that handles errors properly
    async function managerFetch(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(data).forEach(key => formData.append(key, data[key]));
            
            const response = await fetch('ajax_handler_manager.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('HTTP ' + response.status);
            
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', text);
                return {error: 'Invalid server response'};
            }
        } catch (error) {
            console.error('Fetch Error:', error);
            return {error: error.message};
        }
    }

    // Financial Health - COMPLETE
    async function loadFinancialHealth() {
        const display = document.getElementById('financial-health-display');
        if (!display) return;
        
        const startDate = document.getElementById('finance-start-date');
        const endDate = document.getElementById('finance-end-date');
        
        display.innerHTML = '<div class="loading">Analyzing financial health...</div>';
        
        const result = await managerFetch('getFinancialHealth', {
            start_date: startDate ? startDate.value : '2020-01-01',
            end_date: endDate ? endDate.value : '2025-12-31'
        });
        
        if (result.success && result.data) {
            let html = '<div style="text-align: center; margin-bottom: 20px;">';
            html += '<div style="font-size: 2.5em; color: #60a5fa; font-weight: bold;">';
            html += (result.data.avgHealthScore || 0) + '%';
            html += '</div><div style="color: #9ca3af;">Average Health Score</div></div>';
            
            // Top Performers
            if (result.data.topPerformers && result.data.topPerformers.length > 0) {
                html += '<h4 style="color: #22c55e; margin-top: 15px;">Top Performers</h4>';
                html += '<div style="font-size: 0.9em;">';
                result.data.topPerformers.slice(0, 3).forEach(function(company) {
                    html += '<div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #374151;">';
                    html += '<span>' + (company.CompanyName || 'Unknown') + '</span>';
                    html += '<span style="color: #60a5fa;">' + (company.ProfitMargin || 0) + '%</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            // Bottom Performers
            if (result.data.bottomPerformers && result.data.bottomPerformers.length > 0) {
                html += '<h4 style="color: #ef4444; margin-top: 15px;">Need Attention</h4>';
                html += '<div style="font-size: 0.9em;">';
                result.data.bottomPerformers.slice(0, 3).forEach(function(company) {
                    html += '<div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #374151;">';
                    html += '<span>' + (company.CompanyName || 'Unknown') + '</span>';
                    html += '<span style="color: #f59e0b;">' + (company.ProfitMargin || 0) + '%</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            
            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error: ' + (result.error || 'Failed to load data') + '</div>';
        }
    }

    // Regional Disruptions - COMPLETE
    async function loadRegionalDisruptions() {
        const display = document.getElementById('regional-disruptions-display');
        if (!display) return;
        
        display.innerHTML = '<div class="loading">Loading regional data...</div>';
        
        const result = await managerFetch('getRegionalDisruptions', {
            start_date: '2020-01-01',
            end_date: '2025-12-31'
        });
        
        if (result.success && result.data) {
            let html = '<div style="max-height: 300px; overflow-y: auto;">';
            
            if (result.data.length === 0) {
                html += '<p style="color: #9ca3af;">No regional data available</p>';
            } else {
                result.data.slice(0, 10).forEach(function(region) {
                    const total = region.totalDisruptions || 0;
                    const high = region.highImpact || 0;
                    const medium = region.mediumImpact || 0;
                    const low = region.lowImpact || 0;
                    
                    html += '<div style="margin-bottom: 15px;">';
                    html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                    html += '<strong>' + (region.region || 'Unknown') + '</strong>';
                    html += '<span style="color: #9ca3af;">' + total + ' events</span>';
                    html += '</div>';
                    
                    if (total > 0) {
                        // Progress bar showing severity distribution
                        html += '<div style="background: #374151; height: 20px; border-radius: 10px; overflow: hidden;">';
                        html += '<div style="display: flex; height: 100%;">';
                        if (high > 0) html += '<div style="background: #ef4444; width: ' + ((high/total)*100) + '%;"></div>';
                        if (medium > 0) html += '<div style="background: #f59e0b; width: ' + ((medium/total)*100) + '%;"></div>';
                        if (low > 0) html += '<div style="background: #22c55e; width: ' + ((low/total)*100) + '%;"></div>';
                        html += '</div></div>';
                        
                        html += '<div style="font-size: 0.8em; color: #9ca3af; margin-top: 3px;">';
                        html += 'High: ' + high + ' | Med: ' + medium + ' | Low: ' + low;
                        if (region.avgRecoveryTime) {
                            html += ' | Avg Recovery: ' + region.avgRecoveryTime + 'h';
                        }
                        html += '</div>';
                    }
                    
                    html += '</div>';
                });
            }
            
            html += '</div>';
            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading regional data</div>';
        }
    }

    // Critical Companies - COMPLETE
    async function loadCriticalCompanies() {
        const display = document.getElementById('critical-companies-display');
        if (!display) return;
        
        display.innerHTML = '<div class="loading">Analyzing network criticality...</div>';
        
        const result = await managerFetch('getCriticalCompanies');
        
        if (result.success && result.data) {
            let html = '<div style="max-height: 300px; overflow-y: auto;">';
            
            if (result.data.length === 0) {
                html += '<p style="color: #9ca3af;">No critical companies identified</p>';
            } else {
                result.data.slice(0, 10).forEach(function(company, index) {
                    const riskColor = company.RiskLevel === 'Critical' ? '#ef4444' : 
                                     company.RiskLevel === 'High' ? '#f59e0b' : 
                                     company.RiskLevel === 'Medium' ? '#eab308' : '#22c55e';
                    
                    html += '<div style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: rgba(31, 41, 55, 0.5); border-radius: 8px; border-left: 4px solid ' + riskColor + ';">';
                    html += '<div style="flex: 1;">';
                    html += '<strong>#' + (index + 1) + ' ' + (company.CompanyName || 'Unknown') + '</strong>';
                    html += '<div style="font-size: 0.85em; color: #9ca3af; margin-top: 3px;">';
                    html += (company.CompanyType || 'Unknown') + ' | Tier ' + (company.TierLevel || '?');
                    html += '</div>';
                    html += '<div style="font-size: 0.85em; color: #9ca3af;">';
                    html += (company.DownstreamDependencies || 0) + ' downstream | ';
                    html += (company.HighImpactDisruptions || 0) + ' high-impact events';
                    html += '</div></div>';
                    html += '<div style="text-align: center; padding: 0 10px;">';
                    html += '<div style="font-size: 1.2em; font-weight: bold; color: ' + riskColor + ';">';
                    html += company.CriticalityScore || 0;
                    html += '</div>';
                    html += '<div style="font-size: 0.75em; color: ' + riskColor + ';">';
                    html += company.RiskLevel || 'Low';
                    html += '</div></div></div>';
                });
            }
            
            html += '</div>';
            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading critical companies</div>';
        }
    }

    // Network Overview - COMPLETE
    async function loadNetworkOverview() {
        const display = document.getElementById('network-overview-display');
        if (!display) return;
        
        display.innerHTML = '<div class="loading">Loading network statistics...</div>';
        
        const result = await managerFetch('getNetworkOverview');
        
        if (result.success && result.data) {
            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px;">';
            
            // Total Companies
            html += '<div style="text-align: center; padding: 15px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.3);">';
            html += '<div style="font-size: 2em; color: #60a5fa; font-weight: bold;">' + (result.data.totalCompanies || 0) + '</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Companies</div>';
            html += '</div>';
            
            // Connections
            html += '<div style="text-align: center; padding: 15px; background: rgba(34, 197, 94, 0.1); border-radius: 8px; border: 1px solid rgba(34, 197, 94, 0.3);">';
            html += '<div style="font-size: 2em; color: #22c55e; font-weight: bold;">' + (result.data.totalConnections || 0) + '</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Connections</div>';
            html += '</div>';
            
            // Active Disruptions
            html += '<div style="text-align: center; padding: 15px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.3);">';
            html += '<div style="font-size: 2em; color: #ef4444; font-weight: bold;">' + (result.data.activeDisruptions || 0) + '</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Active Issues</div>';
            html += '</div>';
            
            // Network Health
            html += '<div style="text-align: center; padding: 15px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3);">';
            html += '<div style="font-size: 2em; color: #f59e0b; font-weight: bold;">' + (result.data.avgNetworkHealth || 0) + '%</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Health</div>';
            html += '</div>';
            
            html += '</div>';
            
            // Company type breakdown if available
            if (result.data.companyTypes) {
                html += '<div style="margin-top: 20px;">';
                html += '<h4 style="color: #e5e7eb; margin: 10px 0;">Company Distribution by Type</h4>';
                html += '<div style="display: flex; gap: 20px; flex-wrap: wrap;">';
                
                for (let type in result.data.companyTypes) {
                    html += '<div style="text-align: center; padding: 10px; background: rgba(31, 41, 55, 0.5); border-radius: 8px;">';
                    html += '<div style="font-size: 1.5em; color: #60a5fa; font-weight: bold;">' + result.data.companyTypes[type] + '</div>';
                    html += '<div style="font-size: 0.85em; color: #9ca3af;">' + type + '</div>';
                    html += '</div>';
                }
                
                html += '</div></div>';
            }
            
            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading network overview: ' + (result.error || 'Unknown error') + '</div>';
        }
    }
    
    // Auto-load network overview when page loads
    window.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('network-overview-display')) {
            setTimeout(loadNetworkOverview, 500);
        }
    });
    </script>

<?php elseif ($page === 'scm' && $role === 'scm'): ?>

    <!-- SUPPLY CHAIN MANAGER DASHBOARD (supply/123) -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Supply Chain Manager Dashboard</h1>
                <p class="app-subtitle">Company info, KPIs, and disruption metrics.</p>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=scm" class="nav-link active">Overview</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
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

            <div class="panel">
                <h2>Key KPIs</h2>
                <div class="kpi-section">
                    <div class="date-range-controls">
                        <label>From:</label>
                        <input type="date" id="kpi-start-date" value="2020-01-01">
                        <label>To:</label>
                        <input type="date" id="kpi-end-date" value="2025-12-31">
                        <button onclick="loadKPIs()" class="btn-load">Load KPIs</button>
                    </div>
                    <div id="kpi-display"></div>
                </div>
            </div>

            <div class="panel">
                <h2>Disruption Metrics</h2>
                <p>DF, ART, HDR, TD calculations</p>
            </div>

            <div class="panel">
                <h2>Alerts</h2>
                <div id="alerts-container" class="alerts-container">
                    <div class="loading">Loading alerts...</div>
                </div>
            </div>
        </section>
    </div>

<?php else: ?>

    <!-- ACCESS DENIED / SESSION EXPIRED -->
    <div class="card">
        <h1 class="app-title">Session Expired</h1>
        <p class="app-subtitle">Please log in to continue.</p>
        <a href="index.php?page=login" class="btn-primary">Back to Login</a>
    </div>

<?php endif; ?>

</div>
</body>
</html>
<?php
if (isset($conn) && $conn && !$conn->connect_error) {
    $conn->close();
}
?>
