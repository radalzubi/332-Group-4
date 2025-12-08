<?php
date_default_timezone_set('America/New_York');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//actual_supply_chain_manager.php
session_start();

// Database connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

header('Content-Type: application/json');

if (!$conn || $conn->connect_error) {
    echo json_encode([
        "success" => false,
        "error" => "Database connection failed"
    ]);
    exit;
}

function fetchAll($result) {
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'companyInfoSearch':
        companyInfoSearch($conn);
        break;

    case 'disruptionEventsDisplay':
        disruptionEventsDisplay($conn);
        break;

    case 'transactionsExamination':
        transactionsExamination($conn);
        break;

    case 'alertsOngoingDisruptions':
        alertsOngoingDisruptions($conn);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);
        break;

}

/* ===========================================================
   COMPANY INFO SEARCH
   =========================================================== */
function companyInfoSearch($conn) {

    $response = [
    'success' => true,
    'data' => [] ];


    $companyName = isset($_POST['companyName']) ? $conn->real_escape_string($_POST['companyName']) : '';
    $startDate   = isset($_POST['startDate']) ? $conn->real_escape_string($_POST['startDate']) : '1000-01-01';
    $endDate     = isset($_POST['endDate'])   ? $conn->real_escape_string($_POST['endDate'])   : date('Y-m-d');


    // 1. Basic company info
    $sql = "SELECT * FROM Company WHERE CompanyName='$companyName'";
    $result = $conn->query($sql);
    $response['companyInfo'] = $result ? fetchAll($result) : [];


    // 2. Location
    $sql = "
    SELECT c.CompanyID, c.CompanyName, l.City, l.CountryName, l.ContinentName
    FROM Company c
    JOIN Location l ON c.LocationID = l.LocationID
    WHERE c.CompanyName='$companyName'
    ";
    $result = $conn->query($sql);
    $response['address'] = $result ? fetchAll($result) : [];


    // 3. Dependencies
    $sql = "
    SELECT 
        c.CompanyID AS BaseCompanyID,
        depUp.CompanyID AS UpstreamCompanyID,
        depUp.CompanyName AS UpstreamCompanyName,
        depDown.CompanyID AS DownstreamCompanyID,
        depDown.CompanyName AS DownstreamCompanyName
    FROM Company c
    LEFT JOIN DependsOn d1 ON c.CompanyID = d1.DownstreamCompanyID
    LEFT JOIN Company depUp ON d1.UpstreamCompanyID = depUp.CompanyID
    LEFT JOIN DependsOn d2 ON c.CompanyID = d2.UpstreamCompanyID
    LEFT JOIN Company depDown ON d2.DownstreamCompanyID = depDown.CompanyID
    WHERE c.CompanyName='$companyName'
    ";
    $result = $conn->query($sql);
    $response['dependencies'] = $result ? fetchAll($result) : [];


    // 4. Latest Financials
    $sql = "
    SELECT f.*
    FROM FinancialReport f
    JOIN Company c ON f.CompanyID = c.CompanyID
    WHERE c.CompanyName='$companyName'
    ORDER BY f.RepYear DESC, FIELD(f.Quarter,'Q4','Q3','Q2','Q1') DESC
    LIMIT 1
    ";
    $result = $conn->query($sql);
    $response['latestFinancial'] = $result ? fetchAll($result) : [];


    // 5. Manufacturer Capacity
    $sql = "
    SELECT c.CompanyName, m.FactoryCapacity
    FROM Company c
    JOIN Manufacturer m ON c.CompanyID = m.CompanyID
    WHERE c.CompanyName='$companyName'
    ";
    $result = $conn->query($sql);
    $response['manufacturerCapacity'] = $result ? fetchAll($result) : [];


    // 6. Distributor unique routes
    $sql = "
    SELECT c.CompanyName,
           COUNT(DISTINCT CONCAT(s.SourceCompanyID, '->', s.DestinationCompanyID)) AS UniqueRoutes
    FROM Company c
    JOIN Distributor d ON c.CompanyID = d.CompanyID
    JOIN Shipping s ON d.CompanyID = s.DistributorID
    WHERE c.CompanyName='$companyName'
    GROUP BY c.CompanyName
    ";
    $result = $conn->query($sql);
    $response['uniqueRoutes'] = $result ? fetchAll($result) : [];


    // 7. Products supplied
    $sql = "
    SELECT p.ProductID, p.ProductName, sp.SupplyPrice
    FROM SuppliesProduct sp
    JOIN Product p ON sp.ProductID = p.ProductID
    JOIN Company c ON sp.SupplierID = c.CompanyID
    WHERE c.CompanyName='$companyName'
    ";
    $result = $conn->query($sql);
    $response['productsSupplied'] = $result ? fetchAll($result) : [];


  // -------------------------
// Shipping Transactions
// -------------------------
$sql = "SELECT 
            t.TransactionID,
            'Shipping' AS TransactionType,
            s.SourceCompanyID,
            s.DestinationCompanyID,
            s.Quantity,
            s.ActualDate AS TransactionDate,
            p.ProductName
        FROM InventoryTransaction t
        JOIN Shipping s ON t.TransactionID = s.TransactionID
        JOIN Product p ON s.ProductID = p.ProductID
        JOIN Company c 
            ON (s.SourceCompanyID = c.CompanyID OR s.DestinationCompanyID = c.CompanyID)
        WHERE c.CompanyName = '$companyName'
          AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY s.ActualDate DESC";

$result = $conn->query($sql);
$response['data']['shippingTransactions'] = $result ? fetchAll($result) : [];


// -------------------------
// Receiving Transactions
// -------------------------
$sql = "SELECT 
            t.TransactionID,
            'Receiving' AS TransactionType,
            r.ReceivedDate AS TransactionDate,
            r.QuantityReceived AS Quantity,
            r.ReceiverCompanyID,
            s.SourceCompanyID,
            p.ProductName
        FROM InventoryTransaction t
        JOIN Receiving r ON t.TransactionID = r.TransactionID
        JOIN Shipping s ON r.ShipmentID = s.ShipmentID
        JOIN Product p ON s.ProductID = p.ProductID
        JOIN Company c ON r.ReceiverCompanyID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
          AND r.ReceivedDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY r.ReceivedDate DESC";

$result = $conn->query($sql);
$response['data']['receivingTransactions'] = $result ? fetchAll($result) : [];


// -------------------------
// Adjustment Transactions
// -------------------------
$sql = "SELECT 
            t.TransactionID,
            'Adjustment' AS TransactionType,
            a.AdjustmentDate,
            a.QuantityChange,
            a.CompanyID,
            p.ProductName,
            a.Reason
        FROM InventoryTransaction t
        JOIN InventoryAdjustment a ON t.TransactionID = a.TransactionID
        JOIN Product p ON a.ProductID = p.ProductID
        JOIN Company c ON a.CompanyID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
          AND a.AdjustmentDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY a.AdjustmentDate DESC";

$result = $conn->query($sql);
$response['data']['adjustmentTransactions'] = $result ? fetchAll($result) : [];


// --------------------------------------------------------
// Distributor KPIs (Only applies if company is distributor)
// --------------------------------------------------------
$sql = "WITH distributorShipments AS (
            SELECT 
                s.ShipmentID,
                s.PromisedDate,
                s.ActualDate,
                DATEDIFF(s.ActualDate, s.PromisedDate) AS daysDelayed
            FROM Shipping s
            JOIN Company c ON s.DistributorID = c.CompanyID
            WHERE c.CompanyName = '$companyName'
              AND c.Type = 'Distributor'
              AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
        )
        SELECT 
            c.CompanyName,
            c.Type AS CompanyType,
            CASE WHEN c.Type = 'Distributor' THEN 
                (SELECT ROUND(100.0 * SUM(CASE WHEN ActualDate <= PromisedDate THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0), 4)
                 FROM distributorShipments)
            ELSE NULL END AS OnTimeDeliveryRate,
            CASE WHEN c.Type = 'Distributor' THEN 
                (SELECT ROUND(AVG(daysDelayed),4) FROM distributorShipments)
            ELSE NULL END AS AverageDelay,
            CASE WHEN c.Type = 'Distributor' THEN 
                (SELECT ROUND(STDDEV_POP(daysDelayed),4) FROM distributorShipments)
            ELSE NULL END AS StandardDeviationDelay
        FROM Company c
        WHERE c.CompanyName = '$companyName'";

