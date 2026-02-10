<?php
require_once 'config.php';

$successMessage = '';
$errorMessage = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $errorMessage = 'Invalid document ID.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $franchise_no = trim($_POST['franchisee_no'] ?? '');
    $franchisee_first_name = trim($_POST['franchisee_first_name'] ?? '');
    $franchisee_middle_name = trim($_POST['franchisee_middle_name'] ?? '');
    $franchisee_last_name = trim($_POST['franchisee_last_name'] ?? '');
    $franchisee_ext_name = trim($_POST['franchisee_ext_name'] ?? '');

    $address = trim($_POST['address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');

    $make = trim($_POST['make'] ?? '');
    $year_model = trim($_POST['year_model'] ?? '');
    $motor_no = trim($_POST['motor_no'] ?? '');
    $plate_no = trim($_POST['plate_no'] ?? '');

    $driver_first_names = $_POST['driver_first_name'] ?? [];
    $driver_middle_names = $_POST['driver_middle_name'] ?? [];
    $driver_last_names = $_POST['driver_last_name'] ?? [];
    $driver_ext_names = $_POST['driver_ext_name'] ?? [];

    if (!is_array($driver_first_names)) { $driver_first_names = []; }
    if (!is_array($driver_middle_names)) { $driver_middle_names = []; }
    if (!is_array($driver_last_names)) { $driver_last_names = []; }
    if (!is_array($driver_ext_names)) { $driver_ext_names = []; }

    $driver_rows = [];
    $maxDriverRows = max(count($driver_first_names), count($driver_middle_names), count($driver_last_names), count($driver_ext_names));
    for ($i = 0; $i < $maxDriverRows; $i++) {
        $first = trim($driver_first_names[$i] ?? '');
        $middle = trim($driver_middle_names[$i] ?? '');
        $last = trim($driver_last_names[$i] ?? '');
        $ext = trim($driver_ext_names[$i] ?? '');
        if ($first === '' && $middle === '' && $last === '' && $ext === '') {
            continue;
        }
        $driver_rows[] = [
            'driver_first_name' => $first,
            'driver_middle_name' => $middle,
            'driver_last_name' => $last,
            'driver_ext_name' => $ext,
        ];
    }

    $primary_driver = $driver_rows[0] ?? [
        'driver_first_name' => '',
        'driver_middle_name' => '',
        'driver_last_name' => '',
        'driver_ext_name' => '',
    ];
    $driver_first_name = $primary_driver['driver_first_name'];
    $driver_middle_name = $primary_driver['driver_middle_name'];
    $driver_last_name = $primary_driver['driver_last_name'];
    $driver_ext_name = $primary_driver['driver_ext_name'];

    $registration_date = trim($_POST['registration_date'] ?? '');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $registration_no = trim($_POST['registration_no'] ?? '');
    $sticker_no = trim($_POST['sticker_no'] ?? '');
    $toda_no = trim($_POST['toda_no'] ?? '');

    $franchisee_fee = !empty($_POST['franchisee_fee']) ? (float)$_POST['franchisee_fee'] : 0.00;
    $sticker_fee = !empty($_POST['sticker_fee']) ? (float)$_POST['sticker_fee'] : 0.00;
    $filing_fee = !empty($_POST['filing_fee']) ? (float)$_POST['filing_fee'] : 0.00;
    $penalty_fee = !empty($_POST['penalty_fee']) ? (float)$_POST['penalty_fee'] : 0.00;
    $transfer_fee = !empty($_POST['transfer_fee']) ? (float)$_POST['transfer_fee'] : 0.00;
    $plate_fee = !empty($_POST['plate_fee']) ? (float)$_POST['plate_fee'] : 0.00;
    $total_amount = !empty($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0.00;

    $or_no = trim($_POST['or_no'] ?? '');
    $or_date = trim($_POST['or_date'] ?? '');
    $ctc_no = trim($_POST['ctc_no'] ?? '');
    $ctc_date = trim($_POST['ctc_date'] ?? '');

    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($franchise_no) || empty($franchisee_first_name) || empty($franchisee_last_name)) {
        $errorMessage = 'Franchise No., First Name, and Last Name are required.';
    } else {
        // Save current document state to history before updating
        $currentDoc = [];
        $historyStmt = $conn->prepare("SELECT * FROM documents WHERE id = ?");
        if ($historyStmt) {
            $historyStmt->bind_param('i', $id);
            $historyStmt->execute();
            $historyResult = $historyStmt->get_result();
            if ($historyResult) {
                $currentDoc = $historyResult->fetch_assoc() ?? [];
                $historyResult->free();
            }
            $historyStmt->close();
        }

        if (!empty($currentDoc)) {
            // Get next version number
            $versionStmt = $conn->prepare("SELECT MAX(version) as max_version FROM document_history WHERE document_id = ?");
            if ($versionStmt) {
                $versionStmt->bind_param('i', $id);
                $versionStmt->execute();
                $versionResult = $versionStmt->get_result();
                $versionRow = $versionResult->fetch_assoc();
                $nextVersion = ($versionRow['max_version'] ?? 0) + 1;
                $versionResult->free();
                $versionStmt->close();

                // Insert into history
                $insertHistorySql = "INSERT INTO document_history (
                    document_id, version, franchise_no, franchisee_first_name, franchisee_middle_name, franchisee_last_name, franchisee_ext_name,
                    address, barangay, make, year_model, motor_no, plate_no,
                    driver_first_name, driver_middle_name, driver_last_name, driver_ext_name,
                    registration_date, expiration_date, registration_no, sticker_no, toda_no,
                    franchisee_fee, sticker_fee, filing_fee, penalty_fee, transfer_fee, plate_fee, total_amount,
                    or_no, or_date, ctc_no, ctc_date, remarks
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )";
                
                $insertHistoryStmt = $conn->prepare($insertHistorySql);
                if ($insertHistoryStmt) {
                    $insertHistoryStmt->bind_param(
                        "iisssssssssssssssssssdddddddssssss",
                        $id, $nextVersion,
                        $currentDoc['franchise_no'], $currentDoc['franchisee_first_name'], $currentDoc['franchisee_middle_name'],
                        $currentDoc['franchisee_last_name'], $currentDoc['franchisee_ext_name'],
                        $currentDoc['address'], $currentDoc['barangay'], $currentDoc['make'], $currentDoc['year_model'],
                        $currentDoc['motor_no'], $currentDoc['plate_no'],
                        $currentDoc['driver_first_name'], $currentDoc['driver_middle_name'], $currentDoc['driver_last_name'], $currentDoc['driver_ext_name'],
                        $currentDoc['registration_date'], $currentDoc['expiration_date'], $currentDoc['registration_no'], $currentDoc['sticker_no'], $currentDoc['toda_no'],
                        $currentDoc['franchisee_fee'], $currentDoc['sticker_fee'], $currentDoc['filing_fee'], $currentDoc['penalty_fee'],
                        $currentDoc['transfer_fee'], $currentDoc['plate_fee'], $currentDoc['total_amount'],
                        $currentDoc['or_no'], $currentDoc['or_date'], $currentDoc['ctc_no'], $currentDoc['ctc_date'], $currentDoc['remarks']
                    );
                    $insertHistoryStmt->execute();
                    $insertHistoryStmt->close();
                }
            }
        }

        // Now update the document with new values
        $sql = "UPDATE documents SET
                    franchise_no = ?, franchisee_first_name = ?, franchisee_middle_name = ?, franchisee_last_name = ?, franchisee_ext_name = ?,
                    address = ?, barangay = ?, make = ?, year_model = ?, motor_no = ?, plate_no = ?,
                    driver_first_name = ?, driver_middle_name = ?, driver_last_name = ?, driver_ext_name = ?,
                    registration_date = ?, expiration_date = ?, registration_no = ?, sticker_no = ?, toda_no = ?,
                    franchisee_fee = ?, sticker_fee = ?, filing_fee = ?, penalty_fee = ?, transfer_fee = ?, plate_fee = ?, total_amount = ?,
                    or_no = ?, or_date = ?, ctc_no = ?, ctc_date = ?, remarks = ?
                WHERE id = ?";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "ssssssssssssssssssssdddddddsssssi",
                $franchise_no, $franchisee_first_name, $franchisee_middle_name, $franchisee_last_name, $franchisee_ext_name,
                $address, $barangay, $make, $year_model, $motor_no, $plate_no,
                $driver_first_name, $driver_middle_name, $driver_last_name, $driver_ext_name,
                $registration_date, $expiration_date, $registration_no, $sticker_no, $toda_no,
                $franchisee_fee, $sticker_fee, $filing_fee, $penalty_fee, $transfer_fee, $plate_fee, $total_amount,
                $or_no, $or_date, $ctc_no, $ctc_date, $remarks,
                $id
            );

            if ($stmt->execute()) {
                $stmt->close();

                $conn->query("DELETE FROM document_drivers WHERE document_id = {$id}");
                if (!empty($driver_rows)) {
                    $driverStmt = $conn->prepare(
                        "INSERT INTO document_drivers (document_id, driver_first_name, driver_middle_name, driver_last_name, driver_ext_name)
                         VALUES (?, ?, ?, ?, ?)"
                    );
                    if ($driverStmt) {
                        foreach ($driver_rows as $driverRow) {
                            $driverStmt->bind_param(
                                "issss",
                                $id,
                                $driverRow['driver_first_name'],
                                $driverRow['driver_middle_name'],
                                $driverRow['driver_last_name'],
                                $driverRow['driver_ext_name']
                            );
                            $driverStmt->execute();
                        }
                        $driverStmt->close();
                    }
                }

                if (!headers_sent()) {
                    header('Location: template.php?page=documents&success=updated');
                    exit;
                }
                echo "<script>window.location.replace('template.php?page=documents&success=updated');</script>";
                exit;
            } else {
                $errorMessage = "Error: " . $stmt->error;
                $stmt->close();
            }
        } else {
            $errorMessage = "Error preparing statement: " . $conn->error;
        }
    }
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

