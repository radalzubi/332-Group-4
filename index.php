<?php

session_start();

// Database connection ($conn), configured in config.php
require_once __DIR__ . '/config.php';

$db_connected = true;
if (!$conn || $conn->connect_error) {
    $db_connected = false;
    error_log("Database connection failed: " . ($conn ? $conn->connect_error : "Please retry connection"));
}

// Login page
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

// Login entry
if ($page === 'do_login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // USERNAMES & PASSWORDS
    if ($username === 'managerL?P35!' && $password === '91649cbwoy24?@') {
        $_SESSION['role'] = 'senior';
        $_SESSION['username'] = 'manager';
        header("Location: index.php?page=erp");
        exit();
    }
    if ($username === 'supply64198!' && $password === '3kfehTrwE2@s') {
        $_SESSION['role'] = 'scm';
        $_SESSION['username'] = 'supply';
        header("Location: index.php?page=scm");
        exit();
    }

    header("Location: index.php?page=login&error=1");
    exit();
}

// Logout option
if ($page === 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php?page=login");
    exit();
}

// Security check
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;

if ($page === 'erp' && $role !== 'senior') {
    header("Location: index.php?page=login");
    exit();
}
if ($page === 'scm' && $role !== 'scm') {
    header("Location: index.php?page=login");
    exit();
}
if ($page === 'company_search' && $role !== 'scm') {
    header("Location: index.php?page=login");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">


<!--Front-end display-->
<head>
 <meta charset="utf-8"/>
 <title>
  ERP System
 </title>
 <link href="style.css" rel="stylesheet"/>
 <!--connects to style.css for additional graphics-->
 <script src="https://cdn.jsdelivr.net/npm/chart.js">
 </script>
 <script src="https://cdn.plot.ly/plotly-2.34.0.min.js">
 </script>
 <script defer="" src="script.js">
 </script>
</head>

<script src="script.js" defer></script>

</head>
<body class="app-body">
<div class="app-shell">

<?php if ($page === 'login'): ?>

    <!-- LOGIN PAGE DISPLAY -->
    <div class="card" id="login-card">
        <header class="card-header">
            <h1 class="app-title">Group 4 ERP System</h1>
        </header>

        <!-- Team members -->
        <section class="members-row">
            <div class="member">
                <span>Rad AlZubi</span>
            </div>
            <div class="member">
                <span>Ryder Aldrich</span>
            </div>
            <div class="member">
                <span>Natalie Anderson</span>
            </div>
            <div class="member">
                <span>Tanitoluwa Adeleye</span>
            </div>
            <div class="member">
                <span>Anushka Heda</span>
            </div>
            <div class="member">
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
                <input type="text" id="username" name="username" placeholder="Your username">
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Your password">
            </div>

            <button type="submit" class="btn-primary">Login</button>
        </form>
    </div>

<?php elseif ($page === 'erp' && $role === 'senior'): ?>

    <!-- SENIOR MANAGER DASHBOARD (manager/123) -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Senior Manager Dashboard</h1>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <!-- DISPLAY PANELS -->
        <section class="grid">
            <!-- General info panel -->
             <div class="panel-wide">
                <div id="network-overview-display" style="margin-top: 15px;"></div>
            </div>
            
            <script> // Auto load network overview panel
                window.addEventListener('DOMContentLoaded', function() {
                    if (document.getElementById('network-overview-display')) {
                     setTimeout(getNetworkOverview, 500);
                    } }); </script>



            <!-- Most critical companies panel -->
            <div class="panel">
                <h2>Critical Companies</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box">
                        <input type="text" id="critical-companies-search" placeholder="Enter company name or ID">
                        <button onclick="mostCriticalCompanies()" class="btn-company-search">Search</button>
                    </div>
                <div id="critical-companies-display" style="margin-top: 15px;"></div>
                </div>
            </div>

            <!-- Nontrivial plot 2 panel -->
            <div class="panel">
                <h2>Vulnurable Regions</h2>
                <div id="regional-criticality-display" class="disruptions-4">
                    <div class="loading">Loading chart...</div>
                </div>
            </div>

            <!-- Regional disruption overview panel -->
            <div class="panel">
                <h2>Regional Disruptions</h2>
                <div id="regional-disruptions-display" style="margin-top: 15px;"></div>
            </div>

            <!-- Disruption frequency over time panel -->
            <div class="panel">
                <h2>Disruption Frequency</h2>
                <div class="date-range-controls">
                    <label>From:</label>
                    <input type="date" id="disruption-start-date" value="2019-01-01">
                    <label>To:</label>
                    <input type="date" id="disruption-end-date" value="2025-12-31">
                    <input type="text" id="disruption-company-filter" placeholder="Filter by company name or ID">
                    <button onclick="disruptionFrequency()" class="btn-load">View</button>
                </div>

                <div id="disruption-trend-display" style="margin-top: 15px;">
                    <div class="loading">Enter desired timeframe and click "View" to see data.</div>
                </div>
            </div>

            <!-- Top distributors by shipment volume panel -->
            <div class="panel">
                <h2>Top Distributors by Shipment Volume</h2>
                    <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box">
                        <input type="text" id="distributor-search" placeholder="Filter by distributor name or ID">
                        <button onclick="topDistributorsByVolume()" class="btn-company-search">Search</button>
                    </div>
                </div>
                <div id="distributor-volume-results"></div>
                <div id="top-distributors-display" style="margin-top: 15px;"></div>
            </div>

            <!-- Nontrivial plot 1 panel-->
            <div class="panel">
                <h2>Distributors by Shipments</h2>
                <div id="nontrivial-plot-1" class="trend-scroll">
                    <div class="loading">Loading distributor shipment volumes...</div>
                </div>
            </div>

            <!-- Companies affected by disruption event panel -->
            <div class="panel">
                <h2>Companies Affected by Disruption Events</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box">
                        <input type="text" id="event-company-search" placeholder="Enter disruption event name or ID">
                        <button onclick="companiesAffectedByEvent()" class="btn-company-search">Search</button>
                    </div>
                </div>
                <div id="event-companies-display" style="margin-top: 15px;">
                    <div class="loading">Search by disruption event name or event ID.</div>
                </div>
            </div>

            <!-- All disruptions for specific company panel -->
            <div class="panel">
                <h2>Disruptions By Company</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                <div class="company-search-box">
                    <input type="text" id="company-disruption-search" placeholder="Enter company name or ID">
                    <button onclick="allDisruptionsForCompany()" class="btn-company-search">Search</button>
                </div>
            </div>

                <div id="company-disruptions-display" style="margin-top: 15px;">
                    <div class="loading">Search by company name or ID.</div>
                </div>
            </div>

            <!-- Distributors sorted by average delay panel -->
            <div class="panel">
                <h2>Average Distributor Delay Time</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box distributor-search-box">
                        <input type="text" id="distributor-delay-search" placeholder="Enter distributor name or ID">
                        <button onclick="loadDistributorDelays()" class="btn-company-search">Find</button>
                    </div>
                </div>
                <div id="distributor-delay-display" style="margin-top: 15px;">
                    <div class="loading">Loading distributor delays...</div>
                </div>
            </div>

            <!-- Company financials by region panel -->
            <div class="panel">
                <h2>Company Financials</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box">
                        <input type="text" id="company-financial-search" placeholder="Enter company name or ID">
                        <button onclick="searchCompanyFinancials()" class="btn-company-search">Search</button>
                    </div>
                </div>
                <div id="company-financial-display" style="margin-top: 15px;">
                    <div class="loading">Loading company financials by region...</div>
                </div>
            </div>

            <!-- Financial health panel -->
            <div class="panel-wide">
                <h2>Financial Health</h2>
                <div class="date-range-controls">
                    <label>From:</label>
                    <input type="date" id="finance-start-date" value="2020-01-01">
                    <label>To:</label>
                    <input type="date" id="finance-end-date" value="2025-12-31">
                    <button onclick="avgFinancialHealth()" class="btn-load">View</button>
                </div>
                <div id="financial-health-display" style="margin-top: 15px;">
                    <div class="loading">Enter desired timeframe and click "View" to see data.</div>
                </div>
            </div>

        </section>
    </div>

<!-- Senior Manager functions (JS) -->
    <script>
    // Manager-specific fetch function that handles errors properly
    async function managerFetch(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(data).forEach(key => formData.append(key, data[key]));
            
            const response = await fetch('ajax_handler_senior_managers.php', {
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

// Financial Health Overview
async function avgFinancialHealth() {
    const display = document.getElementById('financial-health-display');
    if (!display) return;

    display.innerHTML = '<div class="loading">Loading financial health overview...</div>';

    // Call backend
    const startDate = document.getElementById('finance-start-date')?.value || '2020-01-01';
    const endDate   = document.getElementById('finance-end-date')?.value || '2025-12-31';

    const result = await managerFetch('avg_financial_health', {
        startDate,
        endDate
    });

    // If valid data returned
    if (result.success && Array.isArray(result.data) && result.data.length > 0) {
        const typeOrder = { Manufacturer: 0, Distributor: 1, Retailer: 2 };
        const transformed = result.data
            
            .map(item => ({
                CompanyName: item.CompanyName,
                CompanyType: item.CompanyType || 'Unknown',
                HealthScore: Number(item.avgHealthScore) || 0
            }))
            .sort((a, b) => {
                const groupDiff = (typeOrder[a.CompanyType] ?? 99) - (typeOrder[b.CompanyType] ?? 99);
                if (groupDiff !== 0) return groupDiff;
                return (b.HealthScore || 0) - (a.HealthScore || 0);
            })
            .slice(0, 999);

        display.innerHTML = renderFinancialHealthChart(transformed);
        return;
    }

    // Otherwise use fallback demo
    const errorNote = result.error
        ? `<div class="trend-note error">${result.error}</div>`
        : '<div class="trend-note error">No financial data returned.</div>';

    display.innerHTML = errorNote;
}

// AVG BAR CHART FOR FINANCIAL HEALTH
function renderFinancialHealthChart(data) {
    const baseWidth = 500;
    const width = Math.max(baseWidth, data.length * 60);
    const height = 380;
    const topPadding = 50;
    const bottomPadding = 50;
    const padding = 50;
    const barGap = 20;

    const typeColors = {
        Manufacturer: { fill: 'rgba(191,219,254,0.9)', stroke: '#93c5fd' },
        Distributor: { fill: 'rgba(96,165,250,0.75)', stroke: '#3b82f6' },
        Retailer: { fill: 'rgba(37,99,235,0.85)', stroke: '#1d4ed8' },
        Default: { fill: 'rgba(96,165,250,0.6)', stroke: '#3b82f6' }
    };
    
    const maxScore = Math.max.apply(null, data.map(d => d.HealthScore).concat([1]));
    const barWidth = (width - padding * 2) / data.length - barGap;
    const chartHeight = height - topPadding - bottomPadding;
    const scale = chartHeight / maxScore;

const bars = data.map((item, idx) => {
    const x = padding + idx * (barWidth + barGap);
    const barHeight = item.HealthScore * scale;
    const y = height - bottomPadding - barHeight;
    const colors = typeColors[item.CompanyType] || typeColors.Default;

    return `
        <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}"
            fill="${colors.fill}" stroke="${colors.stroke}" rx="4"></rect>

        <!-- Health score above the bar -->
        <text x="${x + barWidth / 2}" y="${y - 8}"
            text-anchor="middle" fill="#e5e7eb" font-size="11">
            ${item.HealthScore}
        </text>

        <!-- Vertical company name inside the bar -->
        <text
            transform="translate(${x + barWidth / 2}, ${y + barHeight / 2}) rotate(-90)"
            text-anchor="middle"
            fill="white"
            font-size="11"
            style="pointer-events:none;">
            ${item.CompanyName}
        </text>
    `;
}).join('');

    const avg = Math.round(data.reduce((sum, d) => sum + d.HealthScore, 0) / data.length);

    const groups = data.reduce((acc, item, idx) => {
        const type = item.CompanyType || 'Default';
        if (!acc[type]) {
            acc[type] = { start: idx, count: 0 };
        }
        acc[type].count += 1;
        return acc;
    }, {});

    const groupOrder = ['Manufacturer', 'Distributor', 'Retailer'];
    const groupLabels = groupOrder
        .filter(type => groups[type])
        .map(type => {
            const { start, count } = groups[type];
            const firstCenter = padding + start * (barWidth + barGap) + barWidth / 2;
            const lastCenter = firstCenter + (count - 1) * (barWidth + barGap);
            const center = (firstCenter + lastCenter) / 2;
            return `<text x="${center}" y="${height - bottomPadding + 26}" text-anchor="middle" fill="#cbd5e1" font-size="12" font-weight="600">${type}(s)</text>`;
        }).join('');

    return `
        <div class="financial-summary">Average Financial Health Score: ${avg}</div> 

        <div style="overflow-x: auto; width: 100%;">
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" class="trend-chart">
                <!-- Axes -->
                <line x1="${padding}" y1="${height - bottomPadding}" x2="${width - padding}" y2="${height - bottomPadding}" stroke="#4b5563" />
                <line x1="${padding}" y1="${topPadding}" x2="${padding}" y2="${height - bottomPadding}" stroke="#4b5563" />

                ${bars}
                ${groupLabels}
            </svg>
        </div>
    `;

}

// Top distributors by volume functions
    async function topDistributorsByVolume() {
        const display = document.getElementById('top-distributors-display');
        if (!display) return;

    const searchValue = document.getElementById('distributor-search')?.value.trim() || "";

    display.innerHTML = '<div class="loading">Analyzing shipment volume by distributor...</div>';

    const result = await managerFetch('top_distributors_by_volume', {
        search: searchValue
    });

        if (result.success && Array.isArray(result.data)) {
            let html = '<div style="max-height: 360px; overflow-y: auto;">';

            if (result.data.length === 0) {
                html += '<p style="color: #9ca3af;">No distributors found by that name or ID.</p>';
            } else {
                result.data.slice(0, 10).forEach((distributor, index) => {
                    const volume = Number(distributor.totalShipped || 0);
                    const color = volume > 1000 ? '#22c55e' :
                                volume > 500  ? '#eab308' :
                                volume > 100  ? '#f97316' : '#ef4444';

                    html += '<div style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: rgba(31, 41, 55, 0.5); border-radius: 8px; border-left: 4px solid ' + color + ';">';
                    html += '<div style="flex: 1;">';
            		html += '<strong>' + (distributor.CompanyName || 'Unknown Company') + '<div style="font-style: italic; font-size: 0.85em;">' + '(ID ' + (distributor.CompanyID || '') + ')' + '</div>' + '</strong>';
                    html += '</div>';
                    html += '<div style="text-align: center; padding: 0 10px;">';
                    html += '<div style="font-size: 1.2em; font-weight: bold; color: ' + color + ';">';
                    html += volume;
                    html += '</div>';
                    html += '<div style="font-size: 0.75em; color: ' + color + ';">';
                    html += 'Shipments';
                    html += '</div></div>';
                    html += '</div>';
                });
            }

            if (result.data.length > 10) {
                html += `<p style="color:#9ca3af; margin-top:10px; font-size:0.85em; font-style: italic;">
                This only displays the top 10 distributors. To see more distributors, search for a specific distributor name or ID.</p>`; }

            html += '</div>';
            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading top distributors.</div>';
        }
    }

// nontrivial plot 1, maps distributor shipments via bar chart
    async function loadDistributorShipmentPlot() {
        const display = document.getElementById('nontrivial-plot-1');
        if (!display) return;

        display.innerHTML = '<div class="loading">Loading distributor shipment volumes...</div>';

        // Pull shipment totals directly from the SQL database via AJAX
        const result = await managerFetch('nontrivial_distributor_plot');

        if (!result.success || !Array.isArray(result.data)) {
            const message = result.error ? result.error : 'No distributor shipment data available.';
            display.innerHTML = `<div class="error-message">${message}</div>`;
            return;
        }

        const distributors = result.data.map((row, idx) => ({
            id: row.CompanyID || idx,
            name: row.CompanyName || `Distributor ${idx + 1}`,
            volume: Number(row.TotalShipments ?? row.totalShipped ?? 0) || 0
        }));

        if (distributors.length === 0) {
            display.innerHTML = '<p style="color:#9ca3af;">No distributor shipments found.</p>';
            return;
        }

        const sorted = distributors.sort((a, b) => b.volume - a.volume);
        const names = sorted.map(item => item.name);
        const volumes = sorted.map(item => item.volume);

        const chartId = 'nontrivial-plot-1-chart';
        const widthHint = Math.max((names.length * 28) + 360, display.clientWidth || 720);
        const heightHint = Math.max(320, names.length * 34);

        display.innerHTML = `<div class="trend-scroll"><div id="${chartId}"></div></div>`;

        Plotly.newPlot(chartId, [
            {
                type: 'bar',
                orientation: 'h',
                x: volumes,
                y: names,
                marker: {
                    color: '#60a5fa',
                    line: { color: '#1e3a8a', width: 1.2 }
                },
                text: volumes.map(v => v.toLocaleString()),
                textposition: 'outside',
                hovertemplate: '<b>%{y}</b><br>Shipments: %{x}<extra></extra>'
            }
        ], {
            title: { text: 'Distributor Shipment Volume', font: { color: '#e5e7eb', size: 16 } },
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)',
            height: heightHint,
            width: widthHint,
            margin: { t: 50, r: 30, b: 50, l: 80 },
            xaxis: {
                title: 'Total Shipments',
                tickfont: { color: '#e5e7eb' },
                titlefont: { color: '#e5e7eb' }
            },
            yaxis: {
                automargin: true,
                tickfont: { color: '#e5e7eb' }
            },
            bargap: 0.25
        });
    }

    // Company financials panel, overview & company search
    async function loadCompanyFinancialOverview() {
        const display = document.getElementById('company-financial-display');
        if (!display) return;

        display.innerHTML = '<div class="loading">Loading company financials by region...</div>';

        const result = await managerFetch('company_financials_overview');

        if (result.success && Array.isArray(result.data) && result.data.length > 0) {
            const regions = result.data.sort((a, b) => (a.ContinentName || '').localeCompare(b.ContinentName || ''));
            let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px;">';
                        regions.forEach(region => {
                const avg = Number(region.AvgHealthScore || 0);
                const color = avg >= 80 ? '#22c55e' : avg >= 60 ? '#eab308' : avg >= 40 ? '#f97316' : '#ef4444';

                html += `<div class="company-card" style="border-left: 4px solid ${color};">`;
                html += `<h3 style="margin-bottom:6px;">${region.ContinentName || 'Unknown Region'}</h3>`;
                html += `<div class="company-info-row"><span class="company-info-label">Avg Health:</span><span class="company-info-value" style="color:${color};">${avg.toFixed(2)}</span></div>`;
                html += `<div class="company-info-row"><span class="company-info-label">Companies:</span><span class="company-info-value">${region.CompanyCount || 0}</span></div>`;
                html += '</div>';
            });

            html += '</div>';
            display.innerHTML = html;
        } else {
            const msg = result.error ? result.error : 'No regional financial data available.';
            display.innerHTML = `<div style="color: #ef4444;">${msg}</div>`;
        }
    }

    async function searchCompanyFinancials() {
        const display = document.getElementById('company-financial-display');
        const input = document.getElementById('company-financial-search');
        if (!display || !input) return;

        const query = input.value.trim();

        if (query === '') {
            loadCompanyFinancialOverview();
            return;
        }

        display.innerHTML = `<div class="loading">Searching financials for "${query}"...</div>`;

        const result = await managerFetch('company_financial_details', { query });

        if (result.success && Array.isArray(result.data) && result.data.length > 0) {
            renderCompanyFinancialDetails(display, result.data);
        } else {
            const msg = result.error ? result.error : 'No financial reports found for that company.';
            display.innerHTML = `<div style="color:#9ca3af;">${msg}</div>`;
        }
    }

// User search for financial details function
function renderCompanyFinancialDetails(display, rows) {
        const meta = rows[0];
        const healthScores = rows.map(r => Number(r.HealthScore || 0)).filter(n => !Number.isNaN(n));
        const avgHealth = healthScores.length ? (healthScores.reduce((a, b) => a + b, 0) / healthScores.length) : 0;

        let html = '<div class=\"company-card\">';
        html += `<h3>${meta.CompanyName || 'Unknown Company'} <span style="font-size:0.85em; color:#9ca3af;">(ID ${meta.CompanyID || 'N/A'})</span></h3>`;
        html += `<div class="company-info-row"><span class="company-info-label">Type:</span><span class="company-info-value">${meta.CompanyType || 'Unknown'}</span></div>`;
        html += `<div class="company-info-row"><span class="company-info-label">Region:</span><span class="company-info-value">${meta.ContinentName || 'Unknown'} (${meta.CountryName || 'N/A'})</span></div>`;
        html += `<div class="company-info-row"><span class="company-info-label">Average Health:</span><span class="company-info-value">${avgHealth.toFixed(2)}</span></div>`;
        html += '</div>';

        html += '<div style=\"max-height: 320px; overflow-y: auto; margin-top: 10px;\">';
        html += '<table style=\"width:100%; border-collapse: collapse; font-size: 0.9rem;\">';
        html += '<thead><tr style=\"text-align:left; color:#9ca3af;\"><th style=\"padding:6px;\">Year</th><th style=\"padding:6px;\">Quarter</th><th style=\"padding:6px;\">Health Score</th></tr></thead><tbody>';

        rows.sort((a, b) => (Number(b.RepYear) - Number(a.RepYear)) || (Number(b.Quarter) - Number(a.Quarter)));
        rows.forEach(row => {
            const score = Number(row.HealthScore || 0);
            const color = score >= 80 ? '#22c55e' : score >= 60 ? '#eab308' : score >= 40 ? '#f97316' : '#ef4444';
            html += `<tr style=\"border-bottom:1px solid #374151;\">
                        <td style=\"padding:6px; color:#e5e7eb;\">${row.RepYear || '—'}</td>
                        <td style=\"padding:6px; color:#e5e7eb;\">${row.Quarter || '—'}</td>
                        <td style=\"padding:6px; color:${color}; font-weight:600;\">${score.toFixed(2)}</td>
                    </tr>`;
        });

        html += '</tbody></table></div>';
        display.innerHTML = html;
    }

    window.addEventListener('DOMContentLoaded', () => {
        loadCompanyFinancialOverview();
        const input = document.getElementById('company-financial-search');
        if (input) {
            input.addEventListener('keypress', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    searchCompanyFinancials();
                }
            });
        }
    });

// Add new company function
async function addNewCompany() {
    const name   = document.getElementById('new-company-name').value.trim();
    const type   = document.getElementById('new-company-type').value;
    const region = document.getElementById('new-company-region').value;
    const tier   = document.getElementById('new-company-tier').value;

    const feedback = document.getElementById('add-company-feedback');
    feedback.innerHTML = "Creating company...";

    const result = await managerFetch('add_new_company', {
        companyName: name,
        companyType: type,
        locationID: region,
        tierLevel: tier
    });

    if (result.success) {
        feedback.style.color = "#4ade80";
        feedback.innerHTML = "Successfully created company with ID " + result.newCompanyID;

        // Refresh overview
        getNetworkOverview();

    } else {
        feedback.style.color = "#ef4444";
        feedback.innerHTML = "Error: " + result.error;
    }
}

// Displays regional disruptions
    async function regionalDisruptions() {
        const display = document.getElementById('regional-disruptions-display');
        if (!display) return;

        display.innerHTML = '<div class="loading">Loading regional disruption overview...</div>';

        const result = await managerFetch('regional_disruptions', {
            startDate: '2020-01-01',
            endDate: '2025-12-31'
        });
        
        if (!result.success || !Array.isArray(result.data)) {
            display.innerHTML = '<div style="color: #ef4444;">Error loading regional data</div>';
            return;
        }

        const regions = result.data
            .map(region => ({
                name: region.ContinentName || 'Unknown',
                total: Number(region.TotalDisruptions || 0),
                high: Number(region.HighImpactDisruptions || 0)
            }))
            .filter(region => region.total > 0 || region.high > 0)
            .sort((a, b) => b.total - a.total);

        if (regions.length === 0) {
            display.innerHTML = '<p style="color: #9ca3af;">No regional disruption activity for the selected window.</p>';
            return;
        }

        const chartId = 'regional-disruptions-chart';
        const labels = regions.map(region => region.name);
        const highImpact = regions.map(region => region.high);
        const remaining = regions.map(region => Math.max(region.total - region.high, 0));

        display.innerHTML = `
            <div id="${chartId}" class="charts-123"></div>
        `;

        Plotly.newPlot(chartId, [
            {
                type: 'bar',
                name: 'High impact disruptions',
                x: labels,
                y: highImpact,
                marker: { color: '#c084fc' },
                hovertemplate: '<b>%{x}</b><br>High impact: %{y}<extra></extra>'
            },
            {
                type: 'bar',
                name: 'Other disruptions',
                x: labels,
                y: remaining,
                marker: { color: '#38bdf8' },
                hovertemplate: '<b>%{x}</b><br>Other: %{y}<extra></extra>'
            }
        ], {
            title: { text: 'Disruptions by Region', font: { size: 16, color: '#e5e7eb' } },
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)',
            barmode: 'stack',
            margin: { t: 40, r: 20, b: 30, l: 55 },
            xaxis: { title: 'Region', tickfont: { color: '#e5e7eb' }, titlefont: { color: '#e5e7eb' } },
            yaxis: { title: '# of Disruptions', tickfont: { color: '#e5e7eb' }, titlefont: { color: '#e5e7eb' }, rangemode: 'tozero' },
            legend: { orientation: 'h', y: -0.35, font: { color: '#e5e7eb' } }
        });
    }
        