$result = $conn->query($sql);
$response['data']['distributorKPIs'] = $result ? fetchAll($result) : [];


// ----------------------------------------
// Financial Health – Last 4 Quarters
// ----------------------------------------
$sql = "WITH LastFourQuarters AS (
            SELECT 
                CONCAT('Q', MOD(QUARTER(CURDATE())-n-1+4,4)+1) AS Quarter,
                YEAR(CURDATE()) - IF(QUARTER(CURDATE())-n <= 0, 1, 0) AS RepYear
            FROM (
                SELECT 0 AS n UNION ALL 
                SELECT 1 UNION ALL 
                SELECT 2 UNION ALL 
                SELECT 3
            ) AS offsets
        )
        SELECT 
            c.CompanyName,
            fr.CompanyID,
            fr.Quarter,
            fr.RepYear,
            fr.HealthScore
        FROM FinancialReport fr
        JOIN LastFourQuarters lfq 
            ON fr.RepYear = lfq.RepYear 
           AND fr.Quarter = lfq.Quarter
        JOIN Company c 
            ON fr.CompanyID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
        ORDER BY fr.RepYear DESC, fr.Quarter DESC";

$result = $conn->query($sql);
$response['data']['financialHealthPastYear'] = $result ? fetchAll($result) : [];


// ----------------------------------------
// Disruption Count – Past Year (All)
// ----------------------------------------
$sql = "SELECT 
            dc.CategoryName, 
            COUNT(de.EventID) AS EventCount
        FROM DisruptionCategory dc
        LEFT JOIN DisruptionEvent de 
          ON dc.CategoryID = de.CategoryID
         AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        GROUP BY dc.CategoryName
        ORDER BY EventCount DESC";

