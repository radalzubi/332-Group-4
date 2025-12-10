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
            console.log("FETCH STATUS:", response.status);
            console.log("FETCH BODY RAW:", text);


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

// Company info search panel
    window.searchCompany = async function () {
        const input      = document.getElementById("company-search");
        const resultsDiv = document.getElementById("company-results");
        const startInput = document.getElementById("company-start-date");
        const endInput   = document.getElementById("company-end-date");

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

        const dateRange = {
            startDate: (startInput && startInput.value) || '1000-01-01',
            endDate: (endInput && endInput.value) || new Date().toISOString().slice(0, 10)
        };

        const result = await supplyManagerFetch("companyInfoSearch", {
            companyName: name,
            ...dateRange
        });

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

        displayCompanyResults(result, dateRange);
    };

    function displayCompanyResults(data, dateRange = {}) {
        const resultsDiv = document.getElementById("company-results");
        if (!resultsDiv) return;

        const company = (data.companyInfo && data.companyInfo[0]) || {};
        const address = (data.address && data.address[0]) || {};
        const latestFinancial = (data.latestFinancial && data.latestFinancial[0]) || {};
        const dependencies = Array.isArray(data.dependencies) ? data.dependencies : [];
        const manufacturer = Array.isArray(data.manufacturerCapacity)
            ? data.manufacturerCapacity[0]
            : null;
        const distributor = Array.isArray(data.uniqueRoutes)
            ? data.uniqueRoutes[0]
            : null;
        const productList = Array.isArray(data.productsSupplied)
            ? data.productsSupplied
            : [];
        const transactionData = (data.data && typeof data.data === 'object') ? data.data : {};
        const distributorKPIs = Array.isArray(transactionData.distributorKPIs)
            ? transactionData.distributorKPIs
            : [];
        const financialHealth = Array.isArray(transactionData.financialHealthPastYear)
            ? transactionData.financialHealthPastYear
            : [];
        const disruptionCounts = Array.isArray(transactionData.disruptionCountsPastYear)
            ? transactionData.disruptionCountsPastYear
            : [];
        const disruptionEvents = Array.isArray(transactionData.disruptionEventsPastYear)
            ? transactionData.disruptionEventsPastYear
            : [];
        const upstream = [];
        const downstream = [];
        const companyKey = company.CompanyID || 'unknown';
        const companyStatusId = `save-status-company-${companyKey}`;
        const transactionStatusId = `save-status-transaction-${companyKey}`;

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

        const capacityText = (company.Type === 'Manufacturer' && manufacturer)
            ? (manufacturer.FactoryCapacity ?? 'N/A')
            : null;

        const routesText = (company.Type === 'Distributor' && distributor)
            ? (distributor.UniqueRoutes ?? 'N/A')
            : null;
        const uniqueProducts = productList.map(p => p.ProductName).filter(Boolean);
        const distinctProducts = [...new Set(uniqueProducts)];
        const productDiversity = distinctProducts.length;

        const shippingTransactions = Array.isArray(transactionData.shippingTransactions)
            ? transactionData.shippingTransactions
            : [];
        const receivingTransactions = Array.isArray(transactionData.receivingTransactions)
            ? transactionData.receivingTransactions
            : [];
        const adjustmentTransactions = Array.isArray(transactionData.adjustmentTransactions)
            ? transactionData.adjustmentTransactions
            : [];
        const kpiData = distributorKPIs.length ? distributorKPIs[0] : null;
        const onTimeDeliveryRate = kpiData && kpiData.OnTimeDeliveryRate !== null
            ? Number(kpiData.OnTimeDeliveryRate)
            : null;
        const averageDelay = kpiData && kpiData.AverageDelay !== null
            ? Number(kpiData.AverageDelay)
            : null;
        const stdDevDelay = kpiData && kpiData.StandardDeviationDelay !== null
            ? Number(kpiData.StandardDeviationDelay)
            : null;
        const kpiRangeStart = dateRange.startDate || 'selected start date';
        const kpiRangeEnd = dateRange.endDate || 'selected end date';
        const statCards = `
            <div class="company-info-grid">
                <div class="company-info-row">
                    <span class="company-info-label">Company ID:</span>
                    <span class="company-info-value">${company.CompanyID || 'N/A'}</span>
                </div>
                <div class="company-info-row">
                    <span class="company-info-label">Type & Tier:</span>
                    <span class="company-info-value">${company.Type || 'Unknown'} (Tier ${company.TierLevel || 'N/A'})</span>
                </div>
                ${capacityText !== null ? `
                <div class="company-info-row">
                    <span class="company-info-label">Capacity:</span>
                    <span class="company-info-value">${capacityText}</span>
                </div>` : ''}
                ${routesText !== null ? `
                <div class="company-info-row">
                    <span class="company-info-label">Unique Routes Operated:</span>
                    <span class="company-info-value">${routesText}</span>
                </div>` : ''}
                <div class="company-info-row">
                    <span class="company-info-label">Address:</span>
                    <span class="company-info-value">${addressText}</span>
                </div>
                <div class="company-info-row">
                    <span class="company-info-label">Most Recent Financials:</span>
                    <span class="company-info-value">${financialText}</span>
                </div>
            </div>
        `;

        const dependencySection = `
            <div class="company-dependency-grid">
                <div class="company-info-row">
                    <span class="company-info-label">Upstream:</span>
                    <span class="company-info-value">${uniqueUpstream.length ? uniqueUpstream.join(', ') : 'No upstream dependencies'}</span>
                </div>
                <div class="company-info-row">
                    <span class="company-info-label">Downstream:</span>
                    <span class="company-info-value">${uniqueDownstream.length ? uniqueDownstream.join(', ') : 'No downstream dependents'}</span>
                </div>
            </div>
        `;

        const manufacturerSection = (company.Type === 'Manufacturer') ? `
            <div class="company-card" style="margin-top: 10px;">
                <h4 style="margin-top: 0; color: #60a5fa;">Products Supplied</h4>
                <div class="company-info-row">
                    <span class="company-info-label">Diversity:</span>
                    <span class="company-info-value">${productDiversity} product${productDiversity === 1 ? '' : 's'}</span>
                </div>
                <div class="company-info-row" style="align-items: flex-start;">
                    <span class="company-info-label">Product List:</span>
                    <span class="company-info-value">${distinctProducts.length ? distinctProducts.join(', ') : 'No products found'}</span>
                </div>
            </div>
        ` : '';

        const transactionList = (label, items, formatter) => {
            if (!items.length) return `<p style="color:#9ca3af; margin: 6px 0 0 0;">No ${label.toLowerCase()} in this range.</p>`;
            return `<ul style="margin: 6px 0 0 0; padding-left: 16px; color: #e5e7eb;">${items
                .map(item => `<li style="margin-bottom: 6px;">${formatter(item)}</li>`)
                .join('')}</ul>`;
        };

        const transactionSection = `
            <div class="company-card transactions" style="margin-top: 10px;">
                <h4 style="margin-top: 0; color: #60a5fa;">Transactions</h4>
                <div class="transaction-grid">
                    <div>
                        <strong style="color:#9ca3af;">Shipping</strong>
                        ${transactionList('Shipping', shippingTransactions, tx => {
                            const date = tx.TransactionDate || tx.ActualDate || 'N/A';
                            return `${date}: ${tx.Quantity || 'N/A'} units of ${tx.ProductName || 'Unknown Product'} from ${tx.SourceCompanyID || 'N/A'} to ${tx.DestinationCompanyID || 'N/A'}`;
                        })}
                    </div>
                    <div>
                        <strong style="color:#9ca3af;">Receiving</strong>
                        ${transactionList('Receiving', receivingTransactions, tx => {
                            const date = tx.TransactionDate || tx.ReceivedDate || 'N/A';
                            return `${date}: ${tx.Quantity || 'N/A'} units of ${tx.ProductName || 'Unknown Product'} received by ${tx.ReceiverCompanyID || 'N/A'} from ${tx.SourceCompanyID || 'N/A'}`;
                        })}
                    </div>
                    <div>
                        <strong style="color:#9ca3af;">Adjustments</strong>
                        ${transactionList('Adjustments', adjustmentTransactions, tx => {
                            const date = tx.AdjustmentDate || 'N/A';
                            const qty = tx.QuantityChange || 'N/A';
                            const reason = tx.Reason ? ` – ${tx.Reason}` : '';
                            return `${date}: ${qty} units of ${tx.ProductName || 'Unknown Product'} (Company ${tx.CompanyID || 'N/A'})${reason}`;
                        })}
                    </div>
                </div>
            </div>
        `;
        
        const savePanel = `
            <div class="company-card save-card" style="margin-top: 10px;">
                <h3 style="margin-top: 0; color: #60a5fa;">Update Information</h3>
                <p class="panel-subtitle">Update company info or transaction info</p>

                <form class="update-form" onsubmit="return saveCompanyUpdate(event, '${companyKey}', '${companyStatusId}')">
                    <div class="form-row">
                        <label>Company Name</label>
                        <input type="text" name="companyName" value="${company.CompanyName || ''}" placeholder="Edit company name">
                    </div>
                    <div class="inline-inputs">
                        <div class="form-row">
                            <label>Type</label>
                            <select name="companyType" value="${company.Type || ''}">
                                <option value="">Select type</option>
                                <option ${company.Type === 'Manufacturer' ? 'selected' : ''}>Manufacturer</option>
                                <option ${company.Type === 'Distributor' ? 'selected' : ''}>Distributor</option>
                                <option ${company.Type === 'Retailer' ? 'selected' : ''}>Retailer</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label>Capacity / Routes</label>
                            <input type="text" name="capability" value="${capacityText || routesText || ''}" placeholder="Factory capacity or routes">
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Notes</label>
                        <textarea name="notes" placeholder="Notes about changes"></textarea>
                    </div>
                    <button type="submit" class="btn-save">Save</button>
                    <p class="save-status" id="${companyStatusId}"></p>
                </form>

                <form class="update-form" onsubmit="return saveTransactionDraft(event, '${companyKey}', '${transactionStatusId}')">
                    <div class="inline-inputs">
                        <div class="form-row">
                            <label>Transaction Date</label>
                            <input type="date" name="transactionDate" value="${new Date().toISOString().slice(0,10)}">
                        </div>
                        <div class="form-row">
                            <label>Quantity</label>
                            <input type="number" name="quantity" min="0" placeholder="0">
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Product</label>
                        <input type="text" name="product" placeholder="Product name or SKU">
                    </div>
                    <div class="form-row">
                        <label>Notes</label>
                        <textarea name="details" placeholder="Shipment, receiving, or adjustment details"></textarea>
                    </div>
                    <button type="submit" class="btn-save">Save</button>
                    <p class="save-status" id="${transactionStatusId}"></p>
                </form>
            </div>
        `;

        const hasDistributorKPIs = kpiData && kpiData.CompanyType === 'Distributor';

        const onTimeCard = hasDistributorKPIs && Number.isFinite(onTimeDeliveryRate)
            ? `
                <div class="kpi-card">
                    <h4>On-Time Delivery Rate</h4>
                    <div class="kpi-value">${onTimeDeliveryRate.toFixed(2)}%</div>
                    <div class="kpi-subtitle">Shipments delivered by promised date<br><small>${kpiRangeStart} → ${kpiRangeEnd}</small></div>
                </div>
            `
            : `
                <div class="kpi-card">
                    <h4>On-Time Delivery Rate</h4>
                    <div class="kpi-value">N/A</div>
                    <div class="kpi-subtitle">${company.Type === 'Distributor' ? 'No shipments in selected range.' : 'Distributor metric only.'}</div>
                </div>
            `;

        const delayCard = hasDistributorKPIs && (Number.isFinite(averageDelay) || Number.isFinite(stdDevDelay))
            ? `
                <div class="kpi-card">
                    <h4>Delay Statistics</h4>
                    <div class="kpi-value">${Number.isFinite(averageDelay) ? averageDelay.toFixed(2) : 'N/A'} days</div>
                    <div class="kpi-subtitle">Average delay across shipments<br><small>Std Dev: ${Number.isFinite(stdDevDelay) ? stdDevDelay.toFixed(2) : 'N/A'} days</small></div>
                </div>
            `
            : `
                <div class="kpi-card">
                    <h4>Delay Statistics</h4>
                    <div class="kpi-value">N/A</div>
                    <div class="kpi-subtitle">${company.Type === 'Distributor' ? 'No delay data in selected range.' : 'Distributor metric only.'}</div>
                </div>
            `;

        const healthScores = financialHealth
            .map(entry => Number(entry.HealthScore))
            .filter(score => Number.isFinite(score));
        const avgHealthScore = healthScores.length
            ? (healthScores.reduce((sum, val) => sum + val, 0) / healthScores.length)
            : null;
        const financialCard = `
            <div class="kpi-card">
                <h4>Financial Health (Past Year)</h4>
                <div class="kpi-value">${avgHealthScore !== null ? avgHealthScore.toFixed(2) : 'N/A'}</div>
                <div class="kpi-subtitle">Average of last 4 quarters</div>
                ${financialHealth.length ? `
                    <div style="margin-top:8px; text-align:left; font-size:0.85rem; color:#cbd5e1; line-height:1.4; max-height:120px; overflow-y:auto;">
                        ${financialHealth
                            .map(entry => `${entry.Quarter || 'Q?'} ${entry.RepYear || ''}: ${entry.HealthScore ?? 'N/A'}`)
                            .join('<br>')}
                    </div>
                ` : `<div class="kpi-subtitle">No reports found in past year.</div>`}
            </div>
        `;

        const totalDisruptions = disruptionCounts.reduce((sum, item) => sum + (Number(item.EventCount) || 0), 0);
        const disruptionList = disruptionCounts.length
            ? disruptionCounts
                .map(item => {
                    const count = Number(item.EventCount) || 0;
                    const share = totalDisruptions > 0 ? ` (${((count / totalDisruptions) * 100).toFixed(1)}%)` : '';
                    return `<div style="display:flex; justify-content:space-between; margin-bottom:4px; color:#e5e7eb;"><span>${item.CategoryName || 'Unknown'}</span><span>${count}${share}</span></div>`;
                })
                .join('')
            : '<div class="kpi-subtitle">No disruptions in the past year.</div>';

        const disruptionEventsList = disruptionEvents.length
            ? `<div style="margin-top:8px; max-height:140px; overflow-y:auto; text-align:left; font-size:0.85rem; color:#cbd5e1; line-height:1.4;">
                    ${disruptionEvents
                        .map(event => {
                            const recovery = event.EventRecoveryDate ? ` → Recovery: ${event.EventRecoveryDate}` : '';
                            return `<div style="margin-bottom:6px;"><strong>${event.CategoryName || 'Event'}</strong> (ID ${event.EventID || 'N/A'})<br>${event.EventDate || 'Date N/A'}${recovery}</div>`;
                        })
                        .join('')}
                </div>`
            : '<div class="kpi-subtitle">No events logged in the past year.</div>';

        const disruptionCard = `
            <div class="kpi-card" style="grid-column: span 2; text-align:left;">
                <h4>Disruptions (Past Year)</h4>
                <div class="kpi-subtitle" style="margin-bottom:8px;">Category distribution and recent events</div>
                <div>${disruptionList}</div>
                ${disruptionEventsList}
            </div>
        `;

        const kpiContent = `
            <div class="kpi-grid" style="margin-top: 10px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                ${onTimeCard}
                ${delayCard}
                ${financialCard}
                ${disruptionCard}
            </div>
        `;

        const kpiPanel = `
            <div class="company-card" style="margin-top: 10px;">
                <h3 style="margin-top: 0; color: #60a5fa;">KPI Overview</h3>
                <p class="panel-subtitle">Using transactions from ${kpiRangeStart} to ${kpiRangeEnd}</p>
                ${kpiContent}
            </div>
        `;

        resultsDiv.innerHTML = `
            <div class="company-results-grid">
                <div class="company-left-column">
                    <div class="company-card general-info">
                        <h3>${company.CompanyName || 'Company Details'}</h3>
                        ${statCards}
                        ${dependencySection}
                        ${manufacturerSection}
                    </div>
                    ${savePanel}
                    ${kpiPanel}
                </div>
                ${transactionSection}
            </div>
        `;
    }