// Displays disruption frequency over user defined time
    async function disruptionFrequency() {
        const display = document.getElementById('disruption-trend-display');
        if (!display) return;

        const startDate = document.getElementById('disruption-start-date');
        const endDate = document.getElementById('disruption-end-date');
        const companyFilter = document.getElementById('disruption-company-filter');

        display.innerHTML = '<div class="loading">Plotting disruption frequency...</div>';

        const startDateValue = startDate ? startDate.value : '2019-01-01';
        const endDateValue = endDate ? endDate.value : '2025-12-31';
        const companyQuery = companyFilter ? companyFilter.value.trim() : '';

        const result = await managerFetch('disruption_frequency', {
            startDate: startDateValue,
            endDate: endDateValue,
            companyQuery
        });

        if (result.success && Array.isArray(result.data) && result.data.length > 0) {
            const normalized = result.data
                .map(row => ({
                    period: row.EventDate,
                    total: Number(row.TotalDisruptions || 0)
                }))
                .filter(row => row.period)
                .sort((a, b) => new Date(a.period) - new Date(b.period));

            if (normalized.length > 0) {
                const widthHint = Math.max(display.clientWidth || 0, normalized.length * 70);
                display.innerHTML = renderDisruptionTrendChart(normalized, {
                    width: widthHint,
                    height: 320,
                    companyQuery
                });
                return;
            }
        }

        // error message
        const message = result.success
            ? `No disruptions returned for this range${companyQuery ? ` for "${companyQuery}"` : ''}. Adjust your dates or try a different company.`
            : 'Unable to reach the database right now. Please try again later.';

        display.innerHTML = `<div class="trend-note error">${message}</div>`;
    }