$result = $conn->query($sql);
$response['data']['disruptionCountsPastYear'] = $result ? fetchAll($result) : [];


// ----------------------------------------
// Disruption Events List – Past Year
// ----------------------------------------
$sql = "SELECT 
            dc.CategoryID,
            dc.CategoryName,
            dc.Description,
            de.EventID,
            de.EventDate,
            de.EventRecoveryDate
        FROM DisruptionEvent de
        JOIN DisruptionCategory dc 
            ON de.CategoryID = dc.CategoryID
        WHERE de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        ORDER BY de.EventDate DESC";

$result = $conn->query($sql);
$response['data']['disruptionEventsPastYear'] = $result ? fetchAll($result) : [];

echo json_encode($response);
}







/* ===========================================================
   DISRUPTION EVENTS
   =========================================================== */
function disruptionEventsDisplay($conn) {
    $response = array(
        'success' => true,
        'data' => array()
    );


    $companyName = isset($_POST['companyName']) && $_POST['companyName'] !== '' 
                    ? $conn->real_escape_string($_POST['companyName']) 
                    : null;

    $region = isset($_POST['region']) && $_POST['region'] !== '' 
              ? $conn->real_escape_string($_POST['region']) 
              : null;

    $tier = isset($_POST['tier']) && $_POST['tier'] !== '' 
            ? $conn->real_escape_string($_POST['tier']) 
            : null;

    $startDate = isset($_POST['startDate']) && $_POST['startDate'] !== '' 
                 ? $conn->real_escape_string($_POST['startDate']) 
                 : '1000-01-01';

    $endDate = isset($_POST['endDate']) && $_POST['endDate'] !== '' 
               ? $conn->real_escape_string($_POST['endDate']) 
               : date('Y-m-d');

    $sql = "
        SELECT 
            c.CompanyID,
            c.CompanyName,
            c.TierLevel,
            l.ContinentName AS Region,
            de.EventID,
            de.EventDate,
            de.EventRecoveryDate,
            ic.ImpactLevel
        FROM DisruptionEvent de
        JOIN ImpactsCompany ic 
            ON de.EventID = ic.EventID
        JOIN Company c 
            ON ic.AffectedCompanyID = c.CompanyID
        JOIN Location l 
            ON c.LocationID = l.LocationID
        WHERE de.EventDate BETWEEN '$startDate' AND '$endDate'
    ";

    if ($companyName) {
        $sql .= " AND c.CompanyName = '$companyName' ";
    }

    if ($region) {
        $sql .= " AND l.ContinentName = '$region' ";
    }

    if ($tier) {
        $sql .= " AND c.TierLevel = '$tier' ";
    }

    $sql .= " ORDER BY de.EventDate DESC ";

    $result = $conn->query($sql);
    $disruptions = $result ? fetchAll($result) : [];

    $response['data']['disruptionsInRange'] = $disruptions;

    // ----------------------------------------
    // Company-level metrics
    // ----------------------------------------
    $companyMetrics = [];
    $totalDisruptions = count($disruptions);

    foreach ($disruptions as $d) {
        $cid = $d['CompanyID'];

        if (!isset($companyMetrics[$cid])) {
            $companyMetrics[$cid] = array(
                'CompanyID'   => $cid,
                'CompanyName' => $d['CompanyName'],
                'Region'      => $d['Region'],
                'Ndisruptions'=> 0,
                'DF_quarters' => array(),
                'RecoveryDays'=> array(),
                'HighImpact'  => 0
            );
        }

        $companyMetrics[$cid]['Ndisruptions']++;

        // Disruption Frequency (per quarter)
        if (!empty($d['EventDate'])) {
            $yr_q = date('Y', strtotime($d['EventDate'])) . '-Q' . ceil(date('n', strtotime($d['EventDate'])) / 3);
            $companyMetrics[$cid]['DF_quarters'][$yr_q] = 1;
        }

        // Average Recovery Time
        if (!empty($d['EventRecoveryDate'])) {
            $companyMetrics[$cid]['RecoveryDays'][] =
                (strtotime($d['EventRecoveryDate']) - strtotime($d['EventDate'])) / (60 * 60 * 24);
        }

        // High Disruption Ratio
        if (isset($d['ImpactLevel']) && $d['ImpactLevel'] === 'High') {
            $companyMetrics[$cid]['HighImpact']++;
        }
    }

    $dfList = [];
    $artList = [];
    $hdrList = [];
    $tdList = [];

    foreach ($companyMetrics as $m) {
        $quartersCount = count($m['DF_quarters']);

        $DF = $quartersCount > 0 ? round($m['Ndisruptions'] / $quartersCount, 2) : null;
        $ART = count($m['RecoveryDays']) > 0
               ? round(array_sum($m['RecoveryDays']) / count($m['RecoveryDays']), 2)
               : null;

        $HDR = $m['Ndisruptions'] > 0
               ? round(100 * $m['HighImpact'] / $m['Ndisruptions'], 2)
               : null;

        $TD = count($m['RecoveryDays']) > 0
              ? round(array_sum($m['RecoveryDays']), 2)
              : null;

        $dfList[] = array(
            'CompanyID'   => $m['CompanyID'],
            'CompanyName' => $m['CompanyName'],
            'DF'           => $DF
        );

        $artList[] = array(
            'CompanyID'   => $m['CompanyID'],
            'CompanyName' => $m['CompanyName'],
            'ART'          => $ART
        );

        $hdrList[] = array(
            'CompanyID'   => $m['CompanyID'],
            'CompanyName' => $m['CompanyName'],
            'HDR'          => $HDR
        );

        $tdList[] = array(
            'CompanyID'   => $m['CompanyID'],
            'CompanyName' => $m['CompanyName'],
            'TD'           => $TD
        );
    }

    $response['data']['DF']  = $dfList;
    $response['data']['ART'] = $artList;
    $response['data']['HDR'] = $hdrList;
    $response['data']['TD']  = $tdList;

    // ----------------------------------------
    // Regional Risk Concentration
    // ----------------------------------------
    $regionCounts = [];

    foreach ($disruptions as $d) {
        $r = $d['Region'];
        if (!isset($regionCounts[$r])) {
            $regionCounts[$r] = 0;
        }
        $regionCounts[$r]++;
    }

    $rrcList = [];

    if ($totalDisruptions > 0) {
        foreach ($regionCounts as $r => $count) {
            $rrcList[] = array(
                'Region' => $r,
                'RRC'    => round(100 * $count / $totalDisruptions, 2)
            );
        }
    }

    $response['data']['RRC'] = $rrcList;

    // ----------------------------------------
    // Severity Distribution
    // ----------------------------------------
    $severityCounts = array(
        'Low'    => 0,
        'Medium' => 0,
        'High'   => 0
    );

    foreach ($disruptions as $d) {
        if (isset($d['ImpactLevel']) && isset($severityCounts[$d['ImpactLevel']])) {
            $severityCounts[$d['ImpactLevel']]++;
        }
    }

    $severityList = [];

    foreach (['Low', 'Medium', 'High'] as $lvl) {
        $severityList[] = array(
            'ImpactLevel' => $lvl,
            'Count'       => $severityCounts[$lvl]
        );
    }

    $response['data']['SeverityDistribution'] = $severityList;

    echo json_encode($response);
}







