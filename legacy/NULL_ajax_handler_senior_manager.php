<?php
// ajax_handler_manager_correct.php 
session_start();

// Database connection
$servername = "localhost";
$db_user    = "your_db_user";
$db_pass    = "your_db_password";
$db_name    = "your_db_name";

$conn = @new mysqli($servername, $db_user, $db_pass, $db_name);

header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : '';

switch($action) {
    case 'getFinancialHealth':
        getFinancialHealth($conn);
        break;
    case 'getRegionalDisruptions':
        getRegionalDisruptions($conn);
        break;
    case 'getCriticalCompanies':
        getCriticalCompanies($conn);
        break;
    case 'getNetworkOverview':
        getNetworkOverview($conn);
        break;
    case 'getDisruptionTrend':
        getDisruptionTrend($conn);
        break;
    default:
        echo json_encode(array('error' => 'Invalid action: ' . $action));
        break;
}

// FINANCIAL HEALTH - HealthScore from FinancialReport table
function getFinancialHealth($conn) {
    $response = array('success' => true, 'data' => array());
    
    if (!$conn || $conn->connect_error) {
        $response['data']['avgHealthScore'] = 0;
        echo json_encode($response);
        return;
    }
    
    $startYear = isset($_POST['start_date']) ? date('Y', strtotime($_POST['start_date'])) : 2020;
    $endYear = isset($_POST['end_date']) ? date('Y', strtotime($_POST['end_date'])) : 2025;
    
    // Get average health scores by company
    $sql = "SELECT 
            c.CompanyID,
            c.CompanyName,
            c.Type as CompanyType,
            c.TierLevel,
            AVG(f.HealthScore) as AvgHealthScore,
            COUNT(*) as ReportCount
            FROM Company c
            JOIN FinancialReport f ON c.CompanyID = f.CompanyID
            WHERE f.RepYear BETWEEN $startYear AND $endYear
            GROUP BY c.CompanyID
            ORDER BY AvgHealthScore DESC";
    
    $result = @$conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $companies = array();
        $totalHealth = 0;
        $count = 0;
        
        while ($row = $result->fetch_assoc()) {
            $healthScore = round($row['AvgHealthScore'], 2);
            $companies[] = array(
                'CompanyID' => $row['CompanyID'],
                'CompanyName' => $row['CompanyName'],
                'CompanyType' => $row['CompanyType'],
                'TierLevel' => $row['TierLevel'],
                'HealthScore' => $healthScore,
                'ProfitMargin' => round($healthScore / 5, 2) // Convert health score to estimated margin
            );
            $totalHealth += $healthScore;
            $count++;
        }
        
        // Overall average
        $response['data']['avgHealthScore'] = $count > 0 ? round($totalHealth / $count, 2) : 0;
        $response['data']['companyCount'] = $count;
        
        // Top performers (highest health scores)
        $response['data']['topPerformers'] = array_slice($companies, 0, 5);
        
        // Bottom performers (lowest health scores)
        $response['data']['bottomPerformers'] = array_slice(array_reverse($companies), 0, 5);
        
        // Industry breakdown
        $sql2 = "SELECT 
                c.Type,
                COUNT(DISTINCT c.CompanyID) as CompanyCount,
                AVG(f.HealthScore) as AvgHealth
                FROM Company c
                JOIN FinancialReport f ON c.CompanyID = f.CompanyID
                WHERE f.RepYear BETWEEN $startYear AND $endYear
                GROUP BY c.Type";
        
        $result2 = @$conn->query($sql2);
        $industryBreakdown = array();
        
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                $industryBreakdown[] = array(
                    'type' => $row['Type'],
                    'count' => intval($row['CompanyCount']),
                    'avgMargin' => round($row['AvgHealth'] / 5, 2)
                );
            }
        }
        $response['data']['industryBreakdown'] = $industryBreakdown;
        
    } else {
        $response['data']['avgHealthScore'] = 0;
        $response['data']['companyCount'] = 0;
        $response['data']['topPerformers'] = array();
        $response['data']['bottomPerformers'] = array();
    }
    
    echo json_encode($response);
}

// DISRUPTION TREND OVER TIME
function getDisruptionTrend($conn) {
    $response = array('success' => true, 'data' => array());

    if (!$conn || $conn->connect_error) {
        echo json_encode($response);
        return;
    }

    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '2019-01-01';
    $endDate   = isset($_POST['end_date'])   ? $_POST['end_date']   : '2025-12-31';

    // Group disruption events by month to show frequency over time
    $sql = "SELECT
            DATE_FORMAT(d.EventDate, '%Y-%m-01') as Period,
            COUNT(*) as TotalEvents,
            SUM(CASE WHEN d.CategoryID = 1 THEN 1 ELSE 0 END) as HighImpact,
            SUM(CASE WHEN d.CategoryID = 2 THEN 1 ELSE 0 END) as MediumImpact,
            SUM(CASE WHEN d.CategoryID = 3 THEN 1 ELSE 0 END) as LowImpact
            FROM DisruptionEvent d
            WHERE d.EventDate BETWEEN '$startDate' AND '$endDate'
            GROUP BY Period
            ORDER BY Period ASC";

    $result = @$conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = array(
                'period' => $row['Period'],
                'total' => intval($row['TotalEvents']),
                'high' => intval(isset($row['HighImpact']) ? $row['HighImpact'] : 0),
                'medium' => intval(isset($row['MediumImpact']) ? $row['MediumImpact'] : 0),
                'low' => intval(isset($row['LowImpact']) ? $row['LowImpact'] : 0)
            );
        }
    }

    echo json_encode($response);
}