// Disruption line plot
function renderDisruptionTrendChart(data, options = {}) {
        const containerWidth = Math.max(options.width || 620, 520);
        const width = containerWidth;
        const height = options.height || 320;
        const padding = 55;
        const maxCount = Math.max.apply(null, data.map(item => item.total || 0).concat([1]));

        const xStep = (width - padding * 2) / Math.max(data.length - 1, 1);
        const yScale = (height - padding * 2) / maxCount;

        const points = data.map((item, idx) => {
            const x = padding + idx * xStep;
            const y = height - padding - ((item.total || 0) * yScale);
            return { x, y, label: item.period };
        });

        const linePath = points.reduce((path, pt, idx) => {
            const command = idx === 0 ? 'M' : 'L';
            return path + `${command}${pt.x},${pt.y} `;
        }, '');

        const areaPath = points.length > 1
            ? `${linePath}L${points[points.length - 1].x},${height - padding} L${points[0].x},${height - padding} Z`
            : `${linePath}L${points[0].x},${height - padding} Z`;

        const totalEvents = data.reduce((sum, row) => sum + (row.total || 0), 0);

        const axisTicks = data.map((item, idx) => {
            const dateLabel = item.period ? new Date(item.period).toLocaleDateString('en-US', { month: 'short', year: '2-digit' }) : '';
            const x = padding + idx * xStep;
            return `<g>
                        <line x1="${x}" y1="${height - padding}" x2="${x}" y2="${height - padding + 6}" stroke="#4b5563" />
                        <text x="${x}" y="${height - padding + 22}" text-anchor="middle" fill="#9ca3af" font-size="10">${dateLabel}</text>
                    </g>`;
        }).join('');

        const companyNote = options.companyQuery
            ? `<div class="trend-chip muted">Company Filter: ${options.companyQuery}</div>`
            : '';

        return `
            <div class="trend-summary">
                <div class="trend-chip total">
                    <div class="trend-value">${totalEvents}</div>
                    <div class="trend-label">Avg Disruptions</div>
                </div>
                ${companyNote}
            </div>
            <div class="trend-scroll">
                <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" class="trend-chart">
                    <g>
                        <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" stroke="#4b5563" />
                        <line x1="${padding}" y1="${padding}" x2="${padding}" y2="${height - padding}" stroke="#4b5563" />
                        ${axisTicks}
                    </g>
                    <path d="${areaPath}" fill="rgba(59,130,246,0.15)" stroke="none"></path>
                    <path d="${linePath}" fill="none" stroke="#60a5fa" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                    ${points.map(pt => `<circle cx="${pt.x}" cy="${pt.y}" r="4" fill="#8b5cf6" stroke="#c084fc" stroke-width="1.5"></circle>`).join('')}
                    ${points.map(pt => `<text x="${pt.x}" y="${pt.y - 10}" text-anchor="middle" fill="#e5e7eb" font-size="10">${Math.round((height - padding - pt.y) / yScale)}↑</text>`).join('')}
                </svg>
            </div>
        `;
    }

