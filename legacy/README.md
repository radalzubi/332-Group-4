# Legacy handlers

Earlier versions of the AJAX handlers, kept for reference. Nothing in the app
calls them: `index.php` posts to `ajax_handler_senior_managers.php` and
`script.js` posts to `ajax_handler_supply_chain_managers.php`, both in the repo
root.

| File | What it was |
| --- | --- |
| `ajax_handler.php` | Debug stub that returns fake data without a database, used to test the front end's AJAX wiring |
| `CORRECT_ajax_handler_supply_chain_managers.php` | Earlier supply chain manager handler (`companyInfoSearch`, `disruptionEventsDisplay`, `transactionsExamination`, `alertsOngoingDisruptions`) |
| `NULL_ajax_handler_senior_manager.php` | Earlier senior manager handler (`getFinancialHealth`, `getRegionalDisruptions`, `getCriticalCompanies`, `getNetworkOverview`, `getDisruptionTrend`) |

They still carry their own placeholder database settings and are not kept in
sync with the live handlers.
