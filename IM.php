<?php

error_reporting(E_ERROR | E_PARSE);

// Siguraduhing naka-set sa tamang timezone para saktong oras ng Pilipinas ang makuha
date_default_timezone_set("Asia/Manila");

$conn = new mysqli("localhost", "root", "", "im");

if ($conn->connect_error) {
    die("
    <div style='color:white; background:#ef4444; padding:30px; text-align:center; font-family:sans-serif; border-radius:12px; max-width:500px; margin:80px auto; box-shadow:0 10px 25px rgba(0,0,0,0.5);'>
        <h2>🚨 MySQL is OFF!</h2>
        <p>Paki-bukas muna ang iyong XAMPP Control Panel at i-start ang Apache at MySQL.</p>
    </div>
    ");
}

$current_file = basename($_SERVER['PHP_SELF']);
$today = date('Y-m-d');
$db_time = date('Y-m-d H:i:s');

// DYNAMIC DATABASE PASSCODE SYSTEM FETCH
$system_passcode = 'admin123'; // Default fallback
$check_settings_table = $conn->query("SHOW TABLES LIKE 'settings'");
if ($check_settings_table && $check_settings_table->num_rows > 0) {
    $pass_res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'admin_passcode'");
    if ($pass_res && $pass_res->num_rows > 0) {
        $pass_row = $pass_res->fetch_assoc();
        $system_passcode = $pass_row['setting_value'] ?? 'admin123';
    }
}


// =====================================
// REGISTER MEMBER WITH MULTIPLE QUANTITY SUPPORT
// =====================================
if (isset($_POST['register'])) {

    $name = strtoupper(trim($conn->real_escape_string($_POST['name'])));
    $contact = trim($conn->real_escape_string($_POST['contact']));
    $mtype = $conn->real_escape_string($_POST['mtype']);
    $regdate = $_POST['regdate'];

    $addons_array = isset($_POST['addons']) ? $_POST['addons'] : [];
    $quantities = isset($_POST['qty']) ? $_POST['qty'] : [];

    if (strpos($mtype, 'Walk-in') === false) {
        
        // SECURITY GUARD 1: ANTI-DUPLICATE NUMBER
        $check_contact = $conn->query("SELECT id FROM gymdb WHERE contact='$contact' AND expdate >= '$today'");
        if ($check_contact && $check_contact->num_rows > 0) {
            echo "<script>alert('Error: Ang contact number na $contact ay ginagamit na ng isang active member!'); window.location.href='$current_file';</script>";
            exit();
        }

        // SECURITY GUARD 2: ANTI-DOUBLE MEMBERSHIP
        $check_name = $conn->query("SELECT id FROM gymdb WHERE name='$name' AND expdate >= '$today'");
        if ($check_name && $check_name->num_rows > 0) {
            echo "<script>alert('Active pa ang membership ni $name!'); window.location.href='$current_file';</script>";
            exit();
        }
    }

    $price = 0;
    if ($mtype == "Monthly Membership (30 Days)") {
        $price = 500;
        $expdate = date('Y-m-d', strtotime($regdate . ' +30 days'));
    }
    elseif ($mtype == "Half-Month (15 Days)") {
        $price = 300;
        $expdate = date('Y-m-d', strtotime($regdate . ' +15 days'));
    }
    else {
        $price = 50;
        $expdate = $regdate;
    }

    $saved_addons_list = [];
    $processed_addons_records = [];

    if (!empty($addons_array)) {
        foreach ($addons_array as $index => $addon_string) {
            $parts = explode(" - ₱", $addon_string);
            if (count($parts) == 2) {
                $p_name = trim($parts[0]);
                $p_price = (float)$parts[1];
                
                $qty = isset($quantities[$index]) ? (int)$quantities[$index] : 1;
                if ($qty < 1) $qty = 1; 

                // ADVANCED GUARD: Siguraduhing may sapat na stock bago i-proseso ang pagbawas
                $check_stock_query = $conn->query("SELECT stock FROM products WHERE product_name = '" . $conn->real_escape_string($p_name) . "'");
                if ($check_stock_query && $stock_row = $check_stock_query->fetch_assoc()) {
                    if ($stock_row['stock'] < $qty) {
                        echo "<script>alert('Error: Kulang ang stock para sa item: $p_name! Available stock: " . $stock_row['stock'] . "'); window.location.href='$current_file';</script>";
                        exit();
                    }
                }
                
                $saved_addons_list[] = ($qty > 1) ? "$p_name ($qty)" : $p_name;
                
                $processed_addons_records[] = [
                    'name' => $p_name,
                    'price' => $p_price,
                    'qty' => $qty
                ];
            }
        }
    }
    
    $addons_field_value = !empty($saved_addons_list) ? implode(", ", $saved_addons_list) : "None";

    $sql = "INSERT INTO gymdb (name, contact, mtype, ptype, addons, regdate, expdate) VALUES ('$name', '$contact', '$mtype', 'Cash', '$addons_field_value', '$regdate', '$expdate')";

    if ($conn->query($sql)) {
        $member_id = $conn->insert_id;

        $conn->query("INSERT INTO attendance (member_id, name, gym_date, att_addons, amount) VALUES ('$member_id', '$name', '$db_time', '$mtype', '$price')");

        if (!empty($processed_addons_records)) {
            foreach ($processed_addons_records as $addon_item) {
                $product_name = $addon_item['name'];
                $addon_price = $addon_item['price'];
                $item_qty = $addon_item['qty'];
                $total_item_amount = $addon_price * $item_qty;

                $conn->query("INSERT INTO attendance (member_id, name, gym_date, att_addons, amount) VALUES ('$member_id', '$name', '$db_time', '" . $conn->real_escape_string($product_name . " (x" . $item_qty . ")") . "', '$total_item_amount')");

                $conn->query("UPDATE products SET stock = stock - $item_qty WHERE product_name = '" . $conn->real_escape_string($product_name) . "' AND stock >= $item_qty");
            }
        }

        header("Location: $current_file");
        exit();
    } else {
        die("<div style='background:#ef4444; color:white; padding:25px; text-align:center; font-family:sans-serif; border-radius:10px; max-width:600px; margin:50px auto;'><h2>🚨 Database Error!</h2><p><strong>Mensahe:</strong> " . htmlspecialchars($conn->error) . "</p><br><a href='$current_file' style='color:#eab308; font-weight:bold; text-decoration:none;'>← Bumalik</a></div>");
    }
}


// =====================================
// GYM NOW ATTENDANCE LOG (WITH STOCK GUARD)
// =====================================
if (isset($_POST['gym_now_submit'])) {
    $m_id = (int)$_POST['member_id'];
    $m_name = strtoupper($conn->real_escape_string($_POST['member_name']));
    $att_addons = $conn->real_escape_string($_POST['att_addons']);
    $modal_qty = (int)$_POST['modal_qty'];
    if($modal_qty < 1) $modal_qty = 1;
    
    if ($att_addons == "Check-in Only") {
        $conn->query("INSERT INTO attendance (member_id, name, gym_date, att_addons, amount) VALUES ('$m_id', '$m_name', '$db_time', 'Check-in Only', '0')");
    } else {
        $parts = explode(" - ₱", $att_addons);
        if (count($parts) == 2) {
            $product_name = trim($parts[0]);
            $addon_price = (float)$parts[1];
            $total_amount = $addon_price * $modal_qty;

            // CRITICAL ERROR PREVENTER: I-validate muna kung may sapat pang stock bago ibawas
            $chk_stk = $conn->query("SELECT stock FROM products WHERE product_name = '" . $conn->real_escape_string($product_name) . "'");
            if ($chk_stk && $stk_row = $chk_stk->fetch_assoc()) {
                if ($stk_row['stock'] < $modal_qty) {
                    echo "<script>alert('🚨 FAILED: Kulang ang natitirang stock sa inventory! Kasalukuyang Stock: " . $stk_row['stock'] . "'); window.location.href='$current_file';</script>";
                    exit();
                }
            }

            $conn->query("INSERT INTO attendance (member_id, name, gym_date, att_addons, amount) VALUES ('$m_id', '$m_name', '$db_time', '" . $conn->real_escape_string($product_name . " (x" . $modal_qty . ")") . "', '$total_amount')");
            $conn->query("UPDATE products SET stock = stock - $modal_qty WHERE product_name = '" . $conn->real_escape_string($product_name) . "' AND stock >= $modal_qty");
        }
    }

    echo "<script>alert('Gym attendance recorded and stock updated successfully!'); window.location.href='$current_file';</script>";
    exit();
}


if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM gymdb WHERE id='$id'");
    header("Location: $current_file");
    exit();
}