/* ===========================================================
   ONGOING ALERTS
   =========================================================== */
function alertsOngoingDisruptions($conn) {

    $response = [
    'success' => true,
    'data' => [] ];


    $sql = "
    SELECT c.CompanyName, de.EventID, de.EventDate AS StartDate, dc.CategoryName
    FROM DisruptionEvent de
    JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
    JOIN ImpactsCompany ic ON de.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    WHERE de.EventRecoveryDate IS NULL
       OR de.EventRecoveryDate > CURRENT_DATE()
    ORDER BY de.EventDate DESC
    ";

    $result = $conn->query($sql);
    $response['data']['ongoingAlerts'] = $result ? fetchAll($result) : [];

    echo json_encode($response);
}




/* ===========================================================
   Transactions Examination
   =========================================================== */
function transactionsExamination($conn) {
    $response = array('success' => true, 'data' => array());


    $startDate = isset($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
    $endDate   = isset($_POST['endDate']) ? $_POST['endDate'] : date('Y-m-d');
    $location  = isset($_POST['location']) ? $_POST['location'] : null;
    $sourceCompany = isset($_POST['sourceCompany']) ? $_POST['sourceCompany'] : null;
    $destinationCompany = isset($_POST['destinationCompany']) ? $_POST['destinationCompany'] : null;
    $distributor = isset($_POST['distributor']) ? $_POST['distributor'] : null;

    $startEsc = $conn->real_escape_string($startDate);
    $endEsc   = $conn->real_escape_string($endDate);

    /* -------------------------------- SHIPPING -------------------------------- */
    $sqlShipping = "
        SELECT 
            s.ShipmentID,
            s.DistributorID,
            d.CompanyName AS DistributorName,
            s.ProductID,
            p.ProductName,
            p.Category,
            s.SourceCompanyID,
            cs.CompanyName AS SourceCompany,
            s.DestinationCompanyID,
            cd.CompanyName AS DestinationCompany,
            s.ActualDate,
            s.PromisedDate,
            s.Quantity,
            CASE
                WHEN s.ActualDate IS NULL THEN 'Currently Out on Delivery'
                WHEN s.ActualDate <= s.PromisedDate THEN 'On Time Delivery'
                ELSE 'Late Delivery'
            END AS DeliveryStatus,
            DATEDIFF(s.ActualDate, s.PromisedDate) AS daysDelayed
        FROM Shipping s
        JOIN Company d  ON s.DistributorID = d.CompanyID
        JOIN Product p  ON s.ProductID = p.ProductID
        JOIN Company cs ON s.SourceCompanyID = cs.CompanyID
        JOIN Company cd ON s.DestinationCompanyID = cd.CompanyID
        JOIN Location ls ON cs.LocationID = ls.LocationID
        JOIN Location ld ON cd.LocationID = ld.LocationID
        WHERE s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
    ";

    if ($sourceCompany) {
        $srcEsc = $conn->real_escape_string($sourceCompany);
        $sqlShipping .= " AND cs.CompanyName = '$srcEsc' ";
    }
    if ($destinationCompany) {
        $destEsc = $conn->real_escape_string($destinationCompany);
        $sqlShipping .= " AND cd.CompanyName = '$destEsc' ";
    }
    if ($distributor) {
        $distEsc = $conn->real_escape_string($distributor);
        $sqlShipping .= " AND d.CompanyName = '$distEsc' ";
    }
    if ($location) {
        $locEsc = $conn->real_escape_string($location);
        $sqlShipping .= "
            AND (
                ls.CountryName = '$locEsc'
                OR ls.ContinentName = '$locEsc'
                OR ls.CityName = '$locEsc'
                OR ld.CountryName = '$locEsc'
                OR ld.ContinentName = '$locEsc'
                OR ld.CityName = '$locEsc'
            )
        ";
    }

    $sqlShipping .= " ORDER BY s.ActualDate DESC";

    $result = $conn->query($sqlShipping);
    $response['data']['shipping'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();


    /* -------------------------------- RECEIVING -------------------------------- */
    $sqlReceiving = "
        SELECT 
            r.ReceivingID,
            r.TransactionID,
            r.ShipmentID,
            r.ReceiverCompanyID,
            c.CompanyName AS ReceiverCompany,
            r.ReceivedDate,
            r.QuantityReceived,
            s.SourceCompanyID,
            s.DestinationCompanyID,
            s.DistributorID,
            s.ProductID,
            s.ActualDate
        FROM Receiving r
        JOIN Shipping s ON r.ShipmentID = s.ShipmentID
        JOIN Company c ON r.ReceiverCompanyID = c.CompanyID
        WHERE s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
    ";

    if ($sourceCompany) {
        $srcEsc = $conn->real_escape_string($sourceCompany);
        $sqlReceiving .= " AND s.SourceCompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='$srcEsc' LIMIT 1)";
    }
    if ($destinationCompany) {
        $destEsc = $conn->real_escape_string($destinationCompany);
        $sqlReceiving .= " AND r.ReceiverCompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='$destEsc' LIMIT 1)";
    }
    if ($distributor) {
        $distEsc = $conn->real_escape_string($distributor);
        $sqlReceiving .= " AND s.DistributorID = (SELECT CompanyID FROM Company WHERE CompanyName='$distEsc' LIMIT 1)";
    }

    $sqlReceiving .= " ORDER BY r.ReceivedDate DESC";

    $result = $conn->query($sqlReceiving);
    $response['data']['receiving'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();


    /* -------------------------------- ADJUSTMENTS -------------------------------- */
    $sqlAdjustment = "
        SELECT 
            a.AdjustmentID,
            a.TransactionID,
            a.CompanyID,
            c.CompanyName,
            a.ProductID,
            p.ProductName,
            a.AdjustmentDate,
            a.QuantityChange,
            a.Reason
        FROM InventoryAdjustment a
        JOIN Company c ON a.CompanyID = c.CompanyID
        JOIN Product p ON a.ProductID = p.ProductID
        WHERE a.AdjustmentDate BETWEEN '$startEsc' AND '$endEsc'
    ";

    if ($sourceCompany) {
        $srcEsc = $conn->real_escape_string($sourceCompany);
        $sqlAdjustment .= " AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='$srcEsc' LIMIT 1)";
    }
    if ($destinationCompany) {
        $destEsc = $conn->real_escape_string($destinationCompany);
        $sqlAdjustment .= " AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='$destEsc' LIMIT 1)";
    }
    if ($distributor) {
        $distEsc = $conn->real_escape_string($distributor);
        $sqlAdjustment .= " AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='$distEsc' LIMIT 1)";
    }

    $sqlAdjustment .= " ORDER BY a.AdjustmentDate DESC";

    $result = $conn->query($sqlAdjustment);
    $response['data']['adjustment'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();

/* ---------------- DISTRIBUTOR-SPECIFIC ANALYSIS ---------------- */
    if ($distributor) {
        $distEsc = $conn->real_escape_string($distributor);

        // Shipping volume summary
        $sqlShippingVolume = "
            SELECT d.CompanyName AS DistributorName,
                   SUM(s.Quantity) AS TotalQuantityShipped,
                   COUNT(DISTINCT s.ShipmentID) AS TotalShipments,
                   COUNT(DISTINCT s.ProductID) AS NumProductsHandled
            FROM Shipping s
            JOIN Company d ON s.DistributorID = d.CompanyID
            WHERE d.CompanyName='$distEsc'
              AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        ";

        $result = $conn->query($sqlShippingVolume);
        $response['data']['shippingVolume'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();

        // On-time delivery rate
        $sqlOnTimeRate = "
            SELECT d.CompanyName AS DistributorName,
                   ROUND(
                       100 * SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) 
                       / GREATEST(COUNT(*),1), 2
                   ) AS onTimeDeliveryRate_pct
            FROM Shipping s
            JOIN Company d ON s.DistributorID = d.CompanyID
            WHERE d.CompanyName='$distEsc'
              AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
            GROUP BY d.CompanyName
        ";

        $result = $conn->query($sqlOnTimeRate);
        $response['data']['onTimeDeliveryRate'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();


        //-------------------------------------------------------------
        // 1. Products handled
        //-------------------------------------------------------------
        $sqlProducts = "
        SELECT 
               p.ProductID,
               p.ProductName,
               p.Category,
               sp.SupplyPrice,
               COUNT(*) AS NumShipments,
               SUM(s.Quantity) AS TotalQuantity,
               (COALESCE(sp.SupplyPrice,0) * SUM(s.Quantity)) AS TotalRevenuePerProduct,
               s.DistributorID
        FROM Shipping s
        JOIN Product p ON s.ProductID = p.ProductID
        LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
        JOIN Company d ON s.DistributorID = d.CompanyID
        WHERE d.CompanyName='$distEsc'
          AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        GROUP BY p.ProductID, p.ProductName, p.Category, sp.SupplyPrice, s.DistributorID
        ";

        $resultProducts = $conn->query($sqlProducts);
        $productsHandled = $resultProducts ? $resultProducts->fetch_all(MYSQLI_ASSOC) : [];
        $response['productsHandled'] = $productsHandled;

        $distributorID = !empty($productsHandled) ? $productsHandled[0]['DistributorID'] : null;


        //-------------------------------------------------------------
        // 2. Status of shipments (On Time / Late / Out)
        //-------------------------------------------------------------
        $sqlKPIs = "
        SELECT d.CompanyName AS DistributorName,
               COUNT(DISTINCT s.ShipmentID) AS shipmentCount,
               COALESCE(SUM(s.Quantity),0) AS shipmentVolume,
               SUM(CASE WHEN s.ActualDate IS NULL THEN 1 ELSE 0 END) AS shipmentsOutOnDelivery,
               SUM(CASE WHEN s.ActualDate > s.PromisedDate THEN 1 ELSE 0 END) AS lateShipments,
               SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) AS onTimeShipments
        FROM Shipping s
        JOIN Company d ON s.DistributorID = d.CompanyID
        WHERE d.CompanyName='$distEsc'
          AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        ";

        $resultKPIs = $conn->query($sqlKPIs);
        $response['distributorKPIs'] = $resultKPIs ? $resultKPIs->fetch_all(MYSQLI_ASSOC) : [];


        //-------------------------------------------------------------
        // 3. Disruption exposure for distributor
        //-------------------------------------------------------------
        $sqlExposure = "
        SELECT d.CompanyName,
               COUNT(DISTINCT de.EventID) AS Ndisruptions,
               (COUNT(DISTINCT de.EventID) + 2 * COALESCE(SUM(CASE WHEN ic.ImpactLevel='High' THEN 1 ELSE 0 END),0)) AS disruptionExposure
        FROM Company d
        LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
        LEFT JOIN DisruptionEvent de ON ic.EventID = de.EventID
        WHERE d.CompanyName = '$distEsc'
          AND (de.EventDate BETWEEN '$startEsc' AND '$endEsc' OR de.EventDate IS NULL)
        GROUP BY d.CompanyName
        ";

        $resultExposure = $conn->query($sqlExposure);
        $response['disruptionExposure'] = $resultExposure ? $resultExposure->fetch_all(MYSQLI_ASSOC) : [];


        //-------------------------------------------------------------
        // 4. Shipments per category (first plot)
        //-------------------------------------------------------------
        $sqlShipmentsByCategory = "
        SELECT p.Category,
               SUM(s.Quantity) AS TotalQuantity,
               COUNT(*) AS NumShipments
        FROM Shipping s
        JOIN Product p ON s.ProductID = p.ProductID
        JOIN Company d ON s.DistributorID = d.CompanyID
        WHERE d.CompanyName='$distEsc'
          AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        GROUP BY p.Category
        ORDER BY p.Category
        ";

        $resultShipmentsByCategory = $conn->query($sqlShipmentsByCategory);
        $response['shipmentsByCategory'] = $resultShipmentsByCategory ? $resultShipmentsByCategory->fetch_all(MYSQLI_ASSOC) : [];


        //-------------------------------------------------------------
        // 5. Revenue per category (second plot)
        //-------------------------------------------------------------
        $sqlRevenueByCategory = "
        SELECT p.Category,
               SUM(COALESCE(sp.SupplyPrice,0) * s.Quantity) AS TotalRevenue
        FROM Shipping s
        JOIN Product p ON s.ProductID = p.ProductID
        LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
        JOIN Company d ON s.DistributorID = d.CompanyID
        WHERE d.CompanyName='$distEsc'
          AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        GROUP BY p.Category
        ORDER BY TotalRevenue DESC
        ";

        $resultRevenueByCategory = $conn->query($sqlRevenueByCategory);
        $response['revenueByCategory'] = $resultRevenueByCategory ? $resultRevenueByCategory->fetch_all(MYSQLI_ASSOC) : [];


        //-------------------------------------------------------------
        // 6. Price distribution (third plot)
        //-------------------------------------------------------------
        $sqlPriceDistribution = "
        SELECT p.ProductName,
               p.Category,
               sp.SupplyPrice,
               SUM(s.Quantity) AS TotalQuantity
        FROM Shipping s
        JOIN Product p ON s.ProductID = p.ProductID
        LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
        JOIN Company d ON s.DistributorID = d.CompanyID
        WHERE d.CompanyName='$distEsc'
          AND s.ActualDate BETWEEN '$startEsc' AND '$endEsc'
        GROUP BY p.ProductID, p.ProductName, p.Category, sp.SupplyPrice
        ORDER BY sp.SupplyPrice ASC
        ";

        $resultPriceDistribution = $conn->query($sqlPriceDistribution);
        $response['priceDistribution'] = $resultPriceDistribution ? $resultPriceDistribution->fetch_all(MYSQLI_ASSOC) : [];


        //-------------------------------------------------------------
        // 7. Disruption events by category (fourth plot)
        //-------------------------------------------------------------
        $sqlDisruptionsByCategory = "
        SELECT dc.CategoryName AS EventCategory,
               COUNT(DISTINCT de.EventID) AS NumEvents,
               SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END) AS NumHighImpactEvents
        FROM Company d
        LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
        LEFT JOIN DisruptionEvent de ON ic.EventID = de.EventID
        LEFT JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
        WHERE d.CompanyName='$distEsc'
          AND de.EventDate BETWEEN '$startEsc' AND '$endEsc'
        GROUP BY dc.CategoryName
        ORDER BY NumEvents DESC
        ";

        $resultDisruptionsByCategory = $conn->query($sqlDisruptionsByCategory);
        $response['disruptionsByCategory'] = $resultDisruptionsByCategory ? $resultDisruptionsByCategory->fetch_all(MYSQLI_ASSOC) : [];
    }

    echo json_encode($response);
}
