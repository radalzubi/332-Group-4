<?php
// ajax_handler.php - DEBUG VERSION
// This does NOT use the database. It just returns fake data so we can
// prove that your JavaScript and AJAX wiring are correct.

header('Content-Type: application/json');

// Accept both POST and GET just in case
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// If nothing was sent, say so
if ($action === '') {
    echo json_encode(array(
        'success' => false,
        'error'   => 'No action provided',
        'debug'   => $_REQUEST   // shows what (if anything) came in
    ));
    exit;
}

switch ($action) {

    // ---------------------------------------------------------
    // 1) SEARCH COMPANY
    // ---------------------------------------------------------
    case 'searchCompany':
        $name = isset($_REQUEST['name']) ? $_REQUEST['name'] : '';

        // Return a fake "search result" that echoes the name you typed
        echo json_encode(array(
            'success' => true,
            'data' => array(
                array(
                    'CompanyName'         => ($name === '' ? 'Demo Company' : 'Result for: '.$name),
                    'CompanyType'         => 'Manufacturer',
                    'TierLevel'           => 2,
                    'Address'             => 'Demo City, DS',
                    'CurrentHealthScore'  => 88,
                    'OutgoingTransactions'=> 42,
                    'IncomingTransactions'=> 37,
                    'UpstreamCount'       => 5,
                    'DownstreamCount'     => 9
                )
            )
        ));
        break;

    // ---------------------------------------------------------
    // 2) KPIs
    // ---------------------------------------------------------
    case 'getKPIs':
        // Ignore dates/stuff for now, just return demo numbers
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'onTimeRate'         => 93.5,
                'totalShipments'     => 150,
                'delayedShipments'   => 10,
                'avgDelay'           => 1.7,
                'stdDelay'           => 0.9,
                'avgHealthScore'     => 82.3,
                'totalDisruptions'   => 7,
                'highImpactEvents'   => 2,
                'mediumImpactEvents' => 3,
                'lowImpactEvents'    => 2
            )
        ));
        break;

    // ---------------------------------------------------------
    // 3) DISRUPTION METRICS
    // ---------------------------------------------------------
    case 'getDisruptionMetrics':
        echo json_encode(array(
            'success' => true,
            'data' => array(
                'frequency'         => 12,
                'avgResolutionDays' => 4.2,
                'openEvents'        => 3
            )
        ));
        break;

    // ---------------------------------------------------------
    // 4) ALERTS
    // ---------------------------------------------------------
    case 'getAlerts':
        echo json_encode(array(
            'success' => true,
            'data' => array(
                array(
                    'EventID'     => 1,
                    'Description' => 'Demo alert: system is in test mode.',
                    'EventDate'   => date('Y-m-d H:i:s'),
                    'Severity'    => 'low',
                    'CompanyName' => 'System'
                ),
                array(
                    'EventID'     => 2,
                    'Description' => 'Demo high severity alert.',
                    'EventDate'   => date('Y-m-d H:i:s', time() - 3600),
                    'Severity'    => 'high',
                    'CompanyName' => 'Example Corp'
                )
            )
        ));
        break;

    // ---------------------------------------------------------
    // 5) UNKNOWN ACTION
    // ---------------------------------------------------------
    default:
        echo json_encode(array(
            'success' => false,
            'error'   => 'Invalid action: ' . $action,
            'debug'   => $_REQUEST
        ));
        break;
}
?>