$search = isset($_GET['search']) ? $conn->real_escape_string(trim($_GET['search'])) : "";
$result = $conn->query("SELECT * FROM gymdb WHERE name LIKE '%$search%' AND mtype NOT LIKE '%Walk-in%' ORDER BY id DESC");
$walkins = $conn->query("SELECT * FROM gymdb WHERE name LIKE '%$search%' AND mtype LIKE '%Walk-in%' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CBG Fitness Center Tracker</title>
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
*{
    margin:0; padding:0; box-sizing:border-box;
}
body{
    background: var(--bg);
    color: #f3f4f6;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 30px 15px;
}
.container{
    max-width: 1250px;
    margin: auto;
    background: var(--card);
    padding: 35px;
    border-radius: 20px;
    border: 1px solid var(--border);
    box-shadow: 0 20px 40px rgba(0,0,0,0.7);
}
.brand-header {
    display: flex;
    align-items: center;
    gap: 25px;
    margin-bottom: 35px;
    padding-bottom: 25px;
    border-bottom: 2px dashed var(--border);
}
.brand-logo {
    width: 95px; height: 95px;
    object-fit: cover;
    background: #fff; 
    padding: 3px;
    border-radius: 50%;
    border: 3px solid var(--primary);
    box-shadow: 0 0 15px rgba(250, 204, 21, 0.2);
}
.brand-text h1 {
    color: var(--primary);
    font-size: 32px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 900;
}
.brand-text p {
    color: var(--text-muted);
    font-size: 15px;
    margin-top: 3px;
}
.grid{
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}
label{
    display: block;
    margin-bottom: 8px;
    color: var(--primary);
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
input, select{
    width: 100%;
    padding: 14px;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: white;
    font-size: 15px;
    transition: all 0.3s;
}
input:focus, select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 8px rgba(250, 204, 21, 0.2);
}
.btn{
    width: 100%;
    padding: 16px;
    background: var(--primary);
    border: none;
    border-radius: 10px;
    font-weight: 800;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    cursor: pointer;
    color: #000;
    transition: all 0.3s ease;
}
.btn:hover{
    background: var(--primary-hover);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(250, 204, 21, 0.4);
}
.addon-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}
.addon-card {
    background: #1f2937;
    padding: 12px 16px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border: 1px solid var(--border);
    transition: all 0.2s;
}
.addon-card:hover {
    border-color: #4b5563;
    background: #2563eb10;
}
.addon-card label {
    color: white;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    flex-grow: 1;
}
.addon-card input[type="checkbox"] {
    width: 18px; height: 18px;
    accent-color: var(--primary);
    cursor: pointer;
}
.addon-card input[type="number"] {
    width: 65px;
    padding: 6px;
    text-align: center;
    font-weight: bold;
    border-radius: 6px;
    background: #0f172a;
    border: 1px solid #4b5563;
}
.table-wrapper {
    overflow-x: auto;
    background: #0f172a;
    border-radius: 12px;
    border: 1px solid var(--border);
    margin-top: 15px;
    margin-bottom: 45px;
}
table{
    width: 100%;
    border-collapse: collapse;
}
th{
    background: #1f2937;
    color: var(--primary);
    padding: 16px;
    text-align: left;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}
