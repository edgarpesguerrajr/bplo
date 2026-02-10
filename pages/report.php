<?php
require_once 'config.php';

$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$officeType = $_GET['office_type'] ?? '';
$office = $_GET['office'] ?? '';
$fundingSource = $_GET['funding_source'] ?? '';
$claimStatus = $_GET['claim_status'] ?? '';
$reportType = $_GET['report_type'] ?? '';
$showResult = $reportType !== '';

$tfSources = ['TF-Regular', 'TF-PCSO', 'TF-MWSS', 'TF'];
if ($fundingSource === 'SEF' || in_array($fundingSource, $tfSources, true)) {
    $officeType = '';
}

$totalAmount = null;
$rciRows = [];
$esreRows = [];
$csvUrl = '';

if ($showResult) {
        $sql = "SELECT SUM(CASE WHEN do.id IS NULL THEN d.responsibility_amount ELSE do.responsibility_amount END) AS total_amount
            FROM documents d
            LEFT JOIN document_offices do ON do.document_id = d.id";

    $conditions = [];
    $types = '';
    $params = [];

    if ($officeType !== '' && $office !== '') {
        $conditions[] = "do.office_type = ? AND do.office = ?";
        $types .= 'ss';
        $params[] = $officeType;
        $params[] = $office;
    } elseif ($officeType !== '') {
        $conditions[] = "do.office_type = ?";
        $types .= 's';
        $params[] = $officeType;
    } elseif ($office !== '') {
        $conditions[] = "(do.office = ? OR (do.id IS NULL AND d.office = ?))";
        $types .= 'ss';
        $params[] = $office;
        $params[] = $office;
    }

    if ($fundingSource !== '') {
        $conditions[] = "d.funding_source = ?";
        $types .= 's';
        $params[] = $fundingSource;
    }

    if ($claimStatus !== '') {
        $conditions[] = "d.status = ?";
        $types .= 's';
        $params[] = $claimStatus;
    }

    if ($dateFrom !== '') {
        $conditions[] = "d.cheque_date >= ?";
        $types .= 's';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $conditions[] = "d.cheque_date <= ?";
        $types .= 's';
        $params[] = $dateTo;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $totalAmount = (float)($row['total_amount'] ?? 0);
            $result->free();
        }
        $stmt->close();
    }
}

if ($showResult && $reportType === 'rci') {
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
}

