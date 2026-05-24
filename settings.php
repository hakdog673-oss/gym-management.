<?php
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set("Asia/Manila");

$conn = new mysqli("localhost", "root", "", "im");

if ($conn->connect_error) {
    die("<div style='color:white; background:#ef4444; padding:30px; text-align:center; font-family:sans-serif;'>🚨 MySQL is OFF! Paki-start ang Apache at MySQL sa XAMPP Control Panel.</div>");
}

$current_file = basename($_SERVER['PHP_SELF']);
$msg = "";
$msg_type = "";

// Siguraduhing may tamang istraktura ang settings table para hindi mag-crash ang select query
$conn->query("CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` VARCHAR(255) NOT NULL
)");

// Magpasok ng default passcode kung walang laman ang table
$conn->query("INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES ('admin_passcode', 'admin123')");

// PROCESS 1: CHANGE ADMIN PASSCODE
if (isset($_POST['change_pass'])) {
    $current_input = trim($_POST['current_passcode']);
    $new_input = trim($_POST['new_passcode']);

    // Kunin ang kasalukuyang passcode sa database nang ligtas
    $stmt_get = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_passcode'");
    $stmt_get->execute();
    $res = $stmt_get->get_result();
    $row = $res->fetch_assoc();
    $db_passcode = $row['setting_value'] ?? 'admin123';
    $stmt_get->close();

    if ($current_input === $db_passcode) {
        if (!empty($new_input)) {
            $stmt_up = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_passcode', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt_up->bind_param("ss", $new_input, $new_input);
            if ($stmt_up->execute()) {
                $msg = "🛡️ Passcode successfully updated!";
                $msg_type = "success";
            } else {
                $msg = "❌ Nabigong i-update ang passcode sa database.";
                $msg_type = "error";
            }
            $stmt_up->close();
        } else {
            $msg = "❌ Hindi pwedeng blanko ang bagong passcode.";
            $msg_type = "error";
        }
    } else {
        $msg = "❌ MALI ang iyong kasalukuyang password!";
        $msg_type = "error";
    }
}

