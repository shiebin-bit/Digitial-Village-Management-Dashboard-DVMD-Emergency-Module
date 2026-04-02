<?php
require_once('includes/auth_user.php');
require_once('includes/dbconnect.php');
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- SECURITY: ACCESS CONTROL ---
// Ensure only Penghulu (Role 1) can access this page
if ($_SESSION['role'] != '1') {
    header("Location: loginpage.php");
    exit();
}

// --- SECURITY: AUTHORIZATION ---
// Using 'area_id' from session which corresponds to the 'area_id' column in tbl_users.
// For Penghulu, this ID typically represents their assigned Subdistrict/Mukim.
$user_area_id = filter_var($_SESSION['area_id'] ?? 0, FILTER_VALIDATE_INT);

if (!$user_area_id) {
    die("Security Error: No subdistrict assigned to this account. Please contact the administrator.");
}

// URL Parameter Handling - Sanitized
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? 'overview';
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$village_filter = filter_input(INPUT_GET, 'village', FILTER_VALIDATE_INT) ?: '';
$selectedVillage = $village_filter;

// --- INITIALIZATION ---
$householdStats = [];
$totalSaraQualified = 0; 
$incidentResults = null;
$sosResults = null;
$totalVillagersCount = 0;
$success_msg = null;
$error_msg = null;

// get subdistrict name
$stmt = $conn->prepare("SELECT name FROM tbl_subdistricts WHERE id = ?");
$stmt->bind_param("i", $user_area_id);
$stmt->execute();
$stmt->bind_result($subdistrict_name);
$stmt->fetch();
$stmt->close();

// --- AUTHORIZED ANNOUNCEMENT LOGIC ---
// Only broadcast to villages within the logged-in user's jurisdiction (area_id)
$stmt = $conn->prepare("SELECT id FROM tbl_villages WHERE subdistrict_id = ?");
$stmt->bind_param("i", $user_area_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $villages[] = $row['id'];
}
$stmt->close();

if (isset($_POST['submit_announcement'])) {
    
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if (empty($villages)) {
        $error = "No villages found in this subdistrict.";
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($villages), '?'));
    $types = str_repeat('i', count($villages));

    if ($title && $description && $type) {

        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM tbl_announcements
            WHERE village_id IN ($placeholders)
            AND created_at >= (NOW() - INTERVAL 5 MINUTE)
        ");
        $stmt->bind_param($types, ...$villages);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $error = "Please wait 5 minutes before posting another announcement.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO tbl_announcements (title, message, type, village_id)
                 VALUES (?, ?, ?, ?)"
            );

            foreach ($villages as $village_id) {
                $stmt->bind_param("sssi", $title, $description, $type, $village_id);
                $stmt->execute();
            }
            $success = "Announcement published successfully.";
            $stmt->close();
        }
    } else {
        $error = "All fields are required.";
    }
}

// --- AUTHORIZED DATA FETCHING ---
// Get total villagers ONLY for this jurisdiction
$stmtCount = $conn->prepare("SELECT COUNT(*) AS total FROM tbl_villagers vr JOIN tbl_villages v ON vr.village_id = v.id WHERE v.subdistrict_id = ?");
$stmtCount->bind_param("i", $user_area_id);
$stmtCount->execute();
$totalVillagersCount = $stmtCount->get_result()->fetch_assoc()['total'] ?? 0;
$stmtCount->close();

// Get villages in this jurisdiction
$v_query_stmt = $conn->prepare("SELECT v.id, v.village_name, COUNT(vr.id) as population FROM tbl_villages v LEFT JOIN tbl_villagers vr ON v.id = vr.village_id WHERE v.subdistrict_id = ? GROUP BY v.id, v.village_name ORDER BY v.village_name ASC");
$v_query_stmt->bind_param("i", $user_area_id);
$v_query_stmt->execute();
$villagesResult = $v_query_stmt->get_result();

if ($page === 'incident') {
    $sql = "SELECT i.*, v.village_name FROM tbl_incidents i JOIN tbl_villages v ON i.village_id = v.id WHERE i.status IN ('Pending', 'In Progress', 'Progressing') AND v.subdistrict_id = ?";
    $params = [$user_area_id]; $types = "i";
    if ($search !== '') { $sql .= " AND (i.description LIKE ? OR i.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    if ($village_filter !== '') { $sql .= " AND i.village_id = ?"; array_push($params, $village_filter); $types .= "i"; }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute(); $incidentResults = $stmt->get_result(); $stmt->close();
    }
}