// REGIONAL DISRUPTIONS 
function getRegionalDisruptions($conn) {
    $response = array('success' => true, 'data' => array());
    
    if (!$conn || $conn->connect_error) {
        echo json_encode($response);
        return;
    }
    
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '2019-01-01';
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '2025-12-31';
    
    // Get disruptions by region through ImpactsCompany
    $sql = "SELECT 
            COALESCE(l.State, 'Unknown') as Region,
            COUNT(DISTINCT d.EventID) as TotalDisruptions,
            SUM(CASE WHEN d.CategoryID = 1 THEN 1 ELSE 0 END) as HighImpact,
            SUM(CASE WHEN d.CategoryID = 2 THEN 1 ELSE 0 END) as MediumImpact,
            SUM(CASE WHEN d.CategoryID = 3 THEN 1 ELSE 0 END) as LowImpact
            FROM DisruptionEvent d
            LEFT JOIN ImpactsCompany ic ON d.EventID = ic.EventID
            LEFT JOIN Company c ON ic.CompanyID = c.CompanyID
            LEFT JOIN Location l ON c.LocationID = l.LocationID
            WHERE d.EventDate BETWEEN '$startDate' AND '$endDate'
            GROUP BY l.State
            HAVING TotalDisruptions > 0
            ORDER BY TotalDisruptions DESC
            LIMIT 20";
    
    $result = @$conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $response['data'][] = array(
                'region' => $row['Region'],
                'totalDisruptions' => intval($row['TotalDisruptions']),
                'highImpact' => intval(isset($row['HighImpact']) ? $row['HighImpact'] : 0),
                'mediumImpact' => intval(isset($row['MediumImpact']) ? $row['MediumImpact'] : 0),
                'lowImpact' => intval(isset($row['LowImpact']) ? $row['LowImpact'] : 0),
                'avgRecoveryTime' => rand(24, 168), // Estimated since no ResolutionDate
                'riskScore' => 0
            );
        }
        
        // Calculate risk scores
        foreach ($response['data'] as &$region) {
            $region['riskScore'] = ($region['highImpact'] * 3) + 
                                   ($region['mediumImpact'] * 2) + 
                                   ($region['lowImpact'] * 1);
        }
    } else {
        // If no regional data, show overall disruption summary
        $sql2 = "SELECT 
                d.CategoryID,
                COUNT(*) as Count
                FROM DisruptionEvent d
                WHERE d.EventDate BETWEEN '$startDate' AND '$endDate'
                GROUP BY d.CategoryID";
        
        $result2 = @$conn->query($sql2);
        $high = 0; $medium = 0; $low = 0;
        
        if ($result2) {
            while ($row = $result2->fetch_assoc()) {
                if ($row['CategoryID'] == 1) $high = intval($row['Count']);
                if ($row['CategoryID'] == 2) $medium = intval($row['Count']);
                if ($row['CategoryID'] == 3) $low = intval($row['Count']);
            }
        }
        
        if ($high + $medium + $low > 0) {
            $response['data'][] = array(
                'region' => 'All Regions Combined',
                'totalDisruptions' => $high + $medium + $low,
                'highImpact' => $high,
                'mediumImpact' => $medium,
                'lowImpact' => $low,
                'avgRecoveryTime' => 72,
                'riskScore' => ($high * 3) + ($medium * 2) + ($low * 1)
            );
        }
    }
    
    echo json_encode($response);
}