td{
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 15px;
    vertical-align: middle;
}
tr:hover td {
    background: #1f293750;
}
.status-active{
    background: #065f46; color: #34d399;
    padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 12px; display: inline-block;
}
.status-expired{
    background: #7f1d1d; color: #f87171;
    padding: 4px 10px; border-radius: 20px; font-weight: bold; font-size: 12px; display: inline-block;
}
.btn-gym{
    background: #2563eb; color: white; border: none;
    padding: 10px 18px; border-radius: 8px; cursor: pointer;
    font-weight: bold; font-size: 13px; text-transform: uppercase; transition: 0.2s;
}
.btn-gym:hover:not(.btn-disabled){
    background: #1d4ed8; box-shadow: 0 4px 12px rgba(37,99,235,0.4);
}
.btn-disabled{
    background: #374151; cursor: not-allowed; opacity: 0.5; color: #9ca3af;
}
.topbar{
    margin-top: 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}
.history-btn{
    background: #059669; color: white; text-decoration: none;
    padding: 14px 24px; border-radius: 10px; font-weight: bold;
    cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s;
}
.history-btn:hover{
    background: #047857; box-shadow: 0 5px 15px rgba(5,150,105,0.4);
}
.section-title {
    color: white; font-size: 20px; margin-top: 40px; margin-bottom: 15px;
    border-left: 5px solid var(--primary); padding-left: 12px;
    text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;
}
.modal {
    display: none; position: fixed; z-index: 999; left: 0; top: 0;
    width: 100%; height: 100%; background-color: rgba(0,0,0,0.75); backdrop-filter: blur(6px);
}
.modal-content {
    background-color: var(--card); margin: auto; padding: 30px;
    border: 1px solid var(--border); width: 90%; max-width: 460px;
    border-radius: 16px; position: relative; top: 15%; box-shadow: 0 10px 30px rgba(0,0,0,0.8);
}
.close-btn {
    color: var(--text-muted); float: right; font-size: 30px; font-weight: bold; cursor: pointer; line-height: 20px;
}
.close-btn:hover { color: white; }
</style>
</head>
<body>