// nontrivial plot #2, criticality by region
async function loadRegionalCriticality() {
    const display = document.getElementById('regional-criticality-display');
    if (!display) return;

    display.innerHTML = '<div class="loading">Calculating regional criticality...</div>';

    const result = await managerFetch('nontrivial_criticality_plot');

    if (result.success && Array.isArray(result.data)) {
        const regions = result.data
            .map(item => ({
                region: item.Region || 'Unknown',
                score: Number(item.CriticalityScore || 0)
            }))
            .filter(item => item.score > 0)
            .sort((a, b) => b.score - a.score);

        if (regions.length === 0) {
            display.innerHTML = '<p style="color:#9ca3af;">No regional criticality data available.</p>';
            return;
        }

        const chartId = 'regional-criticality-chart';
        const width = Math.max(340, regions.length * 110);

        display.innerHTML = `
            <div class="trend-scroll">
                <div id="${chartId}" style="min-width:${width}px; height:340px;"></div>
            </div>
        `;

        const maxY = Math.max(...regions.map(r => r.score)) + 100;

        Plotly.newPlot(chartId, [
            {
                type: 'bar',
                x: regions.map(r => r.region),
                y: regions.map(r => r.score),
                marker: {
                    color: regions.map((_, idx) => idx === 0 ? '#f87171' : '#60a5fa'),
                    line: { color: '#1f2937', width: 1 }
                },
                text: regions.map(r => Math.round(r.score)),
                textposition: 'outside',
                hovertemplate: '<b>%{x}</b><br>Criticality: %{y}<extra></extra>'
            }
        ], {
            title: { text: 'Regional Vulnerability by Criticality', font: { size: 16, color: '#e5e7eb' } },
            paper_bgcolor: 'rgba(0,0,0,0)',
            plot_bgcolor: 'rgba(0,0,0,0)',
            margin: { t: 30, r: 20, b: 70, l: 60 },
            xaxis: { title: 'Region', tickfont: { color: '#e5e7eb' }, titlefont: { color: '#e5e7eb' } },
            yaxis: { title: 'Criticality Score', tickfont: { color: '#e5e7eb' }, titlefont: { color: '#e5e7eb' }, rangemode: 'tozero', range: [0, maxY] },
            height: 340
        });
        return;
    }

    const errorMsg = result && result.error ? result.error : 'Unable to load regional criticality.';
    display.innerHTML = `<div class="trend-note error">${errorMsg}</div>`;
}

