<?php
// dashboard.php - Dynamic Session Panel, Multi-part Uploads, and CRUD Controller
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$error_msg = "";

try {
    // Modified query to fetch both the creation date and the automatic MySQL update timestamp
    $stmt = $pdo->prepare("SELECT name, email, mobile, profile_pic, created_at, last_updated FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $user_id]);
    $current_user = $stmt->fetch();
    
    if (!$current_user) {
        session_destroy();
        header("Location: index.html");
        exit;
    }
} catch (PDOException $e) {
    $error_msg = "Failed to load profile context: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name   = trim($_POST['name'] ?? '');
    $new_email  = trim($_POST['email'] ?? ''); 
    $new_mobile = trim($_POST['mobile'] ?? '');
    
    if (empty($new_name) || empty($new_email) || empty($new_mobile)) {
        $error_msg = "All tracking form parameters are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email formatting expression.";
    } elseif (!preg_match('/^[0-9]{10}$/', $new_mobile)) {
        $error_msg = "Contact number must be exactly 10 digits.";
    } else {
        try {
            $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
            $emailCheck->execute(['email' => $new_email, 'id' => $user_id]);
            if ($emailCheck->fetch()) {
                throw new Exception("This email address is already locked by another user registry entry.");
            }

            $uploaded_file_name = $current_user['profile_pic']; 

            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file_tmp_path = $_FILES['profile_pic']['tmp_name'];
                $file_name     = $_FILES['profile_pic']['name'];
                $file_size     = $_FILES['profile_pic']['size'];
                
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png'];
                
                if (!in_array($file_ext, $allowed_extensions)) {
                    throw new Exception("Invalid file extension. Only JPG, JPEG, and PNG are allowed.");
                }
                if ($file_size > 2 * 1024 * 1024) { 
                    throw new Exception("File size limit breached. Maximum allocation is 2MB.");
                }
                
                $uploaded_file_name = "avatar_" . $user_id . "_" . time() . "." . $file_ext;
                
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $dest_path = $upload_dir . $uploaded_file_name;
                if (!move_uploaded_file($file_tmp_path, $dest_path)) {
                    throw new Exception("Server failed to write file to disk.");
                }
                
                if ($current_user['profile_pic'] !== 'default_avatar.png' && file_exists($upload_dir . $current_user['profile_pic'])) {
                    @unlink($upload_dir . $current_user['profile_pic']);
                }
            }

            // MySQL will automatically bump the last_updated ON UPDATE timestamp row trigger natively
            $updateStmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, mobile = :mobile, profile_pic = :pic WHERE id = :id");
            $updateStmt->execute([
                'name'   => $new_name,
                'email'  => $new_email,
                'mobile' => $new_mobile,
                'pic'    => $uploaded_file_name,
                'id'     => $user_id
            ]);

            $_SESSION['user_name']   = $new_name;
            $_SESSION['user_email']  = $new_email;
            $_SESSION['user_mobile'] = $new_mobile;
            $_SESSION['user_pic']    = $uploaded_file_name;

            header("Location: dashboard.php?success=Profile updated successfully");
            exit;

        } catch (Exception $e) {
            $error_msg = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    try {
        if ($current_user['profile_pic'] !== 'default_avatar.png' && file_exists('uploads/' . $current_user['profile_pic'])) {
            @unlink('uploads/' . $current_user['profile_pic']);
        }

        $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $deleteStmt->execute(['id' => $user_id]);

        session_unset();
        session_destroy();
        header("Location: index.html");
        exit;
    } catch (PDOException $e) {
        $error_msg = "Failed to terminate identity record: " . $e->getMessage();
    }
}

if (isset($_GET['success'])) { $msg = htmlspecialchars($_GET['success']); }
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset(); session_destroy(); header("Location: index.html"); exit;
}

$avatar_path = 'default_avatar.png';
if (!empty($current_user['profile_pic']) && file_exists('uploads/' . $current_user['profile_pic'])) {
    $avatar_path = 'uploads/' . $current_user['profile_pic'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="icon" type="image/x-icon" href="dashboard-icon.png">
    <link rel="stylesheet" href="style.css">
    <style>
        body { align-items: flex-start; padding: 40px 20px; }
        
        .dash-grid {
            position: relative; z-index: 1;
            display: grid; grid-template-columns: 1fr 1fr;
            width: min(1040px, 95vw); gap: 24px; margin: 0 auto;
            transition: all 0.3s ease;
            align-items: stretch;
        }

        .dash-grid.centered-view {
            grid-template-columns: 1fr;
            width: min(480px, 95vw);
        }

        .panel-card {
            background: rgba(43, 34, 26, 0.65);
            backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
            border: 1px solid var(--border-color); border-radius: 16px;
            padding: 32px; box-shadow: 0 24px 60px rgba(0,0,0,0.5);
            display: flex; flex-direction: column;
            height: 100%;
        }

        .profile-sidebar { align-items: center; text-align: center; justify-content: space-between; }
        .sidebar-top-group { width: 100%; display: flex; flex-direction: column; align-items: center; }
        
        /* ── CHANGE #3: SLIGHT AMBIENT GLOW AROUND THE AVATAR RING ── */
        .avatar-container {
            position: relative; width: 120px; height: 120px;
            border-radius: 50%; margin-bottom: 18px; overflow: hidden;
            border: 2px solid var(--accent-color);
            box-shadow: 0 0 20px rgba(231, 192, 140, 0.25), inset 0 0 15px rgba(231, 192, 140, 0.1);
            cursor: pointer;
            transition: box-shadow 0.3s ease;
        }
        .avatar-img { width: 100%; height: 100%; object-fit: cover; background: #221a14; transition: transform 0.3s; }
        .avatar-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,0.6);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity 0.25s;
        }
        .avatar-overlay svg { width: 24px; height: 24px; fill: var(--accent-color); }
        .avatar-container:hover { box-shadow: 0 0 35px rgba(231, 192, 140, 0.45); }
        .avatar-container:hover .avatar-overlay { opacity: 1; }
        .avatar-container:hover .avatar-img { transform: scale(1.06); }

        .user-title { font-size: 1.5rem; font-weight: 700; color: var(--text-main); margin-bottom: 4px; }
        
        /* ── CHANGE #4: USER ACTIVE PULSING RADIAL COMPRESSION STATUS BADGE ── */
        .system-badge {
            font-family: monospace; font-size: 0.68rem; letter-spacing: 1px; text-transform: uppercase;
            background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2);
            padding: 5px 14px; border-radius: 20px; margin-bottom: 24px; display: inline-flex;
            align-items: center; gap: 8px;
        }
        .pulse-dot {
            width: 6px; height: 6px; background-color: #4ade80; border-radius: 50%;
            position: relative; display: inline-block;
            animation: pulseCompress 2s infinite ease-in-out;
        }
        @keyframes pulseCompress {
            0% { transform: scale(0.6); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.7); }
            50% { transform: scale(1.2); box-shadow: 0 0 0 8px rgba(74, 222, 128, 0); }
            100% { transform: scale(0.6); box-shadow: 0 0 0 0 rgba(74, 222, 128, 0); }
        }

        .registry-list { width: 100%; text-align: left; display: flex; flex-direction: column; gap: 14px; margin-bottom: 12px; }
        .registry-item { display: flex; flex-direction: column; gap: 3px; border-bottom: 1px solid rgba(92,77,62,0.3); padding-bottom: 8px; }
        
        .registry-label { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.8px; color: var(--text-muted); }
        .registry-value { font-size: 0.92rem; font-weight: 500; color: var(--text-main); }

        .btn-logout { text-decoration: none !important; }

        .main-workspace { 
            gap: 24px; 
            display: none; 
            opacity: 0;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            justify-content: space-between;
        }
        .main-workspace.show-panel { display: flex; opacity: 1; transform: translateY(0); }
        
        .workspace-block { width: 100%; }
        .block-title { font-size: 1rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--accent-color); margin-bottom: 20px; padding-bottom: 6px; border-bottom: 1px solid var(--border-color); }

        .custom-file-upload {
            display: flex; align-items: center; gap: 10px; width: 100%;
            background: var(--input-bg); border: 1px dashed var(--border-color);
            padding: 12px; border-radius: 8px; cursor: pointer; transition: border-color 0.2s;
        }
        .custom-file-upload:hover { border-color: var(--accent-color); }
        .custom-file-upload span { font-size: 0.8rem; color: var(--text-muted); }

        /* ── CHANGE #1: EXPANDED FILE METADATA LABELS ── */
        .file-specs { font-size: 0.68rem; color: var(--text-muted); margin-top: 5px; padding-left: 2px; line-height: 1.4; }

        /* ── CHANGE #2: RETROFITTED ACCOUNT DETAILS TIMESTAMP METRICS ── */
        .telemetry-divider {
            border: none; border-top: 1px solid var(--border-color);
            margin: 24px 0 16px 0; width: 100%; opacity: 0.6;
        }
        .telemetry-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
            margin-top: 12px; text-align: left;
        }
        .telemetry-box {
            background: rgba(0, 0, 0, 0.2); border: 1px solid rgba(231,192,140,0.08);
            border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 4px;
        }
        .telemetry-num { font-size: 0.85rem; font-weight: 700; color: var(--text-main); font-family: monospace; }
        
        .toast-box {
            position: fixed; top: 24px; right: 24px; z-index: 1000;
            display: flex; align-items: center; gap: 12px; padding: 16px 20px;
            border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            animation: slideIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            font-size: 0.88rem; font-weight: 600;
        }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast-box.success { background: #1c3d27; color: #4ade80; border: 1px solid #22c55e; }
        .toast-box.error { background: #471c1c; color: #f87171; border: 1px solid #ef4444; }
        
        .sidebar-actions { width: 100%; display: flex; flex-direction: column; gap: 12px; margin-top: 24px; }

        @media(max-width: 820px) {
            .dash-grid, .dash-grid.centered-view { grid-template-columns: 1fr; width: min(520px, 92vw); align-items: flex-start; }
            .panel-card { height: fit-content; }
            body { padding: 20px 0; }
        }
    </style>
</head>
<body>

<canvas id="particles"></canvas>

<?php if (!empty($msg)): ?>
    <div class="toast-box success" id="sysToast">
        <svg style="width:18px; height:18px; fill:currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span><?php echo $msg; ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($error_msg)): ?>
    <div class="toast-box error" id="sysToast">
        <svg style="width:18px; height:18px; fill:currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <span><?php echo $error_msg; ?></span>
    </div>
<?php endif; ?>

<div class="dash-grid centered-view" id="dashboardGridWrapper">
    
    <aside class="panel-card profile-sidebar">
        <div class="sidebar-top-group">
            <div class="avatar-container" onclick="openUpdateFormPanel(); document.getElementById('edit-pic').click();">
                <img class="avatar-img" src="<?php echo $avatar_path; ?>" alt="Avatar Context">
                <div class="avatar-overlay">
                    <svg viewBox="0 0 24 24"><path d="M4 4h3l2-2h6l2 2h3c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2zm8 3c-2.76 0-5 2.24-5 5s2.24 5 5 5 5-2.24 5-5-2.24-5-5-5zm0 8c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3z"/></svg>
                </div>
            </div>
            
            <h1 class="user-title"><?php echo htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            
            <div class="system-badge"><div class="pulse-dot"></div>USER ACTIVE</div>
            
            <div class="registry-list">
                <div class="registry-item">
                    <span class="registry-label">Full Name</span>
                    <span class="registry-value"><?php echo htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="registry-item">
                    <span class="registry-label">Email Address</span>
                    <span class="registry-value"><?php echo htmlspecialchars($current_user['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="registry-item">
                    <span class="registry-label">Contact Number</span>
                    <span class="registry-value"><?php echo htmlspecialchars($current_user['mobile'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <div class="sidebar-actions">
            <button class="submit-btn" id="toggleViewFormBtn" onclick="toggleFormWorkspace()" style="margin: 0;">Update Details</button>
            <a href="dashboard.php?action=logout" class="submit-btn btn-logout" style="margin: 0; padding: 11px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase;">Sign Out Session</a>
            
            <form action="dashboard.php" method="POST" style="width:100%; display:flex;" onsubmit="return confirm('CRITICAL WARNING: Are you completely certain you want to permanently delete this identity block? Your profile data records will be wiped out.');">
                <input type="hidden" name="action" value="delete_account">
                <button type="submit" class="submit-btn btn-delete" style="width: 100%; margin: 0; padding: 11px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase;">delete Account</button>
            </form>
        </div>
    </aside>

    <main class="panel-card main-workspace" id="formWorkspacePanel">
        <div class="workspace-block">
            <h2 class="block-title">Update registry properties</h2>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data" class="fields">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="field-group">
                    <label for="edit-name">Full Name</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                        <input type="text" id="edit-name" name="name" value="<?php echo htmlspecialchars($current_user['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>

                <div class="field-group">
                    <label for="edit-email">Email Address</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                        <input type="email" id="edit-email" name="email" value="<?php echo htmlspecialchars($current_user['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                
                <div class="field-group">
                    <label for="edit-mobile">Contact Number</label>
                    <div class="input-wrap">
                        <svg class="input-icon" viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        <input type="tel" id="edit-mobile" name="mobile" value="<?php echo htmlspecialchars($current_user['mobile'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                </div>
                
                <div class="field-group" style="display: none;">
                    <input type="file" id="edit-pic" name="profile_pic" accept=".jpg,.jpeg,.png" onchange="document.getElementById('file-name-indicator').textContent = this.files[0].name;">
                </div>

                <div class="field-group">
                    <label>Profile Image File Attachment</label>
                    <div class="custom-file-upload" onclick="document.getElementById('edit-pic').click();">
                        <svg style="width:16px; height:16px; fill:var(--text-muted);" viewBox="0 0 24 24"><path d="M9 16h6v-6h4l-7-7-7 7h4v6zm-4 2h14v2H5v-2z"/></svg>
                        <span id="file-name-indicator">click to choose new profile image</span>
                    </div>
                    <div class="file-specs">Allowed extensions: .jpg, .jpeg, .png </div>
                    <div class="file-specs">Maximum File Size: 2MB</div>
                </div>
                
                <button type="submit" class="submit-btn" style="width: 100%;">update system changes</button>
            </form>
        </div>

        <div class="workspace-block">
            <hr class="telemetry-divider">
            <h2 class="registry-label" style="color: var(--accent-color); font-weight: 700;">Account Details</h2>
            
            <div class="telemetry-grid">
                <div class="telemetry-box">
                    <span class="registry-label">Account Created</span>
                    <span class="telemetry-num"><?php echo date("Y-m-d", strtotime($current_user['created_at'])); ?></span>
                </div>
                <div class="telemetry-box">
                    <span class="registry-label">Last Updated</span>
                    <span class="telemetry-num"><?php echo date("Y-m-d H:i", strtotime($current_user['last_updated'] ?? $current_user['created_at'])); ?></span>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    const toast = document.getElementById('sysToast');
    if(toast){ setTimeout(() => { toast.style.transition = 'opacity 0.5s ease, transform 0.5s ease'; toast.style.opacity = '0'; toast.style.transform = 'translateY(-20px)'; setTimeout(() => toast.remove(), 500); }, 5000); }

    function toggleFormWorkspace() {
        const grid = document.getElementById('dashboardGridWrapper');
        const panel = document.getElementById('formWorkspacePanel');
        const toggleBtn = document.getElementById('toggleViewFormBtn');
        
        if (panel.classList.contains('show-panel')) {
            panel.classList.remove('show-panel');
            setTimeout(() => { grid.classList.add('centered-view'); }, 150);
            toggleBtn.textContent = "Update Details";
        } else {
            grid.classList.remove('centered-view');
            setTimeout(() => { panel.classList.add('show-panel'); }, 50);
            toggleBtn.textContent = "Cancel Updation";
        }
    }

    function openUpdateFormPanel() {
        const panel = document.getElementById('formWorkspacePanel');
        if (!panel.classList.contains('show-panel')) {
            toggleFormWorkspace();
        }
    }
</script>
</body>
</html>