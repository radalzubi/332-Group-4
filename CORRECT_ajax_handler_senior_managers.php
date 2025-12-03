<?php
// CORRECT_ajax_handler_senior_managers.php

session_start();

// Database connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(["error" => "Can't connect to the database"]));
}

header('Content-Type: application/json');

$response = [];

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'avg_financial_health': // Average financial health by company
            $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : '1000-01-01';
            $endDate   = !empty($_POST['endDate'])   ? $_POST['endDate']   : date("Y-m-d");
            $type      = !empty($_POST['companyType']) ? $_POST['companyType'] : null;
            $response['avg_financial_health'] = avgFinancialHealth($conn, $startDate, $endDate, $type);
            break;
        case 'regional_disruption_overview': // Regional disruption overview
            $response['regional_disruption_overview'] = regionalDisruptionOverview($conn);
            break;
        case 'most_critical_companies': // Most critical companies, from criticality
            $response['most_critical_companies'] = mostCriticalCompanies($conn);
            break;
        case 'disruption_frequency': // Disruoiption frequency over time
            $startDate = $_POST['startDate'];
            $endDate   = $_POST['endDate'];
            $response['disruption_frequency'] = disruptionFrequency($conn, $startDate, $endDate);
            break;
        case 'company_financials_by_region': // Company financials by region
            $region = $_POST['region'];
            $response['company_financials_by_region'] = companyFinancialsByRegion($conn, $region);
            break;
        case 'company_search': // ability to bring up any company's data
            $name = $_POST['companyName'];
            $startDate = $_POST['startDate'] ?? null;
            $endDate   = $_POST['endDate'] ?? null;
            $response['company_info'] = companyInfoSearch($conn, $name, $startDate, $endDate);
            break;
        case 'top_distributors_volume': // Top distributors by shipment volume
            $response['top_distributors_volume'] = topDistributorsByVolume($conn);
            break;
        case 'distributor_details': // Ability to bring up any distributor
            $name = $_POST['distributorName'];
            $response['distributor_details'] = distributorDetails($conn, $name);
            break;
        case 'companies_by_event': // Companies affected by specific disruption event
            $eventID = $_POST['eventID'];
            $response['companies_by_event'] = companiesByDisruptionEvent($conn, $eventID);
            break;
        case 'disruptions_by_company': // All disruptions for a specific company
            $name = $_POST['companyName'];
            $response['disruptions_by_company'] = disruptionsByCompany($conn, $name);
            break;
        case 'distributors_by_delay': // Distributors sorted by average delay
            $response['distributors_by_delay'] = distributorsByAverageDelay($conn);
            break;
        case 'add_new_company': // Ability to add a new company
            $name = $_POST['companyName'];
            $location = $_POST['locationID'];
            $tier = $_POST['tierLevel'];
            $type = $_POST['companyType'];
            $response['add_new_company'] = addNewCompany($conn, $name, $location, $tier, $type);
            break;
        case 'criticality_by_region': // 1/2 nontrivial, criticality by region
            $response['criticality_by_region'] = criticalityByRegion($conn);
            break;
        case 'top_distributors_shipments': // 2/2 nontrivial, top distributors by number of shipments
        $response['top_distributors_shipments'] = topDistributorsByNumberOfShipments($conn);
        break;
    }
}

