<?php
require_once 'config.php';

$errorMessage = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$viewVersion = isset($_GET['version']) ? (int)$_GET['version'] : null;

if ($id <= 0) {
    $errorMessage = 'Invalid document ID.';
}

$document = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $document = $result ? $result->fetch_assoc() : null;
        $stmt->close();
    }
}

if ($id > 0 && !$document) {
    $errorMessage = 'Document not found.';
}

// Fetch history timeline
$historyTimeline = [];
if ($id > 0) {
    try {
        $historyStmt = $conn->prepare("SELECT * FROM document_history WHERE document_id = ? ORDER BY version ASC");
        if ($historyStmt) {
            $historyStmt->bind_param('i', $id);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            if ($historyResult) {
                $historyTimeline = $historyResult->fetch_all(MYSQLI_ASSOC);
                $historyResult->free();
            }
            $historyStmt->close();
        }
    } catch (Exception $e) {
        // document_history table may not exist yet - that's okay
        $historyTimeline = [];
    }
}

// Determine which version to display
$displayDocument = $document;
if ($viewVersion !== null && $viewVersion > 0) {
    // Check if the requested version exists in history
    foreach ($historyTimeline as $historyEntry) {
        if ($historyEntry['version'] == $viewVersion) {
            $displayDocument = $historyEntry;
            break;
        }
    }
}

$driverRows = [];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT driver_first_name, driver_middle_name, driver_last_name, driver_ext_name FROM document_drivers WHERE document_id = ? ORDER BY id ASC");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $driverRows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
        }
        $stmt->close();
    }
}

// If viewing a historical version, override driver data with historical driver data
if ($viewVersion !== null && $displayDocument && isset($displayDocument['driver_first_name'])) {
    $driverRows = [[
        'driver_first_name' => $displayDocument['driver_first_name'] ?? '',
        'driver_middle_name' => $displayDocument['driver_middle_name'] ?? '',
        'driver_last_name' => $displayDocument['driver_last_name'] ?? '',
        'driver_ext_name' => $displayDocument['driver_ext_name'] ?? '',
    ]];
}

if (empty($driverRows) && $document) {
    $driverRows[] = [
        'driver_first_name' => $document['driver_first_name'] ?? '',
        'driver_middle_name' => $document['driver_middle_name'] ?? '',
        'driver_last_name' => $document['driver_last_name'] ?? '',
        'driver_ext_name' => $document['driver_ext_name'] ?? '',
    ];
}

$expirationDate = $displayDocument['expiration_date'] ?? null;
$currentDate = date('Y-m-d');
$isExpired = $expirationDate && $expirationDate < $currentDate;
$statusText = $isExpired ? 'EXPIRED' : 'VALID';
$statusClass = $isExpired ? 'danger' : 'success';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/add_page.css">
    <title>View Document</title>
