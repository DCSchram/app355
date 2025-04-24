<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
  header("Location: login.php");
  exit();
}

require './database/database.php';
$pdo = DB::connect();

// Fetch people for lead person dropdown
$peopleStmt = $pdo->query("SELECT id, fname, lname FROM iss_persons ORDER BY fname, lname");
$people = $peopleStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle filter toggle via session
if (isset($_GET['toggle_filter'])) {
  $_SESSION['show_all_issues'] = !($_SESSION['show_all_issues'] ?? false);
  header("Location: index.php");
  exit();
}
$showAll = $_SESSION['show_all_issues'] ?? false;

// Handle mark closed
if (isset($_GET['close']) && $_SESSION['is_admin']) {
  $stmt = $pdo->prepare("UPDATE iss_issues SET priority = 'CLOSED', close_date = ? WHERE id = ?");
  $stmt->execute([date('Y-m-d'), $_GET['close']]);
  header("Location: index.php");
  exit();
}

// Get person name from Id
function getPersonName($people, $id) {
  foreach ($people as $person) {
    if ($person['id'] == $id) {
      return $person['fname'] . ' ' . $person['lname'];
    }
  }
  return '[Unknown]';
}

// Create or update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $group = $_POST['group'];
  $lead_person = $_POST['lead_person'];
  $project = $_POST['project'];
  $summary = $_POST['summary'];

  // If there is an id posted, it is an update
  if (!empty($_POST['id'])) {
    // Get the person id from the issue
    $stmt = $pdo->prepare("SELECT per_id FROM iss_issues WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $original = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user can update
    if ($_SESSION['is_admin'] || $original['per_id'] == $_SESSION['user_id']) {
      $stmt = $pdo->prepare("UPDATE iss_issues SET `org`=?, lead_person=?, project=?, short_description=?, open_date=? WHERE id=?");
      $stmt->execute([$group, $lead_person, $project, $summary, date('Y-m-d'), $_POST['id']]);
    }
  } else {
    // Insertion
    $stmt = $pdo->prepare("INSERT INTO iss_issues (`org`, lead_person, project, short_description, open_date, per_id, priority) VALUES (?, ?, ?, ?, ?, ?, 'OPEN')");
    $stmt->execute([$group, $lead_person, $project, $summary, date('Y-m-d'), $_SESSION['user_id']]);
  }

  // Reload page
  header("Location: index.php");
  exit();
}


// Delete issue
if (isset($_GET['delete'])) {
  // Get the issue Id to delete
  $issueId = $_GET['delete'];
  // Get the person who created the issue
  $stmt = $pdo->prepare("SELECT per_id FROM iss_issues WHERE id = ?");
  $stmt->execute([$issueId]);
  $issue = $stmt->fetch(PDO::FETCH_ASSOC);

  // Check if user can delete
  if ($_SESSION['is_admin'] || $issue['per_id'] == $_SESSION['user_id']) {
    $stmt = $pdo->prepare("DELETE FROM iss_issues WHERE id = ?");
    $stmt->execute([$issueId]);
  }

  // Reload page
  header("Location: index.php");
  exit();
}

// Load issue for editing
$editIssue = null;
if (isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM iss_issues WHERE id = ?");
  $stmt->execute([$_GET['edit']]);
  $editIssue = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch issues based on filter
$issuesStmt = $pdo->prepare(
  $showAll ? "SELECT * FROM iss_issues ORDER BY id DESC" : "SELECT * FROM iss_issues WHERE priority = 'OPEN' ORDER BY id DESC"
);
$issuesStmt->execute();
$issues = $issuesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Hot Issues Tracker</title>
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
    <h2>Hot Issues Tracker</h2>
    <div>
      <a href="people.php" class="btn btn-outline-secondary">Manage People</a>
      <a href="logout.php" class="btn btn-outline-danger">Logout</a>
    </div>
  </div>

  <!-- Form -->
  <div class="form-section">
    <form method="POST">
      <input type="hidden" name="id" value="<?= $editIssue['id'] ?? '' ?>">
      <div class="row g-3">
        <div class="col-md-2">
          <input type="text" name="group" class="form-control" placeholder="Group" required value="<?= $editIssue['org'] ?? '' ?>">
        </div>
        <div class="col-md-2">
          <select name="lead_person" class="form-select" required>
            <option value="">-- Select Lead Person --</option>
            <?php foreach ($people as $person): ?>
              <?php
                $selected = isset($editIssue['lead_person']) && $editIssue['lead_person'] == $person['id'] ? 'selected' : '';
                $name = htmlspecialchars($person['fname'] . ' ' . $person['lname']);
              ?>
              <option value="<?= $person['id'] ?>" <?= $selected ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input type="text" name="project" class="form-control" placeholder="Project" required value="<?= $editIssue['project'] ?? '' ?>">
        </div>
        <div class="col-md-3">
          <input type="text" name="summary" class="form-control" placeholder="Short Summary" required value="<?= $editIssue['short_description'] ?? '' ?>">
        </div>
        <div class="col-md-1">
          <button type="submit" class="btn btn-primary w-100">Save</button>
        </div>
        <div class="col-md-2">
          <a href="?toggle_filter=1" class="btn btn-outline-primary">
            <?= $showAll ? "Show Open Issues" : "Show All Issues" ?>
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Table -->
  <table class="table table-striped">
    <thead class="table-dark">
      <tr>
        <th>Group</th>
        <th>Lead Person</th>
        <th>Project</th>
        <th>Summary</th>
        <th>Date Opened</th>
        <th>Priority</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($issues as $issue): ?>
        <?php
          $canEdit = $_SESSION['is_admin'] || $issue['per_id'] == $_SESSION['user_id'];
          $canMarkClosed = $_SESSION['is_admin'] && $issue['priority'] == 'OPEN';
          $editClass = $canEdit ? 'btn-warning' : 'btn-secondary disabled-link';
          $deleteClass = $canEdit ? 'btn-danger' : 'btn-secondary disabled-link';
          $closeClass = $canMarkClosed ? 'btn-success' : 'btn-secondary disabled-link';
        ?>
        <tr>
          <td><?= htmlspecialchars($issue['org']) ?></td>
          <td><?= htmlspecialchars(getPersonName($people, $issue['per_id'])) ?></td>
          <td><?= htmlspecialchars($issue['project']) ?></td>
          <td><?= htmlspecialchars($issue['short_description']) ?></td>
          <td><?= htmlspecialchars($issue['open_date']) ?></td>
          <td><?= htmlspecialchars($issue['priority']) ?></td>
          <td>
            <a href="<?= $canEdit ? "?edit={$issue['id']}" : "#" ?>" class="btn btn-sm <?= $editClass ?>">Edit</a>
            <a href="<?= $canEdit ? "?delete={$issue['id']}" : "#" ?>" class="btn btn-sm <?= $deleteClass ?>" onclick="<?= $canEdit ? "return confirm('Delete this issue?')" : "return false" ?>">Delete</a>
            <a href="<?= $canMarkClosed ? "?close={$issue['id']}" : "#" ?>" class="btn btn-sm <?= $closeClass ?>" onclick="<?= $canMarkClosed ? "return confirm('Mark this issue as closed?')" : "return false"?>">Mark Closed</a>
            <a href="comments.php?issue_id=<?= $issue['id'] ?>" class="btn btn-sm btn-warning">Comment</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

</body>
</html>