// PROCESS 2: SYSTEM MAINTENANCE & RESET
if (isset($_POST['system_reset'])) {
    $input_confirm_pass = trim($_POST['confirm_passcode']);
    
    $stmt_get = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'admin_passcode'");
    $stmt_get->execute();
    $res = $stmt_get->get_result();
    $row = $res->fetch_assoc();
    $db_passcode = $row['setting_value'] ?? 'admin123';
    $stmt_get->close();

    if ($input_confirm_pass === $db_passcode) {
        $conn->query("TRUNCATE TABLE attendance");
        $msg = "💥 Sukses! Lahat ng kasaysayan ng attendance at benta ay permanenteng nabura.";
        $msg_type = "success";
    } else {
        $msg = "❌ RESET FAILED: Maling passcode. Hindi pinahintulutan ang pagbura.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings & Maintenance</title>
<style>
:root{
    --primary: #facc15;
    --primary-hover: #eab308;
    --bg: #090d16;
    --card: #111827;
    --input-bg: #1f2937;
    --border: #374151;
    --text-muted: #9ca3af;
}
*{ margin: 0; padding: 0; box-sizing: border-box; }
body{
    background: var(--bg); color: #f3f4f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px 15px;
}
.container{
    max-width: 1000px; margin: auto; background: var(--card);
    padding: 35px; border-radius: 20px; border: 1px solid var(--border);
    box-shadow: 0 20px 40px rgba(0,0,0,0.7);
}
.brand-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 35px; padding-bottom: 25px; border-bottom: 2px dashed var(--border);
    flex-wrap: wrap; gap: 20px;
}
.brand-text h1 { color: var(--primary); font-size: 28px; text-transform: uppercase; letter-spacing: 1px; font-weight: 900; }
.navigation-buttons { display: flex; gap: 10px; flex-wrap: wrap; }
.nav-btn {
    background: transparent; color: white; border: 1px solid var(--border);
    padding: 10px 18px; border-radius: 8px; font-weight: bold; text-decoration: none;
    font-size: 13px; transition: 0.2s; text-transform: uppercase;
}
.nav-btn:hover { background: #1f2937; color: var(--primary); }
.nav-primary { border-color: var(--primary); color: var(--primary); }
.nav-primary:hover { background: var(--primary); color: black; }

.grid-settings { display: grid; grid-template-columns: 1fr; gap: 25px; }
@media(min-width: 768px) { .grid-settings { grid-template-columns: 1fr 1fr; } }

.sub-card { background: #111827; border: 1px solid var(--border); border-radius: 16px; padding: 25px; }
.card-title { color: white; font-size: 16px; font-weight: 800; margin-bottom: 12px; text-transform: uppercase; }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; color: var(--text-muted); font-size: 11px; font-weight: bold; margin-bottom: 6px; text-transform: uppercase; }
input { padding: 12px; background: var(--input-bg); border: 1px solid var(--border); border-radius: 8px; color: white; font-size: 14px; width: 100%; }

.btn-action { background: var(--primary); color: black; border: none; padding: 14px; border-radius: 8px; font-weight: bold; width: 100%; cursor: pointer; text-transform: uppercase; font-size: 13px; transition: 0.2s; }
.btn-action:hover { background: var(--primary-hover); }
.btn-danger { background: #7f1d1d; color: #f87171; border: 1px solid #b91c1c; }
.btn-danger:hover { background: #991b1b; color: white; }

.banner { padding: 15px; background: rgba(52,211,153,0.15); border: 1px solid #059669; color: #34d399; border-radius: 8px; margin-bottom: 25px; font-weight: bold; text-align: center; font-size: 14px; }
.banner.error { background: rgba(239,68,68,0.15); border-color: #dc2626; color: #f87171; }
</style>
</head>
<body>

<div class="container">
    <div class="brand-header">
        <div class="brand-text">
            <h1>⚙️ SYSTEM CONFIGURATION</h1>
        </div>
        <div class="navigation-buttons">
            <a href="IM.php" class="nav-btn nav-primary">← Dashboard</a>
            <a href="history.php" class="nav-btn">📊 History Logs</a>
            <a href="inventory.php" class="nav-btn">📦 Inventory</a>
        </div>
    </div>

    <?php if(!empty($msg)): ?>
        <div class="banner <?php echo $msg_type == 'error' ? 'error' : ''; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="grid-settings">
        <div class="sub-card">
            <div class="card-title">🔒 Change Admin Passcode</div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Palitan ang passcode para sa seguridad ng iyong system database upang maiwasan ang hindi awtorisadong pag-access.</p>
            <form method="POST">
                <div class="form-group">
                    <label>Kasalukuyang Password</label>
                    <input type="password" name="current_passcode" required placeholder="••••••••">
                </div>
                <div class="form-group">
                    <label>Bagong Password</label>
                    <input type="text" name="new_passcode" required placeholder="I-type ang bagong password">
                </div>
                <button type="submit" name="change_pass" class="btn-action">Layout 💾 Save New Passcode</button>
            </form>
        </div>

        <div class="sub-card" style="border-color: #7f1d1d; background: rgba(127, 29, 29, 0.02);">
            <div class="card-title" style="color: #f87171;">⚠️ System Maintenance & Reset</div>
            <p style="font-size:13px; color:var(--text-muted); margin-bottom:20px;">Burahin ang lahat ng kasalukuyang records at logs ng miyembro. Ang aksyong ito ay permanente at hindi na pwedeng bawiin kailanman.</p>
            <form method="POST" onsubmit="return confirm('CRITICAL WARNING!!!\n\nSigurado ka bang buburahin ang LAHAT ng records ng attendance sa database? Hindi na ito mababawi!') && confirm('FINAL VERIFICATION:\n\nSigurado ka ba talaga 100%?');">
                <div class="form-group">
                    <label>I-type ang Passcode para kumpirmahin</label>
                    <input type="password" name="confirm_passcode" required placeholder="••••••••">
                </div>
                <button type="submit" name="system_reset" class="btn-action btn-danger">💥 Truncate Logs / Delete All</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>