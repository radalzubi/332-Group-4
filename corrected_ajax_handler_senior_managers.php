<?php
// corrected_ajax_handler_senior_managers.php

session_start();

// Database connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'get_network_overview':
        $response['data'] = getNetworkOverview($conn);
        break;

    case 'avg_financial_health':
        $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
        $endDate   = !empty($_POST['endDate']) ? $_POST['endDate'] : date("Y-m-d");
        $type      = !empty($_POST['companyType']) ? $_POST['companyType'] : null;
        $response['data'] = avgFinancialHealth($conn, $startDate, $endDate, $type);
        break;

    case 'regional_disruption_overview':
        $response['data'] = regionalDisruptionOverview($conn);
        break;

    case 'most_critical_companies':
        $response['data'] = mostCriticalCompanies($conn);
        break;

    case 'disruption_frequency':
        $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
        $endDate   = !empty($_POST['endDate']) ? $_POST['endDate'] : date("Y-m-d");
        $response['data'] = disruptionFrequency($conn, $startDate, $endDate);
        break;

    case 'company_financials_by_region':
        $region = !empty($_POST['region']) ? $_POST['region'] : '';
        $response['data'] = companyFinancialsByRegion($conn, $region);
        break;

    case 'top_distributors_volume':
        $response['data'] = topDistributorsByVolume($conn);
        break;

    case 'distributor_details':
        $name = !empty($_POST['distributorName']) ? $_POST['distributorName'] : '';
        $response['data'] = distributorDetailsByName($conn, $name);
        break;

    case 'companies_by_event':
        $eventID = isset($_POST['eventID']) ? (int)$_POST['eventID'] : 0;
        $response['data'] = companiesAffectedByDisruptionEvent($conn, $eventID);
        break;

    case 'disruptions_by_company':
        $name = !empty($_POST['companyName']) ? $_POST['companyName'] : '';
        $response['data'] = getDisruptionsByCompanyName($conn, $name);
        break;

    case 'distributors_by_delay':
        $response['data'] = distributorsByAverageDelay($conn);
        break;

    case 'add_new_company':
        $name = !empty($_POST['companyName']) ? $_POST['companyName'] : '';
        $location = isset($_POST['locationID']) ? (int)$_POST['locationID'] : 0;
        $tier = !empty($_POST['tierLevel']) ? $_POST['tierLevel'] : '';
        $type = !empty($_POST['companyType']) ? $_POST['companyType'] : '';
        $response['data'] = addNewCompany($conn, $name, $location, $tier, $type);
        break;

    case 'criticality_by_region':
        $response['data'] = getCriticalityByRegion($conn);
        break;

    case 'top_distributors_shipments':
        $response['data'] = topDistributorsByNumberOfShipments($conn);
        break;

    default:
        $response = ["success" => false, "error" => "Invalid action: $action"];
        break;
}

echo json_encode($response, JSON_PRETTY_PRINT);
exit();


