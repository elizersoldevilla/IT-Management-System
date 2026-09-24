<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();
if (!has_privilege('manage_assets')) {
    redirect('dashboard.php');
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Invalid Asset ID");
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
$stmt->execute([$id]);
$asset = $stmt->fetch();

if (!$asset) {
    die("Asset not found");
}


$qrData = "Asset: " . $asset['serial_number'] .
    "\nCategory: " . $asset['category'] .
    "\nBrand: " . $asset['brand'] .
    "\nModel: " . $asset['model'] .
    "\nDepartment Name: " . ($asset['department'] ?: 'N/A') .
    "\nPerson Accountability: " . ($asset['received_by'] ?: 'N/A');
$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Asset QR Code - <?php echo htmlspecialchars($asset['serial_number']); ?></title>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f4f7f6;
            color: #000;
        }

        .qr-card {
            background: white;
            display: inline-block;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 25px;
        }

        .logo-container {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #000;
        }

        .hospital-logo {
            max-width: 600px;
            height: auto;
        }

        .qr-card .qr-image {
            margin-bottom: 25px;
            width: 350px;
            height: 350px;
        }

        .qr-card h3 {
            margin: 0 0 20px 0;
            color: #000;
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            text-transform: uppercase;
        }

        .meta {
            text-align: left;
            margin-top: 30px;
            font-size: 22px;
            line-height: 2.5;
            color: #000;
            border-top: 5px solid #000;
            padding-top: 35px;
        }

        .meta div {
            margin-bottom: 12px;
        }

        .meta strong {
            color: #000;
            display: inline-block;
            width: 380px;
            font-weight: 900;
            text-transform: uppercase;
            font-size: 22px;
            letter-spacing: 0.1em;
        }

        .meta span {
            font-weight: 900;
            font-size: 32px;
            color: #000;
            display: inline;
        }

        .actions {
            margin-top: 30px;
        }

        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 800;
            margin: 0 8px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 13px;
        }

        .btn-print {
            background-color: #000;
            color: white;
        }

        .btn-save {
            background-color: #10b981;
            color: white;
        }

        .btn:hover {
            opacity: 0.95;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                padding: 0;
                background: white;
            }

            .qr-card {
                box-shadow: none;
                padding: 30px;
            }
        }
    </style>
</head>

<body>
    <div id="capture" class="qr-card">
        <div class="logo-container">
            <img src="assets/images/TGH.png" alt="Hospital Logo" class="hospital-logo"
                crossorigin="anonymous">
        </div>
        <img src="<?php echo $qrUrl; ?>" alt="QR Code" class="qr-image" crossorigin="anonymous">
        <h3><?php echo htmlspecialchars($asset['serial_number']); ?></h3>
        <div class="meta">
            <div><strong>Category:</strong> <span><?php echo htmlspecialchars($asset['category']); ?></span></div>
            <div><strong>Brand:</strong> <span><?php echo htmlspecialchars($asset['brand']); ?></span></div>
            <div><strong>Model:</strong> <span><?php echo htmlspecialchars($asset['model']); ?></span></div>
            <div><strong>Department Name:</strong>
                <span><?php echo htmlspecialchars($asset['department'] ?: 'N/A'); ?></span>
            </div>
            <div><strong>Person Accountability:</strong>
                <span><?php echo htmlspecialchars($asset['received_by'] ?: 'N/A'); ?></span>
            </div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Label
        </button>
        <button class="btn btn-save" onclick="saveAsImage()">
            <i class="fas fa-image"></i> Save as Image
        </button>
    </div>

    <script>
        function saveAsImage() {
            const capture = document.querySelector("#capture");
            html2canvas(capture, {
                useCORS: true,
                scale: 3,
                backgroundColor: "#ffffff"
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = 'Asset-QR-<?php echo $asset['serial_number']; ?>.png';
                link.href = canvas.toDataURL("image/png");
                link.click();
            });
        }
    </script>
</body>

</html>