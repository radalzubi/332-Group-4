# Group 4 ERP System

A role-based supply chain ERP web app built by a team of six for Purdue's IE 332 (ERP/SCM systems) course. Users log in as either a **Senior Manager** or a **Supply Chain Manager** and get a dashboard over a relational model of companies, supply dependencies, shipments, financial reports and disruption events. The app is a PHP/MySQL back end that serves JSON to a vanilla JavaScript front end rendered with Chart.js and Plotly.

## Features

### Senior Manager dashboard
A network-level view for spotting risk and financial health across the supply chain.

- **Network overview**: total companies, dependency connections, recent disruptions, average financial health for the current year, and breakdowns by company type and tier.
- **Critical companies**: ranks companies by criticality (downstream companies affected multiplied by high-impact disruption count), with search by name or ID.
- **Regional views**: disruption counts per region (continent), plus a "vulnerable regions" criticality chart.
- **Disruption frequency**: disruption counts over a chosen date range, optionally filtered by company.
- **Disruption lookups**: companies affected by a given disruption event, and all disruptions for a given company.
- **Distributor analytics**: top distributors by shipment volume, a distributor shipment plot, and average distributor delay time, with distributor detail lookup.
- **Financials**: average financial health by company over a date range, and company financials by region with per-company detail.
- **Add company**: creates a new company record along with its type-specific row (manufacturer, distributor or retailer).

### Supply Chain Manager dashboard
An operations view for monitoring disruptions and looking up individual companies.

- **Alerts**: ongoing disruptions (events with no recovery date, or a recovery date in the future), with a refresh button.
- **Disruption metrics**: high-impact disruption rate, average recovery time histogram, total downtime histogram, severity distribution, and disruption frequency.
- **Company info search** (separate page): company details and location, upstream and downstream dependencies, products supplied, transaction history over a selectable date range, on-time delivery rate, delay statistics, financial health and disruptions for the past year.
- **Update forms**: save edits to a company's name, type, and capacity or route count, and save transaction drafts.

## Architecture

```
Browser (index.php + script.js)
   |  fetch() with action=<name>
   v
ajax_handler_*.php  -- mysqli -->  MySQL database
   |
   v
JSON response -> rendered as tables, Chart.js and Plotly charts
```

- **`index.php`** handles session login, role checks and logout, and renders one of four views by `?page=`: `login`, `erp` (senior manager), `scm` (supply chain manager) and `company_search`. Access to each view is gated on the session role. The senior manager panels and their JavaScript live inline in this file.
- **AJAX handlers** receive an `action` parameter, dispatch to a function per feature, run the SQL through `mysqli` and return JSON. `ajax_handler_senior_managers.php` serves the senior dashboard and `ajax_handler_supply_chain_managers.php` serves the supply chain dashboard.
- **`script.js`** contains the login form handling and the supply chain manager logic: company search rendering, update forms, alerts and charts.
- **SQL**: the queries are also written out as MySQL stored procedures in the two `... SQL Queries - Natalie` files. The PHP handlers run their SQL directly rather than calling those procedures.

## Tech stack

- PHP with `mysqli` and PHP sessions
- MySQL, using joins, CTEs and aggregation across the data model
- JavaScript (no framework), HTML and CSS
- [Chart.js](https://www.chartjs.org/) and [Plotly.js](https://plotly.com/javascript/), both loaded from a CDN

## Data model

The SQL references these tables (the schema itself is not in this repo): `Company`, `Location`, `DependsOn`, `Manufacturer`, `Distributor`, `Retailer`, `Product`, `SuppliesProduct`, `Shipping`, `Receiving`, `InventoryTransaction`, `FinancialReport`, `DisruptionEvent`, `DisruptionCategory` and `ImpactsCompany`. The supply chain handler also creates `CompanyUpdates` and `TransactionDrafts` on first use.

## Repository layout

| File | Purpose |
| --- | --- |
| `index.php` | Login, session and role checks, page views, and senior manager front end |
| `script.js` | Login handling and supply chain manager front end |
| `style.css` | Styling |
| `ajax_handler_senior_managers.php` | JSON API for the senior manager dashboard |
| `ajax_handler_supply_chain_managers.php` | JSON API for the supply chain manager dashboard |
| `legacy/` | Earlier handler versions and a fake-data debug stub, kept for reference; not used by the front end |
| `Senior Manager Module SQL Queries - Natalie`, `Supply Chain Module SQL Queries - Natalie` | Stored-procedure versions of the module queries |
| `A3 Documentation.txt` | Short team progress log and notes |

## Running locally

The database schema and seed data are **not included** in this repo, so you need to supply your own MySQL database with the tables listed above before the dashboards return data.

1. Install PHP (with the `mysqli` extension) and MySQL.
2. Create a database and load a schema matching the tables above.
3. Update the MySQL connection settings, which are set near the top of `index.php`, `ajax_handler_senior_managers.php` and `ajax_handler_supply_chain_managers.php`, to point at your own database.
4. From the repo root, start a PHP server: `php -S localhost:8000`
5. Open `http://localhost:8000/index.php`. The page loads Chart.js and Plotly from CDNs, so it needs internet access.

Login uses one demo account per role, defined in `index.php`. That suits a course demo; a production deployment would add real user management.

## Team

Built by Group 4:

- [radalzubi](https://github.com/radalzubi) (project lead)
- [ryder-aldrich](https://github.com/ryder-aldrich)
- [natalie-elizabeth-anderson](https://github.com/natalie-elizabeth-anderson)
- [tantoadeleye](https://github.com/tantoadeleye)
- [anushkaheda](https://github.com/anushkaheda)
- [john307810-maker](https://github.com/john307810-maker)