// Most critical companies function
async function mostCriticalCompanies() {
    const display = document.getElementById('critical-companies-display');
    if (!display) return;

    display.innerHTML = '<div class="loading">Analyzing network criticality...</div>';

    const searchValue = document.getElementById('critical-companies-search')?.value.trim() || "";
    const result = await managerFetch('most_critical_companies', { search: searchValue });


    if (result.success && Array.isArray(result.data)) {
        let html = '<div style="max-height: 300px; overflow-y: auto;">';

        if (result.data.length === 0) {
            html += '<p style="color: #9ca3af;">No critical companies identified</p>';
        } else {
            result.data.slice(0, 25).forEach((company, index) => {

                const downstream = Number(company.DownstreamCount || 0);
                const highImpact = Number(company.HighImpactCount || 0);

                // Criticality Formula
                const criticalityScore = downstream * highImpact;

                // Color scheme based on score
                const color =
                    criticalityScore > 20 ? '#ef4444' :     // red (severe)
                    criticalityScore > 10 ? '#f97316' :     // orange (high)
                    criticalityScore > 5  ? '#eab308' :     // yellow (medium)
                                            '#22c55e';      // green (low)

                html += `
                    <div style="
                        display: flex;
                        align-items: center;
                        padding: 10px;
                        margin-bottom: 8px;
                        background: rgba(31, 41, 55, 0.5);
                        border-radius: 8px;
                        border-left: 4px solid ${color};
                    ">

                        <div style="flex: 1;">
                            <strong>${company.CompanyName || 'Unknown'}</strong>
                            <div style="font-size: 0.85em; color: #9ca3af; margin-top: 3px;">
                                ${company.CompanyType || 'Unknown'} <div style="font-style: italic;">(ID ${company.CompanyID})</div>
                            </div>
                        </div>

                        <div style="text-align: center; padding: 0 10px;">
                            <div style="font-size: 1.2em; font-weight: bold; color: ${color};">
                                ${criticalityScore}
                            </div>
                            <div style="font-size: 0.75em; color: ${color};">
                                Criticality Score
                            </div>
                        </div>

                    </div>
                `;
            });
        }

        if (result.data.length > 25) {
                html += `<p style="color:#9ca3af; margin-top:10px; font-size:0.85em; font-style: italic;">
                This only displays the most critical 25 companies. To see more companies, search for a specific company name or ID.</p>`; }
        html += '</div>';
        display.innerHTML = html;

    } else {
        display.innerHTML = '<div style="color: #ef4444;">Error loading critical companies</div>';
    }
}

// Network overview function
    async function getNetworkOverview() {
        const display = document.getElementById('network-overview-display');
        if (!display) return;
        
        display.innerHTML = '<div class="loading">Loading network statistics...</div>';
        
        const result = await managerFetch('get_network_overview');
        
        if (result.success && result.data) {
            let html = '<div style="display: grid; grid-template-columns: none; grid-auto-flow: column; grid-auto-columns: max-content; gap: 15px;">';
            
            // Total companies
            html += '<div style="text-align: center; padding: 15px; display: flex; flex-direction: column; justify-content: center; background: rgba(59, 130, 246, 0.1); border-radius: 8px; border: 1px solid rgba(59, 130, 246, 0.3); width: 320px; min-height: 120px;">';
            html += '<div style="font-size: 2em; color: #60a5fa; font-weight: bold;">' + (result.data.totalCompanies || 0) + '</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Total Companies</div>';
            if (result.data.companyTypes) { // shows type
                html += '<div style="margin-top: 20px;">';
                html += '<div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; width: 100%;">';
                
                for (let type in result.data.companyTypes) {
                    html += '<div style="text-align: center; padding: 10px; background: rgba(31, 41, 55, 0.5); border-radius: 8px;">';
                    html += '<div style="font-size: 1.5em; color: #60a5fa; font-weight: bold;">' + result.data.companyTypes[type] + '</div>';
                    html += '<div style="font-size: 0.85em; color: #9ca3af;">' + type + '</div>';
                    html += '</div>';
                }
                
                html += '</div></div>';
            }
            html += '</div>';
            
            // Overall health
            html += '<div style="text-align: center; padding: 15px; display: flex; flex-direction: column; justify-content: center; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px solid rgba(245, 158, 11, 0.3); width: 120px; min-height: 120px;">';
            html += '<div style="font-size: 2em; color: #f59e0b; font-weight: bold;">' + (result.data.avgNetworkHealth || 0) + '%</div>';
            html += '<div style="color: #9ca3af; font-size: 0.85em;">Overall Health</div>';
            html += '</div>';
            
            // Add new company
            html += '<div style="align-items: stretch; text-align: center; display: flex; flex-direction: column; justify-content: center; padding: 15px; background: rgba(0, 255, 21, 0.19); border-radius: 8px; border: 1px solid rgba(9, 255, 0, 0.3); width: 120px; min-height: 120px;">';
            html += '<button onclick="addNewCompanyPanel()" style="margin-top: 10px; width: 100%; height: 100%; padding: 5px 10px; font-size: 5em; border: none; border-radius: 6px; background: linear-gradient(135deg,#38bdf8,#6366f1); color: #134e22ff; cursor: pointer;" class="btn-load">+</button>';
            html += '<div style="color: #ffffffff; font-weight: bold; font-size: 0.75em; padding: 5px;">New Company</div>';
            html += '</div>';

            // Appears once new company button is clicked
            html += '<div id="add-company-form" style="display:none; margin-top:10px; max-width:320px; background:rgba(31,41,55,0.5); border:1px solid #4b5563; border-radius:8px; padding:12px;">';
            html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
            html += '<div style="font-size:0.95em; font-weight:bold; color:#e5e7eb;">Add Company</div>';
            html += '</div>';
            html += '<div style="display:flex; flex-direction:column; gap:8px;">';
            html += '<input type="text" id="new-company-name" placeholder="Company Name" style="padding:6px; border-radius:5px; border:1px solid #4b5563; background:#111827; color:#f9fafb; font-size:0.85rem;" />';
            html += '<select id="new-company-type" '
                + 'style="padding:6px; border-radius:5px; border:1px solid #4b5563; background:#111827; color:#f9fafb; font-size:0.85rem;">'
                + '<option value="Manufacturer">Manufacturer</option>'
                + '<option value="Distributor">Distributor</option>'
                + '<option value="Retailer">Retailer</option></select>';
            html += '<input type="number" id="new-company-region" min="1" max="13" placeholder="Location ID (Region 1-13)" '
                + 'style="padding:6px; border-radius:5px; border:1px solid #4b5563; background:#111827; color:#f9fafb; font-size:0.85rem;" />';
            html += '<input type="number" id="new-company-tier" min="1" max="3" placeholder="Tier (1, 2, or 3)" '
                + 'style="padding:6px; border-radius:5px; border:1px solid #4b5563; background:#111827; color:#f9fafb; font-size:0.85rem;" />';
            html += '<div style="display:flex; justify-content:space-between; gap:10px; margin-top:4px;">';
            html += '<button onclick="addNewCompany()" '
                + 'style="flex:1; padding:6px; font-size:0.85rem; border:none; border-radius:6px; background:linear-gradient(135deg,#38bdf8,#6366f1); color:#0b1020; cursor:pointer;">'
                + 'Create</button>';
            html += '<button onclick="addNewCompanyPanel()" '
                + 'style="flex:1; padding:6px; font-size:0.85rem; border:none; border-radius:6px; background:#4b5563; color:#e5e7eb; cursor:pointer;">'
                + 'Cancel</button>';
            html += '</div>';
            html += '</div>';
            html += '<div id="add-company-feedback" style="margin-top:8px; font-size:0.8rem;"></div>';
            html += '</div>';



            display.innerHTML = html;
        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading network overview: ' + (result.error || 'Unknown error') + '</div>';
        }
    }

