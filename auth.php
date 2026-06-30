<?php
// auth.php - Server-Side Data Processor & Session Controller
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Routing destination unspecified.']);
    exit;
}

/* ═══════════════════════════════════════
   🛡️ STATE: ACCOUNT REGISTRATION (SIGN UP)
   ═══════════════════════════════════════ */
if ($action === 'signup') {
    $name    = trim($input['name'] ?? '');
    $email   = trim($input['email'] ?? '');
    $mobile  = trim($input['mobile'] ?? '');
    $pwd     = $input['pwd'] ?? '';
    $confirm = $input['confirm'] ?? '';

    // 1. Strict Structural Field Content Verification
    if (empty($name) || empty($email) || empty($mobile) || empty($pwd) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All structural fields are required.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email interface formatting.']);
        exit;
    }
    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        echo json_encode(['success' => false, 'message' => 'Mobile number must be a valid 10-digit numeric sequence.']);
        exit;
    }
    if (strlen($pwd) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must meet the 8+ length baseline parameter.']);
        exit;
    }
    if ($pwd !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Confirmation password mismatch validation trap.']);
        exit;
    }

    try {
        // 2. Scan Schema table for existing Email conflicts
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'An account with this email address already exists.']);
            exit;
        }

        // 3. Hash password with secure cost metrics (BCRYPT)
        $passwordHash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);

        // 4. Safely write profile parameters to database
        $insertStmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password_hash) VALUES (:name, :email, :mobile, :password_hash)");
        $insertStmt->execute([
            'name'          => $name,
            'email'         => $email,
            'mobile'        => $mobile,
            'password_hash' => $passwordHash
        ]);

        echo json_encode(['success' => true, 'message' => 'Account synchronized successfully!']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database operation fault: ' . $e->getMessage()]);
        exit;
    }
}

/* ═══════════════════════════════════════
   🔓 STATE: SESSION AUTHENTICATION (SIGN IN)
   ═══════════════════════════════════════ */
if ($action === 'signin') {
    $email = trim($input['email'] ?? '');
    $pwd   = $input['pwd'] ?? '';

    if (empty($email) || empty($pwd)) {
        echo json_encode(['success' => false, 'message' => 'Email and password elements are required.']);
        exit;
    }

    try {
        // Parameter binding tracking query to neutralize injection signatures
        $stmt = $pdo->prepare("SELECT id, name, email, mobile, password_hash, profile_pic FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Pass payload strings into cryptographic verification engines
        if ($user && password_verify($pwd, $user['password_hash'])) {
            // Populate isolated global session environments
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_mobile']= $user['mobile'];
            $_SESSION['user_pic']   = $user['profile_pic'];

            echo json_encode([
                'success' => true, 
                'name'    => $user['name']
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Incorrect email or password configurations.']);
            exit;
        }

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Authentication node thread crash: ' . $e->getMessage()]);
        exit;
    }
}