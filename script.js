// script.js – IE 332 ERP Dashboard JS
// Make sure this file is in the SAME folder as index.php and ajax_handler.php

document.addEventListener("DOMContentLoaded", () => {
    console.log("script.js loaded");
    let severityRegionChart = null;

    // =========================================================
    // LOGIN FORM VALIDATION + SHAKE ANIMATION
    // =========================================================
    const loginForm   = document.getElementById("login-form");
    const usernameInp = document.getElementById("username");
    const passwordInp = document.getElementById("password");
    const clientError = document.getElementById("clientError");
    const loginCard   = document.getElementById("login-card");

    if (loginForm && usernameInp && passwordInp && clientError && loginCard) {
        loginForm.addEventListener("submit", (e) => {
            const u = usernameInp.value.trim();
            const p = passwordInp.value.trim();

            clientError.textContent = "";
            clientError.classList.add("hidden");

            // Clear old shakes
            [usernameInp, passwordInp, loginCard].forEach(el => {
                el.classList.remove("shake");
                void el.offsetWidth; // force reflow so animation retriggers
            });

            if (u === "" || p === "") {
                e.preventDefault();
                clientError.textContent = "Both username and password are required.";
                clientError.classList.remove("hidden");

                usernameInp.classList.add("shake");
                passwordInp.classList.add("shake");
                loginCard.classList.add("shake");
            }
        });
    }

    // =========================================================
    // GENERIC AJAX HELPER
    // =========================================================
    async function fetchData(action, data = {}) {
        try {
            const formData = new FormData();
            formData.append("action", action);
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch("ajax_handler.php", {
                method: "POST",
                body: formData
            });

            const text = await response.text();
            console.log(`AJAX [${action}] raw response:`, text);

            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("JSON parse error:", e);
                return { success: false, error: "Bad JSON from server", raw: text };
            }
        } catch (err) {
            console.error("fetchData error:", err);
            return { success: false, error: err.message || "Network error" };
        }
    }

    // =========================================================
    // KPIs PANEL
    // =========================================================
    window.loadKPIs = async function () {
        const startInput  = document.getElementById("kpi-start-date");
        const endInput    = document.getElementById("kpi-end-date");
        const companyInput= document.getElementById("kpi-company");
        const loadingDiv  = document.getElementById("kpi-loading");
        const errorDiv    = document.getElementById("kpi-error");
        const displayDiv  = document.getElementById("kpi-display");

        if (!displayDiv) {
            console.warn("loadKPIs: #kpi-display not found");
            return;
        }

        const start_date   = (startInput  && startInput.value)  || "2020-01-01";
        const end_date     = (endInput    && endInput.value)    || "2025-12-31";
        const company_name = (companyInput && companyInput.value.trim()) || "";

        if (loadingDiv) loadingDiv.style.display = "block";
        if (errorDiv) {
            errorDiv.textContent = "";
            errorDiv.style.display = "none";
        }
        displayDiv.innerHTML = `<p class="loading">Loading KPIs...</p>`;

        const result = await fetchData("getKPIs", {
            start_date,
            end_date,
            company_name
        });

        if (loadingDiv) loadingDiv.style.display = "none";

        if (!result || !result.success || !result.data) {
            const msg = result && result.error ? result.error : "Unknown error";
            console.error("loadKPIs error:", result);
            if (errorDiv) {
                errorDiv.textContent = "Error loading KPIs: " + msg;
                errorDiv.style.display = "block";
            } else {
                displayDiv.innerHTML = `<p style="color:#f87171;">Error loading KPIs: ${msg}</p>`;
            }
            return;
        }

        displayKPIs(result.data);
    };

    function displayKPIs(kpis) {
        const displayDiv = document.getElementById("kpi-display");
        if (!displayDiv) return;

        const onTimeRate       = kpis.onTimeRate       || 0;
        const totalShipments   = kpis.totalShipments   || 0;
        const delayedShipments = kpis.delayedShipments || 0;
        const avgDelay         = kpis.avgDelay         || 0;
        const stdDelay         = kpis.stdDelay         || 0;
        const avgHealthScore   = kpis.avgHealthScore   || 0;
        const totalDisruptions = kpis.totalDisruptions || 0;
        const highImpactEvents = kpis.highImpactEvents || 0;

        const disruptionRate = (totalDisruptions > 0)
            ? Math.round((highImpactEvents / totalDisruptions) * 100)
            : 0;

        const html = `
            <div class="kpi-grid">
                <div class="kpi-card">
                    <h4>On-Time Delivery Rate</h4>
                    <div class="kpi-value">${onTimeRate}%</div>
                    <div class="kpi-subtitle">${totalShipments} total shipments</div>
                </div>
                <div class="kpi-card">
                    <h4>Average Delay</h4>
                    <div class="kpi-value">${avgDelay}</div>
                    <div class="kpi-subtitle">days (σ = ${stdDelay})</div>
                </div>
                <div class="kpi-card">
                    <h4>Delayed Shipments</h4>
                    <div class="kpi-value">${delayedShipments}</div>
                    <div class="kpi-subtitle">out of ${totalShipments}</div>
                </div>
                <div class="kpi-card">
                    <h4>Financial Health</h4>
                    <div class="kpi-value">${avgHealthScore}</div>
                    <div class="kpi-subtitle">average score</div>
                </div>
                <div class="kpi-card">
                    <h4>Total Disruptions</h4>
                    <div class="kpi-value">${totalDisruptions}</div>
                    <div class="kpi-subtitle">${highImpactEvents} high impact</div>
                </div>
                <div class="kpi-card">
                    <h4>Disruption Rate</h4>
                    <div class="kpi-value">${disruptionRate}%</div>
                    <div class="kpi-subtitle">high severity ratio</div>
                </div>
            </div>
        `;

        displayDiv.innerHTML = html;
    }

    // =========================================================
    // COMPANY SEARCH PANEL
    // =========================================================
    window.searchCompany = async function () {
        const input      = document.getElementById("company-search");
        const resultsDiv = document.getElementById("company-results");

        if (!input || !resultsDiv) {
            console.warn("searchCompany: missing #company-search or #company-results");
            return;
        }

        const name = input.value.trim();
        if (name === "") {
            resultsDiv.innerHTML = `<p style="color:#9ca3af;">Please enter a company name to search.</p>`;
            return;
        }

        resultsDiv.innerHTML = `<p class="loading">Searching for "${name}"...</p>`;

        const result = await fetchData("searchCompany", { name });

        if (!result || !result.success || !result.data || result.data.length === 0) {
            const msg = (result && result.message) || (result && result.error) || "No companies found";
            resultsDiv.innerHTML = `<p style="color:#9ca3af;">${msg}</p>`;
            return;
        }

        displayCompanyResults(result.data);
    };

    function displayCompanyResults(companies) {
        const resultsDiv = document.getElementById("company-results");
        if (!resultsDiv) return;

        let html = "";
        companies.forEach(company => {
            const healthScore   = company.CurrentHealthScore    || "N/A";
            const address       = company.Address              || "N/A";
            const type          = company.CompanyType          || "Unknown";
            const tier          = company.TierLevel            || "N/A";
            const outgoing      = company.OutgoingTransactions || 0;
            const incoming      = company.IncomingTransactions || 0;
            const upstream      = company.UpstreamCount        || 0;
            const downstream    = company.DownstreamCount      || 0;

            html += `
                <div class="company-card">
                    <h3>${company.CompanyName}</h3>

                    <div class="company-info-row">
                        <span class="company-info-label">Type:</span>
                        <span class="company-info-value">${type} (Tier ${tier})</span>
                    </div>

                    <div class="company-info-row">
                        <span class="company-info-label">Address:</span>
                        <span class="company-info-value">${address}</span>
                    </div>

                    <div class="company-info-row">
                        <span class="company-info-label">Health Score:</span>
                        <span class="company-info-value">${healthScore}</span>
                    </div>

                    <div class="company-info-row">
                        <span class="company-info-label">Transactions:</span>
                        <span class="company-info-value">${outgoing} outgoing • ${incoming} incoming</span>
                    </div>

                    <div class="company-info-row">
                        <span class="company-info-label">Network:</span>
                        <span class="company-info-value">${upstream} upstream • ${downstream} downstream</span>
                    </div>
                </div>
            `;
        });

        resultsDiv.innerHTML = html;
    }

    // =========================================================
    // DISRUPTION METRICS PANEL
    // =========================================================
    window.loadDisruptionMetrics = async function () {
        function updateSeverityRegionChart(regionalData) {
            const canvas = document.getElementById("severityRegionChart");
            if (!canvas) {
                console.warn("updateSeverityRegionChart: #severityRgionChart not found");
                return;
            }

            const ctx = canvas.getContext("2d");

            if(!regionalData || regionalData.length == 0) {
                if (severityRegionChart) {
                    severityRegionChart.destroy();
                    severityRegionChart = null;
                }
                return;
            }

            const regions = regionalData.map(r => r.Region);
            const lowData = regionalData.map(r => Number(r.Low) || 0);
            const medData = regionalData.map(r => Number(r.Medium) || 0);
            const highData = regionalData.map(r => Number(r.High) || 0);

            if (severityRegionChart) {
                severityRegionChart.destroy();
            }

            severityRegionChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: regions,
                    datasets: [
                        {
                            label: "Low",
                            data: lowData,
                            stack: "severity",
                            backgroundColor: "rgba(34, 197, 94, 0.7)"
                        },
                        {
                            label: "Medium",
                            data: medData,
                            stack: "severity",
                            backgroundColor: "rgba(245, 158, 11, 0.7)"
                        },
                        {
                            label: "High",
                        data: highData,
                        stack: "severity",
                         backgroundColor: "rgba(239, 68, 68, 0.7)"
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: "top"
                        },
                        tooltip: {
                            callbacks: {
                                footer: (tooltipItems) => {
                                    let total = 0;
                                    tooltipItems.forEach(t => total += t.parsed.y || 0);
                                    return "Total: " + total;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            title: {
                                display: true,
                                text: "Region"
                            }
                        },
                        y: {
                            stacked: true,
                            title: {
                                display: true,
                                text: "Number of Disruptions"
                            },
                            beginAtZero: true
                        }
                    }
                }
            });

        }

        async function loadSeverityRegionChart() {
            const canvas = document.getElementById("severityRegionChart");
            if (!canvas) return;

            const formData = new FormData();
            formData.append("action", "disruptionEventsDisplay");

            try {
                const response = await fetch("actual_supply_chain_manager.php", {
                    method: "POST",
                    body: formData
                });

                const text = await response.text();
                console.log("loadSeverityRegionChart raw:", text);

                let json;
                try {
                    json = JSON.parse(text);
                } catch (e) {
                    console.error("loadSeverityRegionChart JSON parse error:", e);
                    return;
                }

                if(!json.success || !json.data) {
                    console.error("loadSeverityRegionChart sever error:", json.error);
                    return;
                }

                const regionalData = json.data.RegionalSeverityDistribution || [];
                updateSeverityRegionChart(regionalData);
            } catch (err) {
                console.error("loadSeverityRegionChart fetch error:", err);
            }
            
        }
        const startInput  = document.getElementById("metric-start-date");
        const endInput    = document.getElementById("metric-end-date");
        const loadingDiv  = document.getElementById("metrics-loading");
        const displayDiv  = document.getElementById("metrics-display");

        if (!displayDiv) {
            console.warn("loadDisruptionMetrics: #metrics-display not found");
            return;
        }

        const start_date = (startInput && startInput.value) || "2020-01-01";
        const end_date   = (endInput   && endInput.value)   || "2025-12-31";

        if (loadingDiv) loadingDiv.style.display = "block";
        displayDiv.innerHTML = "";

        const result = await fetchData("getDisruptionMetrics", {
            start_date,
            end_date
        });

        if (loadingDiv) loadingDiv.style.display = "none";

        if (!result || !result.success || !result.data) {
            displayDiv.innerHTML = `<p style="color:#f87171;">Error loading disruption metrics.</p>`;
            console.error("loadDisruptionMetrics error:", result);
            return;
        }

        const data = result.data;
        const freq = data.frequency         || 0;
        const art  = data.avgResolutionDays || 0;
        const open = data.openEvents        || 0;

        const html = `
            <div class="kpi-grid">
                <div class="kpi-card">
                    <h4>Disruption Frequency</h4>
                    <div class="kpi-value">${freq}</div>
                    <div class="kpi-subtitle">events in range</div>
                </div>
                <div class="kpi-card">
                    <h4>Avg Resolution Time</h4>
                    <div class="kpi-value">${art.toFixed ? art.toFixed(1) : art}</div>
                    <div class="kpi-subtitle">days</div>
                </div>
                <div class="kpi-card">
                    <h4>Open Events</h4>
                    <div class="kpi-value">${open}</div>
                    <div class="kpi-subtitle">currently active</div>
                </div>
            </div>
        `;

        displayDiv.innerHTML = html;
    };

    // =========================================================
    // ALERTS PANEL
    // =========================================================
    async function loadAlerts() {
        const container = document.getElementById("alerts-container");
        if (!container) {
            return;
        }

        container.innerHTML = `<div class="loading">Loading alerts...</div>`;

        const result = await fetchData("getAlerts", {});

        if (!result || !result.success || !result.data) {
            container.innerHTML = `<p style="color:#9ca3af;">No alerts or error loading alerts.</p>`;
            console.error("loadAlerts error:", result);
            return;
        }

        let html = "";
        result.data.forEach(alert => {
            const severity = (alert.Severity || alert.severity || "low").toLowerCase();
            let severityClass = "alert-low";
            if (severity === "high")   severityClass = "alert-high";
            if (severity === "medium") severityClass = "alert-medium";

            html += `
                <div class="alert ${severityClass}">
                    <div class="alert-header">
                        <span class="alert-severity">${severity.toUpperCase()}</span>
                        <span class="alert-date">${alert.EventDate || ""}</span>
                    </div>
                    <div class="alert-body">
                        <p>${alert.Description || "No description"}</p>
                        <p class="alert-company">${alert.CompanyName || ""}</p>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    // =========================================================
    // AUTO-LOAD THINGS ON SCM PAGE
    // =========================================================
    // If the KPI panel exists, auto-load KPIs shortly after page load
    if (document.getElementById("kpi-display")) {
        setTimeout(() => {
            console.log("Auto-loading KPIs...");
            window.loadKPIs();
        }, 500);
    }

    // If the alerts container exists, load alerts and refresh every 30s
    if (document.getElementById("alerts-container")) {
        console.log("Setting up alerts auto-refresh");
        loadAlerts();
        setInterval(loadAlerts, 30000);
    }

    if (document.getElementById("severityRegionChart")) {
        loadSeverityRegionChart();
    }

    async function loadSeverityRegionChart() {
    const formData = new FormData();
    formData.append("action", "disruptionEventsDisplay");

    const response = await fetch("actual_supply_chain_manager.php", {
        method: "POST",
        body: formData
    });

    const json = await response.json();
    const data = json.data?.RegionalSeverityDistribution || [];

    new Chart(document.getElementById("severityRegionChart"), {
        type: "bar",
        data: {
            labels: data.map(r => r.Region),
            datasets: [
                {
                    label: "Low",
                    data: data.map(r => r.Low),
                    backgroundColor: "rgba(34,197,94,0.6)"
                },
                {
                    label: "Medium",
                    data: data.map(r => r.Medium),
                    backgroundColor: "rgba(245,158,11,0.6)"
                },
                {
                    label: "High",
                    data: data.map(r => r.High),
                    backgroundColor: "rgba(239,68,68,0.6)"
                }
            ]
        },
        options: {
            responsive: true,
            scales: { x: { stacked: true }, y: { stacked: true } }
        }
    });
}

async function loadRegionalHeatMap() {
    const container = document.getElementById("regional-heatmap");
    if (!container) return;

    container.innerHTML = '<div class="loading">Loading global heatmap...</div>';

    // Use your real AJAX helper
    const result = await fetchData("regional_disruption_overview", {
        startDate: "2020-01-01",
        endDate: "2025-12-31"
    });

    if (!result.success || !result.data) {
        container.innerHTML =
            '<p style="color:#ef4444;">Error loading regional disruption data.</p>';
        console.error("Global heatmap error:", result);
        return;
    }

    const regions = result.data;

    const labels = regions.map(r => r.ContinentName || "Unknown");
    const values = regions.map(r => r.TotalDisruptions || 0);

    container.innerHTML = "";

    const data = [
        {
            type: "choropleth",
            locationmode: "country names",
            locations: labels,
            z: values,
            colorscale: "Reds",
            colorbar: { title: "Disruptions" }
        }
    ];

    const layout = {
        title: "Global Supply Chain Disruptions",
        geo: {
            projection: { type: "natural earth" }
        },
        margin: { t: 40 }
    };

    Plotly.newPlot("regional-heatmap", data, layout);
}
});