if ($showResult && $reportType === 'esre') {
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

if ($showResult) {
    $queryParams = $_GET;
    $csvUrl = 'pages/download_report_csv.php?' . http_build_query($queryParams);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,-50..200" />
    <title>Reports</title>
    <style>
        
    .report-form {
    max-width: 100%;
    }

    .report-form .form-row-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
    }

    .report-form .form-row-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 20px;
    }

    .report-form .form-group {
    display: flex;
    flex-direction: column;
    }

    .report-form label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 8px;
    }

    .report-form .form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--color-border-hr);
    border-radius: 8px;
    background: var(--color-bg-sidebar);
    font-size: 0.95rem;
    color: var(--color-text-primary);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .report-form .form-control:focus {
    border-color: var(--color-hover-primary);
    box-shadow: 0 0 6px var(--color-shadow);
    outline: none;
    }

    .report-form .btn {
    width: 100%;
    max-width: 220px;
    }

    .report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
    }

    .print-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 14px;
    border-radius: 8px;
    border: 1px solid var(--color-border-hr);
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
    cursor: pointer;
    transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.1s ease;
    }

    .print-btn:hover {
    background: var(--color-hover-secondary);
    border-color: var(--color-hover-primary);
    }

    .print-btn:active {
    transform: scale(0.98);
    }

    .print-btn .material-symbols-outlined {
    font-size: 18px;
    line-height: 1;
    }

    .filters-summary {
    margin-bottom: 12px;
    font-size: 0.95rem;
    color: var(--color-text-primary);
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    }

    .filters-summary strong {
    font-weight: 600;
    }

    @media print {
    body * {
        visibility: hidden;
    }

    .report-print-area, .report-print-area * {
        visibility: visible;
    }

    .report-print-area {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }

    .print-btn {
        display: none !important;
    }
    }

    @media (max-width: 768px) {
    .report-form .form-row-2 {
        grid-template-columns: 1fr;
    }
    }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <h1 class="page-title">Reports</h1>
        </div>
        <div class="card">
            <div class="form-title">
                <h2>Generate Report</h2>
            </div>
            <form class="report-form" method="GET" action="template.php">
                <input type="hidden" name="page" value="report">
                <div class="form-row-2">
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                </div>

                <div class="form-row-4">
                    <div class="form-group">
                        <label for="funding_source">Funding Source</label>
                        <select id="funding_source" name="funding_source" class="form-control">
                            <option value="" <?php echo $fundingSource === '' ? 'selected' : ''; ?>>All Sources</option>
                            <option value="GF" <?php echo $fundingSource === 'GF' ? 'selected' : ''; ?>>GF - General Fund</option>
                            <option value="SEF" <?php echo $fundingSource === 'SEF' ? 'selected' : ''; ?>>SEF - Special Education Fund</option>
                            <option value="TF-Regular" <?php echo $fundingSource === 'TF-Regular' ? 'selected' : ''; ?>>TF - Regular</option>
                            <option value="TF-PCSO" <?php echo $fundingSource === 'TF-PCSO' ? 'selected' : ''; ?>>TF - PCSO</option>
                            <option value="TF-MWSS" <?php echo $fundingSource === 'TF-MWSS' ? 'selected' : ''; ?>>TF - MWSS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="office_type">Office Type</label>
                        <select id="office_type" name="office_type" class="form-control">
                            <option value="" <?php echo $officeType === '' ? 'selected' : ''; ?>>All Office Types</option>
                            <option value="General Fund" <?php echo $officeType === 'General Fund' ? 'selected' : ''; ?>>General Fund</option>
                            <option value="Health Services" <?php echo $officeType === 'Health Services' ? 'selected' : ''; ?>>Health Services</option>
                            <option value="Social Welfare Services" <?php echo $officeType === 'Social Welfare Services' ? 'selected' : ''; ?>>Social Welfare Services</option>
                            <option value="Economic Services" <?php echo $officeType === 'Economic Services' ? 'selected' : ''; ?>>Economic Services</option>
                            <option value="Public Debt" <?php echo $officeType === 'Public Debt' ? 'selected' : ''; ?>>Public Debt</option>
                            <option value="Local Disaster Risk Reduction" <?php echo $officeType === 'Local Disaster Risk Reduction' ? 'selected' : ''; ?>>Local Disaster Risk Reduction</option>
                            <option value="Special Purpose Fund" <?php echo $officeType === 'Special Purpose Fund' ? 'selected' : ''; ?>>Special Purpose Fund</option>
                            <option value="Continuing Appropriation" <?php echo $officeType === 'Continuing Appropriation' ? 'selected' : ''; ?>>Continuing Appropriation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="office">Office</label>
                        <select id="office" name="office" class="form-control" data-selected="<?php echo htmlspecialchars($office); ?>">
                            <option value="">All Offices</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="claim_status">Status</label>
                        <select id="claim_status" name="claim_status" class="form-control">
                            <option value="" <?php echo $claimStatus === '' ? 'selected' : ''; ?>>All</option>
                            <option value="claimed" <?php echo $claimStatus === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                            <option value="unclaimed" <?php echo $claimStatus === 'unclaimed' ? 'selected' : ''; ?>>Unclaimed</option>
                            <option value="stale" <?php echo $claimStatus === 'stale' ? 'selected' : ''; ?>>Stale</option>
                            <option value="cancelled" <?php echo $claimStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-2" style="margin-top: 20px;">
                    <button type="submit" class="btn" name="report_type" value="rci">Generate RCI</button>
                    <button type="submit" class="btn" name="report_type" value="esre">Generate eSRE</button>
                </div>
            </form>

            <?php if ($showResult && $reportType === 'rci'): ?>
                <div class="form-row-2 report-print-area">
                    <div class="form-group">
                        <div class="report-header">
                            <label>Report of Cheque Issued (RCI)</label>
                            <div style="display: inline-flex; gap: 8px;">
                                <a class="btn print-btn" href="<?php echo htmlspecialchars($csvUrl); ?>">
                                    <span class="material-symbols-outlined">download</span>
                                    <span>Download CSV</span>
                                </a>
                                <button type="button" class="btn print-btn" onclick="window.print();">
                                    <span class="material-symbols-outlined">print</span>
                                    <span>Print</span>
                                </button>
                            </div>
                        </div>
                        <div class="filters-summary">
                            <span><strong>Date From:</strong> <?php echo htmlspecialchars($dateFrom !== '' ? $dateFrom : 'All'); ?></span>
                            <span><strong>Date To:</strong> <?php echo htmlspecialchars($dateTo !== '' ? $dateTo : 'All'); ?></span>
                            <span><strong>Funding Source:</strong> <?php echo htmlspecialchars($fundingSource !== '' ? $fundingSource : 'All'); ?></span>
                            <span><strong>Office Type:</strong> <?php echo htmlspecialchars($officeType !== '' ? $officeType : 'All'); ?></span>
                            <span><strong>Office:</strong> <?php echo htmlspecialchars($office !== '' ? $office : 'All'); ?></span>
                            <span><strong>Status:</strong> <?php echo htmlspecialchars($claimStatus !== '' ? ucfirst($claimStatus) : 'All'); ?></span>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="documents-table">
                                <thead>
                                    <tr>
                                        <th>Cheque Date</th>
                                        <th>Cheque No.</th>
                                        <th>DV Date</th>
                                        <th>DV No.</th>
                                        <th>CAFOA Date</th>
                                        <th>CAFOA No.</th>
                                        <th>Offices</th>
                                        <th>Payee</th>
                                        <th>Particulars</th>
                                        <th>DV Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($rciRows) === 0): ?>
                                        <tr>
                                            <td colspan="10" style="text-align:center; padding: 16px;">No records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rciRows as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['cheque_date'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['cheque_no'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['dv_date'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['dv_no'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['cafoa_date'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['cafoa_no'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['offices'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['payee'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['particulars'] ?? ''); ?></td>
                                                <td>₱<?php echo number_format((float)($row['dv_amount'] ?? 0), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showResult && $reportType === 'esre'): ?>
                <div class="form-row-2 report-print-area">
                    <div class="form-group">
                        <div class="report-header">
                            <label>eSRE</label>
                            <div style="display: inline-flex; gap: 8px;">
                                <a class="btn print-btn" href="<?php echo htmlspecialchars($csvUrl); ?>">
                                    <span class="material-symbols-outlined">download</span>
                                    <span>Download CSV</span>
                                </a>
                                <button type="button" class="btn print-btn" onclick="window.print();">
                                    <span class="material-symbols-outlined">print</span>
                                    <span>Print</span>
                                </button>
                            </div>
                        </div>
                        <div class="filters-summary">
                            <span><strong>Date From:</strong> <?php echo htmlspecialchars($dateFrom !== '' ? $dateFrom : 'All'); ?></span>
                            <span><strong>Date To:</strong> <?php echo htmlspecialchars($dateTo !== '' ? $dateTo : 'All'); ?></span>
                            <span><strong>Funding Source:</strong> <?php echo htmlspecialchars($fundingSource !== '' ? $fundingSource : 'All'); ?></span>
                            <span><strong>Office Type:</strong> <?php echo htmlspecialchars($officeType !== '' ? $officeType : 'All'); ?></span>
                            <span><strong>Office:</strong> <?php echo htmlspecialchars($office !== '' ? $office : 'All'); ?></span>
                            <span><strong>Status:</strong> <?php echo htmlspecialchars($claimStatus !== '' ? ucfirst($claimStatus) : 'All'); ?></span>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="documents-table">
                                <thead>
                                    <tr>
                                        <th>Office</th>
                                        <th>PS</th>
                                        <th>MOOE</th>
                                        <th>CO</th>
                                        <th>FE</th>
                                        <th>Status</th>
                                        <th>Status Date</th>
                                        <th>Claimant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $psTotal = 0.0;
                                        $mooeTotal = 0.0;
                                        $coTotal = 0.0;
                                        $feTotal = 0.0;
                                        foreach ($esreRows as $row) {
                                            $psTotal += (float)($row['ps_total'] ?? 0);
                                            $mooeTotal += (float)($row['mooe_total'] ?? 0);
                                            $coTotal += (float)($row['co_total'] ?? 0);
                                            $feTotal += (float)($row['fe_total'] ?? 0);
                                        }
                                    ?>
                                    <?php if (count($esreRows) === 0): ?>
                                        <tr>
                                            <td colspan="8" style="text-align:center; padding: 16px;">No records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($esreRows as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['office'] ?? ''); ?></td>
                                                <td>₱<?php echo number_format((float)($row['ps_total'] ?? 0), 2); ?></td>
                                                <td>₱<?php echo number_format((float)($row['mooe_total'] ?? 0), 2); ?></td>
                                                <td>₱<?php echo number_format((float)($row['co_total'] ?? 0), 2); ?></td>
                                                <td>₱<?php echo number_format((float)($row['fe_total'] ?? 0), 2); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'unclaimed')); ?></td>
                                                <td>
                                                    <?php
                                                        $statusDate = '';
                                                        $statusValue = $row['status'] ?? 'unclaimed';
                                                        if ($statusValue === 'claimed') {
                                                            $statusDate = $row['date_claimed'] ?? '';
                                                        } elseif ($statusValue === 'stale') {
                                                            $statusDate = $row['date_staled'] ?? '';
                                                        } elseif ($statusValue === 'cancelled') {
                                                            $statusDate = $row['date_cancelled'] ?? '';
                                                        }
                                                        echo htmlspecialchars($statusDate);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['claimant'] ?? ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                            <td><strong>Total</strong></td>
                                            <td><strong>₱<?php echo number_format($psTotal, 2); ?></strong></td>
                                            <td><strong>₱<?php echo number_format($mooeTotal, 2); ?></strong></td>
                                            <td><strong>₱<?php echo number_format($coTotal, 2); ?></strong></td>
                                            <td><strong>₱<?php echo number_format($feTotal, 2); ?></strong></td>
                                            <td></td>
                                            <td></td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    const gfOfficeTypeOptions = {
        "General Fund": [
            { value: "1011", label: "1011 - Municipal Mayor's Office" },
            { value: "1016", label: "1016 - Municipal Vice Mayor's Office" },
            { value: "1021", label: "1021 - Sangguniang Bayan" },
            { value: "1022", label: "1022 - Office of the Secretary to the Sangguniang" },
            { value: "1031", label: "1031 - Municipal Administrative Services" },
            { value: "1031-001", label: "1031-001 - Management Administration" },
            { value: "1031-002", label: "1031-002 - Human Resource Management" },
            { value: "1031-003", label: "1031-003 - Business Permit and Licensing" },
            { value: "1031-004", label: "1031-004 - Public Employment Services Office" },
            { value: "1031-005", label: "1031-005 - Information Management Service" },
            { value: "1031-006", label: "1031-006 - Public Information Management Office" },
            { value: "1031-007", label: "1031-007 - Municipal Anti Drug Abuse Center" },
            { value: "1031-008", label: "1031-008 - Population Management Office" },
            { value: "1031-009", label: "1031-009 - Technology and Livelihood Development" },
            { value: "1031-010", label: "1031-010 - Community Affairs Office" },
            { value: "1031-011", label: "1031-011 - Sports and Development" },
            { value: "1031-012", label: "1031-012 - Municipal Cemetery Management" },
            { value: "1031-013", label: "1031-013 - Public Safety Office" },
            { value: "1031-014", label: "1031-014 - Tourism Management Office" },
            { value: "1041", label: "1041 - Municipal Planning Development Office" },
            { value: "1051", label: "1051 - Civil Registry Office" },
            { value: "1061", label: "1061 - General Services Office" },
            { value: "1071", label: "1071 - Municipal Budget Office" },
            { value: "1081", label: "1081 - Municipal Accounting & Internal Audit" },
            { value: "1091", label: "1091 - Municipal Treasurer's Office" },
            { value: "1091-400", label: "1091-400 - Municipal Treasurer's Office (FE)" },
            { value: "1101", label: "1101 - Municipal Assessor's Office" },
            { value: "1131", label: "1131 - Legal Services Department" },
            { value: "1181", label: "1181 - Philippine National Police" },
            { value: "1191", label: "1191 - Office of the Fire Department" }
        ],
        "Health Services": [
            { value: "4411", label: "4411 - Municipal Health Department" },
            { value: "4918", label: "4918 - LDF - Purchase, Construction" },
            { value: "4919", label: "4919 - LDF - Other Health Development Projects" },
            { value: "5000", label: "5000 - Labor and Employment" },
            { value: "5999", label: "5999 - Public Employment Services Offices" },
            { value: "6918", label: "6918 - LDF - Purchase, Construction" }
        ],
        "Social Welfare Services": [
            { value: "7611", label: "7611 - Municipal Social Welfare Development" },
            { value: "7918", label: "7918 - 20% CDF Social Welfare Services" },
            { value: "7999", label: "7999 - LDF - Miscellaneous, Other Social Services" }
        ],
        "Economic Services": [
            { value: "8700", label: "8700 - Agricultural Development" },
            { value: "8711", label: "8711 - Agriculture Department" },
            { value: "8731", label: "8731 - National Resource Services" },
            { value: "8751", label: "8751 - Municipal Engineering Services" },
            { value: "8800", label: "8800 - Operation of Market and Slaughterhouse" },
            { value: "8811", label: "8811 - Tanay Public Market" },
            { value: "8812", label: "8812 - Operation of Slaughterhouse" },
            { value: "8852", label: "8852 - Tourism Services - Daranak" },
            { value: "8918", label: "8918 - LDF - Purchase, Construction" },
            { value: "8919", label: "8919 - LDF - Other Economic Development Projects" }
        ],
        "Public Services": [],
        "Public Debt": [
            { value: "9921-400", label: "9921-400 - Public Debt (FE)" }
        ],
        "Local Disaster Risk Reduction": [
            { value: "9941", label: "9941 - 30% QRF - Relief Recovery" },
            { value: "9942", label: "9942 - 70% Preparedness and Mitigation Projects" },
            { value: "9943", label: "9943 - 70% Preparedness and Mitigation" }
        ],
        "Special Purpose Fund": [
            { value: "9999-1", label: "9999-1 - AID to Barangays" },
            { value: "9999-2", label: "9999-2 - Others - Local Children's Protection Council" },
            { value: "9999-3", label: "9999-3 - Others - Senior Citizens and PWD" },
            { value: "9999-4", label: "9999-4 - Others - Gender and Development" }
        ],
        "Continuing Appropriation": [
            { value: "C1011", label: "C1011 - Municipal Mayor's Office-Continuing" },
            { value: "C1016", label: "C1016 - Municipal Vice Mayor's Office-Continuing" },
            { value: "C1021", label: "C1021 - Sangguniang Bayan-Continuing" },
            { value: "C1031", label: "C1031 - Municipal Administrative Services-Continuing" },
            { value: "C1041", label: "C1041 - Municipal Planning Development Office-Continuing" },
            { value: "C1061", label: "C1061 - General Services Office-Continuing" },
            { value: "C1071", label: "C1071 - Municipal Budget Office-Continuing" },
            { value: "C1081", label: "C1081 - Municipal Accounting & Internal Audit-Continuing" },
            { value: "C1091", label: "C1091 - Municipal Treasurer's Office-Continuing" },
            { value: "C1131", label: "C1131 - Legal Services Department-Continuing" },
            { value: "C1181", label: "C1181 - Philippine National Police-Continuing" },
            { value: "C4411", label: "C4411 - Municipal Health Department-Continuing" },
            { value: "C4918", label: "C4918 - LDF - Purchase, Construction-Continuing" },
            { value: "C6918", label: "C6918 - LDF - Purchase, Construction-Continuing" },
            { value: "C8731", label: "C8731 - National Resource Services-Continuing" },
            { value: "C8751", label: "C8751 - Municipal Engineering Services-Continuing" },
            { value: "C8811", label: "C8811 - Tanay Public Market-Continuing" },
            { value: "C8812", label: "C8812 - Operation of Slaughterhouse-Continuing" },
            { value: "C8852", label: "C8852 - Tourism Services - Daranak-Continuing" },
            { value: "C8918", label: "C8918 - LDF - Purchase, Construction-Continuing" },
            { value: "C9940", label: "C9940 - Local Disaster Risk Reduction-Continuing" },
            { value: "C9943", label: "C9943 - 70% Preparedness and Mitigation-Continuing" }
        ]
    };

    const sefOfficeOptions = [
        { value: "Cluster 1", label: "Cluster 1" },
        { value: "Cluster 2", label: "Cluster 2" },
        { value: "Cluster 3", label: "Cluster 3" },
        { value: "Cluster 4", label: "Cluster 4" }
    ];

    const tfOfficeOptions = [
        { value: "General Public Services", label: "General Public Services" },
        { value: "Education, Culture & Sports/Manpower Development", label: "Education, Culture & Sports/Manpower Development" },
        { value: "Health, Nutrition & Population Control", label: "Health, Nutrition & Population Control" },
        { value: "Labor and Employment", label: "Labor and Employment" },
        { value: "Housing and Community Development", label: "Housing and Community Development" },
        { value: "Social Services and Social Welfare", label: "Social Services and Social Welfare" },
        { value: "Economic Services", label: "Economic Services" }
    ];

    const officeTypeSelect = document.getElementById('office_type');
    const officeSelect = document.getElementById('office');
    const fundingSourceSelect = document.getElementById('funding_source');
    const tfFundingSources = ['TF-Regular', 'TF-PCSO', 'TF-MWSS', 'TF'];

    const setOfficeOptions = (options, selectedOffice = '') => {
        if (!officeSelect) return;
        officeSelect.innerHTML = '<option value="">All Offices</option>';
        options.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            officeSelect.appendChild(option);
        });
        if (selectedOffice) {
            officeSelect.value = selectedOffice;
        }
    };

    const updateOfficeOptions = () => {
        if (!officeSelect || !officeTypeSelect) return;
        const selectedOffice = officeSelect.dataset.selected || '';
        const fundingSource = fundingSourceSelect ? fundingSourceSelect.value : '';

        if (fundingSource === 'SEF') {
            officeTypeSelect.value = '';
            officeTypeSelect.disabled = true;
            setOfficeOptions(sefOfficeOptions, selectedOffice);
            return;
        }

        if (tfFundingSources.includes(fundingSource)) {
            officeTypeSelect.value = '';
            officeTypeSelect.disabled = true;
            setOfficeOptions(tfOfficeOptions, selectedOffice);
            return;
        }

        officeTypeSelect.disabled = false;
        const officeType = officeTypeSelect.value;
        const options = gfOfficeTypeOptions[officeType] || [];
        setOfficeOptions(options, selectedOffice);
    };

    if (officeTypeSelect) {
        officeTypeSelect.addEventListener('change', () => {
            if (officeSelect) {
                officeSelect.dataset.selected = '';
            }
            updateOfficeOptions();
        });
    }

    if (fundingSourceSelect) {
        fundingSourceSelect.addEventListener('change', () => {
            if (officeSelect) {
                officeSelect.dataset.selected = '';
            }
            updateOfficeOptions();
        });
    }

    updateOfficeOptions();
    </script>
</body>
</html>