<?php
require_once 'config.php';

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Franchisee Information
    $franchise_no = trim($_POST['franchisee_no'] ?? '');
    $franchisee_first_name = trim($_POST['franchisee_first_name'] ?? '');
    $franchisee_middle_name = trim($_POST['franchisee_middle_name'] ?? '');
    $franchisee_last_name = trim($_POST['franchisee_last_name'] ?? '');
    $franchisee_ext_name = trim($_POST['franchisee_ext_name'] ?? '');
    
    // Address Information
    $address = trim($_POST['address'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    
    // Vehicle Information
    $make = trim($_POST['make'] ?? '');
    $year_model = trim($_POST['year_model'] ?? '');
    $motor_no = trim($_POST['motor_no'] ?? '');
    $plate_no = trim($_POST['plate_no'] ?? '');
    
    // Driver Information (multiple)
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

    // Registration Information
    $registration_date = trim($_POST['registration_date'] ?? '');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $registration_no = trim($_POST['registration_no'] ?? '');
    $sticker_no = trim($_POST['sticker_no'] ?? '');
    $toda_no = trim($_POST['toda_no'] ?? '');
    
    // Fees Information
    $franchisee_fee = !empty($_POST['franchisee_fee']) ? (float)$_POST['franchisee_fee'] : 0.00;
    $sticker_fee = !empty($_POST['sticker_fee']) ? (float)$_POST['sticker_fee'] : 0.00;
    $filing_fee = !empty($_POST['filing_fee']) ? (float)$_POST['filing_fee'] : 0.00;
    $penalty_fee = !empty($_POST['penalty_fee']) ? (float)$_POST['penalty_fee'] : 0.00;
    $transfer_fee = !empty($_POST['transfer_fee']) ? (float)$_POST['transfer_fee'] : 0.00;
    $plate_fee = !empty($_POST['plate_fee']) ? (float)$_POST['plate_fee'] : 0.00;
    $total_amount = !empty($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0.00;
    
    // OR and CTC Information
    $or_no = trim($_POST['or_no'] ?? '');
    $or_date = trim($_POST['or_date'] ?? '');
    $ctc_no = trim($_POST['ctc_no'] ?? '');
    $ctc_date = trim($_POST['ctc_date'] ?? '');
    
    // Remarks
    $remarks = trim($_POST['remarks'] ?? '');
    
    // Validate required fields
    if (empty($franchise_no) || empty($franchisee_first_name) || empty($franchisee_last_name)) {
        $errorMessage = 'Franchise No., First Name, and Last Name are required.';
    } else {
        // Insert into documents table
        $sql = "INSERT INTO documents (
                    franchise_no, franchisee_first_name, franchisee_middle_name, franchisee_last_name, franchisee_ext_name,
                    address, barangay, make, year_model, motor_no, plate_no,
                    driver_first_name, driver_middle_name, driver_last_name, driver_ext_name,
                    registration_date, expiration_date, registration_no, sticker_no, toda_no,
                    franchisee_fee, sticker_fee, filing_fee, penalty_fee, transfer_fee, plate_fee, total_amount,
                    or_no, or_date, ctc_no, ctc_date, remarks, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param(
                "ssssssssssssssssssssdddddddsssss",
                $franchise_no, $franchisee_first_name, $franchisee_middle_name, $franchisee_last_name, $franchisee_ext_name,
                $address, $barangay, $make, $year_model, $motor_no, $plate_no,
                $driver_first_name, $driver_middle_name, $driver_last_name, $driver_ext_name,
                $registration_date, $expiration_date, $registration_no, $sticker_no, $toda_no,
                $franchisee_fee, $sticker_fee, $filing_fee, $penalty_fee, $transfer_fee, $plate_fee, $total_amount,
                $or_no, $or_date, $ctc_no, $ctc_date, $remarks
            );

            if ($stmt->execute()) {
                $documentId = $stmt->insert_id;
                $stmt->close();

                if (!empty($driver_rows) && $documentId) {
                    $driverStmt = $conn->prepare(
                        "INSERT INTO document_drivers (document_id, driver_first_name, driver_middle_name, driver_last_name, driver_ext_name)
                        VALUES (?, ?, ?, ?, ?)"
                    );
                    if ($driverStmt) {
                        foreach ($driver_rows as $driverRow) {
                            $driverStmt->bind_param(
                                "issss",
                                $documentId,
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
                    header('Location: template.php?page=documents&success=added');
                    exit;
                }
                echo "<script>window.location.replace('template.php?page=documents&success=added');</script>";
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

if (isset($_GET['success']) && $_GET['success'] === 'added') {
    $successMessage = 'Document added successfully!';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="static/add_page.css">
    <title>Add Document</title>
</head>
<body>
    <div class="main-content">
        <div class="header-add">
            <h1 class="page-title">Documents</h1>
            <div class="breadcrumb">
                <span class="material-symbols-rounded">chevron_right</span>
                <span class="">Add Document</span>
            </div>
        </div>
        <div class="card">
            <div class="form-title">
                <h2>Add New Document</h2>
            </div>
            <?php if ($errorMessage): ?>
                <p style="color: red;"><?php echo $errorMessage; ?></p>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <p style="color: green;"><?php echo $successMessage; ?></p>
                <?php endif; ?>
                <form action="template.php?page=add_document" method="POST" id="addDocumentForm">

                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="franchise_no">Franchise No.</label>
                            <input type="text" id="franchisee_no" name="franchisee_no" class="form-control" placeholder="Enter Franchise No." required>
                        </div>
                    </div>

                    <!------- Franchisee Section ------->
                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="franchisee_first_name">Franchisee First Name</label>
                            <input type="text" id="franchisee_first_name" name="franchisee_first_name" class="form-control" placeholder="Enter Franchisee First Name" required>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_middle_name">Franchisee Middle Name</label>
                            <input type="text" id="franchisee_middle_name" name="franchisee_middle_name" class="form-control" placeholder="Enter Franchisee Middle Name" required>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_last_name">Franchisee Last Name</label>
                            <input type="text" id="franchisee_last_name" name="franchisee_last_name" class="form-control" placeholder="Enter Franchisee Last Name" required>
                        </div>
                        <div class="form-group">
                            <label for="franchisee_ext_name">Franchisee Ext Name</label>
                            <select id="franchisee_ext_name" name="franchisee_ext_name" class="form-control">
                                <option value="">--Select Ext Name--</option>
                                <option value="JR">JR</option>
                                <option value="SR">SR</option>
                                <option value="II">II</option>
                                <option value="III">III</option>
                                <option value="IV">IV</option>
                                <option value="V">V</option>
                            </select>
                        </div>
                    </div>

                    <!------- Address Section ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" placeholder="Enter Address" required>
                        </div>
                        <div class="form-group">
                            <label for="barangay">Barangay</label>
                            <select id="barangay" name="barangay" class="form-control">
                                <option value="">--Select Barangay--</option>
                                <option value="CAYABU">CAYABU</option>
                                <option value="CAYUMBAY">CAYUMBAY</option>
                                <option value="DARAITAN">DARAITAN</option>
                                <option value="KATIPUNAN BAYANI">KATIPUNAN BAYANI</option>
                                <option value="KAYBUTO">KAYBUTO</option>
                                <option value="LAIBAN">LAIBAN</option>
                                <option value="MADILAYDILAY">MADILAYDILAY</option>
                                <option value="MAG AMPON">MAG AMPON</option>
                                <option value="MAMUYAO">MAMUYAO</option>
                                <option value="PINAGKAMALIGAN">PINAGKAMALIGAN</option>
                                <option value="PLAZA ALDEA">PLAZA ALDEA</option>
                                <option value="SAMPALOC">SAMPALOC</option>
                                <option value="SAN ANDRES">SAN ANDRES</option>
                                <option value="SAN ISIDRO">SAN ISIDRO</option>
                                <option value="SANTA INEZ">SANTA INEZ</option>
                                <option value="STO NIÑO">STO NIÑO</option>
                                <option value="TABING ILOG">TABING ILOG</option>
                                <option value="TANDANG KUTYO">TANDANG KUTYO</option>
                                <option value="TINUCAN">TINUCAN</option>
                                <option value="WAWA">WAWA</option>
                            </select>
                        </div>
                    </div>

                    <!------- Vehicle Section ------->
                    <div class="form-row-4">
                        <div class="form-group">
                            <label for="make">Make</label>
                            <select id="make" name="make" class="form-control">
                                <option value="">--Select Make--</option>
                                <option value="CHONGQING">CHONGQING</option>
                                <option value="GLOBALMOTORS">GLOBALMOTORS</option>
                                <option value="HAOJUE">HAOJUE</option>
                                <option value="HONDA">HONDA</option>
                                <option value="KAWASAKI">KAWASAKI</option>
                                <option value="MICROBASE MOTORBIKE">MICROBASE MOTORBIKE</option>
                                <option value="MITSUKOSHI">MITSUKOSHI</option>
                                <option value="MOTORSTAR">MOTORSTAR</option>
                                <option value="MOTOPOSH">MOTOPOSH</option>
                                <option value="PMR">PMR</option>
                                <option value="RUSI">RUSI</option>
                                <option value="SINCHUANN GRAND ROYAL">SINCHUANN GRAND ROYAL</option>
                                <option value="SKYGO">SKYGO</option>
                                <option value="SUZUKI">SUZUKI</option>
                                <option value="SUNRISER">SUNRISER</option>
                                <option value="YAMAHA">YAMAHA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="year_model">Year Model</label>
                            <input type="text" id="year_model" name="year_model" class="form-control" placeholder="Enter Year Model">
                        </div>
                        <div class="form-group">
                            <label for="motor_no">Motor No.</label>
                            <input type="text" id="motor_no" name="motor_no" class="form-control" placeholder="Enter Motor No.">
                        </div>
                        <div class="form-group">
                            <label for="plate_no">Plate No.</label>
                            <input type="text" id="plate_no" name="plate_no" class="form-control" placeholder="Enter Plate No.">
                        </div>
                    </div>

                    <!------- Driver ------->
                    <div class="card responsibility-section">
                        <h3>Driver Details</h3>
                        <div id="officeRows">
                            <div class="form-row-4 office-row" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                                <div class="form-group">
                                    <label for="driver_first_name">Driver First Name</label>
                                    <input type="text" id="driver_first_name" name="driver_first_name[]" class="form-control" placeholder="Enter Driver First Name">
                                </div>
                                <div class="form-group">
                                    <label for="driver_middle_name">Driver Middle Name</label>
                                    <input type="text" id="driver_middle_name" name="driver_middle_name[]" class="form-control" placeholder="Enter Driver Middle Name">
                                </div>
                                <div class="form-group">
                                    <label for="driver_last_name">Driver Last Name</label>
                                    <input type="text" id="driver_last_name" name="driver_last_name[]" class="form-control" placeholder="Enter Driver Last Name">
                                </div>
                                <div class="form-group">
                                    <label for="driver_ext_name">Driver Ext Name</label>
                                    <select id="driver_ext_name" name="driver_ext_name[]" class="form-control">
                                        <option value="">--Select Ext Name--</option>
                                        <option value="JR">JR</option>
                                        <option value="SR">SR</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                        <option value="V">V</option>
                                    </select>
                                    <button type="button" class="btn remove-office-btn" style="display:none;">Remove</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn" id="addOfficeBtn">+ Add Driver</button>
                    </div>

                    <!------- Registration Details ------->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="registration_date">Registration Date</label>
                            <input type="date" id="registration_date" name="registration_date" class="form-control" placeholder="Enter Registration Date">
                        </div>
                        <div class="form-group">
                            <label for="expiration_date">Expiration Date</label>
                            <input type="date" id="expiration_date" name="expiration_date" class="form-control" placeholder="Enter Expiration Date">
                        </div>
                        <div class="form-group">
                            <label for="registration_no">Registration No.</label>
                            <input type="text" id="registration_no" name="registration_no" class="form-control" placeholder="Enter Registration No.">
                        </div>
                    </div>

                    <!------- Fees Section ------->
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="franchisee_fee">Franchisee Fee</label>
                            <input type="number" id="franchisee_fee" name="franchisee_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="sticker_fee">Sticker Fee</label>
                            <input type="number" id="sticker_fee" name="sticker_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="filing_fee">Filing Fee</label>
                            <input type="number" id="filing_fee" name="filing_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="penalty_fee">Penalty Fee</label>
                            <input type="number" id="penalty_fee" name="penalty_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="transfer_fee">Transfer Fee</label>
                            <input type="number" id="transfer_fee" name="transfer_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                        <div class="form-group">
                            <label for="plate_fee">Plate Fee</label>
                            <input type="number" id="plate_fee" name="plate_fee" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label for="total_amount">Total Amount</label>
                            <input type="number" id="total_amount" name="total_amount" class="form-control no-arrows" placeholder="0.00" step="0.01" min="0" readonly>
                        </div>
                    </div>

                    <!------- OR Details ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="or_no">OR No.</label>
                            <input type="text" id="or_no" name="or_no" class="form-control" placeholder="Enter OR No.">
                        </div>
                        <div class="form-group">
                            <label for="or_date">OR Date</label>
                            <input type="date" id="or_date" name="or_date" class="form-control" >
                        </div>
                    </div>

                    <!------- CTC Details ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="ctc_no">CTC No.</label>
                            <input type="text" id="ctc_no" name="ctc_no" class="form-control" placeholder="Enter CTC No." >
                        </div>
                        <div class="form-group">
                            <label for="ctc_date">CTC Date</label>
                            <input type="date" id="ctc_date" name="ctc_date" class="form-control" >
                        </div>
                    </div>

                    <!------- Sticker TODA Details ------->
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="sticker_no">Sticker No.</label>
                            <input type="text" id="sticker_no" name="sticker_no" class="form-control" placeholder="Enter Sticker No." >
                        </div>
                        <div class="form-group">
                            <label for="toda_no">TODA No.</label>
                            <input type="text" id="toda_no" name="toda_no" class="form-control" placeholder="Enter TODA No." >
                        </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" class="form-control" placeholder="Enter remarks" rows="3"></textarea>
                    </div>

                    <!------- SAVE CANCEL ------->
                    <div class="form-row-2">
                        <button type="submit" class="btn submit-btn">Save</button>
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
            if (input) input.addEventListener('input', updateTotal);
        });
    </script>
</body>
</html>