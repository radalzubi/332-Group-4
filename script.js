/* script.js for the supply chain managers (senior managers is located in index because 
   they were each done by different group members) */
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
    window.supplyManagerFetch = async function (action, data = {}) {
        try {
            const formData = new FormData();
            formData.append("action", action);
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            const response = await fetch("ajax_handler_supply_chain_managers.php", {
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
            console.error("supplyManagerFetch error:", err);
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

        const result = await supplyManagerFetch("getKPIs", {
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
            resultsDiv.innerHTML = `<p style="color:#9ca3af;">Please enter a company name or ID.</p>`;
            return;
        }

        resultsDiv.innerHTML = `<p class="loading">Searching for "${name}"...</p>`;

        const result = await supplyManagerFetch("companyInfoSearch", { companyName: name });

        if (!result || !result.success) {
            const msg = (result && result.error) || "Error searching for company.";
            resultsDiv.innerHTML = `<p style="color:#f87171;">${msg}</p>`;
            return;
        }

        const hasData = (result.companyInfo && result.companyInfo.length > 0)
            || (result.address && result.address.length > 0)
            || (result.dependencies && result.dependencies.length > 0);

        if (!hasData) {
            resultsDiv.innerHTML = `<p style="color:#9ca3af;">No results found for "${name}".</p>`;
            return;
        }

        displayCompanyResults(result);
    };

    function displayCompanyResults(data) {
        const resultsDiv = document.getElementById("company-results");
        if (!resultsDiv) return;

        const company = (data.companyInfo && data.companyInfo[0]) || {};
        const address = (data.address && data.address[0]) || {};
        const latestFinancial = (data.latestFinancial && data.latestFinancial[0]) || {};
        const dependencies = Array.isArray(data.dependencies) ? data.dependencies : [];

        const upstream = [];
        const downstream = [];

        dependencies.forEach(dep => {
            if (dep.UpstreamCompanyID && dep.UpstreamCompanyName) {
                upstream.push(`${dep.UpstreamCompanyName} (ID ${dep.UpstreamCompanyID})`);
            }
            if (dep.DownstreamCompanyID && dep.DownstreamCompanyName) {
                downstream.push(`${dep.DownstreamCompanyName} (ID ${dep.DownstreamCompanyID})`);
            }
        });

        const uniqueUpstream = [...new Set(upstream)];
        const uniqueDownstream = [...new Set(downstream)];

        const addressText = address.City || address.CountryName || address.ContinentName
            ? [address.City, address.CountryName, address.ContinentName].filter(Boolean).join(', ')
            : 'N/A';

        const financialText = latestFinancial.RepYear
            ? `${latestFinancial.Quarter || ''} ${latestFinancial.RepYear} • Health Score: ${latestFinancial.HealthScore ?? 'N/A'}`
            : 'No financial reports found.';

        const statCards = `
            <div class="company-info-row">
                <span class="company-info-label">Company ID:</span>
                <span class="company-info-value">${company.CompanyID || 'N/A'}</span>
            </div>
            <div class="company-info-row">
                <span class="company-info-label">Type & Tier:</span>
                <span class="company-info-value">${company.Type || 'Unknown'} (Tier ${company.TierLevel || 'N/A'})</span>
            </div>
            <div class="company-info-row">
                <span class="company-info-label">Address:</span>
                <span class="company-info-value">${addressText}</span>
            </div>
            <div class="company-info-row">
                <span class="company-info-label">Most Recent Financials:</span>
                <span class="company-info-value">${financialText}</span>
            </div>
        `;

        const dependencySection = `
            <div class="company-info-row">
                <span class="company-info-label">Depends on:</span>
                <span class="company-info-value">${uniqueUpstream.length ? uniqueUpstream.join('<br>') : 'No upstream dependencies'}</span>
            </div>
            <div class="company-info-row">
                <span class="company-info-label">Required by:</span>
                <span class="company-info-value">${uniqueDownstream.length ? uniqueDownstream.join('<br>') : 'No downstream dependents'}</span>
            </div>
        `;

        resultsDiv.innerHTML = `
            <div class="company-card">
                <h3>${company.CompanyName || 'Company Details'}</h3>
                ${statCards}
                ${dependencySection}
            </div>
        `;
    }

    // =========================================================
    // DISRUPTION METRICS PANEL
    // =========================================================
    window.loadDisruptionMetrics = async function () {
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

        const result = await supplyManagerFetch("getDisruptionMetrics", {
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

        const result = await supplyManagerFetch("alertsOngoingDisruptions", {});

        if (!result || !result.success || !result.data) {
            container.innerHTML = `<p style="color:#9ca3af;">No alerts or error loading alerts.</p>`;
            console.error("loadAlerts error:", result);
            return;
        }

        let html = "";
        result.data.ongoingAlerts.forEach(alert => {
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

// RRC Heatmap function 
    window.rrcHeatmap = async function () {
        const display = document.getElementById('rrc-heatmap-display');
        if (!display) return;

        display.innerHTML = '<div class="loading">Loading regional risk heatmap...</div>';

        const locationID = document.getElementById('heatmap-location-id')?.value || '';

        const result = await supplyManagerFetch('disruptionEventsDisplay', { locationID });

        if (result.success && Array.isArray(result.data.RRC) && result.data.RRC.length > 0) {
            const transformed = result.data.RRC
            .map(item => ({
            region: item.Region,
            rrc: Number(item.RRC) / 100 || 0
        }))
        .sort((a, b) => b.rrc - a.rrc);

    display.innerHTML = renderRRCHeatmapChart(transformed);
    return;
}


        const errorNote = result.error
            ? `<div class="trend-note error">${result.error}</div>`
            : '<div class="trend-note error">No RRC data returned.</div>';

        display.innerHTML = errorNote;
    }

    function renderRRCHeatmapChart(data) {
        const baseWidth = 500;
        const width = Math.max(baseWidth, data.length * 80);
        const height = 320;
        const padding = 50;
        const cellWidth = (width - padding * 2) / data.length;
        const cellHeight = 60;

        const maxRRC = Math.max(...data.map(d => d.rrc));
        function rrcToColor(rrc) {
            const intensity = Math.round(255 - (rrc / maxRRC) * 180);
            return `rgb(255, ${intensity}, ${intensity})`;
        }

        const cells = data.map((item, idx) => {
            const x = padding + idx * cellWidth;
            const y = padding;

            return `
                <rect x="${x}" y="${y}" width="${cellWidth}" height="${cellHeight}"
                    fill="${rrcToColor(item.rrc)}" stroke="#374151" rx="4"></rect>

                <text x="${x + cellWidth / 2}" y="${y + cellHeight / 2 + 4}" 
                    text-anchor="middle" fill="white" font-size="12" font-weight="bold">
                    ${item.region.includes(" ") 
                        ? `<tspan x="${x + cellWidth / 2}" dy="-7">${item.region.split(" ")[0]}</tspan>
                        <tspan x="${x + cellWidth / 2}" dy="14">${item.region.split(" ")[1]}</tspan>`
                    : item.region}
                </text>

                <text x="${x + cellWidth / 2}" y="${y + cellHeight + 20}"
                    text-anchor="middle" fill="#e5e7eb" font-size="11">
                    ${(item.rrc * 100).toFixed(1)}%
                </text>
            `;
        }).join('');

        return `
            <div class="rrc-summary">Regional Risk Concentration across regions</div>
            <div style="overflow-x: auto; width: 100%;">
                <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" class="trend-chart">
                    ${cells}
                </svg>
            </div>
        `;
    }

// Severity disruptions chart
async function loadSeverityStackedBar() {
    const result = await supplyManagerFetch("disruptionEventsDisplay", {
        startDate: "2020-01-01",
        endDate: "2025-12-31"
    });

    if (!result.success || !result.data || !result.data.SeverityDistribution) {
        console.error("Severity load error:", result);
        return;
    }

    const severity = result.data.SeverityDistribution;

    const low = severity.find(x => x.ImpactLevel === "Low")?.Count || 0;
    const med = severity.find(x => x.ImpactLevel === "Medium")?.Count || 0;
    const high = severity.find(x => x.ImpactLevel === "High")?.Count || 0;

    const canvas = document.getElementById("severityChart");
    if (!canvas) {
        console.warn("severityChart canvas not found in DOM");
        return;
    }

    const ctx = canvas.getContext("2d");

    if (window.severityChartInstance) {
        window.severityChartInstance.destroy();
    }

    window.severityChartInstance = new Chart(ctx, {
        type: "bar",
        data: {
            labels: ["Severity Distribution"],
            datasets: [
                {
                    label: "Low",
                    data: [low],
                    backgroundColor: "#22c55e"
                },
                {
                    label: "Medium",
                    data: [med],
                    backgroundColor: "#f59e0b"
                },
                {
                    label: "High",
                    data: [high],
                    backgroundColor: "#ef4444"
                }
            ]
        },
        options: {
            responsive: false,
            plugins: {
                title: {
                    display: true,
                    text: "Disruption Severity Distribution (Stacked)"
                }
            },
            scales: {
                x: { stacked: true,
                    barPercentage: 0.3,
                    categoryPercentage: 0.5
                },
                y: {stacked: true, 
                    beginAtZero: true }
            }
        }
    });
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
