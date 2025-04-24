<!DOCTYPE html>
<html lang="en">

<?php
session_start();
if (!isset($_SESSION['logged_in'])) {
  header("Location: login.php");
  exit();
}

require './database/database.php';
$pdo = DB::connect();

$issueId = $_GET['issue_id'] ?? null;
if (!$issueId) {
  header("Location: index.php");
  exit();
}

// Fetch issue title or data
$stmt = $pdo->prepare("SELECT * FROM iss_issues WHERE id = ?");
$stmt->execute([$issueId]);
$issue = $stmt->fetch(PDO::FETCH_ASSOC);

// If there is no issue that matches the Id
if (!$issue) {
    // Show error message
  echo "ERROR: Issue not found.";
  ?>
  <!-- Link back to index -->
  <a href="index.php" class="btn btn-outline-secondary"> Return to the Issues page</a>
  <?php 
  exit();
}

// Add new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
  $stmt = $pdo->prepare("INSERT INTO iss_comments (per_id, iss_id, long_comment, posted_date) VALUES (?, ?, ?, ?)");
  $stmt->execute([
    $_SESSION['user_id'],
    $issueId,
    $_POST['comment'],
    date('Y-m-d')
  ]);
  header("Location: comments.php?issue_id=$issueId");
  exit();
}

// Delete comment
if (isset($_GET['delete'])) {
  $commentId = $_GET['delete'];
  $stmt = $pdo->prepare("SELECT per_id FROM iss_comments WHERE id = ?");
  $stmt->execute([$commentId]);
  $comment = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($_SESSION['is_admin'] || $comment['per_id'] == $_SESSION['user_id']) {
    $stmt = $pdo->prepare("DELETE FROM iss_comments WHERE id = ?");
    $stmt->execute([$commentId]);
  }

  header("Location: comments.php?issue_id=$issueId");
  exit();
}

// Fetch all comments on this issue
$stmt = $pdo->prepare("
  SELECT c.*, p.fname, p.lname
  FROM iss_comments c
  JOIN iss_persons p ON c.per_id = p.id
  WHERE c.iss_id = ?
  ORDER BY c.posted_date DESC
");
$stmt->execute([$issueId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- HTML Section -->
<head>
  <meta charset="UTF-8">
  <title>Comments on Issue</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 2rem; }
    .comment-box { background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem; }
  </style>
</head>
<body>

<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Comments on: <?= htmlspecialchars($issue['short_description']) ?></h2>
    <a href="index.php" class="btn btn-outline-secondary">Back to Issues</a>
  </div>

  <!-- Comment Form -->
  <form method="POST" class="mb-4">
    <div class="mb-3">
      <label for="comment" class="form-label">Add Comment</label>
      <textarea name="comment" class="form-control" rows="3" required></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Post Comment</button>
  </form>

  <!-- Comment List -->
  <?php foreach ($comments as $comment): ?>
    <?php
      $canDelete = $_SESSION['is_admin'] || $comment['per_id'] == $_SESSION['user_id'];
    ?>
    <div class="comment-box">
      <div class="d-flex justify-content-between">
        <strong><?= htmlspecialchars($comment['fname'] . ' ' . $comment['lname']) ?></strong>
        <small><?= htmlspecialchars($comment['posted_date']) ?></small>
      </div>
      <p><?= nl2br(htmlspecialchars($comment['long_comment'])) ?></p>
      <div class="text-end">
        <a href="<?= $canDelete ? "comments.php?issue_id=$issueId&delete={$comment['id']}" : "#" ?>"
           class="btn btn-sm <?= $canDelete ? 'btn-danger' : 'btn-secondary disabled-link' ?>"
           onclick="<?= $canDelete ? "return confirm('Delete this comment?')" : "return false" ?>">Delete</a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>