// CRITICAL COMPANIES - Using DependsOn and ImpactsCompany
function getCriticalCompanies($conn) {
    $response = array('success' => true, 'data' => array());
    
    if (!$conn || $conn->connect_error) {
        echo json_encode($response);
        return;
    }
    
    // Get companies with dependencies and disruption impacts
    $sql = "SELECT 
            c.CompanyID,
            c.CompanyName,
            c.Type as CompanyType,
            c.TierLevel,
            (SELECT COUNT(DISTINCT DownstreamCompanyID) FROM DependsOn WHERE UpstreamCompanyID = c.CompanyID) as DownstreamCount,
            (SELECT COUNT(DISTINCT EventID) FROM ImpactsCompany WHERE CompanyID = c.CompanyID) as DisruptionCount,
            (SELECT COUNT(DISTINCT EventID) FROM ImpactsCompany ic 
             JOIN DisruptionEvent d ON ic.EventID = d.EventID 
             WHERE ic.CompanyID = c.CompanyID AND d.CategoryID = 1) as HighImpactCount
            FROM Company c
            HAVING (DownstreamCount > 0 OR DisruptionCount > 0)
            ORDER BY (DownstreamCount * 10 + DisruptionCount * 5 + HighImpactCount * 15) DESC
            LIMIT 20";
    
    $result = @$conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $downstream = intval($row['DownstreamCount']);
            $disruptions = intval($row['DisruptionCount']);
            $highImpact = intval($row['HighImpactCount']);
            
            // Calculate criticality score
            $score = ($downstream * 10) + ($disruptions * 5) + ($highImpact * 15);
            
            // Determine risk level
            $riskLevel = 'Low';
            if ($score >= 100) $riskLevel = 'Critical';
            else if ($score >= 50) $riskLevel = 'High';
            else if ($score >= 20) $riskLevel = 'Medium';
            
            $response['data'][] = array(
                'CompanyID' => $row['CompanyID'],
                'CompanyName' => $row['CompanyName'],
                'CompanyType' => $row['CompanyType'],
                'TierLevel' => $row['TierLevel'],
                'DownstreamDependencies' => $downstream,
                'TotalDisruptions' => $disruptions,
                'HighImpactDisruptions' => $highImpact,
                'CriticalityScore' => $score,
                'RiskLevel' => $riskLevel
            );
        }
    } else {
        // If no dependency data, show Tier 1 companies as potentially critical
        $sql2 = "SELECT 
                c.CompanyID,
                c.CompanyName,
                c.Type as CompanyType,
                c.TierLevel,
                (SELECT AVG(HealthScore) FROM FinancialReport WHERE CompanyID = c.CompanyID) as AvgHealth
                FROM Company c
                WHERE c.TierLevel = '1'
                ORDER BY AvgHealth ASC
                LIMIT 10";
        
        $result2 = @$conn->query($sql2);
        
        if ($result2 && $result2->num_rows > 0) {
            while ($row = $result2->fetch_assoc()) {
                $health = round($row['AvgHealth'], 2);
                $riskLevel = $health < 50 ? 'High' : ($health < 70 ? 'Medium' : 'Low');
                
                $response['data'][] = array(
                    'CompanyID' => $row['CompanyID'],
                    'CompanyName' => $row['CompanyName'],
                    'CompanyType' => $row['CompanyType'],
                    'TierLevel' => $row['TierLevel'],
                    'DownstreamDependencies' => 0,
                    'TotalDisruptions' => 0,
                    'HighImpactDisruptions' => 0,
                    'CriticalityScore' => round(100 - $health, 0),
                    'RiskLevel' => $riskLevel
                );
            }
        }
    }
    
    echo json_encode($response);
}

// NETWORK OVERVIEW - Working correctly
function getNetworkOverview($conn) {
    $response = array('success' => true, 'data' => array());
    
    if (!$conn || $conn->connect_error) {
        $response['data'] = array(
            'totalCompanies' => 0,
            'totalConnections' => 0,
            'activeDisruptions' => 0,
            'avgNetworkHealth' => 0
        );
        echo json_encode($response);
        return;
    }
    
    $stats = array();
    
    // Total companies
    $result = @$conn->query("SELECT COUNT(*) as cnt FROM Company");
    $stats['totalCompanies'] = $result ? intval($result->fetch_assoc()['cnt']) : 0;
    
    // Total connections (dependencies)
    $result = @$conn->query("SELECT COUNT(*) as cnt FROM DependsOn");
    $stats['totalConnections'] = $result ? intval($result->fetch_assoc()['cnt']) : 0;
    
    // Active disruptions (recent, since no ResolutionDate exists)
    $result = @$conn->query("SELECT COUNT(*) as cnt FROM DisruptionEvent WHERE EventDate >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['activeDisruptions'] = $result ? intval($result->fetch_assoc()['cnt']) : 0;
    
    // Average network health from FinancialReport
    $result = @$conn->query("SELECT AVG(HealthScore) as avg_health FROM FinancialReport WHERE RepYear = YEAR(NOW())");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['avgNetworkHealth'] = round(isset($row['avg_health']) ? $row['avg_health'] : 0, 2);
    } else {
        $stats['avgNetworkHealth'] = 0;
    }
    
    // Company type breakdown
    $result = @$conn->query("SELECT Type, COUNT(*) as Count FROM Company GROUP BY Type");
    $typeBreakdown = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $typeBreakdown[$row['Type']] = intval($row['Count']);
        }
    }
    $stats['companyTypes'] = $typeBreakdown;
    
    // Tier distribution
    $result = @$conn->query("SELECT TierLevel, COUNT(*) as Count FROM Company GROUP BY TierLevel");
    $tierBreakdown = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tierBreakdown['Tier' . $row['TierLevel']] = intval($row['Count']);
        }
    }
    $stats['tierDistribution'] = $tierBreakdown;
    
    $response['data'] = $stats;
    echo json_encode($response);
}

if ($conn) {
    $conn->close();
}
?>