if ($page === 'sos') {
    $sql = "SELECT so.*, v.village_name FROM tbl_sos so JOIN tbl_villages v ON so.village_id = v.id WHERE so.status IN ('Pending', 'In Progress', 'Progressing') AND v.subdistrict_id = ?"; 
    $params = [$user_area_id]; $types = "i";
    if ($search !== '') { $sql .= " AND (vr.name LIKE ? OR s.type LIKE ?)"; $s = "%$search%"; array_push($params, $s, $s); $types .= "ss"; }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute(); $sosResults = $stmt->get_result(); $stmt->close();
    }
}

if ($page === 'household') {
    $stats_stmt = $conn->prepare("
        SELECT 
            v.village_name, v.id as village_id,
            SUM(CASE WHEN h.family_group = 'B40' THEN 1 ELSE 0 END) as b40_count,
            SUM(CASE WHEN h.family_group = 'M40' THEN 1 ELSE 0 END) as m40_count,
            SUM(CASE WHEN h.family_group = 'T20' THEN 1 ELSE 0 END) as t20_count,
            SUM(CASE WHEN h.SARA = 'Approved' THEN 1 ELSE 0 END) as sara_approved_count
        FROM tbl_villages v
        LEFT JOIN tbl_villagers vr ON v.id = vr.village_id
        LEFT JOIN tbl_households h ON vr.id = h.villager_id
        WHERE v.subdistrict_id = ?
        GROUP BY v.id, v.village_name
    ");
    $stats_stmt->bind_param("i", $user_area_id);
    $stats_stmt->execute();
    $statsRes = $stats_stmt->get_result();
    while($row = $statsRes->fetch_assoc()) {
        $householdStats[] = $row;
        $totalSaraQualified += (int)$row['sara_approved_count'];
    }
    $stats_stmt->close();
}

// UI Helpers
function getUrgencyClass($level) { return "urgency " . strtolower(trim($level)); }
function getStatusClass($status) { return "status " . strtolower(str_replace(' ', '-', trim($status))); }
function isActive($target) { global $page; return $page === $target ? 'active' : ''; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/icon.png">
    <title>Penghulu Dashboard | Secure DVDM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="css/penghuludashboard.css" rel="stylesheet" type="text/css" />
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <style>
        #map { height: 500px; width: 100%; border-radius: 12px; border: 1px solid #ddd; }
        .announcement-form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #374151; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 14px; }
        .btn-broadcast { width: 100%; padding: 14px; border: none; background: #2563eb; color: white; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.2s; }
        .btn-broadcast:hover { background: #1d4ed8; }
        .alert-banner {
    background: #fef2f2;
    border: 1px solid #fee2e2;
    border-left: 5px solid #dc2626;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
    animation: pulseAlert 2s infinite;
}
    .alert-header {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #dc2626;
        font-weight: 800;
        font-size: 16px;
        margin-bottom: 12px;
        text-transform: uppercase;
    }
    
    .alert-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .alert-item {
        background: white;
        padding: 10px 15px;
        border-radius: 8px;
        color: #7f1d1d;
        font-size: 14px;
        border: 1px solid #fecaca;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    @keyframes pulseAlert {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.2);
        }
    
        70% {
            box-shadow: 0 0 0 10px rgba(220, 38, 38, 0);
        }
    
        100% {
            box-shadow: 0 0 0 0 rgba(220, 38, 38, 0);
        }
    }
        }
    </style>
</head>

<body>
    <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>

    <div class="sidebar">
        <div class="logo"><img src="images/icon.png" style="scale: 0.75;" alt="Logo" class="logo-img"><p>DVDM</p></div>
        <div class="user-info-box">
            <div class="avatar">
                <a class="avatar-upload" title="Upload Avatar">
                    <i class="fas fa-user"></i>
                </a>
            </div>
            <div class="user-info">
                <div style="font-weight: bold;font-size: 15px;">
                    Penghulu (<?= htmlspecialchars($subdistrict_name) ?>)</div>
                <div style="font-size: 14px;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                <div style="font-size: 13px;opacity: 0.8;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
            </div>
        </div>
        <a class="sidebar-link" href="?page=overview"><i class="fas fa-chart-line"></i> Overview</a>
        <a href="?page=announcement" class="<?= isActive('announcement') ?>"><i class="fa-solid fa-bell"></i> Announcement</a>
        <a href="?page=incident" class="<?= isActive('incident') ?>"><i class="fa-solid fa-triangle-exclamation"></i> Incident</a>
        <a href="?page=sos" class="<?= isActive('sos') ?>"><i class="fa-solid fa-bell"></i> SOS Report</a>
        <a href="?page=household" class="<?= isActive('household') ?>"><i class="fa-solid fa-house"></i> Household Level</a>
        <a href="?page=map" class="<?= isActive('map') ?>"><i class="fa-solid fa-map"></i> Map</a>
        <a href="registerpage.php"><i class="fas fa-user-plus"></i> Register </a>
        <a href="logout.php" onclick="return confirm('Are you sure you want to logout?')"><i class="fas fa-right-from-bracket"></i> Logout </a>
    </div>

    <main class="right-content">
        <?php if ($page === 'overview'): ?>
            <div id="dashboard"
                data-area-type="<?= $_SESSION['role'] ?>"
                data-area-id="<?= $_SESSION['area_id'] ?>">
            </div>
            <div class="dashboard-header"><h1>Penghulu Overview</h1><p class="subtitle">Live population and weather data for your jurisdiction.</p></div>
            <section class="section">
                <?php
                $stmt = $conn->prepare("
                (
                    SELECT 
                        i.type,
                        v.village_name,
                        i.date_created,
                        'Incident' AS source
                    FROM tbl_incidents i
                    JOIN tbl_villages v ON i.village_id = v.id
                    WHERE 
                        i.urgency_level = 'High'
                        AND i.status NOT IN ('Resolved', 'Reject')
                        AND v.subdistrict_id = ?
                )
                UNION ALL
                (
                    SELECT 
                        so.type,
                        v.village_name,
                        so.created_at AS date_created,
                        'SOS' AS source
                    FROM tbl_sos so
                    JOIN tbl_villages v ON so.village_id = v.id
                    WHERE 
                        so.urgency_level = 'Critical'
                        AND so.status NOT IN ('Resolved', 'Reject')
                        AND v.subdistrict_id = ?
                )
                
                ORDER BY date_created DESC;
            ");
            $stmt->bind_param('ii', $user_area_id, $user_area_id);
            $stmt->execute(); 
            $alerts = $stmt->get_result();
            $stmt->close();
                ?>
            <?php if ($alerts->num_rows > 0): ?>
                    <div class="alert-banner">
                        <div class="alert-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>CRITICAL ATTENTION REQUIRED (<?= $alerts->num_rows ?>)</span>
                        </div>
                        <div class="alert-list">
                            <?php while ($a = $alerts->fetch_assoc()): ?>
                                <div class="alert-item">
                                    <span class="alert-text">
                                        <b style="color: #b91c1c;"><?= htmlspecialchars($a['type']) ?></b>
                                        <span>in</span>
                                        <u style="color: #0f172a;"><?= htmlspecialchars($a['village_name']) ?></u>
                                    </span>
                                    <small>(Reported: <?= date('d M, h:i A', strtotime($a['date_created'])) ?>)</small>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <div class="top-stats">
                <div class="stat-box">
                    <div class="stat-icon" id="weatherIcon"></div>
                    <div class="stat-content">
                        <p><?php echo htmlspecialchars($subdistrict_name); ?></p>
                        <h2 id="weatherTemp">--°C</h2>
                        <div id="weatherDesc"></div>
                    </div>
                </div>
            </div>
            </section>
            <div class="village-grid">
                <?php if ($villagesResult && $villagesResult->num_rows > 0): while ($v = $villagesResult->fetch_assoc()): ?>
                    <div class="village-card">
                        <h3><?= htmlspecialchars($v['village_name']) ?></h3>
                        <div class="village-stats"><span>Total Villagers</span><b><?= (int)$v['population'] ?></b></div>
                    </div>
                <?php endwhile; endif; ?>
            </div>
            <script src="js/weather.js"></script>

        <?php elseif ($page === 'announcement'): ?>
            <div class="page-header"><h1>Make Announcement</h1></p></div>
            <?php if (!empty($success)): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert error">
                    <i class="fas fa-times-circle"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <div class="table-card"><div class="announcement-form-container">
                <form method="POST">
                    <div class="form-group"><label>Title</label><input type="text" name="title" required placeholder="Enter title..."></div>
                    <div class="form-group"><label>Type</label><select name="type" required>
                        <option value="">-- Select Type --</option>
                        <option value="Emergency">🚨 Emergency</option>
                        <option value="Weather">🌧 Weather</option>
                        <option value="Info">ℹ️ Information</option>
                        <option value="Event">🎉 Event</option>
                    </select></div>
                    <div class="form-group"><label>Message Content</label><textarea name="description" rows="5" required placeholder="Enter message..."></textarea></div>
                    <button type="submit" name="submit_announcement" class="btn-broadcast"><i class="fa-solid fa-paper-plane me-2"></i> Publish Announcement </button>
                </form>
            </div></div>

        <?php elseif ($page === 'map'): ?>
            <h1>Report Map</h1>
            <div id="map" style="height:500px; width:100%; border-radius:10px;"></div>
            <script src="js/reports.js"></script>

        <?php elseif ($page === 'incident'): ?>
            <div class="page-header"><div><h1>Incidents</h1><p>Active reports </p></div>
                <form method="GET" style="display:inline-block;"><input type="hidden" name="page" value="incident">
                    <select name="village" class="village-select" onchange="this.form.submit()">
                        <option value="">All Villages </option>
                        <?php if($villagesResult): $villagesResult->data_seek(0); while ($v = $villagesResult->fetch_assoc()): ?>
                            <option value="<?= $v['id'] ?>" <?= ($selectedVillage == $v['id']) ? 'selected' : '' ?>><?= htmlspecialchars($v['village_name']) ?></option>
                        <?php endwhile; endif; ?>
                    </select>
                </form>
            </div>
            <form method="GET">
                <input type="hidden" name="page" value="incident">
                <input type="hidden" name="village" value="<?= htmlspecialchars($selectedVillage) ?>">
                <div class="controls">
                    <input type="text" name="search" class="search-box" placeholder="Search incidents..." value="<?= htmlspecialchars($search) ?>">
                    <div class="control-buttons"><button class="btn-outline" type="submit">Filter</button></div>
                </div>
            </form>
            <div class="table-card">
                <table>
                    <thead><tr><th>Incident</th><th>Type</th><th>Village</th><th>Urgency</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if ($incidentResults && $incidentResults->num_rows > 0): while ($row = $incidentResults->fetch_assoc()): 
                        $createdTime = strtotime($row['date_created']);
                                $isNew = (time() - $createdTime) <= 24 * 60 * 60;
                                ?>
                            <tr>
                                <td style="text-align: left;"><?= htmlspecialchars($row['description']) ?></td>
                                <td style="text-align: left;"><?= htmlspecialchars($row['type']) ?><?php if ($isNew) echo ' <span class="new-badge">NEW</span>'; ?></td>
                                <td style="text-align: left;"><?= htmlspecialchars($row['village_name']) ?></td>
                                <td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td>
                                <td><span class="badge <?= getStatusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td>
                                    <a class="btn btn-view" href="?page=incident_view&id=<?= $row['id'] ?>">View</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?><tr><td colspan="5" class="empty-state">No active incidents in your jurisdiction.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($page === 'incident_view' && isset($_GET['id'])): ?>
            <?php
            $incidentId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT i.*, v.village_name FROM tbl_incidents i JOIN tbl_villages v ON i.village_id = v.id WHERE i.id = ?");
            $stmt->bind_param("i", $incidentId);
            $stmt->execute();
            $incident = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$incident) {
                echo "<div class='section'><p>Incident not found.</p> <a href='?page=incident' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=incident" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($incident['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($incident['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($incident['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($incident['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($incident['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($incident['village_name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="detail-group">
                            <label>Description</label>
                            <p class="desc-text"><?= nl2br(htmlspecialchars($incident['description'])) ?></p>
                        </div>
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($incident['latitude']) ?>, <?= htmlspecialchars($incident['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($incident['date_created'])) ?></p>
                            </div>
                        </div>

                        <?php if ($incident['status'] !== 'Progressing'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/incident/update_incident.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $incident['id'] ?>">
                                    <button type="submit" name="incident_action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>

                                    <button type="submit" name="incident_action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="image-col">
                        <?php if (!empty($incident['image'])): ?>
                            <div class="image-wrapper">
                                <img src="dvmd/assets/images/<?= htmlspecialchars($incident['image']) ?>" alt="Evidence Photo" style="width:475px; height:400px;">
                                <div class="img-caption">Evidence Photo</div>
                            </div>
                        <?php else: ?>
                            <div class="no-photo"><i class="fas fa-camera-slash"></i>
                                <p>No photo provided</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

        <?php elseif ($page === 'sos'): ?>
            <div class="page-header"><h1>SOS Alerts</h1><p>Emergency reports </p></div>
            <div class="table-card">
                <table>
                    <thead><tr><th>Village</th><th>Type</th><th>Urgency</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php if ($sosResults && $sosResults->num_rows > 0): while ($row = $sosResults->fetch_assoc()): ?>
                            <tr>
                                <td style="text-align: left;"><?= htmlspecialchars($row['village_name']) ?></td>
                                <td style="text-align: left;"><?= htmlspecialchars($row['type']) ?></td>
                                <td><span class="badge <?= getUrgencyClass($row['urgency_level']) ?>"><?= htmlspecialchars($row['urgency_level']) ?></span></td>
                                <td><span class="badge <?= getStatusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td>
                                    <a class="btn btn-view" href="?page=sos_view&id=<?= $row['id'] ?>">View</a>
                                </td>
                            </tr>
                        <?php endwhile; else: ?><tr><td colspan="4" class="empty-state">No active SOS alerts.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            
         <?php elseif ($page === 'sos_view' && isset($_GET['id'])): ?>
            <?php
            $sosId = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT so.*, v.village_name FROM tbl_sos so JOIN tbl_villages v ON so.village_id = v.id WHERE so.id = ?");
            $stmt->bind_param("i", $sosId);
            $stmt->execute();
            $sos = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$sos) {
                echo "<div class='section'><p>SOS not found.</p> <a href='?page=sos' class='btn'>Back</a></div>";
                return;
            }
            ?>

            <section class="section">
                <div class="header-row">
                    <a href="?page=sos" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                    <div class="header-meta">
                        <span class="badge urgency <?= strtolower($sos['urgency_level']) ?>">
                            <i class="fas fa-bell"></i> <?= htmlspecialchars($sos['urgency_level']) ?>
                        </span>
                        <span class="badge state <?= strtolower($sos['status']) ?>">
                            <i class="fas fa-info-circle"></i> <?= htmlspecialchars($sos['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="card-container">
                    <div class="info">
                        <h1 class="title"><?= htmlspecialchars($sos['type']) ?></h1>
                        <p class="reporter">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($sos['village_name']) ?>
                        </p>
                        <hr class="divider">
                        <div class="info-grid">
                            <div class="detail-group">
                                <label>Specific Location</label>
                                <p><?= htmlspecialchars($sos['latitude']) ?>, <?= htmlspecialchars($sos['longitude']) ?></p>
                            </div>
                            <div class="detail-group">
                                <label>Date Reported</label>
                                <p><?= date("d M Y, h:i A", strtotime($sos['created_at'])) ?></p>
                            </div>
                        </div>

                        <?php if ($sos['status'] !== 'Progressing'): ?>
                            <div style="margin-top: 30px;">
                                <form method="POST" action="management/sos/update_sos.php">
                                    <input type="hidden" name="role" value="<?= $_SESSION['role'] ?>">
                                    <input type="hidden" name="id" value="<?= $sos['id'] ?>">
                                    <button type="submit" name="sos_action" value="approve" class="btn btn-approve">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>

                                    <button type="submit" name="sos_action" value="reject" class="btn btn-reject">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                            </div>
                </div>
            </section>

        <?php elseif ($page === 'household'): ?>
            <div class="page-header"><h1>Household Analysis</h1><p>SARA statistics</p></div>
            <div style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; border-radius: 1rem; padding: 25px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div><h2 style="margin: 0; font-size: 1.1rem; opacity: 0.9;">SARA ASSISTANCE (MUKIM TOTAL)</h2><h3 style="margin: 5px 0 0 0; font-size: 2rem; font-weight: 800;"><?= (int)$totalSaraQualified ?> Households Approved</h3></div>
                <i class="fa-solid fa-hand-holding-heart" style="font-size: 3rem; opacity: 0.3;"></i>
            </div>
            <div class="village-grid">
                <?php foreach ($householdStats as $village): ?>
                    <div class="village-card" style="background:white; padding:20px;">
                        <h5 style="font-weight:bold;"><?= htmlspecialchars($village['village_name']) ?></h5>
                        <div style="height: 180px;"><canvas id="chart_<?= (int)$village['village_id'] ?>"></canvas></div>
                        <div style="margin-top: 15px; padding: 12px; background: #f0fdf4; border-radius: 10px; display: flex; align-items: center; justify-content: space-between;">
                            <span style="font-size: 13px; font-weight: 600; color: #166534;">SARA Approved</span>
                            <div style="font-weight: 800; color: #14532d;"><?= (int)$village['sara_approved_count'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const stats = <?= json_encode($householdStats) ?>;
                    stats.forEach(v => {
                        new Chart(document.getElementById(`chart_${v.village_id}`), {
                            type: 'bar',
                            data: { labels: ['B40', 'M40', 'T20'], datasets: [{ data: [v.b40_count, v.m40_count, v.t20_count], backgroundColor: ['rgba(239, 68, 68, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(16, 185, 129, 0.7)'], borderRadius: 5 }] },
                            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    });
                });
            </script>
        <?php endif; ?>
    </main>

    <?php include_once('includes/footer.php'); ?>
    <script src="js/sidebar.js"></script>
</body>
</html>