function avgFinancialHealth($conn, $startDate, $endDate, $type = null) {
    $startDate = $conn->real_escape_string($startDate); //user-entered
    $endDate   = $conn->real_escape_string($endDate); // user-entered
    $typeCondition = "";
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
    WHERE 
        STR_TO_DATE(
            CONCAT(
                f.RepYear, '-',
                CASE f.Quarter
                    WHEN 'Q1' THEN '01-01' //from Piazza Q1 January 1st through March 31st, etc.
                    WHEN 'Q2' THEN '04-01'
                    WHEN 'Q3' THEN '07-01'
                    WHEN 'Q4' THEN '10-01'
                END
            ),
            '%Y-%m-%d'
        ) BETWEEN '$startDate' AND '$endDate'
        $typeCondition
    GROUP BY c.CompanyID, c.CompanyName, c.Type
    ORDER BY avgHealthScore DESC; // from highest to lowest
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function regionalDisruptionOverview($conn) {
    $sql = "
    SELECT
        l.ContinentName,
        COUNT(ic.EventID) AS TotalDisruptions,
        SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END) AS HighImpactDisruptions
    FROM DisruptionEvent de
    JOIN ImpactsCompany ic ON de.EventID = ic.EventID
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    GROUP BY l.ContinentName
    ORDER BY TotalDisruptions DESC, HighImpactDisruptions DESC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function mostCriticalCompanies($conn) {

    $sql = "
    SELECT
        c.CompanyID,
        c.CompanyName,
        c.Type,
        COUNT(DISTINCT d.DownstreamCompanyID) AS downstreamCount, // # of downstream companies affected
        COUNT(DISTINCT CASE WHEN ic.ImpactLevel = 'High' THEN ic.EventID END) AS HighImpactCount, // high impact count
        (
            COUNT(DISTINCT d.DownstreamCompanyID) *
            COUNT(DISTINCT CASE WHEN ic.ImpactLevel = 'High' THEN ic.EventID END)
        ) AS criticality // # downtstream * high impact count
    FROM Company c
    LEFT JOIN DependsOn d ON c.CompanyID = d.UpstreamCompanyID
    LEFT JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    GROUP BY c.CompanyID, c.CompanyName, c.Type
    ORDER BY criticality DESC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function disruptionFrequency($conn, $startDate, $endDate) {
    $startDate = $conn->real_escape_string($startDate);
    $endDate   = $conn->real_escape_string($endDate);
    $sql = "
    SELECT 
        EventDate,
        COUNT(EventID) AS TotalDisruptions
    FROM DisruptionEvent
    WHERE EventDate BETWEEN '$startDate' AND '$endDate'
    GROUP BY EventDate
    ORDER BY EventDate ASC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function companyFinancialsByRegion($conn, $region) {
    $region = $conn->real_escape_string($region);
    $sql = "
    SELECT
        l.ContinentName,
        l.CountryName,
        c.CompanyID,
        c.CompanyName,
        c.Type AS CompanyType,
        f.RepYear,
        f.Quarter,
        f.HealthScore,
        (
            SELECT AVG(f2.HealthScore)
            FROM Company c2
            JOIN Location l2 ON c2.LocationID = l2.LocationID
            JOIN FinancialReport f2 ON c2.CompanyID = f2.CompanyID
            WHERE l2.ContinentName = '$region'
        ) AS AvgHealthScoreByRegion // average health score in the region
    FROM Company c
    JOIN Location l ON c.LocationID = l.LocationID
    JOIN FinancialReport f ON c.CompanyID = f.CompanyID
    WHERE l.ContinentName = '$region' 
    ORDER BY c.CompanyName, f.RepYear, f.Quarter; // all health scores in the region
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Ability to bring up any company's data
if ($_POST['action'] === 'company_search') {
    $companyName = $conn->real_escape_string($_POST['company_name']);
    $startDate   = !empty($_POST['start_date']) ? $conn->real_escape_string($_POST['start_date']) : null;
    $endDate     = !empty($_POST['end_date'])   ? $conn->real_escape_string($_POST['end_date'])   : null;

    $sqlCompany = "
        SELECT *
        FROM Company
        WHERE CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlCompany);
    $response['companyInfo'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlLocation = "
        SELECT 
            c.CompanyID,
            c.CompanyName,
            l.City,
            l.CountryName,
            l.ContinentName
        FROM Company c
        JOIN Location l ON c.LocationID = l.LocationID
        WHERE c.CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlLocation);
    $response['locationInfo'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlDependencies = "
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
        WHERE c.CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlDependencies);
    $response['dependencies'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlFinancial = "
        SELECT f.*
        FROM FinancialReport f
        JOIN Company c ON f.CompanyID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
        ORDER BY f.RepYear DESC, FIELD(f.Quarter, 'Q4','Q3','Q2','Q1') DESC
        LIMIT 1;
    ";
    $result = $conn->query($sqlFinancial);
    $response['latestFinancial'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlCapacity = "
        SELECT 
            c.CompanyName,
            m.FactoryCapacity AS TotalCapacity // capacity if manufacturer
        FROM Company c
        JOIN Manufacturer m ON c.CompanyID = m.CompanyID
        WHERE c.CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlCapacity);
    $response['capacity'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

  $sqlRoutes = "
        SELECT 
            c.CompanyName,
            COUNT(DISTINCT CONCAT(s.SourceCompanyID, '->', s.DestinationCompanyID)) AS UniqueRoutes
        FROM Company c
        JOIN Distributor d ON c.CompanyID = d.CompanyID
        JOIN Shipping s ON d.CompanyID = s.DistributorID
        WHERE c.CompanyName = '$companyName'
        GROUP BY c.CompanyName; // unique routes if distributor
    ";
    $result = $conn->query($sqlRoutes);
    $response['uniqueRoutes'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlSuppliedProducts = "
        SELECT 
            p.ProductID,
            p.ProductName,
            sp.SupplyPrice
        FROM SuppliesProduct sp
        JOIN Product p ON sp.ProductID = p.ProductID
        JOIN Company c ON sp.SupplierID = c.CompanyID
        WHERE c.CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlSuppliedProducts);
    $response['productsSupplied'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlDiversity = "
        SELECT 
            p.Category,
            COUNT(*) AS TotalProductNumber,
            ROUND(
                COUNT(*) * 100.0 / (
                    SELECT COUNT(*) 
                    FROM SuppliesProduct sp2
                    JOIN Company c2 ON sp2.SupplierID = c2.CompanyID
                    WHERE c2.CompanyName = '$companyName'
                ), 4
            ) AS PercentOfTotalProducts
        FROM SuppliesProduct sp
        JOIN Product p ON sp.ProductID = p.ProductID
        JOIN Company c ON sp.SupplierID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
        GROUP BY p.Category; // product diversity by category
    ";
    $result = $conn->query($sqlDiversity);
    $response['productDiversity'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $start = $startDate ? "'$startDate'" : "'1000-01-01'";
    $end   = $endDate ? "'$endDate'" : "CURRENT_DATE";

    $sqlShipping = "
        SELECT 
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
            ON (s.SourceCompanyID = c.CompanyID 
                OR s.DestinationCompanyID = c.CompanyID)
        WHERE c.CompanyName = '$companyName'
        AND s.ActualDate BETWEEN $start AND $end
        ORDER BY s.ActualDate DESC;
    ";
    $result = $conn->query($sqlShipping);
    $response['shippingTransactions'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlReceiving = "
        SELECT 
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
        AND r.ReceivedDate BETWEEN $start AND $end
        ORDER BY r.ReceivedDate DESC;
    ";
    $result = $conn->query($sqlReceiving);
    $response['receivingTransactions'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlAdjustments = "
        SELECT 
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
        AND a.AdjustmentDate BETWEEN $start AND $end
        ORDER BY a.AdjustmentDate DESC;
    ";
    $result = $conn->query($sqlAdjustments);
    $response['adjustmentTransactions'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlKPIs = "
        WITH distributorShipments AS (
            SELECT
                s.ShipmentID,
                s.PromisedDate,
                s.ActualDate,
                DATEDIFF(s.ActualDate, s.PromisedDate) AS daysDelayed
            FROM Shipping s
            JOIN Company c ON s.DistributorID = c.CompanyID
            WHERE c.CompanyName = '$companyName'
            AND c.Type = 'Distributor'
            AND s.ActualDate BETWEEN $start AND $end
        )
        SELECT 
            c.CompanyName,
            c.Type AS CompanyType,

            CASE
                WHEN c.Type = 'Distributor' THEN (
                    SELECT ROUND(
                        100.0 * SUM(CASE WHEN ActualDate <= PromisedDate THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0),
                        4
                    )
                    FROM distributorShipments
                )
            ELSE NULL END AS OnTimeDeliveryRate,

            CASE
                WHEN c.Type = 'Distributor' THEN (
                    SELECT ROUND(AVG(daysDelayed), 4)
                    FROM distributorShipments
                )
            ELSE NULL END AS AverageDelay,

            CASE
                WHEN c.Type = 'Distributor' THEN (
                    SELECT ROUND(STDDEV_POP(daysDelayed), 4)
                    FROM distributorShipments
                )
            ELSE NULL END AS StandardDeviationDelay
        FROM Company c
        WHERE c.CompanyName = '$companyName';
    ";
    $result = $conn->query($sqlKPIs);
    $response['kpi'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlHealth = "
        WITH LastFourQuarters AS (
            SELECT
                CONCAT('Q', MOD(QUARTER(CURDATE()) - n - 1 + 4, 4) + 1) AS Quarter,
                YEAR(CURDATE()) - IF(QUARTER(CURDATE()) - n <= 0, 1, 0) AS RepYear
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
        JOIN Company c ON fr.CompanyID = c.CompanyID
        WHERE c.CompanyName = '$companyName'
        ORDER BY fr.RepYear DESC, fr.Quarter DESC;
    ";
    $result = $conn->query($sqlHealth);
    $response['lastYearHealth'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlDisruptionSummary = "
        SELECT 
            dc.CategoryName,
            COUNT(de.EventID) AS EventCount
        FROM DisruptionCategory dc
        LEFT JOIN DisruptionEvent de 
          ON dc.CategoryID = de.CategoryID 
         AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
        GROUP BY dc.CategoryName
        ORDER BY EventCount DESC; // disruption count last year
    ";
    $result = $conn->query($sqlDisruptionSummary);
    $response['disruptionSummary'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $sqlDisruptionList = "
        SELECT 
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
        ORDER BY de.EventDate DESC; // disruption event list last year
    ";
    $result = $conn->query($sqlDisruptionList);
    $response['disruptionEvents'] = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    echo json_encode($response);
    exit();
}


function topDistributorsByVolume($conn) {
    $sql = "
    SELECT
        c.CompanyID,
        c.CompanyName,
        SUM(s.Quantity) AS totalShipped
    FROM Distributor d
    JOIN Company c ON d.CompanyID = c.CompanyID
    JOIN Shipping s ON d.CompanyID = s.DistributorID
    GROUP BY c.CompanyID, c.CompanyName
    ORDER BY totalShipped DESC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}


function distributorDetailsByName($conn, $distributorName) {
    $distributorName = $conn->real_escape_string($distributorName);
    $sql = "
    SELECT 
        c.CompanyID,
        c.CompanyName,
        l.CountryName,
        l.ContinentName,
        f.Quarter,
        f.RepYear,
        f.HealthScore,
        s.ShipmentID,
        s.ProductID,
        p.ProductName,
        s.SourceCompanyID,
        s.DestinationCompanyID,
        s.PromisedDate,
        s.ActualDate,
        s.Quantity AS QuantityShipped,
        r.ReceivingID,
        r.ReceiverCompanyID,
        r.ReceivedDate,
        r.QuantityReceived,
        sp.ProductID AS SuppliedProductID,
        sp.SupplyPrice, 
        ic.EventID,
        ic.ImpactLevel AS DisruptionImpact
    FROM Distributor d
    JOIN Company c ON d.CompanyID = c.CompanyID
    JOIN Location l ON c.LocationID = l.LocationID
    LEFT JOIN FinancialReport f ON c.CompanyID = f.CompanyID
    LEFT JOIN Shipping s ON d.CompanyID = s.DistributorID
    LEFT JOIN Product p ON s.ProductID = p.ProductID
    LEFT JOIN Receiving r ON s.ShipmentID = r.ShipmentID
    LEFT JOIN SuppliesProduct sp ON d.CompanyID = sp.SupplierID
    LEFT JOIN ImpactsCompany ic ON d.CompanyID = ic.AffectedCompanyID
    WHERE c.CompanyName = '$distributorName'
    ORDER BY f.RepYear DESC, 
             FIELD(f.Quarter,'Q4','Q3','Q2','Q1'),
             s.PromisedDate DESC;
    ";

    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function companiesAffectedByDisruptionEvent($conn, $selectedEventID) {
    $selectedEventID = (int)$selectedEventID;
    $sql = "
    SELECT
        c.CompanyID,
        c.CompanyName,
        ic.ImpactLevel
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    WHERE ic.EventID = $selectedEventID
    ORDER BY c.CompanyName;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function getDisruptionsByCompanyName($conn, $companyName) {
    $companyEscaped = $conn->real_escape_string($companyName);
    $sql = "
        SELECT
            de.EventID,
            de.EventDate,
            de.EventRecoveryDate,
            dc.CategoryName,
            ic.ImpactLevel
        FROM ImpactsCompany ic
        JOIN Company c
            ON ic.AffectedCompanyID = c.CompanyID
        JOIN DisruptionEvent de
            ON ic.EventID = de.EventID
        JOIN DisruptionCategory dc
            ON de.CategoryID = dc.CategoryID
        WHERE c.CompanyName = '$companyEscaped'
        ORDER BY de.EventDate;
    ";
    $result = $conn->query($sql);
    if (!$result) {
        return ["error" => $conn->error];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function distributorsByAverageDelay($conn) {
    $sql = "
    SELECT
        c.CompanyID,
        c.CompanyName,
        AVG(DATEDIFF(s.ActualDate, s.PromisedDate)) AS AvgDelayDays
    FROM Distributor d
    JOIN Company c ON d.CompanyID = c.CompanyID
    JOIN Shipping s ON d.CompanyID = s.DistributorID
    WHERE s.ActualDate IS NOT NULL
    GROUP BY c.CompanyID, c.CompanyName
    ORDER BY AvgDelayDays DESC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
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
        if (!$conn->query($sqlCompany)) {
            throw new Exception("Company insert failed: " . $conn->error);
        }

        $newCompanyID = $conn->insert_id;
        if ($companyType === "Distributor") {

            $sqlType = "
                INSERT INTO Distributor (CompanyID)
                VALUES ($newCompanyID);
            ";
        } elseif ($companyType === "Manufacturer") {
            $sqlType = "
                INSERT INTO Manufacturer (CompanyID, FactoryCapacity)
                VALUES ($newCompanyID, 0);
            ";
        } elseif ($companyType === "Retailer") {
            $sqlType = "
                INSERT INTO Retailer (CompanyID)
                VALUES ($newCompanyID);
            ";
        } else {
            throw new Exception("Company must be a distributor, manufacurer, or retailer.");
        }
        if (!$conn->query($sqlType)) {
            throw new Exception("Type fail " . $conn->error);
        }
        $conn->commit();
        return [
            "success" => true,
            "message" => "New company added.",
            "newCompanyID" => $newCompanyID
        ];

    } catch (Exception $e) {
        $conn->rollback();
        return [
            "success" => false,
            "error" => $e->getMessage()
        ];
    }
}


function getCriticalityByRegion($conn) {
    $sql = "
        SELECT
            l.ContinentName,
            SUM(
                COALESCE(downstreamCount,0) * COALESCE(highImpactCount,0)
            ) AS totalCriticality
        FROM (
            SELECT
                c.CompanyID,
                c.LocationID,
                COUNT(DISTINCT d.DownstreamCompanyID) AS downstreamCount,
                COUNT(DISTINCT CASE 
                    WHEN ic.ImpactLevel = 'High' THEN ic.EventID
                END) AS highImpactCount
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
    $result = $conn->query($sql);
    if (!$result) {
        return ["error" => $conn->error];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function topDistributorsByNumberOfShipments($conn) {
    $sql = "
    SELECT
        c.CompanyID,
        c.CompanyName,
        COUNT(DISTINCT s.ShipmentID) AS totalShipments
    FROM Distributor d
    JOIN Company c ON d.CompanyID = c.CompanyID
    JOIN Shipping s ON d.CompanyID = s.DistributorID
    GROUP BY c.CompanyID, c.CompanyName
    ORDER BY totalShipments DESC;
    ";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

// Output JSON
echo json_encode($response, JSON_PRETTY_PRINT);
?>
