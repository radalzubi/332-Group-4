<?php
date_default_timezone_set('America/New_York');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//actual_supply_chain_manager.php
session_start();

// Database connection ($conn), configured in config.php
require_once __DIR__ . '/config.php';

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
    case 'saveCompanyUpdate':
        saveCompanyUpdate($conn);
        break;
    case 'saveTransactionDraft':
        saveTransactionDraft($conn);
        break;
    case 'disruptionEventsDisplay':
        disruptionEventsDisplay($conn);
        break;
    case 'regionalRiskHeatmap':
        regionalRiskHeatmap($conn);
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

// add/update a company, store inputs
function saveCompanyUpdate($conn) {
    $companyId  = isset($_POST['companyId']) ? trim($_POST['companyId']) : '';
    $name       = isset($_POST['name']) ? trim($_POST['name']) : '';
    $type       = isset($_POST['type']) ? trim($_POST['type']) : '';
    $capability = isset($_POST['capability']) ? trim($_POST['capability']) : '';
    $notes      = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if ($companyId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing company ID']);
        return;
    }
    $createTable = "CREATE TABLE IF NOT EXISTS CompanyUpdates (
        UpdateID INT AUTO_INCREMENT PRIMARY KEY,
        CompanyID VARCHAR(50) NOT NULL,
        CompanyName VARCHAR(255) DEFAULT NULL,
        CompanyType VARCHAR(100) DEFAULT NULL,
        Capability TEXT DEFAULT NULL,
        Notes TEXT DEFAULT NULL,
        UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($createTable)) {
        echo json_encode(['success' => false, 'error' => 'Unable to prepare updates table']);
        return;
    }

// adding
    $insertSql = "INSERT INTO CompanyUpdates (CompanyID, CompanyName, CompanyType, Capability, Notes) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    if ($stmt) {
        $stmt->bind_param("sssss", $companyId, $name, $type, $capability, $notes);
        $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Unable to save company update']);
        return;
    }
    // back to main
    $updateSql = "UPDATE Company SET 
        CompanyName = CASE WHEN ? <> '' THEN ? ELSE CompanyName END,
        Type = CASE WHEN ? <> '' THEN ? ELSE Type END
        WHERE CompanyID = ?";
    $updateStmt = $conn->prepare($updateSql);
    if ($updateStmt) {
        $updateStmt->bind_param("sssss", $name, $name, $type, $type, $companyId);
        $updateStmt->execute();
        $updateStmt->close();
    }
    if ($capability !== '' && $type !== '') {
        if (strcasecmp($type, 'Manufacturer') === 0) {
            $capSql = "INSERT INTO Manufacturer (CompanyID, FactoryCapacity)
                       VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE FactoryCapacity = VALUES(FactoryCapacity)";
            $capStmt = $conn->prepare($capSql);
            if ($capStmt) {
                $capStmt->bind_param("ss", $companyId, $capability);
                $capStmt->execute();
                $capStmt->close();
            }
        } elseif (strcasecmp($type, 'Distributor') === 0) {
            $routesSql = "INSERT INTO Distributor (CompanyID, UniqueRoutes)
                          VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE UniqueRoutes = VALUES(UniqueRoutes)";
            $routesStmt = $conn->prepare($routesSql);
            if ($routesStmt) {
                $routesStmt->bind_param("ss", $companyId, $capability);
                $routesStmt->execute();
                $routesStmt->close();
            }
        }
    }

    echo json_encode(['success' => true]);
}
// in transactions
function saveTransactionDraft($conn) {
    $companyId       = isset($_POST['companyId']) ? trim($_POST['companyId']) : '';
    $transactionDate = isset($_POST['transactionDate']) ? trim($_POST['transactionDate']) : '';
    $quantity        = isset($_POST['quantity']) ? trim($_POST['quantity']) : '';
    $product         = isset($_POST['product']) ? trim($_POST['product']) : '';
    $details         = isset($_POST['details']) ? trim($_POST['details']) : '';
    $transactionDate = $transactionDate !== '' ? $transactionDate : null;
    $quantityValue   = $quantity !== '' ? $quantity : null;
    if ($companyId === '') {
        echo json_encode(['success' => false, 'error' => 'Missing company ID']);
        return;
    }
    $createTable = "CREATE TABLE IF NOT EXISTS TransactionDrafts (
        DraftID INT AUTO_INCREMENT PRIMARY KEY,
        CompanyID VARCHAR(50) NOT NULL,
        TransactionDate DATE DEFAULT NULL,
        Quantity DECIMAL(18, 2) DEFAULT NULL,
        Product VARCHAR(255) DEFAULT NULL,
        Details TEXT DEFAULT NULL,
        CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($createTable)) {
        echo json_encode(['success' => false, 'error' => 'Unable to prepare transaction table']);
        return;
    }
    $insertSql = "INSERT INTO TransactionDrafts (CompanyID, TransactionDate, Quantity, Product, Details)
                  VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);

    if ($stmt) {
        $stmt->bind_param("sssss", $companyId, $transactionDate, $quantityValue, $product, $details);
        $stmt->execute();
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Unable to save transaction draft']);
        return;
    }
    echo json_encode(['success' => true]);
}

