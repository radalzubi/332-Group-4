<?php
// ajax_handler_supply_chain_managers.php
session_start();

// Database Connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);
header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
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
        echo json_encode(['error' => 'Invalid action: ' . $action]);
        break;
}

// Company Info Search 

function companyInfoSearch($conn) {
    $response = ['success' => true];

    if (!$conn || $conn->connect_error) {
        echo json_encode(['success'=>false,'error'=>$conn->connect_error]);
        return;
    }

    $companyName = isset($_POST['companyName']) ? $conn->real_escape_string($_POST['companyName']) : '';
    $startDate = isset($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
    $endDate   = isset($_POST['endDate']) ? $_POST['endDate'] : date('Y-m-d');

    // All from Company
    $sql = "SELECT * FROM Company WHERE CompanyName='$companyName'";
    $response['companyInfo'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // Address (City, Country, Continent)
    $sql = "SELECT 
                c.CompanyID, c.CompanyName, l.City, l.CountryName, l.ContinentName
            FROM Company c
            JOIN Location l ON c.LocationID = l.LocationID
            WHERE c.CompanyName='$companyName'";
    $response['address'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

   // Who they depend on, and who depends on them
    $sql = "SELECT 
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
            WHERE c.CompanyName='$companyName'";
    $response['dependencies'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

   // Most recent financial status
    $sql = "SELECT f.*
            FROM FinancialReport f
            JOIN Company c ON f.CompanyID = c.CompanyID
            WHERE c.CompanyName='$companyName'
            ORDER BY f.RepYear DESC, FIELD(f.Quarter, 'Q4','Q3','Q2','Q1') DESC
            LIMIT 1";
    $response['latestFinancial'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // Capacity (if manufacturer)
    $sql = "SELECT c.CompanyName, m.FactoryCapacity AS TotalCapacity
            FROM Company c
            JOIN Manufacturer m ON c.CompanyID = m.CompanyID
            WHERE c.CompanyName='$companyName'";
    $response['manufacturerCapacity'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // Unique routes operated (if distributor)
    $sql = "SELECT c.CompanyName, COUNT(DISTINCT CONCAT(s.SourceCompanyID,'->',s.DestinationCompanyID)) AS UniqueRoutes
            FROM Company c
            JOIN Distributor d ON c.CompanyID = d.CompanyID
            JOIN Shipping s ON d.CompanyID = s.DistributorID
            WHERE c.CompanyName='$companyName'
            GROUP BY c.CompanyName";
    $response['uniqueRoutes'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // Which products they supply
    $sql = "SELECT p.ProductID, p.ProductName, sp.SupplyPrice
            FROM SuppliesProduct sp
            JOIN Product p ON sp.ProductID = p.ProductID
            JOIN Company c ON sp.SupplierID = c.CompanyID
            WHERE c.CompanyName='$companyName'";
    $response['productsSupplied'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // The diversity of products
    $sql = "SELECT p.Category, COUNT(*) AS TotalProductNumber,
                   ROUND(COUNT(*) * 100.0 / (
                       SELECT COUNT(*) 
                       FROM SuppliesProduct sp2
                       JOIN Company c2 ON sp2.SupplierID = c2.CompanyID
                       WHERE c2.CompanyName='$companyName'
                   ), 4) AS PercentOfTotalProducts
            FROM SuppliesProduct sp
            JOIN Product p ON sp.ProductID = p.ProductID
            JOIN Company c ON sp.SupplierID = c.CompanyID
            WHERE c.CompanyName='$companyName'
            GROUP BY p.Category";
    $response['productDiversity'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // List of transactions - shipping
    $sql = "SELECT t.TransactionID, 'Shipping' AS TransactionType,
                   s.SourceCompanyID, s.DestinationCompanyID,
                   s.Quantity, s.ActualDate AS TransactionDate, p.ProductName
            FROM InventoryTransaction t
            JOIN Shipping s ON t.TransactionID = s.TransactionID
            JOIN Product p ON s.ProductID = p.ProductID
            JOIN Company c ON (s.SourceCompanyID=c.CompanyID OR s.DestinationCompanyID=c.CompanyID)
            WHERE c.CompanyName='$companyName'
              AND s.ActualDate BETWEEN COALESCE('$startDate','1000-01-01') AND COALESCE('$endDate',CURRENT_DATE)
            ORDER BY s.ActualDate DESC";
    $response['shippingTransactions'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // List of transactions - receiving
    $sql = "SELECT t.TransactionID, 'Receiving' AS TransactionType,
                   r.ReceivedDate AS TransactionDate, r.QuantityReceived AS Quantity,
                   r.ReceiverCompanyID, s.SourceCompanyID, p.ProductName
            FROM InventoryTransaction t
            JOIN Receiving r ON t.TransactionID = r.TransactionID
            JOIN Shipping s ON r.ShipmentID = s.ShipmentID
            JOIN Product p ON s.ProductID = p.ProductID
            JOIN Company c ON r.ReceiverCompanyID=c.CompanyID
            WHERE c.CompanyName='$companyName'
              AND r.ReceivedDate BETWEEN COALESCE('$startDate','1000-01-01') AND COALESCE('$endDate',CURRENT_DATE)
            ORDER BY r.ReceivedDate DESC";
    $response['receivingTransactions'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // -------------------------
    // 11. Transactions (Adjustment)
    // -------------------------
    $sql = "SELECT t.TransactionID, 'Adjustment' AS TransactionType,
                   a.AdjustmentDate, a.QuantityChange, a.CompanyID,
                   p.ProductName, a.Reason
            FROM InventoryTransaction t
            JOIN InventoryAdjustment a ON t.TransactionID = a.TransactionID
            JOIN Product p ON a.ProductID = p.ProductID
            JOIN Company c ON a.CompanyID=c.CompanyID
            WHERE c.CompanyName='$companyName'
              AND a.AdjustmentDate BETWEEN COALESCE('$startDate','1000-01-01') AND COALESCE('$endDate',CURRENT_DATE)
            ORDER BY a.AdjustmentDate DESC";
    $response['adjustmentTransactions'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // KPIS - first 2 (on-time delivery rate and avg/standard dev of delay which are distributors only, within user-entered date range)
    $sql = "WITH distributorShipments AS (
                SELECT s.ShipmentID, s.PromisedDate, s.ActualDate,
                       DATEDIFF(s.ActualDate, s.PromisedDate) AS daysDelayed
                FROM Shipping s
                JOIN Company c ON s.DistributorID = c.CompanyID
                WHERE c.CompanyName='$companyName' AND c.Type='Distributor'
                  AND s.ActualDate BETWEEN COALESCE('$startDate','1000-01-01') AND COALESCE('$endDate',CURRENT_DATE)
            )
            SELECT c.CompanyName, c.Type AS CompanyType,
                   CASE WHEN c.Type='Distributor' THEN 
                       (SELECT ROUND(100.0*SUM(CASE WHEN ActualDate<=PromisedDate THEN 1 ELSE 0 END)/NULLIF(COUNT(*),0),4)
                        FROM distributorShipments) ELSE NULL END AS OnTimeDeliveryRate,
                   CASE WHEN c.Type='Distributor' THEN 
                       (SELECT ROUND(AVG(daysDelayed),4) FROM distributorShipments) ELSE NULL END AS AverageDelay,
                   CASE WHEN c.Type='Distributor' THEN 
                       (SELECT ROUND(STDDEV_POP(daysDelayed),4) FROM distributorShipments) ELSE NULL END AS StandardDeviationDelay
            FROM Company c
            WHERE c.CompanyName='$companyName'";
    $response['distributorKPIs'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // KPI financial health status over past year from today - all companies, current date
    $sql = "WITH LastFourQuarters AS (
                SELECT CONCAT('Q', MOD(QUARTER(CURDATE())-n-1+4,4)+1) AS Quarter,
                       YEAR(CURDATE()) - IF(QUARTER(CURDATE())-n<=0,1,0) AS RepYear
                FROM (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS offsets
            )
            SELECT c.CompanyName, fr.CompanyID, fr.Quarter, fr.RepYear, fr.HealthScore
            FROM FinancialReport fr
            JOIN LastFourQuarters lfq ON fr.RepYear=lfq.RepYear AND fr.Quarter=lfq.Quarter
            JOIN Company c ON fr.CompanyID=c.CompanyID
            WHERE c.CompanyName='$companyName'
            ORDER BY fr.RepYear DESC, fr.Quarter DESC";
    $response['financialHealthPastYear'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // KPI distribution of disruption event counts over past year -- all companies
    $sql = "SELECT dc.CategoryName, COUNT(de.EventID) AS EventCount
            FROM DisruptionCategory dc
            LEFT JOIN DisruptionEvent de ON dc.CategoryID=de.CategoryID
                AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            GROUP BY dc.CategoryName
            ORDER BY EventCount DESC";
    $response['disruptionCountsPastYear'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // KPI list of disruption events
    $sql = "SELECT dc.CategoryID, dc.CategoryName, dc.Description,
                   de.EventID, de.EventDate, de.EventRecoveryDate
            FROM DisruptionEvent de
            JOIN DisruptionCategory dc ON de.CategoryID=dc.CategoryID
            WHERE de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ORDER BY de.EventDate DESC";
    $response['disruptionEventsPastYear'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    echo json_encode($response);
}

// Disruption events
function disruptionEventsDisplay($conn) {
    $response = ['success'=>true];

    $companyName   = isset($_POST['companyName']) ? $_POST['companyName'] : null;
    $region        = isset($_POST['region']) ? $_POST['region'] : null;
    $tier          = isset($_POST['tier']) ? $_POST['tier'] : null;
    $startDate     = isset($_POST['startDate']) ? $_POST['startDate'] : null;
    $endDate       = isset($_POST['endDate']) ? $_POST['endDate'] : null;

    $startDate = $startDate ?: '1000-01-01';
    $endDate   = $endDate ?: date('Y-m-d');

    $sql = "
    SELECT c.CompanyID, c.CompanyName, c.TierLevel, l.ContinentName AS Region,
           de.EventID, de.EventDate, de.EventRecoveryDate, ic.ImpactLevel
    FROM DisruptionEvent de
    JOIN ImpactsCompany ic ON de.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    WHERE de.EventDate BETWEEN '".$conn->real_escape_string($startDate)."' 
                            AND '".$conn->real_escape_string($endDate)."'
    ".($companyName ? "AND c.CompanyName='".$conn->real_escape_string($companyName)."'" : "")."
    ".($region ? "AND l.ContinentName='".$conn->real_escape_string($region)."'" : "")."
    ".($tier ? "AND c.TierLevel='".$conn->real_escape_string($tier)."'" : "")."
    ORDER BY de.EventDate DESC
    ";
    $disruptions = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $response['disruptionsInRange'] = $disruptions;

    $companyMetrics = [];
    $totalDisruptions = count($disruptions);

    foreach ($disruptions as $d) {
        $cid = $d['CompanyID'];
        if (!isset($companyMetrics[$cid])) {
            $companyMetrics[$cid] = [
                'CompanyID'=>$cid,
                'CompanyName'=>$d['CompanyName'],
                'Region'=>$d['Region'],
                'Ndisruptions'=>0,
                'DF_quarters'=>[],
                'RecoveryDays'=>[],
                'HighImpact'=>0
            ];
        }
        $companyMetrics[$cid]['Ndisruptions']++;
        // DF 
        $yr_q = date('Y', strtotime($d['EventDate'])).'-'.ceil(date('n', strtotime($d['EventDate']))/3);
        $companyMetrics[$cid]['DF_quarters'][$yr_q] = 1;
        // ART
        if ($d['EventRecoveryDate']) {
            $companyMetrics[$cid]['RecoveryDays'][] = (strtotime($d['EventRecoveryDate']) - strtotime($d['EventDate'])) / (60*60*24);
        }
        // HDR
        if ($d['ImpactLevel']=='High') $companyMetrics[$cid]['HighImpact']++;
    }

    $dfList = $artList = $hdrList = $tdList = [];
    foreach ($companyMetrics as $m) {
        $quartersCount = count($m['DF_quarters']);
        $DF = $quartersCount ? $m['Ndisruptions']/$quartersCount : null;
        $ART = count($m['RecoveryDays']) ? round(array_sum($m['RecoveryDays'])/count($m['RecoveryDays']),2) : null;
        $HDR = $m['Ndisruptions'] ? round(100*$m['HighImpact']/$m['Ndisruptions'],2) : null;
        $TD  = count($m['RecoveryDays']) ? array_sum($m['RecoveryDays']) : null;

        $dfList[]  = ['CompanyID'=>$m['CompanyID'],'CompanyName'=>$m['CompanyName'],'DF'=>$DF];
        $artList[] = ['CompanyID'=>$m['CompanyID'],'CompanyName'=>$m['CompanyName'],'ART'=>$ART];
        $hdrList[] = ['CompanyID'=>$m['CompanyID'],'CompanyName'=>$m['CompanyName'],'HDR'=>$HDR];
        $tdList[]  = ['CompanyID'=>$m['CompanyID'],'CompanyName'=>$m['CompanyName'],'TD'=>$TD];
    }

    $response['DF']  = $dfList;
    $response['ART'] = $artList;
    $response['HDR'] = $hdrList;
    $response['TD']  = $tdList;

    // RRC
    $regionCounts = [];
    foreach ($disruptions as $d) {
        $r = $d['Region'];
        if (!isset($regionCounts[$r])) $regionCounts[$r]=0;
        $regionCounts[$r]++;
    }
    $rrcList = [];
    foreach ($regionCounts as $r=>$count) {
        $rrcList[] = ['Region'=>$r,'RRC'=>round(100*$count/$totalDisruptions,2)];
    }
    $response['RRC'] = $rrcList;

    // DSD
    $severityCounts = ['Low'=>0,'Medium'=>0,'High'=>0];
    foreach ($disruptions as $d) {
        if (isset($severityCounts[$d['ImpactLevel']])) $severityCounts[$d['ImpactLevel']]++;
    }
    $severityList = [];
    foreach (['Low','Medium','High'] as $lvl) {
        $severityList[] = ['ImpactLevel'=>$lvl,'Count'=>$severityCounts[$lvl]];
    }
    $response['SeverityDistribution'] = $severityList;

    echo json_encode($response);
}

//Alerts for new/ongoing disruptions
function alertsOngoingDisruptions($conn) {
    $response = ['success'=>true];

    $sql = "SELECT c.CompanyName, de.EventID, de.EventDate AS StartDate, dc.CategoryName
            FROM DisruptionEvent de
            JOIN DisruptionCategory dc ON de.CategoryID=dc.CategoryID
            JOIN ImpactsCompany ic ON de.EventID=ic.EventID
            JOIN Company c ON ic.AffectedCompanyID=c.CompanyID
            WHERE de.EventRecoveryDate IS NULL OR de.EventRecoveryDate > CURRENT_DATE()
            ORDER BY de.EventDate DESC";
    $response['ongoingAlerts'] = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    echo json_encode($response);
}

// Examination of all Transactions

function transactionsExamination($conn) {
    $response = ['success'=>true];

    $startDate = isset($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
    $endDate   = isset($_POST['endDate']) ? $_POST['endDate'] : date('Y-m-d');
    $location  = isset($_POST['location']) ? $_POST['location'] : null;
    $sourceCompany = isset($_POST['sourceCompany']) ? $_POST['sourceCompany'] : null;
    $destinationCompany = isset($_POST['destinationCompany']) ? $_POST['destinationCompany'] : null;
    $distributor = isset($_POST['distributor']) ? $_POST['distributor'] : null;

    // --- Shipping ---
    $sqlShipping = "
    SELECT 
        s.ShipmentID, s.DistributorID, d.CompanyName AS DistributorName,
        s.ProductID, p.ProductName, p.Category,
        s.SourceCompanyID, cs.CompanyName AS SourceCompany,
        s.DestinationCompanyID, cd.CompanyName AS DestinationCompany,
        s.ActualDate, s.PromisedDate, s.Quantity,
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
    WHERE s.ActualDate BETWEEN '".$conn->real_escape_string($startDate)."' AND '".$conn->real_escape_string($endDate)."'
    ".($sourceCompany ? "AND cs.CompanyName='".$conn->real_escape_string($sourceCompany)."'" : "")."
    ".($destinationCompany ? "AND cd.CompanyName='".$conn->real_escape_string($destinationCompany)."'" : "")."
    ".($distributor ? "AND d.CompanyName='".$conn->real_escape_string($distributor)."'" : "")."
    ".($location ? "AND (ls.CountryName='".$conn->real_escape_string($location)."' OR ls.ContinentName='".$conn->real_escape_string($location)."' OR ls.CityName='".$conn->real_escape_string($location)."' OR ld.CountryName='".$conn->real_escape_string($location)."' OR ld.ContinentName='".$conn->real_escape_string($location)."' OR ld.CityName='".$conn->real_escape_string($location)."')" : "")."
    ORDER BY s.ActualDate DESC
    ";
    $response['shipping'] = $conn->query($sqlShipping)->fetch_all(MYSQLI_ASSOC);

    // --- Receiving ---
    $sqlReceiving = "
    SELECT 
        r.ReceivingID, r.TransactionID, r.ShipmentID,
        r.ReceiverCompanyID, c.CompanyName AS ReceiverCompany,
        r.ReceivedDate, r.QuantityReceived,
        s.SourceCompanyID, s.DestinationCompanyID, s.DistributorID,
        s.ProductID, s.ActualDate
    FROM Receiving r
    JOIN Shipping s ON r.ShipmentID = s.ShipmentID
    JOIN Company c ON r.ReceiverCompanyID = c.CompanyID
    WHERE s.ActualDate BETWEEN '".$conn->real_escape_string($startDate)."' AND '".$conn->real_escape_string($endDate)."'
    ".($sourceCompany ? "AND s.SourceCompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($sourceCompany)."' LIMIT 1)" : "")."
    ".($destinationCompany ? "AND r.ReceiverCompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($destinationCompany)."' LIMIT 1)" : "")."
    ".($distributor ? "AND s.DistributorID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($distributor)."' LIMIT 1)" : "")."
    ORDER BY r.ReceivedDate DESC
    ";
    $response['receiving'] = $conn->query($sqlReceiving)->fetch_all(MYSQLI_ASSOC);

    // --- Adjustments ---
    $sqlAdjustment = "
    SELECT 
        a.AdjustmentID, a.TransactionID, a.CompanyID, c.CompanyName, 
        a.ProductID, p.ProductName, a.AdjustmentDate, a.QuantityChange, a.Reason
    FROM InventoryAdjustment a
    JOIN Company c ON a.CompanyID = c.CompanyID
    JOIN Product p ON a.ProductID = p.ProductID
    WHERE a.AdjustmentDate BETWEEN '".$conn->real_escape_string($startDate)."' AND '".$conn->real_escape_string($endDate)."'
    ".($sourceCompany ? "AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($sourceCompany)."' LIMIT 1)" : "")."
    ".($destinationCompany ? "AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($destinationCompany)."' LIMIT 1)" : "")."
    ".($distributor ? "AND a.CompanyID = (SELECT CompanyID FROM Company WHERE CompanyName='".$conn->real_escape_string($distributor)."' LIMIT 1)" : "")."
    ORDER BY a.AdjustmentDate DESC
    ";
    $response['adjustment'] = $conn->query($sqlAdjustment)->fetch_all(MYSQLI_ASSOC);

    // --- Distributor-specific details ---
if ($distributor) {
    $distEscaped = $conn->real_escape_string($distributor);

    // 8. Shipping volume summary
$sqlShippingVolume = "
SELECT d.CompanyName AS DistributorName,
       SUM(s.Quantity) AS TotalQuantityShipped,
       COUNT(DISTINCT s.ShipmentID) AS TotalShipments,
       COUNT(DISTINCT s.ProductID) AS NumProductsHandled
FROM Shipping s
JOIN Company d ON s.DistributorID = d.CompanyID
WHERE d.CompanyName='$distEscaped'
  AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
";
$response['shippingVolume'] = $conn->query($sqlShippingVolume)->fetch_all(MYSQLI_ASSOC);

    // On-time delivery rate per distributor
    $sqlOnTimeRate = "
    SELECT d.CompanyName AS DistributorName,
           ROUND(
               100 * SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) 
               / GREATEST(COUNT(*),1), 2
           ) AS onTimeDeliveryRate_pct
    FROM Shipping s
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY d.CompanyName
    ";
    $response['onTimeDeliveryRate'] = $conn->query($sqlOnTimeRate)->fetch_all(MYSQLI_ASSOC);
}

    // 1. Products handled
    $sqlProducts = "
    SELECT p.ProductID, p.ProductName, p.Category, sp.SupplyPrice,
           COUNT(*) AS NumShipments,
           SUM(s.Quantity) AS TotalQuantity,
           (COALESCE(sp.SupplyPrice,0) * SUM(s.Quantity)) AS TotalRevenuePerProduct,
           s.DistributorID
    FROM Shipping s
    JOIN Product p ON s.ProductID = p.ProductID
    LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY p.ProductID, p.ProductName, p.Category, sp.SupplyPrice, s.DistributorID
    ";
    $productsHandled = $conn->query($sqlProducts)->fetch_all(MYSQLI_ASSOC);
    $response['productsHandled'] = $productsHandled;

    $distributorID = !empty($productsHandled) ? $productsHandled[0]['DistributorID'] : null;

    // 2. Status of shipments (On Time / Late / Out)
    $sqlKPIs = "
    SELECT d.CompanyName AS DistributorName,
           COUNT(DISTINCT s.ShipmentID) AS shipmentCount,
           COALESCE(SUM(s.Quantity),0) AS shipmentVolume,
           SUM(CASE WHEN s.ActualDate IS NULL THEN 1 ELSE 0 END) AS shipmentsOutOnDelivery,
           SUM(CASE WHEN s.ActualDate > s.PromisedDate THEN 1 ELSE 0 END) AS lateShipments,
           SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) AS onTimeShipments
    FROM Shipping s
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    ";
    $response['distributorKPIs'] = $conn->query($sqlKPIs)->fetch_all(MYSQLI_ASSOC);

    // 3. Disruption exposure for distributor
    $sqlExposure = "
    SELECT d.CompanyName,
           COUNT(DISTINCT de.EventID) AS Ndisruptions,
           (COUNT(DISTINCT de.EventID) + 2 * COALESCE(SUM(CASE WHEN ic.ImpactLevel='High' THEN 1 ELSE 0 END),0)) AS disruptionExposure
    FROM Company d
    LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
    LEFT JOIN DisruptionEvent de ON ic.EventID = de.EventID
    WHERE d.CompanyName='$distEscaped'
      AND (de.EventDate BETWEEN '$startDate' AND '$endDate' OR de.EventDate IS NULL)
    GROUP BY d.CompanyName
    ";
    $response['disruptionExposure'] = $conn->query($sqlExposure)->fetch_all(MYSQLI_ASSOC);

    // 4. Shipments per category (for first plot)
    $sqlShipmentsByCategory = "
    SELECT p.Category,
           SUM(s.Quantity) AS TotalQuantity,
           COUNT(*) AS NumShipments
    FROM Shipping s
    JOIN Product p ON s.ProductID = p.ProductID
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY p.Category
    ORDER BY p.Category
    ";
    $response['shipmentsByCategory'] = $conn->query($sqlShipmentsByCategory)->fetch_all(MYSQLI_ASSOC);

    // 5. Revenue per category (for second plot)
    $sqlRevenueByCategory = "
    SELECT p.Category,
           SUM(COALESCE(sp.SupplyPrice,0) * s.Quantity) AS TotalRevenue
    FROM Shipping s
    JOIN Product p ON s.ProductID = p.ProductID
    LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY p.Category
    ORDER BY TotalRevenue DESC
    ";
    $response['revenueByCategory'] = $conn->query($sqlRevenueByCategory)->fetch_all(MYSQLI_ASSOC);

    // 6. Price distribution (third plot)
    $sqlPriceDistribution = "
    SELECT p.ProductName, p.Category,
           sp.SupplyPrice,
           SUM(s.Quantity) AS TotalQuantity
    FROM Shipping s
    JOIN Product p ON s.ProductID = p.ProductID
    LEFT JOIN SuppliesProduct sp ON sp.ProductID = s.ProductID
    JOIN Company d ON s.DistributorID = d.CompanyID
    WHERE d.CompanyName='$distEscaped'
      AND s.ActualDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY p.ProductID, p.ProductName, p.Category, sp.SupplyPrice
    ORDER BY sp.SupplyPrice ASC
    ";
    $response['priceDistribution'] = $conn->query($sqlPriceDistribution)->fetch_all(MYSQLI_ASSOC);

    // 7. Disruption events by category (fourth plot)
    $sqlDisruptionsByCategory = "
    SELECT dc.CategoryName AS EventCategory,
           COUNT(DISTINCT de.EventID) AS NumEvents,
           SUM(CASE WHEN ic.ImpactLevel='High' THEN 1 ELSE 0 END) AS NumHighImpactEvents
    FROM Company d
    LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
    LEFT JOIN DisruptionEvent de ON ic.EventID = de.EventID
    LEFT JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
    WHERE d.CompanyName='$distEscaped'
      AND (de.EventDate BETWEEN '$startDate' AND '$endDate')
    GROUP BY dc.CategoryName
    ORDER BY NumEvents DESC
    ";
    $response['disruptionsByCategory'] = $conn->query($sqlDisruptionsByCategory)->fetch_all(MYSQLI_ASSOC);
}

    echo json_encode($response);
}
if ($conn) {
    $conn->close();
}
?>
