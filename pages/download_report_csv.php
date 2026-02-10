<?php
require_once __DIR__ . '/../config.php';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$officeType = $_GET['office_type'] ?? '';
$office = $_GET['office'] ?? '';
$fundingSource = $_GET['funding_source'] ?? '';
$claimStatus = $_GET['claim_status'] ?? '';
$reportType = $_GET['report_type'] ?? '';

$rciRows = [];
$esreRows = [];

if ($reportType === 'rci') {
    $rciSql = "SELECT
                d.cheque_date,
                d.cheque_no,
                d.dv_date,
                d.dv_no,
                d.cafoa_date,
                d.cafoa_no,
                GROUP_CONCAT(DISTINCT COALESCE(do.office, d.office) ORDER BY COALESCE(do.office, d.office) SEPARATOR ', ') AS offices,
                d.payee,
                d.particulars,
                d.dv_amount
            FROM documents d
            LEFT JOIN document_offices do ON do.document_id = d.id";

    $rciConditions = [];
    $rciTypes = '';
    $rciParams = [];

    if ($officeType !== '' && $office !== '') {
        $rciConditions[] = "EXISTS (SELECT 1 FROM document_offices do2 WHERE do2.document_id = d.id AND do2.office_type = ? AND do2.office = ?)";
        $rciTypes .= 'ss';
        $rciParams[] = $officeType;
        $rciParams[] = $office;
    } elseif ($officeType !== '') {
        $rciConditions[] = "EXISTS (SELECT 1 FROM document_offices do2 WHERE do2.document_id = d.id AND do2.office_type = ?)";
        $rciTypes .= 's';
        $rciParams[] = $officeType;
    } elseif ($office !== '') {
        $rciConditions[] = "(EXISTS (SELECT 1 FROM document_offices do2 WHERE do2.document_id = d.id AND do2.office = ?) OR (d.office = ? AND NOT EXISTS (SELECT 1 FROM document_offices do3 WHERE do3.document_id = d.id)))";
        $rciTypes .= 'ss';
        $rciParams[] = $office;
        $rciParams[] = $office;
    }

    if ($fundingSource !== '') {
        $rciConditions[] = "d.funding_source = ?";
        $rciTypes .= 's';
        $rciParams[] = $fundingSource;
    }

    if ($claimStatus !== '') {
        $rciConditions[] = "d.status = ?";
        $rciTypes .= 's';
        $rciParams[] = $claimStatus;
    }

    if ($dateFrom !== '') {
        $rciConditions[] = "d.cheque_date >= ?";
        $rciTypes .= 's';
        $rciParams[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $rciConditions[] = "d.cheque_date <= ?";
        $rciTypes .= 's';
        $rciParams[] = $dateTo;
    }

    if (!empty($rciConditions)) {
        $rciSql .= " WHERE " . implode(' AND ', $rciConditions);
    }

    $rciSql .= " GROUP BY d.id ORDER BY d.cheque_date DESC, d.id DESC";

    $rciStmt = $conn->prepare($rciSql);
    if ($rciStmt) {
        if (!empty($rciParams)) {
            $rciStmt->bind_param($rciTypes, ...$rciParams);
        }
        $rciStmt->execute();
        $rciResult = $rciStmt->get_result();
        if ($rciResult) {
            $rciRows = $rciResult->fetch_all(MYSQLI_ASSOC);
            $rciResult->free();
        }
        $rciStmt->close();
    }
} elseif ($reportType === 'esre') {
    $esreSql = "SELECT
                office,
                SUM(CASE WHEN fund_category = 'PS' THEN amount ELSE 0 END) AS ps_total,
                SUM(CASE WHEN fund_category = 'MOOE' THEN amount ELSE 0 END) AS mooe_total,
                SUM(CASE WHEN fund_category = 'CO' THEN amount ELSE 0 END) AS co_total,
                SUM(CASE WHEN fund_category = 'FE' THEN amount ELSE 0 END) AS fe_total,
                status,
                date_claimed,
                date_staled,
                date_cancelled,
                claimant
            FROM (
                SELECT
                    COALESCE(do.office, d.office, 'N/A') AS office,
                    do.office_type AS office_type,
                    COALESCE(do.fund_category, d.fund_category) AS fund_category,
                    COALESCE(do.responsibility_amount, d.responsibility_amount, 0) AS amount,
                    d.status,
                    d.date_claimed,
                    d.date_staled,
                    d.date_cancelled,
                    d.claimant,
                    d.funding_source,
                    d.cheque_date
                FROM documents d
                LEFT JOIN document_offices do ON do.document_id = d.id
            ) x";

    $esreConditions = [];
    $esreTypes = '';
    $esreParams = [];

    if ($officeType !== '' && $office !== '') {
        $esreConditions[] = "office_type = ? AND office = ?";
        $esreTypes .= 'ss';
        $esreParams[] = $officeType;
        $esreParams[] = $office;
    } elseif ($officeType !== '') {
        $esreConditions[] = "office_type = ?";
        $esreTypes .= 's';
        $esreParams[] = $officeType;
    } elseif ($office !== '') {
        $esreConditions[] = "office = ?";
        $esreTypes .= 's';
        $esreParams[] = $office;
    }

    if ($fundingSource !== '') {
        $esreConditions[] = "funding_source = ?";
        $esreTypes .= 's';
        $esreParams[] = $fundingSource;
    }

    if ($claimStatus !== '') {
        $esreConditions[] = "status = ?";
        $esreTypes .= 's';
        $esreParams[] = $claimStatus;
    }

    if ($dateFrom !== '') {
        $esreConditions[] = "cheque_date >= ?";
        $esreTypes .= 's';
        $esreParams[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $esreConditions[] = "cheque_date <= ?";
        $esreTypes .= 's';
        $esreParams[] = $dateTo;
    }

    if (!empty($esreConditions)) {
        $esreSql .= " WHERE " . implode(' AND ', $esreConditions);
    }

    $esreSql .= " GROUP BY office, status, date_claimed, date_staled, date_cancelled, claimant ORDER BY office ASC";

    $esreStmt = $conn->prepare($esreSql);
    if ($esreStmt) {
        if (!empty($esreParams)) {
            $esreStmt->bind_param($esreTypes, ...$esreParams);
        }
        $esreStmt->execute();
        $esreResult = $esreStmt->get_result();
        if ($esreResult) {
            $esreRows = $esreResult->fetch_all(MYSQLI_ASSOC);
            $esreResult->free();
        }
        $esreStmt->close();
    }
}

$filename = $reportType === 'esre' ? 'esre_report.csv' : 'rci_report.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);
$output = fopen('php://output', 'w');

if ($reportType === 'esre') {
    fputcsv($output, ['Office', 'PS', 'MOOE', 'CO', 'FE', 'Status', 'Status Date', 'Claimant']);
    foreach ($esreRows as $row) {
        $statusValue = $row['status'] ?? 'unclaimed';
        $statusDate = '';
        if ($statusValue === 'claimed') {
            $statusDate = $row['date_claimed'] ?? '';
        } elseif ($statusValue === 'stale') {
            $statusDate = $row['date_staled'] ?? '';
        } elseif ($statusValue === 'cancelled') {
            $statusDate = $row['date_cancelled'] ?? '';
        }
        fputcsv($output, [
            $row['office'] ?? '',
            (float)($row['ps_total'] ?? 0),
            (float)($row['mooe_total'] ?? 0),
            (float)($row['co_total'] ?? 0),
            (float)($row['fe_total'] ?? 0),
            ucfirst($statusValue),
            $statusDate,
            $row['claimant'] ?? ''
        ]);
    }
} else {
    fputcsv($output, ['Cheque Date', 'Cheque No.', 'DV Date', 'DV No.', 'CAFOA Date', 'CAFOA No.', 'Offices', 'Payee', 'Particulars', 'DV Amount']);
    foreach ($rciRows as $row) {
        fputcsv($output, [
            $row['cheque_date'] ?? '',
            $row['cheque_no'] ?? '',
            $row['dv_date'] ?? '',
            $row['dv_no'] ?? '',
            $row['cafoa_date'] ?? '',
            $row['cafoa_no'] ?? '',
            $row['offices'] ?? '',
            $row['payee'] ?? '',
            $row['particulars'] ?? '',
            (float)($row['dv_amount'] ?? 0)
        ]);
    }
}

fclose($output);
exit;