// company info (searchable by name)
function companyInfoSearch($conn) {

    $response = [
    'success' => true,
    'data' => [] ];
    $identifier = isset($_POST['companyName']) ? trim($_POST['companyName']) : '';
    $companyName = $conn->real_escape_string($identifier);
    $startDateRaw = isset($_POST['startDate']) ? trim($_POST['startDate']) : '';
    $endDateRaw   = isset($_POST['endDate'])   ? trim($_POST['endDate'])   : '';

    // date needs to be year month day format
    $validateDate = function($date) {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    };

    $startDate = $validateDate($startDateRaw) ? $startDateRaw : '1000-01-01';
    $endDate   = $validateDate($endDateRaw)   ? $endDateRaw   : date('Y-m-d');

    $startDate = $conn->real_escape_string($startDate);
    $endDate   = $conn->real_escape_string($endDate);
    if ($identifier === '') {
        echo json_encode([
            'success' => false,
            'error' => 'No company provided'
        ]);
        return;
    }
    $companyWhere = is_numeric($identifier)
        ? "c.CompanyID='$companyName'"
        : "c.CompanyName='$companyName'";

    // basic from company table (includes company type and tier level)
    $sql = "SELECT * FROM Company c WHERE $companyWhere";
    $result = $conn->query($sql);
    $response['companyInfo'] = $result ? fetchAll($result) : [];

    // if invalid input for company or mismatch 
    if (count($response['companyInfo']) === 0) {
        echo json_encode([
            'success' => false,
            'error'   => 'Company not found'
        ]);
        return;
    }
    $companyIdFilter = $conn->real_escape_string($response['companyInfo'][0]['CompanyID']);
    $companyIdWhere  = "c.CompanyID = '$companyIdFilter'";
    //address
    $sql = "
    SELECT c.CompanyID, c.CompanyName, l.City, l.CountryName, l.ContinentName
    FROM Company c
    JOIN Location l ON c.LocationID = l.LocationID
    WHERE $companyIdWhere
    ";
    $result = $conn->query($sql);
    $response['address'] = $result ? fetchAll($result) : [];
    // who they depend on and who depends on them
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
    WHERE $companyIdWhere
    ";
    $result = $conn->query($sql);
    $response['dependencies'] = $result ? fetchAll($result) : [];
    // most recent financial status
    $sql = "
    SELECT f.*
    FROM FinancialReport f
    JOIN Company c ON f.CompanyID = c.CompanyID
    WHERE $companyIdWhere
    ORDER BY f.RepYear DESC, FIELD(f.Quarter,'Q4','Q3','Q2','Q1') DESC
    LIMIT 1
    ";
    $result = $conn->query($sql);
    $response['latestFinancial'] = $result ? fetchAll($result) : [];
    // capacity (if manufacturer)
    $sql = "
    SELECT c.CompanyName, m.FactoryCapacity
    FROM Company c
    JOIN Manufacturer m ON c.CompanyID = m.CompanyID
    WHERE $companyIdWhere
    ";
    $result = $conn->query($sql);
    $response['manufacturerCapacity'] = $result ? fetchAll($result) : [];
    // unique routes operated (if distributor)
    $sql = "
    SELECT c.CompanyName,
        COALESCE(NULLIF(MAX(d.UniqueRoutes), ''),
        COUNT(DISTINCT CONCAT(s.SourceCompanyID, '->', s.DestinationCompanyID))) AS UniqueRoutes
    FROM Company c
        JOIN Distributor d ON c.CompanyID = d.CompanyID
        LEFT JOIN Shipping s ON d.CompanyID = s.DistributorID
    WHERE $companyIdWhere
    GROUP BY c.CompanyName
    ";
    $result = $conn->query($sql);
    $response['uniqueRoutes'] = $result ? fetchAll($result) : [];
    // which products they supply, diversity of products
    $sql = "
    SELECT p.ProductID, p.ProductName, sp.SupplyPrice
    FROM SuppliesProduct sp
    JOIN Product p ON sp.ProductID = p.ProductID
    JOIN Company c ON sp.SupplierID = c.CompanyID
    WHERE $companyIdWhere
    ";
    $result = $conn->query($sql);
    $response['productsSupplied'] = $result ? fetchAll($result) : [];
// TRANSACTIONS
    // shipping
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
        WHERE $companyIdWhere
          AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY s.ActualDate DESC";

$result = $conn->query($sql);
$response['data']['shippingTransactions'] = $result ? fetchAll($result) : [];

// receiving
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
        WHERE $companyIdWhere
          AND r.ReceivedDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY r.ReceivedDate DESC";
$result = $conn->query($sql);
$response['data']['receivingTransactions'] = $result ? fetchAll($result) : [];

// adjustments
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
        WHERE $companyIdWhere
          AND a.AdjustmentDate BETWEEN '$startDate' AND '$endDate'
        ORDER BY a.AdjustmentDate DESC";

$result = $conn->query($sql);
$response['data']['adjustmentTransactions'] = $result ? fetchAll($result) : [];

// KPIS
    // KPIs only for distributors (on time delivery rate, average and standard deviation of delay)
    $sql = "WITH distributorShipments AS (
            SELECT
                s.ShipmentID,
                COALESCE(s.PromisedDate, s.ActualDate, r.ReceivedDate, CURDATE()) AS PromisedDate,
                COALESCE(r.ReceivedDate, s.ActualDate, CURDATE()) AS DeliveryDate,
                DATEDIFF(
                    COALESCE(r.ReceivedDate, s.ActualDate, CURDATE()),
                    COALESCE(s.PromisedDate, s.ActualDate, r.ReceivedDate, CURDATE())
                ) AS daysDelayed
            FROM Shipping s
                LEFT JOIN Receiving r ON r.ShipmentID = s.ShipmentID
                JOIN Company c ON s.DistributorID = c.CompanyID
            WHERE $companyIdWhere
              AND c.Type = 'Distributor'
              AND COALESCE(r.ReceivedDate, s.ActualDate, s.PromisedDate, CURDATE()) BETWEEN '$startDate' AND '$endDate'
        )
        SELECT
            c.CompanyName,
            c.Type AS CompanyType,
            CASE WHEN c.Type = 'Distributor' THEN
                (
                    SELECT CASE WHEN COUNT(*) = 0 THEN 0 ELSE ROUND(
                        100.0 * COALESCE(SUM(CASE WHEN DeliveryDate <= PromisedDate THEN 1 ELSE 0 END), 0)
                        / COUNT(*),
                        4
                    ) END
                    FROM distributorShipments
                )
            ELSE NULL END AS OnTimeDeliveryRate,
            CASE WHEN c.Type = 'Distributor' THEN
                (SELECT ROUND(COALESCE(AVG(daysDelayed), 0), 4) FROM distributorShipments)
            ELSE NULL END AS AverageDelay,
            CASE WHEN c.Type = 'Distributor' THEN
                (SELECT ROUND(COALESCE(STDDEV_POP(daysDelayed), 0), 4) FROM distributorShipments)
            ELSE NULL END AS StandardDeviationDelay
        FROM Company c
        WHERE $companyIdWhere";