<div class="container">

<div class="brand-header">
    <img src="logo.jpg" alt="Logo" class="brand-logo" onerror="this.style.display='none'">
    <div class="brand-text">
        <h1>CBG Fitness Center</h1>
        <p>Premium Gym Registration & Attendance Control Dashboard</p>
    </div>
</div>

<form method="POST">
    <div class="grid">
        <div>
            <label>PANGALAN NG MEMBER</label>
            <input type="text" name="name" required placeholder="JUAN DELA CRUZ">
        </div>
        <div>
            <label>CONTACT NUMBER (11-DIGITS)</label>
            <input type="text" name="contact" required placeholder="09123456789" 
                   maxlength="11" minlength="11" pattern="[0-9]{11}" inputmode="numeric"
                   oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        </div>
        <div>
            <label>MEMBERSHIP PACKAGE</label>
            <select name="mtype">
                <option>Monthly Membership (30 Days)</option>
                <option>Half-Month (15 Days)</option>
                <option>Walk-in (Daily)</option>
            </select>
        </div>
        <div>
            <label>REGISTRATION DATE</label>
            <input type="date" name="regdate" value="<?php echo $today; ?>">
        </div>
    </div>

    <label>ADD-ONS UPON REGISTRATION</label>
    <div class="addon-container">
        <?php
        $getRegProducts = $conn->query("SELECT product_name, price, stock FROM products WHERE stock > 0 ORDER BY product_name ASC");
        if ($getRegProducts && $getRegProducts->num_rows > 0) {
            $loop_index = 0;
            while ($p_row = $getRegProducts->fetch_assoc()) {
                $chk_value = $p_row['product_name'] . " - ₱" . $p_row['price'];
                ?>
                <div class="addon-card">
                    <label>
                        <input type="checkbox" name="addons[<?php echo $loop_index; ?>]" value="<?php echo htmlspecialchars($chk_value); ?>"> 
                        <div>
                            <strong><?php echo htmlspecialchars($p_row['product_name']); ?></strong><br>
                            <small style="color: var(--text-muted);">Price: ₱<?php echo number_format($p_row['price']); ?> | Stock: <?php echo $p_row['stock']; ?></small>
                        </div>
                    </label>
                    <input type="number" name="qty[<?php echo $loop_index; ?>]" value="1" min="1" max="<?php echo $p_row['stock']; ?>">
                </div>
                <?php
                $loop_index++;
            }
        } else {
            echo '<span style="color:var(--text-muted); font-size:14px; padding:10px;">No available stock items in inventory.</span>';
        }
        ?>
    </div>

    <button type="submit" name="register" class="btn">⚡ REGISTER MEMBER</button>
</form>

<div class="topbar">
    <form method="GET" style="flex-grow: 1; max-width: 500px; display: flex; gap: 10px;">
        <input type="text" name="search" placeholder="Search member by name..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn" style="width:110px; padding:0; font-size:14px;">Search</button>
    </form>
    
    <div>
        <button onclick="secureAdminAccess()" class="history-btn" style="border:none; font-family:inherit; font-size:14px;">🔐 ADMIN DASHBOARD</button>
    </div>
</div>