if (empty($driverRows) && $document) {
    $driverRows[] = [
        'driver_first_name' => $document['driver_first_name'] ?? '',
        'driver_middle_name' => $document['driver_middle_name'] ?? '',
        'driver_last_name' => $document['driver_last_name'] ?? '',
        'driver_ext_name' => $document['driver_ext_name'] ?? '',
    ];
}

if ($document) {
    $expirationDate = $document['expiration_date'] ?? null;
    $currentDate = date('Y-m-d');
    $isExpired = $expirationDate && $expirationDate < $currentDate;
    $statusText = $isExpired ? 'EXPIRED' : 'VALID';
    $statusClass = $isExpired ? 'danger' : 'success';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/add_page.css">
    <title>Edit Document</title>
</head>
<body>
    <div class="main-content">
        <div class="header-add">
            <h1 class="page-title">Documents</h1>
            <div class="breadcrumb">
                <span class="material-symbols-rounded">chevron_right</span>
                <span class="">Edit Document</span>
            </div>
        </div>
        <div class="card">
            <div class="form-title">
                <h2>Edit Document</h2>
            </div>
            <?php if ($errorMessage): ?>
                <p style="color: red;"><?php echo $errorMessage; ?></p>
            <?php endif; ?>
            <form action="template.php?page=edit_document&id=<?php echo $id; ?>" method="POST" id="editDocumentForm">

                <div class="form-row-4">
                    <div class="form-group">
                        <label for="franchise_no">Franchise No.</label>
                        <input type="text" id="franchisee_no" name="franchisee_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['franchise_no']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <div style="padding: 8px 0;">
                            <span class="badge <?php echo $statusClass ?? ''; ?>">
                                <?php echo $statusText ?? ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="form-row-4">
                    <div class="form-group">
                        <label for="franchisee_first_name">Franchisee First Name</label>
                        <input type="text" id="franchisee_first_name" name="franchisee_first_name" class="form-control" value="<?php echo $document ? htmlspecialchars($document['franchisee_first_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="franchisee_middle_name">Franchisee Middle Name</label>
                        <input type="text" id="franchisee_middle_name" name="franchisee_middle_name" class="form-control" value="<?php echo $document ? htmlspecialchars($document['franchisee_middle_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="franchisee_last_name">Franchisee Last Name</label>
                        <input type="text" id="franchisee_last_name" name="franchisee_last_name" class="form-control" value="<?php echo $document ? htmlspecialchars($document['franchisee_last_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="franchisee_ext_name">Franchisee Ext Name</label>
                        <select id="franchisee_ext_name" name="franchisee_ext_name" class="form-control">
                            <option value="">--Select Ext Name--</option>
                            <option value="JR" <?php echo ($document && $document['franchisee_ext_name'] === 'JR') ? 'selected' : ''; ?>>JR</option>
                            <option value="SR" <?php echo ($document && $document['franchisee_ext_name'] === 'SR') ? 'selected' : ''; ?>>SR</option>
                            <option value="II" <?php echo ($document && $document['franchisee_ext_name'] === 'II') ? 'selected' : ''; ?>>II</option>
                            <option value="III" <?php echo ($document && $document['franchisee_ext_name'] === 'III') ? 'selected' : ''; ?>>III</option>
                            <option value="IV" <?php echo ($document && $document['franchisee_ext_name'] === 'IV') ? 'selected' : ''; ?>>IV</option>
                            <option value="V" <?php echo ($document && $document['franchisee_ext_name'] === 'V') ? 'selected' : ''; ?>>V</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo $document ? htmlspecialchars($document['address']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <select id="barangay" name="barangay" class="form-control">
                            <option value="">--Select Barangay--</option>
                            <option value="CAYABU" <?php echo ($document && $document['barangay'] === 'CAYABU') ? 'selected' : ''; ?>>CAYABU</option>
                            <option value="CUYAMBAY" <?php echo ($document && $document['barangay'] === 'CUYAMBAY') ? 'selected' : ''; ?>>CUYAMBAY</option>
                            <option value="DARAITAN" <?php echo ($document && $document['barangay'] === 'DARAITAN') ? 'selected' : ''; ?>>DARAITAN</option>
                            <option value="KATIPUNAN-BAYANI" <?php echo ($document && $document['barangay'] === 'KATIPUNAN-BAYANI') ? 'selected' : ''; ?>>KATIPUNAN-BAYANI</option>
                            <option value="KAYBUTO" <?php echo ($document && $document['barangay'] === 'KAYBUTO') ? 'selected' : ''; ?>>KAYBUTO</option>
                            <option value="LAIBAN" <?php echo ($document && $document['barangay'] === 'LAIBAN') ? 'selected' : ''; ?>>LAIBAN</option>
                            <option value="MADILAYDILAY" <?php echo ($document && $document['barangay'] === 'MADILAYDILAY') ? 'selected' : ''; ?>>MADILAYDILAY</option>
                            <option value="MAG-AMPON" <?php echo ($document && $document['barangay'] === 'MAG-AMPON') ? 'selected' : ''; ?>>MAG-AMPON</option>
                            <option value="MAMUYAO" <?php echo ($document && $document['barangay'] === 'MAMUYAO') ? 'selected' : ''; ?>>MAMUYAO</option>
                            <option value="PINAGKAMALIGAN" <?php echo ($document && $document['barangay'] === 'PINAGKAMALIGAN') ? 'selected' : ''; ?>>PINAGKAMALIGAN</option>
                            <option value="PLAZA ALDEA" <?php echo ($document && $document['barangay'] === 'PLAZA ALDEA') ? 'selected' : ''; ?>>PLAZA ALDEA</option>
                            <option value="SAMPALOC" <?php echo ($document && $document['barangay'] === 'SAMPALOC') ? 'selected' : ''; ?>>SAMPALOC</option>
                            <option value="SAN ANDRES" <?php echo ($document && $document['barangay'] === 'SAN ANDRES') ? 'selected' : ''; ?>>SAN ANDRES</option>
                            <option value="SAN ISIDRO" <?php echo ($document && $document['barangay'] === 'SAN ISIDRO') ? 'selected' : ''; ?>>SAN ISIDRO</option>
                            <option value="SANTA INEZ" <?php echo ($document && $document['barangay'] === 'SANTA INEZ') ? 'selected' : ''; ?>>SANTA INEZ</option>
                            <option value="SANTO NIÑO" <?php echo ($document && $document['barangay'] === 'SANTO NIÑO') ? 'selected' : ''; ?>>SANTO NIÑO</option>
                            <option value="TABING ILOG" <?php echo ($document && $document['barangay'] === 'TABING ILOG') ? 'selected' : ''; ?>>TABING ILOG</option>
                            <option value="TANDANG KUTYO" <?php echo ($document && $document['barangay'] === 'TANDANG KUTYO') ? 'selected' : ''; ?>>TANDANG KUTYO</option>
                            <option value="TINUCAN" <?php echo ($document && $document['barangay'] === 'TINUCAN') ? 'selected' : ''; ?>>TINUCAN</option>
                            <option value="WAWA" <?php echo ($document && $document['barangay'] === 'WAWA') ? 'selected' : ''; ?>>WAWA</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-4">
                    <div class="form-group">
                        <label for="make">Make</label>
                        <select id="make" name="make" class="form-control">
                            <option value="">--Select Make--</option>
                            <option value="CHONGQING" <?php echo ($document && $document['make'] === 'CHONGQING') ? 'selected' : ''; ?>>CHONGQING</option>
                            <option value="GLOBALMOTORS" <?php echo ($document && $document['make'] === 'GLOBALMOTORS') ? 'selected' : ''; ?>>GLOBALMOTORS</option>
                            <option value="HAOJUE" <?php echo ($document && $document['make'] === 'HAOJUE') ? 'selected' : ''; ?>>HAOJUE</option>
                            <option value="HONDA" <?php echo ($document && $document['make'] === 'HONDA') ? 'selected' : ''; ?>>HONDA</option>
                            <option value="KAWASAKI" <?php echo ($document && $document['make'] === 'KAWASAKI') ? 'selected' : ''; ?>>KAWASAKI</option>
                            <option value="MICROBASE MOTORBIKE" <?php echo ($document && $document['make'] === 'MICROBASE MOTORBIKE') ? 'selected' : ''; ?>>MICROBASE MOTORBIKE</option>
                            <option value="MITSUKOSHI" <?php echo ($document && $document['make'] === 'MITSUKOSHI') ? 'selected' : ''; ?>>MITSUKOSHI</option>
                            <option value="MOTORSTAR" <?php echo ($document && $document['make'] === 'MOTORSTAR') ? 'selected' : ''; ?>>MOTORSTAR</option>
                            <option value="MOTOPOSH" <?php echo ($document && $document['make'] === 'MOTOPOSH') ? 'selected' : ''; ?>>MOTOPOSH</option>
                            <option value="PMR" <?php echo ($document && $document['make'] === 'PMR') ? 'selected' : ''; ?>>PMR</option>
                            <option value="RUSI" <?php echo ($document && $document['make'] === 'RUSI') ? 'selected' : ''; ?>>RUSI</option>
                            <option value="SINCHUANN GRAND ROYAL" <?php echo ($document && $document['make'] === 'SINCHUANN GRAND ROYAL') ? 'selected' : ''; ?>>SINCHUANN GRAND ROYAL</option>
                            <option value="SKYGO" <?php echo ($document && $document['make'] === 'SKYGO') ? 'selected' : ''; ?>>SKYGO</option>
                            <option value="SUZUKI" <?php echo ($document && $document['make'] === 'SUZUKI') ? 'selected' : ''; ?>>SUZUKI</option>
                            <option value="SUNRISER" <?php echo ($document && $document['make'] === 'SUNRISER') ? 'selected' : ''; ?>>SUNRISER</option>
                            <option value="YAMAHA" <?php echo ($document && $document['make'] === 'YAMAHA') ? 'selected' : ''; ?>>YAMAHA</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year_model">Year Model</label>
                        <input type="text" id="year_model" name="year_model" class="form-control" value="<?php echo $document ? htmlspecialchars($document['year_model']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="motor_no">Motor No.</label>
                        <input type="text" id="motor_no" name="motor_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['motor_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="plate_no">Plate No.</label>
                        <input type="text" id="plate_no" name="plate_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['plate_no']) : ''; ?>">
                    </div>
                </div>

                <div class="card responsibility-section">
                    <h3>Driver Details</h3>
                    <div id="officeRows">
                        <?php foreach ($driverRows as $index => $driverRow): ?>
                            <div class="form-row-4 office-row" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                                <div class="form-group">
                                    <label for="driver_first_name">Driver First Name</label>
                                    <input type="text" id="driver_first_name" name="driver_first_name[]" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_first_name'] ?? ''); ?>" <?php echo $index === 0 ? 'required' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label for="driver_middle_name">Driver Middle Name</label>
                                    <input type="text" id="driver_middle_name" name="driver_middle_name[]" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_middle_name'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="driver_last_name">Driver Last Name</label>
                                    <input type="text" id="driver_last_name" name="driver_last_name[]" class="form-control" value="<?php echo htmlspecialchars($driverRow['driver_last_name'] ?? ''); ?>" <?php echo $index === 0 ? 'required' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label for="driver_ext_name">Driver Ext Name</label>
                                    <select id="driver_ext_name" name="driver_ext_name[]" class="form-control">
                                        <option value="">--Select Ext Name--</option>
                                        <option value="JR" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'JR') ? 'selected' : ''; ?>>JR</option>
                                        <option value="SR" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'SR') ? 'selected' : ''; ?>>SR</option>
                                        <option value="II" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'II') ? 'selected' : ''; ?>>II</option>
                                        <option value="III" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'III') ? 'selected' : ''; ?>>III</option>
                                        <option value="IV" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'IV') ? 'selected' : ''; ?>>IV</option>
                                        <option value="V" <?php echo (($driverRow['driver_ext_name'] ?? '') === 'V') ? 'selected' : ''; ?>>V</option>
                                    </select>
                                    <button type="button" class="btn remove-office-btn" style="display:none;">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn" id="addOfficeBtn">+ Add Driver</button>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label for="registration_date">Registration Date</label>
                        <input type="date" id="registration_date" name="registration_date" class="form-control" value="<?php echo $document ? htmlspecialchars($document['registration_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="expiration_date">Expiration Date</label>
                        <input type="date" id="expiration_date" name="expiration_date" class="form-control" value="<?php echo $document ? htmlspecialchars($document['expiration_date']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="registration_no">Registration No.</label>
                        <input type="text" id="registration_no" name="registration_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['registration_no'] ?? '') : ''; ?>">
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label for="franchisee_fee">Franchisee Fee</label>
                        <input type="number" id="franchisee_fee" name="franchisee_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['franchisee_fee']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="sticker_fee">Sticker Fee</label>
                        <input type="number" id="sticker_fee" name="sticker_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['sticker_fee']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="filing_fee">Filing Fee</label>
                        <input type="number" id="filing_fee" name="filing_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['filing_fee']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="penalty_fee">Penalty Fee</label>
                        <input type="number" id="penalty_fee" name="penalty_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['penalty_fee']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="transfer_fee">Transfer Fee</label>
                        <input type="number" id="transfer_fee" name="transfer_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['transfer_fee']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="plate_fee">Plate Fee</label>
                        <input type="number" id="plate_fee" name="plate_fee" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['plate_fee']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row-3">
                    <div class="form-group">
                        <label for="total_amount">Total Amount</label>
                        <input type="number" id="total_amount" name="total_amount" class="form-control no-arrows" step="0.01" min="0" value="<?php echo $document ? htmlspecialchars($document['total_amount']) : ''; ?>" readonly>
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="or_no">OR No.</label>
                        <input type="text" id="or_no" name="or_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['or_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="or_date">OR Date</label>
                        <input type="date" id="or_date" name="or_date" class="form-control" value="<?php echo $document ? htmlspecialchars($document['or_date']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row-2">
                    <div class="form-group">
                        <label for="ctc_no">CTC No.</label>
                        <input type="text" id="ctc_no" name="ctc_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['ctc_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="ctc_date">CTC Date</label>
                        <input type="date" id="ctc_date" name="ctc_date" class="form-control" value="<?php echo $document ? htmlspecialchars($document['ctc_date']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-group">
                        <label for="sticker_no">Sticker No.</label>
                        <input type="text" id="sticker_no" name="sticker_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['sticker_no'] ?? '') : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="toda_no">TODA No.</label>
                        <input type="text" id="toda_no" name="toda_no" class="form-control" value="<?php echo $document ? htmlspecialchars($document['toda_no'] ?? '') : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="remarks">Remarks</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="3"><?php echo $document ? htmlspecialchars($document['remarks']) : ''; ?></textarea>
                </div>

                <div class="form-row-2">
                    <button type="submit" class="btn submit-btn">Update</button>
                    <a href="template.php?page=documents" class="btn cancel-btn" style="text-align: center;">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <script>
        const officeRows = document.getElementById('officeRows');
        const addOfficeBtn = document.getElementById('addOfficeBtn');

        const updateRemoveButtons = () => {
            const rows = officeRows.querySelectorAll('.office-row');
            rows.forEach((row) => {
                const btn = row.querySelector('.remove-office-btn');
                if (btn) {
                    btn.style.display = rows.length > 1 ? 'inline-block' : 'none';
                }
            });
        };

        const attachRemoveHandler = (row) => {
            const btn = row.querySelector('.remove-office-btn');
            if (!btn) return;
            btn.addEventListener('click', () => {
                row.remove();
                updateRemoveButtons();
            });
        };

        if (addOfficeBtn) {
            addOfficeBtn.addEventListener('click', () => {
                const template = officeRows.querySelector('.office-row');
                if (!template) return;
                const clone = template.cloneNode(true);
                clone.querySelectorAll('input, select').forEach((el) => {
                    el.value = '';
                });
                officeRows.appendChild(clone);
                attachRemoveHandler(clone);
                updateRemoveButtons();
            });
        }

        officeRows.querySelectorAll('.office-row').forEach((row) => attachRemoveHandler(row));
        updateRemoveButtons();

        const feeFields = ['franchisee_fee', 'sticker_fee', 'filing_fee', 'penalty_fee', 'transfer_fee', 'plate_fee'];
        const totalAmount = document.getElementById('total_amount');

        const updateTotal = () => {
            let total = 0;
            feeFields.forEach((id) => {
                const input = document.getElementById(id);
                const value = input ? parseFloat(input.value) : 0;
                if (!Number.isNaN(value)) {
                    total += value;
                }
            });
            if (totalAmount) totalAmount.value = total.toFixed(2);
        };

        feeFields.forEach((id) => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', updateTotal);
            }
        });
    </script>
</body>
</html>