$result = $conn->query($sql);
$response['data']['distributorKPIs'] = $result ? fetchAll($result) : [];


// KPI for all, financial health over past year from today (current date on the computer)
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
        WHERE $companyIdWhere
        ORDER BY fr.RepYear DESC, fr.Quarter DESC";

$result = $conn->query($sql);
$response['data']['financialHealthPastYear'] = $result ? fetchAll($result) : [];

//KPI for all, distribution of disruption event counts over past year and list of events
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


// DISRUPTION EVENTS DISPLAY (by company, region, tier, over period of time)
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
        JOIN ImpactsCompany ic ON de.EventID = ic.EventID
        JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
        JOIN Location l ON c.LocationID = l.LocationID
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

        // Disruption Frequency (DF)
        if (!empty($d['EventDate'])) {
            $yr_q = date('Y', strtotime($d['EventDate'])) . '-Q' . ceil(date('n', strtotime($d['EventDate'])) / 3);
            $companyMetrics[$cid]['DF_quarters'][$yr_q] = 1;
        }
        // Average Recovery Time (ART)
        if (!empty($d['EventRecoveryDate'])) {
            $companyMetrics[$cid]['RecoveryDays'][] =
                (strtotime($d['EventRecoveryDate']) - strtotime($d['EventDate'])) / (60 * 60 * 24);
        }
        // High-Impact Disruption Rate (HDR)
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

    // Regional Risk Concentration RRC
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
            $rrcList[] = [
                'Region' => $r,
                'RRC'    => round($count / $totalDisruptions, 4),
                'Count'  => $count,
                'Total'  => $totalDisruptions
            ];
        }
    } else {
    $rrcList[] = [
        'Region' => 'No Data',
        'RRC'    => 0,
        'Count'  => 0,
        'Total'  => 0
    ];
}
$response['data']['RRC'] = $rrcList;

    // Disruption Severity Distribution (DSD)
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