<div class="section-title">🏋️ OFFICIAL GYM MEMBERS</div>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Member Information</th>
                <th>Status / Expiration</th>
                <th>Gym Active Log</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()):
                    $exp_time = !empty($row['expdate']) ? strtotime($row['expdate']) : 0;
                    $today_time = strtotime($today);
                    $isExpired = ($exp_time <= $today_time);
                ?>
                <tr>
                    <td>
                        <strong style="font-size:16px; color:#fff usurp;"><?php echo htmlspecialchars($row['name']); ?></strong><br>
                        <small style="color:var(--primary); font-weight:bold;"><?php echo htmlspecialchars($row['contact']); ?></small> &bull; 
                        <small style="color:var(--text-muted);"><?php echo htmlspecialchars($row['mtype']); ?></small>
                    </td>
                    <td>
                        <span class="<?php echo $isExpired ? 'status-expired' : 'status-active'; ?>">
                            <?php echo $isExpired ? 'EXPIRED' : 'ACTIVE'; ?>
                        </span><br>
                        <small style="color:var(--text-muted); display:inline-block; margin-top:4px;">Ends: <?php echo date("M d, Y", strtotime($row['expdate'])); ?></small>
                    </td>
                    <td>
                        <button class="btn-gym <?php echo $isExpired ? 'btn-disabled' : ''; ?>" 
                                <?php echo $isExpired ? 'disabled' : ''; ?>
                                onclick="openGymModal('<?php echo $row['id']; ?>', '<?php echo addslashes($row['name']); ?>')">
                            GYM NOW
                        </button>
                    </td>
                    <td>
                        <a href="?delete=<?php echo $row['id']; ?>" style="color:#f87171; text-decoration:none; font-size:13px; font-weight:bold;" onclick="return confirm('Sigurado ka bang buburahin ang member na ito?')">Remove</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">Walang aktibong opisyal na miyembro ang natagpuan.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="section-title">🏃 DAILY WALK-IN LOGS</div>
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>Walk-in Client</th>
                <th>Fee Logged</th>
                <th>Purchased Items</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if($walkins && $walkins->num_rows > 0): ?>
                <?php while($w_row = $walkins->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong style="color:#fff;"><?php echo htmlspecialchars($w_row['name']); ?></strong><br>
                        <small style="color:var(--text-muted);"><?php echo htmlspecialchars($w_row['contact']); ?></small>
                    </td>
                    <td>
                        <span style="color:#34d399; font-weight:bold;">PAID (₱50.00)</span><br>
                        <small style="color:var(--text-muted);">Date: <?php echo date("M d, Y", strtotime($w_row['regdate'])); ?></small>
                    </td>
                    <td>
                        <span style="background:#1f2937; padding:5px 10px; border-radius:6px; color:var(--primary); font-size:13px; font-weight:bold; border:1px solid var(--border);">
                            <?php echo htmlspecialchars($w_row['addons']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="?delete=<?php echo $w_row['id']; ?>" style="color:#f87171; text-decoration:none; font-size:13px; font-weight:bold;" onclick="return confirm('Sigurado ka bang buburahin ang log na ito?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:30px;">Walang naitalang walk-in customers sa araw na ito.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</div>

<div id="gymModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeGymModal()">&times;</span>
        <h3 style="color:var(--primary); margin-bottom:20px; font-size:22px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">🏋️ LOG ATTENDANCE</h3>
        
        <form action="" method="POST">
            <input type="hidden" id="modal_member_id" name="member_id">
            
            <div style="margin-bottom:18px;">
                <label>MEMBER NAME</label>
                <input type="text" id="modal_member_name" name="member_name" readonly style="background:#0f172a; color:var(--text-muted); font-weight:bold; border-color:var(--border);">
            </div>

            <div style="margin-bottom:18px;">
                <label>ADD-ON PRODUCT (OPTIONAL)</label>
                <select name="att_addons" required>
                    <option value="Check-in Only">No Add-ons (Check-in Only)</option>
                    <?php
                    $getModalProducts = $conn->query("SELECT product_name, price FROM products WHERE stock > 0 ORDER BY product_name ASC");
                    if ($getModalProducts && $getModalProducts->num_rows > 0) {
                        while ($m_row = $getModalProducts->fetch_assoc()) {
                            $opt_value = $m_row['product_name'] . " - ₱" . $m_row['price'];
                            echo '<option value="' . htmlspecialchars($opt_value) . '">' . htmlspecialchars($opt_value) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div style="margin-bottom:25px;">
                <label>QUANTITY</label>
                <input type="number" name="modal_qty" value="1" min="1" style="font-weight:bold; text-align:center; background:#0f172a;">
            </div>

            <button type="submit" name="gym_now_submit" class="btn" style="background:#2563eb; color:white;">CONFIRM & SAVE ENTRY</button>
        </form>
    </div>
</div>

<script>
function secureAdminAccess() {
    let passcode = prompt("🛡️ PASSCODE SYSTEM\n\nI-enter ang Admin Passcode para buksan ang Sales History Dashboard:");
    let activePasscode = "<?php echo $system_passcode; ?>";
    
    if (passcode === activePasscode) {
        window.location.href = "history.php";
    } else if (passcode !== null) {
        alert("🚨 ACCESS DENIED!\nMaling passcode. Para lamang ito sa mga authorized Gym Admins.");
    }
}
function openGymModal(id, name) {
    document.getElementById('modal_member_id').value = id;
    document.getElementById('modal_member_name').value = name;
    document.getElementById('gymModal').style.display = 'block';
}
function closeGymModal() {
    document.getElementById('gymModal').style.display = 'none';
}
window.onclick = function(event) {
    let modal = document.getElementById('gymModal');
    if (event.target == modal) { modal.style.display = 'none'; }
}
</script>

</body>
</html>