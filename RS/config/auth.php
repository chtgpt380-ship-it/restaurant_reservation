<?php
// config/auth.php  —  Session + Auth helpers
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,          // expires when browser closes
        'cookie_secure'   => false,      // set TRUE when using HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

/**
 * Redirect to login page if the user is not authenticated.
 * Call this at the top of every protected page/API.
 *
 * @param array $allowed_roles  e.g. ['admin','manager']  — empty = any logged-in user
 */
function requireLogin(array $allowed_roles = []): void {
    if (empty($_SESSION['user_id'])) {
        // API calls → return JSON 401 instead of redirect
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
            str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthenticated. Please log in.']);
            exit;
        }
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['user_role'], $allowed_roles, true)) {
        http_response_code(403);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Forbidden. Insufficient role.']);
        } else {
            echo '<h1>403 – Access Denied</h1><p>You do not have permission to view this page.</p>';
        }
        exit;
    }
}

/** Return the current logged-in user's ID (or null). */
function currentUserId(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Return the current logged-in user's role (or null). */
function currentRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/** Destroy the session and redirect to login. */
function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: /login.php?msg=logged_out');
    exit;
}
?>