// making heatmap for regional risk concentration RRC
function regionalRiskHeatmap($conn) {
    $startDate = isset($_POST['startDate']) && $_POST['startDate'] !== ''
                 ? $conn->real_escape_string($_POST['startDate'])
                 : '1000-01-01';
    $endDate = isset($_POST['endDate']) && $_POST['endDate'] !== ''
               ? $conn->real_escape_string($_POST['endDate'])
               : date('Y-m-d');
    $sql = "
        SELECT
            region_counts.Region,
            region_counts.RegionDisruptions,
            totals.TotalDisruptions,
            CASE WHEN totals.TotalDisruptions > 0
                 THEN ROUND(100 * region_counts.RegionDisruptions / totals.TotalDisruptions, 2)
                 ELSE 0
            END AS DisruptionPercent
        FROM (
            SELECT
                COALESCE(l.ContinentName, 'Unknown') AS Region,
                COUNT(*) AS RegionDisruptions
            FROM DisruptionEvent de
            JOIN ImpactsCompany ic ON de.EventID = ic.EventID
            JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
            LEFT JOIN Location l ON c.LocationID = l.LocationID
            WHERE de.EventDate BETWEEN ? AND ?
            GROUP BY Region
        ) AS region_counts
        CROSS JOIN (
            SELECT COUNT(*) AS TotalDisruptions
            FROM DisruptionEvent de
            JOIN ImpactsCompany ic ON de.EventID = ic.EventID
            WHERE de.EventDate BETWEEN ? AND ?
        ) AS totals
        ORDER BY DisruptionPercent DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'error'   => 'Failed to prepare regional risk query'
        ]);
        return;
    }
    $stmt->bind_param('ssss', $startDate, $endDate, $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $regions = fetchAll($result);
    $totalDisruptions = 0;
    if (count($regions) > 0) {
        $totalDisruptions = isset($regions[0]['TotalDisruptions'])
            ? (int)$regions[0]['TotalDisruptions']
            : 0;
    }
    echo json_encode([
        'success' => true,
        'data' => [
            'regions' => $regions,
            'totalDisruptions' => $totalDisruptions,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]
    ]);
}

// ALERTS
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
    WHERE de.EventRecoveryDate IS NULL OR de.EventRecoveryDate > CURRENT_DATE()
    ORDER BY de.EventDate DESC
    ";
    $result = $conn->query($sql);
    $response['data']['ongoingAlerts'] = $result ? fetchAll($result) : [];
    echo json_encode($response);
}


// ABILITY TO EXAMINE ALL TRANSACTIONS within time range or location, any leaving specific company or arriving at
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
    //shipments
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
    //receiving
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

    // adjustments
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
// distributor-specific details about transactions
    if ($distributor) {
        $distEsc = $conn->real_escape_string($distributor);
        // shipment volume
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
        // on-time delivery rate
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
        // products handlded
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
        // which shipments are currently out/status distribution
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
        // disruption exposure
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
        // 1/4 useful plots shipments by category
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
        // 2/4 useful plots revenue by category
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
        // 3/4 useful plots price distribution
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
        // 4/4 useful plots disruption events by category
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