// Visibility toggle function
function addNewCompanyPanel() {
    const form = document.getElementById('add-company-form');
    if (!form) return;
    form.style.display = (form.style.display === "none") ? "block" : "none";
}

// Disruptions by company display 
async function allDisruptionsForCompany() {
    
    const display = document.getElementById('company-disruptions-display');
    if (!display) return;

    const searchValue = document.getElementById('company-disruption-search')?.value.trim() || "";

    if (!searchValue) {
        display.innerHTML = '<p style="color: #9ca3af;">Enter a company name or ID to search.</p>';
        return;
    }

    display.innerHTML = '<div class="loading">Retrieving disruptions affecting this company...</div>';
    const result = await managerFetch('disruptions_by_company', {
        companyQuery: searchValue
    });

    if (result.success && Array.isArray(result.data)) {
        let html = '<div style="max-height: 360px; overflow-y: auto;">';

            if (result.data.length === 0) {
                html += '<p style="color: #9ca3af;">No disruptions found for this company</p>';

            } else {
                result.data.forEach((disruption) => {
                    const impact = (disruption.ImpactLevel || '').toLowerCase();
                    const color = impact === 'high' ? '#ef4444' :
                                impact === 'medium' ? '#f97316' :
                                impact === 'low' ? '#22c55e' : '#8b5cf6';
                    html += '<div style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: rgba(31, 41, 55, 0.5); border-radius: 8px; border-left: 4px solid ' + color + ';">';
                    html += '<div style="flex: 1;">';
                    html += '<strong>' + (disruption.CompanyName || 'Unknown Company') + '</strong>';
                    html += '<div style="font-size: 0.85em; color: #9ca3af;">' + (disruption.CategoryName || 'Unknown Category') + '</div>';
                    html += '<div style="font-size: 0.8em; color: #9ca3af;">Event Date: ' + (disruption.EventDate || 'N/A') + '</div>';
                    html += '<div style="font-size: 0.8em; color: #9ca3af;">Recovery Date: ' + (disruption.EventRecoveryDate || 'N/A') + '</div>';
                    html += '</div>';
                    html += '<div style="text-align: center; padding: 0 10px;">';
                    html += '<div style="font-size: 1.1em; font-weight: bold; color: ' + color + '; text-transform: capitalize;">';
                    html += (disruption.ImpactLevel || 'Unknown');
                    html += '</div>';
                    html += '<div style="font-size: 0.75em; color: ' + color + ';">Impact</div>';
                    html += '</div>';
                    html += '</div>';
                });
            }

        html += '</div>';
        display.innerHTML = html;

        } else {
            display.innerHTML = '<div style="color: #ef4444;">Error loading disruptions.</div>';
        }
    }


// Companies affected by disruption event display
async function companiesAffectedByEvent() {
    const display = document.getElementById('event-companies-display');
    if (!display) return;

    const query = document.getElementById('event-company-search')?.value.trim() || "";

    if (!query) {
        display.innerHTML = '<p style="color: #9ca3af;">Enter an event name or event ID to search.</p>';
        return;
    }

    const isEventId = /^\d+$/.test(query);
    const eventID = isEventId ? parseInt(query, 10) : 0;

    display.innerHTML = '<div class="loading">Loading companies affected by this event...</div>';

    const result = await managerFetch('companies_by_event', {
        eventID: isNaN(eventID) ? 0 : eventID,
        eventName: isEventId ? '' : query
    });

    if (result.success && Array.isArray(result.data)) {
        if (result.data.length === 0) {
            display.innerHTML = '<p style="color: #9ca3af;">No companies found for this event. Try a different name or ID.</p>';
            return;
        }

        let html = '<div style="max-height: 360px; overflow-y: auto;">';

        result.data.forEach((company) => {
            const impact = (company.ImpactLevel || '').toLowerCase();
            const color = impact === 'high' ? '#ef4444' :
                        impact === 'medium' ? '#f97316' :
                        impact === 'low' ? '#22c55e' : '#8b5cf6';

            html += '<div style="display:flex; align-items:center; padding:10px; margin-bottom:8px; background: rgba(31, 41, 55, 0.5); border-radius: 8px; border-left: 4px solid ' + color + ';">';
            html += '<div style="flex:1;">';
            html += '<strong>' + (company.CompanyName || 'Unknown Company') + '<div style="font-style: italic; font-size: 0.85em;">' + ' (ID ' + (company.CompanyID || '') + ')</div>' + '</strong>';
            html += '<div style="font-size: 0.85em; color: #9ca3af;">Event ID: ' + (company.EventID || 'N/A') + '</div>';
            html += '<div style="font-size: 0.8em; color: #9ca3af;">Category: ' + (company.CategoryName || 'Unknown Category') + '</div>';
            html += '</div>';
            html += '<div style="text-align: center; padding: 0 10px;">';
            html += '<div style="font-size: 1.1em; font-weight: bold; color: ' + color + '; text-transform: capitalize;">';
            html += (company.ImpactLevel || 'Unknown');
            html += '</div>';
            html += '<div style="font-size: 0.75em; color: ' + color + ';">Impact</div>';
            html += '</div>';
            html += '</div>';
        });

        html += '</div>';
        display.innerHTML = html;
    } else {
        display.innerHTML = '<div style="color: #ef4444;">Error loading companies affected by this event.</div>';
    }
}

// Distributor average delay display
async function loadDistributorDelays() {
    const display = document.getElementById('distributor-delay-display');
    if (!display) return;

    const search = document.getElementById('distributor-delay-search')?.value.trim() || "";
    display.innerHTML = '<div class="loading">Loading distributor delays...</div>';

    const result = await managerFetch('distributors_by_delay', { search });

    if (!result.success || !Array.isArray(result.data)) {
        display.innerHTML = '<div style="color: #ef4444;">Unable to load distributor delays.</div>';
        return;
    }

    let rows = [...result.data];
    const isNumericSearch = /^\d+$/.test(search);
    if (search) {
        const searchLower = search.toLowerCase();
        rows = rows.filter(row => {
            if (isNumericSearch) {
                return String(row.CompanyID) === search;
            }
            return (row.CompanyName || '').toLowerCase().includes(searchLower);
        });
    }

    if (rows.length === 0) {
        display.innerHTML = '<p style="color:#9ca3af;">No distributors found by that name or ID.</p>';
        return;
    }

    rows.sort((a, b) => (Number(b.AvgDelayDays) || 0) - (Number(a.AvgDelayDays) || 0));

    let html = '<div style="max-height: 360px; overflow-y: auto;">';
    rows.forEach((row, idx) => {
        const delay = Number(row.AvgDelayDays);
        const delayVal = Number.isFinite(delay) ? delay.toFixed(2) : 'N/A';
        const color = Number.isFinite(delay)
            ? (delay > 5 ? '#ef4444' : delay > 2 ? '#f97316' : '#22c55e')
            : '#8b5cf6';

        html += '<div style="display: flex; align-items: center; padding: 10px; margin-bottom: 8px; background: rgba(31, 41, 55, 0.5); border-radius: 8px; border-left: 4px solid ' + color + ';">';
        html += '<div style="flex: 1;">';
        html += '<strong>' + (row.CompanyName || 'Unknown Distributor') + '</strong>';
        html += '<strong>' + '<div style="font-size: 0.85em; font-style: italic;">(ID ' + (row.CompanyID || 'N/A') + ')'+ '</div>' + '</strong>';
        html += '</div>';
        html += '<div style="text-align: center; padding: 0 10px;">';
        html += '<div style="font-size: 1.2em; font-weight: bold; color: ' + color + ';">' + delayVal + ' Days' + '</div>';
        html += '<div style="font-size: 0.75em; color: ' + color + ';">Avg Delay</div>';
        html += '</div>';
        html += '</div>';
    });
    html += '</div>';

    display.innerHTML = html;
}