// saves updated company info
    window.saveCompanyUpdate = async function (event, companyId = 'unknown', statusId) {
        event.preventDefault();
        const status = document.getElementById(statusId);
        const form = event.target;

        const payload = {
            companyId,
            name: form.companyName?.value?.trim() || '',
            type: form.companyType?.value || '',
            capability: form.capability?.value?.trim() || '',
            notes: form.notes?.value?.trim() || ''
        };

        if (status) status.textContent = 'Saving to database...';

        const result = await supplyManagerFetch('saveCompanyUpdate', payload);

        if (result && result.success) {
            if (status) status.textContent = 'Company update saved to database.';
        } else {
            const errorMessage = (result && result.error) || 'Could not save update.';
            if (status) status.textContent = errorMessage;
            console.error('Unable to save company update', result);
        }

        return false;
    }

    window.saveTransactionDraft = async function (event, companyId = 'unknown', statusId) {
        event.preventDefault();
        const status = document.getElementById(statusId);
        const form = event.target;

        const draft = {
            companyId,
            transactionDate: form.transactionDate?.value || '',
            quantity: form.quantity?.value || '',
            product: form.product?.value?.trim() || '',
            details: form.details?.value?.trim() || ''
        };

        if (status) status.textContent = 'Saving transaction...';

        const result = await supplyManagerFetch('saveTransactionDraft', draft);

        if (result && result.success) {
            if (status) status.textContent = 'Transaction draft saved to database.';
        } else {
            const errorMessage = (result && result.error) || 'Could not save transaction data.';
            if (status) status.textContent = errorMessage;
            console.error('Unable to save transaction draft', result);
        }

        return false;
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

// Regional Risk Concentration heatmap
    window.loadRegionalRiskHeatmap = async function () {
        const display = document.getElementById('regional-heatmap-card');
        if (!display) return;

        display.innerHTML = '<div class="loading">Loading regional risk heatmap...</div>';

        const result = await supplyManagerFetch('regionalRiskHeatmap', {
            startDate: '2020-01-01',
            endDate: '2025-12-31'
        });

        if (!result?.success) {
            const errorText = result?.error || 'Unable to load regional risk data.';
            console.error('loadRegionalRiskHeatmap error:', result);
            return;
        }
            display.innerHTML = `
                <div class="error-message">${errorText}</div>
                <p class="loading">Showing sample regional data.</p>
                <div id="regional-heatmap-canvas" class="regional-heatmap-canvas"></div>
            `;

            const fallbackData = [
                { region: 'North America', percent: 32, count: 18 },
                { region: 'Europe', percent: 28, count: 16 },
                { region: 'Asia', percent: 22, count: 12 },
                { region: 'South America', percent: 10, count: 6 },
                { region: 'Africa', percent: 8, count: 5 }
            ];
            const fallbackTotal = fallbackData.reduce((sum, entry) => sum + entry.count, 0) || 1;
            renderRegionalRiskHeatmapChart(fallbackData, fallbackTotal);
        const regions = Array.isArray(result?.data?.regions) ? result.data.regions : [];
        const totalDisruptions = Number(result?.data?.totalDisruptions ?? 0);

        if (regions.length === 0 || totalDisruptions === 0) {
            display.innerHTML = '<p class="loading">No disruption data available for the selected range.</p>';
            return;
        }

        const normalized = regions.map(region => ({
            region: region.Region || 'Unknown',
            percent: Number(region.DisruptionPercent) || 0,
            count: Number(region.RegionDisruptions) || 0
        }));

        display.innerHTML = '<div id="regional-heatmap-canvas" class="regional-heatmap-canvas"></div>';
        renderRegionalRiskHeatmapChart(normalized, totalDisruptions);
    }

function renderRegionalRiskHeatmapChart(data, totalDisruptions) {
    const container = document.getElementById('regional-heatmap-canvas');
    if (!container) return;

    const regions = data.map(d => d.region);
    const percents = data.map(d => d.percent);
    const counts = data.map(d => d.count);
    const labels = percents.map(p => `${p.toFixed(1)}%`);

    const trace = {
        z: [percents],
        x: regions,
        y: ['Share'],
        type: 'heatmap',
        colorscale: 'Blues',
        showscale: true,
        zmin: 0,
        zmax: Math.max(...percents, 100),
        text: [labels],
        texttemplate: '%{text}',
        textfont: { color: '#0b1220', size: 14 },
        customdata: [counts],
        hovertemplate:
            'Region: %{x}<br>' +
            'Disruptions: %{customdata} / ' + totalDisruptions + '<br>' +
            'Share: %{text}<extra></extra>'
    };

    const layout = {
        title: 'Share of disruptions by region',
        height: 360,
        margin: { t: 60, l: 40, r: 20, b: 80 },
        xaxis: { tickangle: -30, title: 'Region' },
        yaxis: { showticklabels: false },
        paper_bgcolor: 'rgba(0,0,0,0)',
        plot_bgcolor: 'rgba(0,0,0,0)',
        font: { color: '#e5e7eb' }
    };

    Plotly.newPlot(container, [trace], layout, { responsive: true });
}

window.loadTransactionsExplorer = async function () {
        const display = document.getElementById('transaction-results');
        if (!display) return;

        const filters = {
            startDate: document.getElementById('tx-start-date')?.value || '2020-01-01',
            endDate: document.getElementById('tx-end-date')?.value || '2025-12-31',
            location: document.getElementById('tx-location')?.value.trim() || '',
            sourceCompany: document.getElementById('tx-source')?.value.trim() || '',
            destinationCompany: document.getElementById('tx-destination')?.value.trim() || '',
            distributor: document.getElementById('tx-distributor')?.value.trim() || ''
        };

        display.innerHTML = '<div class="loading">Loading transactions...</div>';

        const result = await supplyManagerFetch('transactionsExamination', filters);
        if (!result?.success) {
            const message = result?.error || 'Unable to load transactions right now.';
            display.innerHTML = `<div class="error-message">${message}</div>`;
            return;
        }

        const data = result.data || {};
        const shipping = Array.isArray(data.shipping) ? data.shipping : [];
        const receiving = Array.isArray(data.receiving) ? data.receiving : [];
        const adjustment = Array.isArray(data.adjustment) ? data.adjustment : [];

        const shippingVolume = Array.isArray(data.shippingVolume) ? data.shippingVolume : [];
        const onTimeRate = Array.isArray(data.onTimeDeliveryRate) ? data.onTimeDeliveryRate : [];

        const distributorKPIs = Array.isArray(result.distributorKPIs)
            ? result.distributorKPIs
            : Array.isArray(data.distributorKPIs) ? data.distributorKPIs : [];
        const productsHandled = Array.isArray(result.productsHandled)
            ? result.productsHandled
            : Array.isArray(data.productsHandled) ? data.productsHandled : [];
        const disruptionExposure = Array.isArray(result.disruptionExposure)
            ? result.disruptionExposure
            : Array.isArray(data.disruptionExposure) ? data.disruptionExposure : [];

        const shippedOut = shipping.filter(s => s.DeliveryStatus === 'Currently Out on Delivery').length;
        const onTimeShipments = shipping.filter(s => s.DeliveryStatus === 'On Time Delivery').length;
        const lateShipments = shipping.filter(s => s.DeliveryStatus === 'Late Delivery').length;

        const formatNumber = (val, fraction = 0) => {
            const num = Number(val);
            return Number.isFinite(num) ? num.toLocaleString(undefined, { maximumFractionDigits: fraction }) : 'N/A';
        };

        const formatPercent = (val) => {
            const num = Number(val);
            return Number.isFinite(num) ? `${num.toFixed(2)}%` : 'N/A';
        };

        let html = '';

        html += `
            <div class="tx-summary-grid">
                <div class="tx-card">
                    <div class="stat-label">Shipping records</div>
                    <div class="stat-value">${formatNumber(shipping.length)}</div>
                    <div class="stat-label">On time: ${formatNumber(onTimeShipments)} · Late: ${formatNumber(lateShipments)}</div>
                </div>
                <div class="tx-card">
                    <div class="stat-label">Shipments out</div>
                    <div class="stat-value">${formatNumber(shippedOut)}</div>
                    <div class="stat-label">Tracking deliveries still in transit.</div>
                </div>
                <div class="tx-card">
                    <div class="stat-label">Receiving events</div>
                    <div class="stat-value">${formatNumber(receiving.length)}</div>
                    <div class="stat-label">Filtered by date/location/company.</div>
                </div>
                <div class="tx-card">
                    <div class="stat-label">Inventory adjustments</div>
                    <div class="stat-value">${formatNumber(adjustment.length)}</div>
                    <div class="stat-label">Stock changes in the same window.</div>
                </div>
            </div>
            <div style="color:#9ca3af; font-size:0.9rem; margin-bottom: 6px;">
                Filters · ${filters.startDate} to ${filters.endDate}
                ${filters.location ? ` | Location: ${filters.location}` : ''}
                ${filters.sourceCompany ? ` | Leaving: ${filters.sourceCompany}` : ''}
                ${filters.destinationCompany ? ` | Arriving: ${filters.destinationCompany}` : ''}
                ${filters.distributor ? ` | Distributor: ${filters.distributor}` : ''}
            </div>
        `;

        const renderShippingCard = (item) => {
            const statusClass = item.DeliveryStatus === 'On Time Delivery'
                ? 'success'
                : item.DeliveryStatus === 'Late Delivery'
                    ? 'warn'
                    : 'info';
            return `
                <div class="tx-card">
                    <h4>${item.SourceCompany || 'Unknown Source'} → ${item.DestinationCompany || 'Unknown Destination'}</h4>
                    <div style="margin-bottom:6px;">
                        <span class="tx-pill ${statusClass}">${item.DeliveryStatus || 'Status Unknown'}</span>
                        <span class="tx-pill info">Qty: ${formatNumber(item.Quantity)}</span>
                    </div>
                    <div class="stat-label">Product: ${item.ProductName || 'N/A'} (${item.Category || 'Category N/A'})</div>
                    <div class="stat-label">Promised: ${item.PromisedDate || 'N/A'} · Actual: ${item.ActualDate || 'Pending'}</div>
                    <div class="stat-label">Distributor: ${item.DistributorName || 'N/A'}</div>
                </div>
            `;
        };

        const renderReceivingCard = (item) => `
            <div class="tx-card">
                <h4>${item.ReceiverCompany || 'Receiver'} received</h4>
                <div class="stat-label">Shipment ID: ${item.ShipmentID || 'N/A'} · Quantity: ${formatNumber(item.QuantityReceived)}</div>
                <div class="stat-label">Received: ${item.ReceivedDate || 'N/A'} | Shipping Actual Date: ${item.ActualDate || 'N/A'}</div>
            </div>
        `;

        const renderAdjustmentCard = (item) => `
            <div class="tx-card">
                <h4>${item.CompanyName || 'Company'} adjustment</h4>
                <div class="stat-label">${item.ProductName || 'Product'} · ${formatNumber(item.QuantityChange)} units</div>
                <div class="stat-label">Date: ${item.AdjustmentDate || 'N/A'} | Reason: ${item.Reason || 'N/A'}</div>
            </div>
        `;

        html += '<h3 class="tx-section-title">Shipping</h3>';
        html += shipping.length
            ? `<div class="tx-list">${shipping.slice(0, 6).map(renderShippingCard).join('')}</div>${shipping.length > 6 ? `<div class="stat-label">Showing 6 of ${shipping.length} shipments.</div>` : ''}`
            : '<div class="stat-label">No shipping records match these filters.</div>';

        html += '<h3 class="tx-section-title">Receiving</h3>';
        html += receiving.length
            ? `<div class="tx-list">${receiving.slice(0, 6).map(renderReceivingCard).join('')}</div>${receiving.length > 6 ? `<div class="stat-label">Showing 6 of ${receiving.length} receipts.</div>` : ''}`
            : '<div class="stat-label">No receiving transactions match these filters.</div>';

        html += '<h3 class="tx-section-title">Inventory Adjustments</h3>';
        html += adjustment.length
            ? `<div class="tx-list">${adjustment.slice(0, 6).map(renderAdjustmentCard).join('')}</div>${adjustment.length > 6 ? `<div class="stat-label">Showing 6 of ${adjustment.length} adjustments.</div>` : ''}`
            : '<div class="stat-label">No adjustments recorded in this window.</div>';

        if (filters.distributor) {
            const volume = shippingVolume[0] || {};
            const rate = onTimeRate[0] || {};
            const kpis = distributorKPIs[0] || {};
            const exposure = disruptionExposure[0] || {};

            html += `<h3 class="tx-section-title">Distributor focus: ${filters.distributor}</h3>`;
            html += `
                <div class="tx-summary-grid">
                    <div class="tx-card">
                        <div class="stat-label">Shipment volume</div>
                        <div class="stat-value">${formatNumber(volume.TotalQuantityShipped)}</div>
                        <div class="stat-label">${formatNumber(volume.TotalShipments)} shipments · ${formatNumber(volume.NumProductsHandled)} products</div>
                    </div>
                    <div class="tx-card">
                        <div class="stat-label">On-time delivery rate</div>
                        <div class="stat-value">${formatPercent(rate.onTimeDeliveryRate_pct)}</div>
                        <div class="stat-label">Based on deliveries within the selected window.</div>
                    </div>
                    <div class="tx-card">
                        <div class="stat-label">Status distribution</div>
                        <div class="stat-value">${formatNumber(kpis.shipmentCount)}</div>
                        <div class="stat-label">Out: ${formatNumber(kpis.shipmentsOutOnDelivery)} · On-time: ${formatNumber(kpis.onTimeShipments)} · Late: ${formatNumber(kpis.lateShipments)}</div>
                    </div>
                    <div class="tx-card">
                        <div class="stat-label">Disruption exposure</div>
                        <div class="stat-value">${formatNumber(exposure.disruptionExposure)}</div>
                        <div class="stat-label">Total disruptions: ${formatNumber(exposure.Ndisruptions)} (Exposure = disruptions + 2× high-impact)</div>
                    </div>
                </div>
            `;

            html += '<h4 class="tx-section-title">Products handled</h4>';
            if (productsHandled.length) {
                const productList = productsHandled
                    .slice(0, 6)
                    .map(p => `<li>${p.ProductName || 'Product'} · ${p.Category || 'Category'} · Qty ${formatNumber(p.TotalQuantity)} shipments (${formatNumber(p.NumShipments)} loads)</li>`)
                    .join('');
                html += `<div class="tx-card"><ul>${productList}</ul>${productsHandled.length > 6 ? `<div class="stat-label">Showing 6 of ${productsHandled.length} products.</div>` : ''}</div>`;
            } else {
                html += '<div class="stat-label">No products found for this distributor in the selected period.</div>';
            }
        }

        display.innerHTML = html;
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
document.addEventListener('DOMContentLoaded', () => {
    const heatmapCard = document.getElementById('regional-heatmap-card');
    if (heatmapCard && typeof window.loadRegionalRiskHeatmap === 'function') {
        window.loadRegionalRiskHeatmap();
    }
});
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('alerts-container')) {
        alertsOngoingDisruptions();
        }
    });
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('transaction-results')) {
        loadTransactionsExplorer();
    }
});