function safeQuery($conn, $sql) {
    $result = $conn->query($sql);
    if ($result === false) {
        die("SQL Error: " . $conn->error . "<br>Query: " . $sql);
    }
    // For INSERT, UPDATE, DELETE, etc.
    if ($result === true) {
        return true;
    }
    // For SELECT queries (no mysqlnd dependency)
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function getNetworkOverview($conn) {
    return [
        'totalCompanies' => 0,
        'totalConnections' => 0,
        'activeDisruptions' => 0,
        'avgNetworkHealth' => 0,
        'companyTypes' => []
    ];
}

function avgFinancialHealth($conn, $startDate, $endDate, $type = null) {
    $startDate = $conn->real_escape_string($startDate);
    $endDate   = $conn->real_escape_string($endDate);
    $typeCondition = '';
    if ($type !== null) {
        $type = $conn->real_escape_string($type);
        $typeCondition = " AND c.Type = '$type' ";
    }
    $sql = "
        SELECT 
            c.CompanyID,
            c.CompanyName,
            c.Type AS CompanyType,
            AVG(f.HealthScore) AS avgHealthScore
        FROM Company c
        JOIN FinancialReport f ON c.CompanyID = f.CompanyID
        WHERE STR_TO_DATE(
            CONCAT(f.RepYear, '-', 
                CASE f.Quarter
                    WHEN 'Q1' THEN '01-01'
                    WHEN 'Q2' THEN '04-01'
                    WHEN 'Q3' THEN '07-01'
                    WHEN 'Q4' THEN '10-01'
                    ELSE '01-01'
                END
            ), '%Y-%m-%d'
        ) BETWEEN '$startDate' AND '$endDate'
        $typeCondition
        GROUP BY c.CompanyID, c.CompanyName, c.Type
        ORDER BY avgHealthScore DESC;
    ";
    return safeQuery($conn, $sql);
}

function regionalDisruptionOverview($conn) {
    $sql = "
    SELECT l.ContinentName,
           COUNT(ic.EventID) AS TotalDisruptions,
           SUM(CASE WHEN ic.ImpactLevel='High' THEN 1 ELSE 0 END) AS HighImpactDisruptions
    FROM DisruptionEvent de
    JOIN ImpactsCompany ic ON de.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    GROUP BY l.ContinentName
    ORDER BY TotalDisruptions DESC, HighImpactDisruptions DESC;
    ";
    return safeQuery($conn, $sql);
}

function mostCriticalCompanies($conn) {
    $sql = "
    SELECT c.CompanyID, c.CompanyName, c.Type,
           COUNT(DISTINCT d.DownstreamCompanyID) AS downstreamCount,
           COUNT(DISTINCT CASE WHEN ic.ImpactLevel='High' THEN ic.EventID END) AS HighImpactCount,
           (COUNT(DISTINCT d.DownstreamCompanyID) * 
            COUNT(DISTINCT CASE WHEN ic.ImpactLevel='High' THEN ic.EventID END)) AS criticality
    FROM Company c
    LEFT JOIN DependsOn d ON c.CompanyID = d.UpstreamCompanyID
    LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    GROUP BY c.CompanyID, c.CompanyName, c.Type
    ORDER BY criticality DESC;
    ";
    return safeQuery($conn, $sql);
}

function disruptionFrequency($conn, $startDate, $endDate) {
    $startDate = $conn->real_escape_string($startDate);
    $endDate   = $conn->real_escape_string($endDate);
    $sql = "
        SELECT EventDate, COUNT(EventID) AS TotalDisruptions
        FROM DisruptionEvent
        WHERE EventDate BETWEEN '$startDate' AND '$endDate'
        GROUP BY EventDate
        ORDER BY EventDate ASC;
    ";
    return safeQuery($conn, $sql);
}

switch ($action) {
    case 'company_search':
        $companyName = !empty($_POST['company_name']) ? $_POST['company_name'] : '';
        $startDate   = !empty($_POST['startDate']) ? $_POST['startDate'] : null;
        $endDate     = !empty($_POST['endDate']) ? $_POST['endDate'] : null;

        // Company info
        $response['companyInfo'] = safeQuery($conn, "
            SELECT * FROM Company WHERE CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        // Location info
        $response['locationInfo'] = safeQuery($conn, "
            SELECT c.CompanyID, c.CompanyName, l.City, l.CountryName, l.ContinentName
            FROM Company c
            JOIN Location l ON c.LocationID = l.LocationID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        // Dependencies
        $response['dependencies'] = safeQuery($conn, "
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
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        // Latest financial report
        $response['latestFinancial'] = safeQuery($conn, "
            SELECT f.*
            FROM FinancialReport f
            JOIN Company c ON f.CompanyID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."'
            ORDER BY f.RepYear DESC, FIELD(f.Quarter, 'Q4','Q3','Q2','Q1') DESC
            LIMIT 1;
        ");

        // Capacity
        $response['capacity'] = safeQuery($conn, "
            SELECT c.CompanyName, m.FactoryCapacity AS TotalCapacity
            FROM Company c
            JOIN Manufacturer m ON c.CompanyID = m.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        // Unique routes
        $response['uniqueRoutes'] = safeQuery($conn, "
            SELECT c.CompanyName,
                   COUNT(DISTINCT CONCAT(s.SourceCompanyID, '->', s.DestinationCompanyID)) AS UniqueRoutes
            FROM Company c
            JOIN Distributor d ON c.CompanyID = d.CompanyID
            JOIN Shipping s ON d.CompanyID = s.DistributorID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."'
            GROUP BY c.CompanyName;
        ");

        // Products supplied
        $response['productsSupplied'] = safeQuery($conn, "
            SELECT p.ProductID, p.ProductName, sp.SupplyPrice
            FROM SuppliesProduct sp
            JOIN Product p ON sp.ProductID = p.ProductID
            JOIN Company c ON sp.SupplierID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        // Product diversity
        $response['productDiversity'] = safeQuery($conn, "
            SELECT p.Category, COUNT(*) AS TotalProductNumber,
                ROUND(
                    COUNT(*) * 100.0 / (
                        SELECT COUNT(*) 
                        FROM SuppliesProduct sp2
                        JOIN Company c2 ON sp2.SupplierID = c2.CompanyID
                        WHERE c2.CompanyName = '".$conn->real_escape_string($companyName)."'
                    ), 4
                ) AS PercentOfTotalProducts
            FROM SuppliesProduct sp
            JOIN Product p ON sp.ProductID = p.ProductID
            JOIN Company c ON sp.SupplierID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."'
            GROUP BY p.Category;
        ");

        // Shipping, receiving, adjustments, KPIs, health, disruptions (with $start/$end handling)
        $start = $startDate ? "'".$conn->real_escape_string($startDate)."'" : "'1000-01-01'";
        $end   = $endDate ? "'".$conn->real_escape_string($endDate)."'" : "CURRENT_DATE";

        $response['shippingTransactions'] = safeQuery($conn, "
            SELECT t.TransactionID, 'Shipping' AS TransactionType, s.SourceCompanyID, s.DestinationCompanyID, s.Quantity, s.ActualDate AS TransactionDate, p.ProductName
            FROM InventoryTransaction t
            JOIN Shipping s ON t.TransactionID = s.TransactionID
            JOIN Product p ON s.ProductID = p.ProductID
            JOIN Company c ON (s.SourceCompanyID = c.CompanyID OR s.DestinationCompanyID = c.CompanyID)
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."' AND s.ActualDate BETWEEN $start AND $end
            ORDER BY s.ActualDate DESC;
        ");

        $response['receivingTransactions'] = safeQuery($conn, "
            SELECT t.TransactionID, 'Receiving' AS TransactionType, r.ReceivedDate AS TransactionDate, r.QuantityReceived AS Quantity, r.ReceiverCompanyID, s.SourceCompanyID, p.ProductName
            FROM InventoryTransaction t
            JOIN Receiving r ON t.TransactionID = r.TransactionID
            JOIN Shipping s ON r.ShipmentID = s.ShipmentID
            JOIN Product p ON s.ProductID = p.ProductID
            JOIN Company c ON r.ReceiverCompanyID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."' AND r.ReceivedDate BETWEEN $start AND $end
            ORDER BY r.ReceivedDate DESC;
        ");

        $response['adjustmentTransactions'] = safeQuery($conn, "
            SELECT t.TransactionID, 'Adjustment' AS TransactionType, a.AdjustmentDate, a.QuantityChange, a.CompanyID, p.ProductName, a.Reason
            FROM InventoryTransaction t
            JOIN InventoryAdjustment a ON t.TransactionID = a.TransactionID
            JOIN Product p ON a.ProductID = p.ProductID
            JOIN Company c ON a.CompanyID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."' AND a.AdjustmentDate BETWEEN $start AND $end
            ORDER BY a.AdjustmentDate DESC;
        ");

        $response['kpi'] = safeQuery($conn, "
            WITH distributorShipments AS (
                SELECT s.ShipmentID, s.PromisedDate, s.ActualDate, DATEDIFF(s.ActualDate, s.PromisedDate) AS daysDelayed
                FROM Shipping s
                JOIN Company c ON s.DistributorID = c.CompanyID
                WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."' AND c.Type = 'Distributor' AND s.ActualDate BETWEEN $start AND $end
            )
            SELECT c.CompanyName, c.Type AS CompanyType,
                CASE WHEN c.Type='Distributor' THEN (
                    SELECT ROUND(100.0 * SUM(CASE WHEN ActualDate <= PromisedDate THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),4) FROM distributorShipments
                ) ELSE NULL END AS OnTimeDeliveryRate,
                CASE WHEN c.Type='Distributor' THEN (
                    SELECT ROUND(AVG(daysDelayed),4) FROM distributorShipments
                ) ELSE NULL END AS AverageDelay,
                CASE WHEN c.Type='Distributor' THEN (
                    SELECT ROUND(STDDEV_POP(daysDelayed),4) FROM distributorShipments
                ) ELSE NULL END AS StandardDeviationDelay
            FROM Company c
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."';
        ");

        $response['lastYearHealth'] = safeQuery($conn, "
            WITH LastFourQuarters AS (
                SELECT CONCAT('Q', MOD(QUARTER(CURDATE()) - n - 1 + 4, 4) + 1) AS Quarter,
                       YEAR(CURDATE()) - IF(QUARTER(CURDATE()) - n <= 0, 1, 0) AS RepYear
                FROM (SELECT 0 AS n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) AS offsets
            )
            SELECT c.CompanyName, fr.CompanyID, fr.Quarter, fr.RepYear, fr.HealthScore
            FROM FinancialReport fr
            JOIN LastFourQuarters lfq ON fr.RepYear = lfq.RepYear AND fr.Quarter = lfq.Quarter
            JOIN Company c ON fr.CompanyID = c.CompanyID
            WHERE c.CompanyName = '".$conn->real_escape_string($companyName)."'
            ORDER BY fr.RepYear DESC, fr.Quarter DESC;
        ");

        $response['disruptionSummary'] = safeQuery($conn, "
            SELECT dc.CategoryName, COUNT(de.EventID) AS EventCount
            FROM DisruptionCategory dc
            LEFT JOIN DisruptionEvent de ON dc.CategoryID = de.CategoryID AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            GROUP BY dc.CategoryName
            ORDER BY EventCount DESC;
        ");

        $response['disruptionEvents'] = safeQuery($conn, "
            SELECT dc.CategoryID, dc.CategoryName, dc.Description, de.EventID, de.EventDate, de.EventRecoveryDate
            FROM DisruptionEvent de
            JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
            WHERE de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ORDER BY de.EventDate DESC;
        ");

        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
        break;

    default:
        echo json_encode(["success" => false, "error" => "Invalid action: $action"]);
        exit();
}

function topDistributorsByVolume($conn) {
    $sql = "
        SELECT c.CompanyID, c.CompanyName, SUM(s.Quantity) AS totalShipped
        FROM Distributor d
        JOIN Company c ON d.CompanyID = c.CompanyID
        JOIN Shipping s ON d.CompanyID = s.DistributorID
        GROUP BY c.CompanyID, c.CompanyName
        ORDER BY totalShipped DESC;
    ";
    return safeQuery($conn, $sql);
}

function distributorDetailsByName($conn, $distributorName) {
    $distributorEscaped = $conn->real_escape_string($distributorName);
    $sql = "
        SELECT 
            c.CompanyID, c.CompanyName, l.CountryName, l.ContinentName,
            f.Quarter, f.RepYear, f.HealthScore,
            s.ShipmentID, s.ProductID, p.ProductName,
            s.SourceCompanyID, s.DestinationCompanyID,
            s.PromisedDate, s.ActualDate, s.Quantity AS QuantityShipped,
            r.ReceivingID, r.ReceiverCompanyID, r.ReceivedDate, r.QuantityReceived,
            sp.ProductID AS SuppliedProductID, sp.SupplyPrice,
            ic.EventID, ic.ImpactLevel AS DisruptionImpact
        FROM Distributor d
        JOIN Company c ON d.CompanyID = c.CompanyID
        JOIN Location l ON c.LocationID = l.LocationID
        LEFT JOIN FinancialReport f ON c.CompanyID = f.CompanyID
        LEFT JOIN Shipping s ON d.CompanyID = s.DistributorID
        LEFT JOIN Product p ON s.ProductID = p.ProductID
        LEFT JOIN Receiving r ON s.ShipmentID = r.ShipmentID
        LEFT JOIN SuppliesProduct sp ON d.CompanyID = sp.SupplierID
        LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
        WHERE c.CompanyName = '$distributorEscaped'
        ORDER BY f.RepYear DESC, FIELD(f.Quarter,'Q4','Q3','Q2','Q1'), s.PromisedDate DESC;
    ";
    return safeQuery($conn, $sql);
}

function companiesAffectedByDisruptionEvent($conn, $eventID) {
    $eventID = (int)$eventID;
    $sql = "
        SELECT c.CompanyID, c.CompanyName, ic.ImpactLevel
        FROM ImpactsCompany ic
        JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
        WHERE ic.EventID = $eventID
        ORDER BY c.CompanyName;
    ";
    return safeQuery($conn, $sql);
}

function getDisruptionsByCompanyName($conn, $companyName) {
    $companyEscaped = $conn->real_escape_string($companyName);
    $sql = "
        SELECT de.EventID, de.EventDate, de.EventRecoveryDate,
               dc.CategoryName, ic.ImpactLevel
        FROM ImpactsCompany ic
        JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
        JOIN DisruptionEvent de ON ic.EventID = de.EventID
        JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
        WHERE c.CompanyName = '$companyEscaped'
        ORDER BY de.EventDate;
    ";
    return safeQuery($conn, $sql);
}

function distributorsByAverageDelay($conn) {
    $sql = "
        SELECT c.CompanyID, c.CompanyName,
               AVG(DATEDIFF(s.ActualDate, s.PromisedDate)) AS AvgDelayDays
        FROM Distributor d
        JOIN Company c ON d.CompanyID = c.CompanyID
        JOIN Shipping s ON d.CompanyID = s.DistributorID
        WHERE s.ActualDate IS NOT NULL
        GROUP BY c.CompanyID, c.CompanyName
        ORDER BY AvgDelayDays DESC;
    ";
    return safeQuery($conn, $sql);
}

function addNewCompany($conn, $companyName, $locationID, $tierLevel, $companyType) {
    $companyEscaped = $conn->real_escape_string($companyName);
    $tierEscaped    = $conn->real_escape_string($tierLevel);
    $typeEscaped    = $conn->real_escape_string($companyType);
    try {
        $conn->begin_transaction();
        $sqlCompany = "
            INSERT INTO Company (CompanyName, LocationID, TierLevel, Type)
            VALUES ('$companyEscaped', $locationID, '$tierEscaped', '$typeEscaped');
        ";
        if (!$conn->query($sqlCompany)) throw new Exception("Company insert failed: " . $conn->error);

        $newCompanyID = $conn->insert_id;

        if ($companyType === "Distributor") {
            $sqlType = "INSERT INTO Distributor (CompanyID) VALUES ($newCompanyID);";
        } elseif ($companyType === "Manufacturer") {
            $sqlType = "INSERT INTO Manufacturer (CompanyID, FactoryCapacity) VALUES ($newCompanyID, 0);";
        } elseif ($companyType === "Retailer") {
            $sqlType = "INSERT INTO Retailer (CompanyID) VALUES ($newCompanyID);";
        } else {
            throw new Exception("Company must be a distributor, manufacturer, or retailer.");
        }

        if (!$conn->query($sqlType)) throw new Exception("Type insert failed: " . $conn->error);

        $conn->commit();
        return ["success" => true, "message" => "New company added.", "newCompanyID" => $newCompanyID];
    } catch (Exception $e) {
        $conn->rollback();
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function getCriticalityByRegion($conn) {
    $sql = "
        SELECT l.ContinentName,
               SUM(COALESCE(downstreamCount,0) * COALESCE(highImpactCount,0)) AS totalCriticality
        FROM (
            SELECT c.CompanyID, c.LocationID,
                   COUNT(DISTINCT d.DownstreamCompanyID) AS downstreamCount,
                   COUNT(DISTINCT CASE WHEN ic.ImpactLevel = 'High' THEN ic.EventID END) AS highImpactCount
            FROM Company c
            LEFT JOIN DependsOn d ON c.CompanyID = d.UpstreamCompanyID
            LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
            LEFT JOIN DisruptionEvent de ON ic.EventID = de.EventID
            GROUP BY c.CompanyID, c.LocationID
        ) AS companyCriticality
        JOIN Location l ON companyCriticality.LocationID = l.LocationID
        GROUP BY l.ContinentName
        ORDER BY totalCriticality DESC;
    ";
    return safeQuery($conn, $sql);
}

function topDistributorsByNumberOfShipments($conn) {
    $sql = "
        SELECT c.CompanyID, c.CompanyName, COUNT(DISTINCT s.ShipmentID) AS totalShipments
        FROM Distributor d
        JOIN Company c ON d.CompanyID = c.CompanyID
        JOIN Shipping s ON d.CompanyID = s.DistributorID
        GROUP BY c.CompanyID, c.CompanyName
        ORDER BY totalShipments DESC;
    ";
    return safeQuery($conn, $sql);
}