window.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('distributor-delay-display')) {
        setTimeout(loadDistributorDelays, 500);
    }
    if (document.getElementById('nontrivial-plot-1')) {
        setTimeout(loadDistributorShipmentPlot, 600);
    }
    if (document.getElementById('alerts-container')) {
        setTimeout(alertsOngoingDisruptions, 400);
    }
});

</script>



<!-- SCM CODE -->
<?php elseif ($page === 'scm' && $role === 'scm'): ?>

    <!-- SUPPLY CHAIN MANAGER DASHBOARD (supply/123) -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Supply Chain Manager Dashboard</h1>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=company_search" class="nav-link">Company Info Search</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
            <div class="panel-wide" style="padding: 10px; background: #1f2937; color: #ffffff; border-radius: 8px; max-width: 100%; margin: 0 0 20px;">
                <h2 style="margin-bottom: 10px;">Alerts</h2>
                <button onclick="alertsOngoingDisruptions()" class="btn" style="margin-bottom: 10px;
                 padding: 8px 16px; background-color: #3b82f6; color: #fff; border: none; border-radius: 6px; cursor: pointer">Refresh Alerts</button>
                <div id="alerts-container" class="alerts-container" style="
            background: rgba(255,255,255,0.05);
            padding: 10px;
            border-radius: 6px;
            min-height: 80px;
    "></div></div>
    <!-- Disruption Metrics panel -->
        <div class="panel-wide">
            <h2>Disruption Metrics</h2>
            <p>HDR, ART, TD, DSD</p>

            <div id="charts-disruptions" style="padding: 20px;">
                <div class="disruptions-123">
                    <div class="charts-123"><div id="chart-1"></div></div>
                    <div class="charts-123"><div id="art-histogram"></div></div>
                    <div class="charts-123"><div id="td-histogram"></div></div>
                    <div class="charts-123">
                        <div style="width: 100%; display: flex; justify-content: center;">
                            <canvas id="severityChart" width="200" height="300"></canvas>
                        </div>
                    </div>
                 </div>

                <div class="disruptions-4">
                    <h3 style="margin: 0 0 5px 0; font-size: 1.1rem;">Disruption Frequency</h3>
                    <div id="disruption-frequency-display"></div>
                </div>
            </div>
        </div>
        </section>
    </div>
</div>

<?php elseif ($page === 'company_search' && $role === 'scm'): ?>

    <!-- COMPANY SEARCH PAGE FOR SUPPLY CHAIN MANAGER -->
    <div class="card wide">
        <header class="card-header horizontal">
            <div>
                <h1 class="app-title">Company Info Search</h1>
                <p class="app-subtitle">Search for specific company details</p>
            </div>
            <nav class="top-nav">
                <a href="index.php?page=scm" class="nav-link">Return</a>
                <a href="index.php?page=logout" class="nav-link logout">Logout</a>
            </nav>
        </header>

        <section class="grid">
            <div class="panel-wide">
                <h2>Company Info</h2>
                <div class="company-search" style="background: transparent; padding: 10px 0;">
                    <div class="company-search-box">
                        <input type="text" id="company-search" placeholder="Enter company name or ID">
                        <button onclick="searchCompany()" class="btn-company-search">Search</button>
                    </div>
                    <div class="date-range-controls" style="margin-top: 10px;">
                        <label for="company-start-date">From:</label>
                        <input type="date" id="company-start-date" value="2020-01-01">
                        <label for="company-end-date">To:</label>
                        <input type="date" id="company-end-date" value="2025-12-31">
                        <span style="color: #9ca3af; font-size: 0.85rem;">Transaction history date specification<span>
                    </div>
                    <div id="company-results"></div>
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
<script>
// High Impact Disruption Rate: Pie Chart use function: disruptionEventsDisplay and variable HDR

async function loadHighImpactDisruptions() {
    const display = document.getElementById('chart-1');
    if (!display) return;

    display.innerHTML = '<div class="loading">Loading charts...</div>';

    const result = await supplyManagerFetch('disruptionEventsDisplay', {
        startDate: '2020-01-01',
        endDate: '2025-12-31'
    });

    if (!result.success || !result.data || !Array.isArray(result.data.SeverityDistribution)) {
        display.innerHTML = `<p style="color:#f87171;">Error loading disruption data</p>`;
        console.error("HDR error:", result);
        return;
    }

    const severity = result.data.SeverityDistribution;

    let high = 0;
    let other = 0;

    severity.forEach(item => {
        if (item.ImpactLevel === 'High') {
            high += Number(item.Count) || 0;
        } else {
            other += Number(item.Count) || 0;
        }
    });

    const HDRChart = [
        { label: 'High Impact Disruptions', value: high },
        { label: 'Other Disruptions', value: other }
    ];

    display.innerHTML = '';
    display.appendChild(renderHighImpactDisruptionsChart(HDRChart));
}

// Pie Chart for High Impact Disruptions 
function renderHighImpactDisruptionsChart(HDRChart) {
// config 
const labels = HDRChart.map(item => item.label);
const values = HDRChart.map(item => item.value);
const config = {
  type: 'pie',
  data: {
    labels: labels,
    datasets: [{
      label: 'High Impact Disruptions',
      data: values,
      backgroundColor: [
       '#FF0000' , // red for high impact
         '#0000FF' // BLUE for other
      ]
    }]
  },
  options: {
    responsive: false,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top'
      },
      title: {
        display: true,
        text: 'High Impact vs Other Disruptions'
      
      }
    }
  }
  
};
const canvas = document.createElement('canvas');
    canvas.width = 250; 
    canvas.height = 250;
    new Chart(canvas, config);
    return canvas; 
   
}
document.addEventListener('DOMContentLoaded', () => {
    loadHighImpactDisruptions();
    loadAverageRecoveryTimes();
    disruptionFrequencyByCompany();
    loadSeverityStackedBar();
    topDistributorsByVolume();
    mostCriticalCompanies();
    regionalDisruptions();
    loadRegionalCriticality();
});
</script>

<script>
// Average Recovery Time supply chain Manager
async function loadAverageRecoveryTimes() {
    const display = document.getElementById('art-histogram');
    if (!display) return;

// Call Backend

const result = await supplyManagerFetch('disruptionEventsDisplay' , {
    start_date: '2020-01-01' ,
    end_date: '2025-12-31'
});

let artValues =[];
// If valid data returned 
if (result.success && result.data && Array.isArray(result.data.ART)) {
    artValues = result.data.ART
        .map(item => item.ART)
        .filter(val => typeof val === "number" && !isNaN(val)); // cleans ART data to keep valid numbers
        artValues.sort((a, b) => a - b);
        console.log("ART values for histogram:", artValues); // to verify ART data is correct

}

// Histogram Code for ART
async function renderAverageRecoveryTimeHistogram(artValues) {

var trace = {
    x: artValues ,
    type: 'histogram',
    marker : {
            color: '#0000FF'

    }
  };
var layout = {
    title: 'Average Recovery Time ',
    xaxis: { title: 'Average Recovery Time in days' },
    yaxis: { title: 'Frequency' },
    bargap: 0.2,

    width: 280,
    height: 320,
    autosize: false,
    margin: { t: 35, l: 35, r: 5, b: 35 }
};

Plotly.newPlot('art-histogram', [trace] , layout); 

    }
    renderAverageRecoveryTimeHistogram(artValues);

    loadTotalDowntime();

}

