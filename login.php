<?php
session_start();
require './database/database.php';

// Login error message placeholder
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Pull email and password from user login
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

    try {
      // Use app credentials to connect (not user's)
      $pdo = DB::connect();
  
      // Lookup user by email
      $stmt = $pdo->prepare("SELECT id, fname, pwd_hash, pwd_salt, admin FROM iss_persons WHERE email = ?");
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
  
      // If there is a user
      if ($user) {
        // Check login with password and salt
        $salt = $user['pwd_salt'];
        $hashedInput = hash('sha256', $salt . $password);
  
        // If login successful
        if (hash_equals($user['pwd_hash'], $hashedInput)) {
          // Set session and redirect to index.php
          $_SESSION['logged_in'] = true;
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['user_name'] = $user['fname'];
          $_SESSION['is_admin'] = (bool)$user['admin'];
          header("Location: index.php");
          exit();
        }
      }
  
      // Authentification failed
      $error = "Invalid email or password.";
  
    } catch (PDOException $e) {
      $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login - Hot Issues Tracker</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      display: flex;
      height: 100vh;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      padding: 2rem;
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
    }
  </style>
</head>
<body>

<div class="login-card">
  <h4 class="mb-4 text-center">Login to Hot Issues Tracker</h4>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST">
    <div class="mb-3">
      <label for="email" class="form-label">Email address</label>
      <input type="email" name="email" class="form-control" id="email" required>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" name="password" class="form-control" id="password" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Login</button>
  </form>
</div>

</body>
</html>
