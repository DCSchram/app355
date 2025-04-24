<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
  header("Location: login.php");
  exit();
}

require './database/database.php';
$pdo = DB::connect();

$isAdmin = $_SESSION['is_admin'];

// Handle Create or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first = $_POST['first_name'];
  $last = $_POST['last_name'];
  $email = $_POST['email'];

  if (!empty($_POST['id'])) {
    // Only admin can update
    if ($isAdmin) {
      $stmt = $pdo->prepare("UPDATE iss_persons SET fname = ?, lname = ?, email = ? WHERE id = ?");
      $stmt->execute([$first, $last, $email, $_POST['id']]);
    }
  } else {
    // Anyone can add a person
    $stmt = $pdo->prepare("INSERT INTO iss_persons (fname, lname, email) VALUES (?, ?, ?)");
    $stmt->execute([$first, $last, $email]);
  }

  header("Location: people.php");
  exit();
}

// Handle Delete
if (isset($_GET['delete']) && $isAdmin) {
  $stmt = $pdo->prepare("DELETE FROM iss_persons WHERE id = ?");
  $stmt->execute([$_GET['delete']]);
  header("Location: people.php");
  exit();
}

// Handle Edit Load
$editPerson = null;
if (isset($_GET['edit']) && $isAdmin) {
  $stmt = $pdo->prepare("SELECT * FROM iss_persons WHERE id = ?");
  $stmt->execute([$_GET['edit']]);
  $editPerson = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all people
$people = $pdo->query("SELECT * FROM iss_persons ORDER BY fname, lname")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>People Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 2rem; }
    .form-section { margin-bottom: 2rem; }
    .disabled-link {
      pointer-events: none;
      opacity: 0.6;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>People Management</h2>
    <div>
      <a href="index.php" class="btn btn-outline-secondary">Back to Issues</a>
      <a href="logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <!-- Form -->
  <div class="form-section">
    <form method="POST">
      <input type="hidden" name="id" value="<?= $editPerson['id'] ?? '' ?>">
      <div class="row g-3">
        <div class="col-md-2">
          <input type="text" name="first_name" class="form-control" placeholder="First Name" required value="<?= $editPerson['fname'] ?? '' ?>">
        </div>
        <div class="col-md-2">
          <input type="text" name="last_name" class="form-control" placeholder="Last Name" required value="<?= $editPerson['lname'] ?? '' ?>">
        </div>
        <div class="col-md-4">
          <input type="email" name="email" class="form-control" placeholder="Email" required value="<?= $editPerson['email'] ?? '' ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary w-100">Save</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Table -->
  <table class="table table-striped">
    <thead class="table-dark">
      <tr>
        <th>First Name</th>
        <th>Last Name</th>
        <th>Email</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($people as $person): ?>
        <?php
          $editUrl = $isAdmin ? "?edit={$person['id']}" : "#";
          $deleteUrl = $isAdmin ? "?delete={$person['id']}" : "#";
          $editClass = $isAdmin ? 'btn-warning' : 'btn-secondary disabled-link';
          $deleteClass = $isAdmin ? 'btn-danger' : 'btn-secondary disabled-link';
        ?>
        <tr>
          <td><?= htmlspecialchars($person['fname']) ?></td>
          <td><?= htmlspecialchars($person['lname']) ?></td>
          <td><?= htmlspecialchars($person['email']) ?></td>
          <td>
            <a href="<?= $editUrl ?>" class="btn btn-sm <?= $editClass ?>">Edit</a>
            <a href="<?= $deleteUrl ?>" class="btn btn-sm <?= $deleteClass ?>" onclick="<?= $isAdmin ? "return confirm('Delete this person?')" : "return false" ?>">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</body>
</html>