// Disruption Frequency Overview
async function disruptionFrequencyByCompany() {
    const display = document.getElementById('disruption-frequency-display');
    if (!display) return;

    display.innerHTML = '<div class="loading">Loading disruption frequency by company...</div>';

    const startDate = document.getElementById('disruption-start-date')?.value || '2020-01-01';
    const endDate   = document.getElementById('disruption-end-date')?.value || '2025-12-31';

    const result = await supplyManagerFetch('disruptionEventsDisplay', {
        start_date: startDate,
        end_date: endDate
    });

    if (result.success && result.data && Array.isArray(result.data.DF) && result.data.DF.length > 0) {

        const transformed = result.data.DF
            .filter(item => item.DF !== null)
            .map(item => ({
                CompanyName: item.CompanyName,
                CompanyType: item.CompanyType || "Unknown",
                DisruptionFrequency: Number(item.DF)
            }))
            .sort((a, b) => b.DisruptionFrequency - a.DisruptionFrequency);

        display.innerHTML = renderDisruptionFrequencyChart(transformed);
        return;
    }

    display.innerHTML = '<div class="trend-note error">No disruption data returned.</div>';
}

// Chart Renderer for Disruption Frequency
function renderDisruptionFrequencyChart(data) {
    const baseWidth = 500;
    const width = Math.max(baseWidth, data.length * 70);
    const height = 380;
    const padding = 50;
    const topPadding = 50;
    const bottomPadding = 50;
    const barGap = 20;

    const typeColors = {
        Manufacturer: { fill: 'rgba(254,202,202,0.9)', stroke: '#f87171' },
        Distributor: { fill: 'rgba(252,211,77,0.75)', stroke: '#facc15' },
        Retailer: { fill: 'rgba(134,239,172,0.85)', stroke: '#4ade80' },
        Default: { fill: 'rgba(209,213,219,0.6)', stroke: '#9ca3af' }
    };

    const maxDF = Math.max(...data.map(d => Number(d.DisruptionFrequency)), 1);
    const barWidth = (width - padding * 2) / data.length - barGap;
    const chartHeight = height - topPadding - bottomPadding;
    const scale = chartHeight / maxDF;

    const bars = data.map((item, idx) => {
        const x = padding + idx * (barWidth + barGap);
        const barHeight = item.DisruptionFrequency * scale;
        const y = height - bottomPadding - barHeight;
        const colors = typeColors[item.CompanyType] || typeColors.Default;

        return `
            <rect x="${x}" y="${y}" width="${barWidth}" height="${barHeight}"
                fill="${colors.fill}" stroke="${colors.stroke}" rx="4"></rect>

            <text x="${x + barWidth / 2}" y="${y - 8}"
                text-anchor="middle" fill="#f3f4f6" font-size="11">
                ${item.DisruptionFrequency}
            </text>

            <text transform="translate(${x + barWidth / 2}, ${y + barHeight / 2}) rotate(-90)"
                text-anchor="middle"
                fill="#ffffff"
                font-size="11">
                ${item.CompanyName}
            </text>
        `;
    }).join('');

    const avgDF = (
        data.reduce((sum, d) => sum + Number(d.DisruptionFrequency), 0) / data.length
    ).toFixed(2);

    return `
        <div class="disruption-summary">Average Disruption Frequency: ${avgDF}</div>

        <div style="
            overflow-x: auto;
            width: 100%;
            max-width: 100%;
            display: block;
            white-space: nowrap;
        ">
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <line x1="${padding}" y1="${height - bottomPadding}"
                      x2="${width - padding}" y2="${height - bottomPadding}"
                      stroke="#4b5563" />

                <line x1="${padding}" y1="${topPadding}"
                      x2="${padding}" y2="${height - bottomPadding}"
                      stroke="#4b5563" />

                ${bars}
            </svg>
        </div>
    `;
}

// Total Downtime  
async function loadTotalDowntime() { 
    const display = document.getElementById('td-histogram');
    if (!display) return;

    // display.innerHTML = '<div class="loading">Loading Total Downtime ...</div>'; // can't get to disappear after chart is loaded..

// Call Backend

const result = await supplyManagerFetch('disruptionEventsDisplay' , {
    start_date: '2020-01-01' ,
    end_date: '2025-12-31'
});

let totaldowntimeValues =[];
// If valid data returned 
 if (result.success && result.data && Array.isArray(result.data.TD)) {
    totaldowntimeValues = result.data.TD   
        .map(item => Number(item.TD))   // 
        .filter(val => !isNaN(val) && val !== null);
        totaldowntimeValues.sort((a, b) => a - b);
        //console.log("TD values for histogram:", totaldowntimeValues); // to verify TD data is correct
        console.log("RAW TD:", result.data.TD); 
        console.log("TD values after cleaning:", totaldowntimeValues);
    }

// Histogram Code for TD
async function renderTotalDowntimeHistogram(totaldowntimeValues) {

var trace = {
    x: totaldowntimeValues ,
    type: 'histogram',
    marker : {
            color: '#008000'

    }
  };
    var layout = {
    title: 'Total Downtime ',
    xaxis: { title: 'Total Downtime in Days' },
    yaxis: { title: 'Frequency' },
    bargap: 0.2,

    width: 280,
    height: 320,
    autosize: false,
    margin: { t: 35, l: 35, r: 5, b: 35 }
};
Plotly.newPlot('td-histogram', [trace] , layout);

}
   renderTotalDowntimeHistogram(totaldowntimeValues);

}

// Alerts for Supply Chain manager for refrence check index.php line 690 onwards TDBV
async function alertsOngoingDisruptions() {
    const display = document.getElementById('alerts-container');
    if (!display) return;

    display.innerHTML = '<div class="loading">Retrieving new and ongoing alerts...</div>';

// call backend
    const result = await supplyManagerFetch('alertsOngoingDisruptions', {});
    console.log("ALERTS API RESULT:", result);

     console.log("ALERTS RAW DATA:", result.data);
// if valid datA is returned 
    if (result.success && result.data && Array.isArray(result.data.ongoingAlerts)) {
        const alerts = result.data.ongoingAlerts;

        let html = '';

        if (alerts.length === 0) {
            html += '<p style="color: #9ca3af;">No ongoing alerts at this time.</p>';
        } else {
            alerts.forEach(alert => {
                const startDate = alert.StartDate ? new Date(alert.StartDate) : null;
                const daysAgo = startDate
                    ? Math.floor((Date.now() - startDate.getTime()) / (1000 * 60 * 60 * 24))
                    : null;

                const severityClass =
                      daysAgo !== null && daysAgo <= 2  ? 'alert-high'
                    : daysAgo !== null && daysAgo <= 7  ? 'alert-medium'
                    : 'alert-low';

                html += `
                    <div class="alerts-item ${severityClass}">
                        <div style="display: flex; justify-content: space-between; gap: 10px; align-items: baseline;">
                            <strong>${alert.CompanyName || 'Unknown Company'}</strong>
                            <span style="font-size: 0.85em; color: #e5e7eb;">${startDate ? startDate.toLocaleDateString() : 'Unknown Date'}</span>
                        </div>
                        <div style="font-size: 0.9em; color: #d1d5db;">Event ID: ${alert.EventID}</div>
                        <div style="font-size: 0.9em; color: #d1d5db;">Category: ${alert.CategoryName}</div>
                    </div>
                `;
            });
        }
        display.innerHTML = html;

    } else {
        display.innerHTML = '<div style="color: #ef4444;">Error loading alerts.</div>';
    }
}
</script>