</head>
<body>
    <div class="main-content">
        <div class="header-add">
            <h1 class="page-title">Documents</h1>
            <div class="breadcrumb">
                <span class="material-symbols-rounded">chevron_right</span>
                <span class="">View Document</span>
            </div>
        </div>
        <div class="card">
            <div class="form-title">
                <h2>View Document</h2>
            </div>
            <?php if ($errorMessage): ?>
                <p style="color: red;"><?php echo $errorMessage; ?></p>
            <?php endif; ?>
            
            <?php if (!empty($historyTimeline)): ?>
                <div style="background: #f0f8ff; border: 1px solid #b0d4ff; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
                    <h3 style="margin-top: 0; font-size: 14px; color: #333;">📋 Update History</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <a href="template.php?page=view_document&id=<?php echo $id; ?>" 
                           class="btn" 
                           style="padding: 6px 12px; font-size: 12px; background: <?php echo $viewVersion === null ? '#4CAF50' : '#ddd'; ?>; color: <?php echo $viewVersion === null ? 'white' : '#333'; ?>; border-radius: 4px; text-decoration: none; border-none;">
                            <strong>Current</strong>
                        </a>
                        <?php foreach ($historyTimeline as $historyEntry): ?>
                            <a href="template.php?page=view_document&id=<?php echo $id; ?>&version=<?php echo $historyEntry['version']; ?>"
                               class="btn"
                               style="padding: 6px 12px; font-size: 12px; background: <?php echo $viewVersion === $historyEntry['version'] ? '#FF9800' : '#ddd'; ?>; color: <?php echo $viewVersion === $historyEntry['version'] ? 'white' : '#333'; ?>; border-radius: 4px; text-decoration: none; border-none; cursor: pointer;">
                                v<?php echo $historyEntry['version']; ?> (<?php echo date('M d, Y H:i', strtotime($historyEntry['changed_at'])); ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($viewVersion !== null): ?>
                        <p style="margin-top: 10px; font-size: 12px; color: #666; margin-bottom: 0;">
                            Showing version <?php echo $viewVersion; ?> from <?php echo date('F d, Y \a\t H:i:s', strtotime($displayDocument['changed_at'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
                <form id="viewDocumentForm">

                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="franchise_no">Franchise No.</label>
                            <input type="text" id="franchise_no" name="franchise_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchise_no']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <div style="padding: 8px 0;">
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!------- Franchisee Section ------->
                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="franchisee_first_name">Franchisee First Name</label>
                            <input type="text" id="franchisee_first_name" name="franchisee_first_name" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchisee_first_name']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_middle_name">Franchisee Middle Name</label>
                            <input type="text" id="franchisee_middle_name" name="franchisee_middle_name" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchisee_middle_name']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_last_name">Franchisee Last Name</label>
                            <input type="text" id="franchisee_last_name" name="franchisee_last_name" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchisee_last_name']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_ext_name">Franchisee Ext Name</label>
                            <input type="text" id="franchisee_ext_name" name="franchisee_ext_name" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchisee_ext_name']) : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- Address Section ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['address']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <input type="text" id="barangay" name="barangay" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['barangay']) : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- Vehicle Section ------->
                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="make">Make</label>
                            <input type="text" id="make" name="make" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['make']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="year_model">Year Model</label>
                            <input type="text" id="year_model" name="year_model" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['year_model']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="motor_no">Motor No.</label>
                            <input type="text" id="motor_no" name="motor_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['motor_no']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="plate_no">Plate No.</label>
                            <input type="text" id="plate_no" name="plate_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['plate_no']) : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- Driver ------->
                    <div class="card responsibility-section">
                        <h3>Driver Details</h3>
                        <?php foreach ($driverRows as $driverRow): ?>
                            <div class="form-row-4" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                                <div class="form-group">
                                    <label for="driver_first_name">Driver First Name</label>
                                    <input type="text" id="driver_first_name" name="driver_first_name" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_first_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="driver_middle_name">Driver Middle Name</label>
                                    <input type="text" id="driver_middle_name" name="driver_middle_name" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_middle_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="driver_last_name">Driver Last Name</label>
                                    <input type="text" id="driver_last_name" name="driver_last_name" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_last_name'] ?? ''); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="driver_ext_name">Driver Ext Name</label>
                                    <input type="text" id="driver_ext_name" name="driver_ext_name" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_ext_name'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!------- Registration Details ------->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="registration_date">Registration Date</label>
                            <input type="date" id="registration_date" name="registration_date" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['registration_date']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="expiration_date">Expiration Date</label>
                            <input type="date" id="expiration_date" name="expiration_date" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['expiration_date']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="registration_no">Registration No.</label>
                            <input type="text" id="registration_no" name="registration_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['registration_no'] ?? '') : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- Fees Section ------->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="franchisee_fee">Franchisee Fee</label>
                            <input type="number" id="franchisee_fee" name="franchisee_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['franchisee_fee']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="sticker_fee">Sticker Fee</label>
                            <input type="number" id="sticker_fee" name="sticker_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['sticker_fee']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="filing_fee">Filing Fee</label>
                            <input type="number" id="filing_fee" name="filing_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['filing_fee']) : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="penalty_fee">Penalty Fee</label>
                            <input type="number" id="penalty_fee" name="penalty_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['penalty_fee']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="transfer_fee">Transfer Fee</label>
                            <input type="number" id="transfer_fee" name="transfer_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['transfer_fee']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="plate_fee">Plate Fee</label>
                            <input type="number" id="plate_fee" name="plate_fee" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['plate_fee']) : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="total_amount">Total Amount</label>
                            <input type="number" id="total_amount" name="total_amount" class="form-control no-arrows" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['total_amount']) : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- OR Details ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="or_no">OR No.</label>
                            <input type="text" id="or_no" name="or_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['or_no']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="or_date">OR Date</label>
                            <input type="date" id="or_date" name="or_date" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['or_date']) : ''; ?>" readonly>
                        </div>
                    </div>

                    <!------- CTC Details ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="ctc_no">CTC No.</label>
                            <input type="text" id="ctc_no" name="ctc_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['ctc_no']) : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="ctc_date">CTC Date</label>
                            <input type="date" id="ctc_date" name="ctc_date" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['ctc_date']) : ''; ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="sticker_no">Sticker No.</label>
                            <input type="text" id="sticker_no" name="sticker_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['sticker_no'] ?? '') : ''; ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="toda_no">TODA No.</label>
                            <input type="text" id="toda_no" name="toda_no" class="form-control" value="<?php echo $displayDocument ? htmlspecialchars($displayDocument['toda_no'] ?? '') : ''; ?>" readonly>
                        </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" rows="3" readonly><?php echo $displayDocument ? htmlspecialchars($displayDocument['remarks']) : ''; ?></textarea>
                    </div>

                    <!------- SAVE CANCEL ------->
                    <div class="form-row-2">
                        <a href="pages/generate_pdf.php?id=<?php echo $id; ?>" class="btn" style="text-align: center; background-color: var(--color-success-bg); color: var(--color-success-text); text-decoration: none;">
                            <span class="material-symbols-outlined">download</span>
                        </a>
                        <a href="template.php?page=documents" class="btn cancel-btn" style="text-align: center;">Back</a>
                    </div>


                </form>
        </div>
    </div>
</body>
</html>
