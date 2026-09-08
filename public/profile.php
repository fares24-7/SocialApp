<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$email = current_user_email();
$error = '';
$success = '';
$redirectAfterPost = false;

// Helper function to find the text column name for INSERT queries
function detectTextColumn($pdo, $tableName, $ignoredList) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$tableName`");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $ignoredLower = array_map('strtolower', $ignoredList);
    
    foreach ($cols as $col) {
        if (!in_array(strtolower($col), $ignoredLower)) {
            return $col;
        }
    }
    return $cols[count($cols) - 1] ?? 'content';
}

// Helper function to extract text content from a fetched database row
function extractTextValue($row, $ignoredList) {
    $ignoredLower = array_map('strtolower', $ignoredList);
    foreach ($row as $key => $val) {
        if (!is_numeric($key) && !in_array(strtolower($key), $ignoredLower)) {
            return $val;
        }
    }
    return '';
}

$postIgnoredCols    = ['postid', 'useremail', 'createdat', 'timestamp', 'date', 'created_at', 'likes', 'firstname', 'lastname', 'like_count', 'user_liked'];
$commentIgnoredCols = ['commentid', 'postid', 'useremail', 'parentcommentid', 'createdat', 'timestamp', 'date', 'created_at', 'likes', 'firstname', 'lastname', 'like_count', 'user_liked'];

$postTextCol    = detectTextColumn($pdo, 'text_posts', $postIgnoredCols);
$commentTextCol = detectTextColumn($pdo, 'comment', $commentIgnoredCols);

// Handle Profile & Post Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Delete Own Post
    if ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM `text_posts` WHERE PostID = ? AND UserEmail = ?');
        $stmt->execute([$postId, $email]);
        $redirectAfterPost = true;
    }

    // Like / Unlike Post
    elseif ($action === 'like_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $checkStmt = $pdo->prepare('SELECT * FROM `post_likes` WHERE PostID = ? AND UserEmail = ?');
        $checkStmt->execute([$postId, $email]);

        if ($checkStmt->fetch()) {
            $delStmt = $pdo->prepare('DELETE FROM `post_likes` WHERE PostID = ? AND UserEmail = ?');
            $delStmt->execute([$postId, $email]);
        } else {
            $insStmt = $pdo->prepare('INSERT INTO `post_likes` (PostID, UserEmail) VALUES (?, ?)');
            $insStmt->execute([$postId, $email]);
        }
        $redirectAfterPost = true;
    }

    // Add Comment / Reply
    elseif ($action === 'add_comment') {
        $postId   = (int)($_POST['post_id'] ?? 0);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $content  = trim($_POST['comment_content'] ?? '');

        if (!empty($content) && $postId > 0) {
            $stmt = $pdo->prepare("INSERT INTO `comment` (PostID, UserEmail, ParentCommentID, `$commentTextCol`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $email, $parentId, $content]);
            $redirectAfterPost = true;
        }
    }

    // Like / Unlike Comment
    elseif ($action === 'like_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $checkStmt = $pdo->prepare('SELECT * FROM `comment_likes` WHERE CommentID = ? AND UserEmail = ?');
        $checkStmt->execute([$commentId, $email]);

        if ($checkStmt->fetch()) {
            $delStmt = $pdo->prepare('DELETE FROM `comment_likes` WHERE CommentID = ? AND UserEmail = ?');
            $delStmt->execute([$commentId, $email]);
        } else {
            $insStmt = $pdo->prepare('INSERT INTO `comment_likes` (CommentID, UserEmail) VALUES (?, ?)');
            $insStmt->execute([$commentId, $email]);
        }
        $redirectAfterPost = true;
    }
}

// PRG (Post/Redirect/Get): prevents browser refresh from submitting the same action again.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $redirectAfterPost) {
    header('Location: profile.php');
    exit;
}

// Fetch Profile Details
$stmt = $pdo->prepare('SELECT FirstName, LastName, BirthDate, Email FROM `user` WHERE Email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Own Posts (No hardcoded column names in SELECT)
$myPostsStmt = $pdo->prepare("
    SELECT p.*, u.FirstName, u.LastName,
           (SELECT COUNT(*) FROM `post_likes` WHERE PostID = p.PostID) AS like_count,
           (SELECT COUNT(*) FROM `post_likes` WHERE PostID = p.PostID AND UserEmail = ?) AS user_liked
    FROM `text_posts` p
    LEFT JOIN `user` u ON p.UserEmail = u.Email
    WHERE p.UserEmail = ?
    ORDER BY p.PostID DESC
");
$myPostsStmt->execute([$email, $email]);
$myPosts = $myPostsStmt->fetchAll(PDO::FETCH_ASSOC);

// Recursive Tree Render Function
function renderProfileCommentTree($parentId, $commentsByParent, $commentIgnoredCols) {
    if (!isset($commentsByParent[$parentId])) {
        return;
    }

    $indentStyle = $parentId === 0 ? 'list-style:none; padding:0; margin-top:10px;' : 'list-style:none; padding-left:18px; margin-top:8px; border-left:2px solid #3498db;';
    echo "<ul style='{$indentStyle}'>";

    foreach ($commentsByParent[$parentId] as $comment) {
        $commentId   = $comment['CommentID'];
        $author      = htmlspecialchars(($comment['FirstName'] ?? 'User') . ' ' . ($comment['LastName'] ?? ''));
        $content     = htmlspecialchars(extractTextValue($comment, $commentIgnoredCols));
        $likeCount   = (int)$comment['like_count'];
        $userLiked   = (int)$comment['user_liked'] > 0;
        $likeBtnText = $userLiked ? "Unlike ({$likeCount})" : "Like ({$likeCount})";
        $formId      = "prof-reply-form-" . $commentId;

        echo "<li style='background:#f8f9fa; padding:8px 10px; margin-bottom:8px; border-radius:4px; font-size:13px;'>";
        echo "<strong>{$author}</strong> <small style='color:#777;'>({$comment['UserEmail']})</small>";
        echo "<p style='margin:4px 0 8px 0; color:#2c3e50;'>{$content}</p>";

        echo "<div style='display:flex; gap:10px; align-items:center;'>";
        echo "<form method='post' style='margin:0;'>";
        echo "<input type='hidden' name='action' value='like_comment'>";
        echo "<input type='hidden' name='comment_id' value='{$commentId}'>";
        echo "<button type='submit' style='background:none; border:none; color:#2980b9; cursor:pointer; padding:0; font-size:12px; font-weight:bold;'>{$likeBtnText}</button>";
        echo "</form>";

        echo "<button type='button' onclick=\"document.getElementById('{$formId}').style.display = (document.getElementById('{$formId}').style.display === 'none' ? 'block' : 'none');\" style='background:none; border:none; color:#7f8c8d; cursor:pointer; padding:0; font-size:12px;'>Reply</button>";
        echo "</div>";

        echo "<div id='{$formId}' style='display:none; margin-top:8px;'>";
        echo "<form method='post' style='display:flex; gap:5px;'>";
        echo "<input type='hidden' name='action' value='add_comment'>";
        echo "<input type='hidden' name='post_id' value='{$comment['PostID']}'>";
        echo "<input type='hidden' name='parent_id' value='{$commentId}'>";
        echo "<input type='text' name='comment_content' class='comment-input' placeholder='Write a reply...' required>";
        echo "<button type='submit' class='comment-submit' style='background:#2980b9;'>Reply</button>";
        echo "</form>";
        echo "</div>";

        renderProfileCommentTree($commentId, $commentsByParent, $commentIgnoredCols);

        echo "</li>";
    }

    echo "</ul>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 580px; max-width: 100%;">
  <?php include '../includes/nav.php'; ?>
  
  <h2>My Profile</h2>
  
  <?php if ($error): ?><p style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

  <div style="background:#f8f9fa; padding:15px; border-radius:6px; margin-bottom:20px;">
      <p style="margin:4px 0;"><strong>Name:</strong> <?= htmlspecialchars(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')) ?></p>
      <p style="margin:4px 0;"><strong>Email:</strong> <?= htmlspecialchars($user['Email'] ?? '') ?></p>
      <p style="margin:4px 0;"><strong>Birth Date:</strong> <?= htmlspecialchars($user['BirthDate'] ?? '') ?></p>

      <div style="margin-top: 15px; display: flex; gap: 10px;">
          <a href="edit-profile.php" style="text-decoration: none; flex: 1;">
              <button type="button" style="width: 100%; padding: 8px;">Edit Profile</button>
          </a>
          <a href="security.php" style="text-decoration: none; flex: 1;">
              <button type="button" style="background:#555; width: 100%; padding: 8px;">Security Settings</button>
          </a>
          <a href="logout.php" style="text-decoration: none; flex: 1;">
              <button type="button" style="background:#c0392b; width: 100%; padding: 8px;">Log Out</button>
          </a>
      </div>
  </div>

  <!-- User's Own Posts -->
  <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px;">My Posts (<?= count($myPosts) ?>)</h3>
  <?php if (empty($myPosts)): ?>
      <p style="color: #777;">You haven't posted anything yet.</p>
  <?php else: ?>
      <?php foreach ($myPosts as $post): ?>
          <?php
          $postId     = $post['PostID'];
          $likeCount  = (int)$post['like_count'];
          $userLiked  = (int)$post['user_liked'] > 0;
          $likeLabel  = $userLiked ? "Unlike ({$likeCount})" : "Like ({$likeCount})";
          $postText   = extractTextValue($post, $postIgnoredCols);

          $cStmt = $pdo->prepare("
              SELECT c.*, u.FirstName, u.LastName,
                     (SELECT COUNT(*) FROM `comment_likes` WHERE CommentID = c.CommentID) AS like_count,
                     (SELECT COUNT(*) FROM `comment_likes` WHERE CommentID = c.CommentID AND UserEmail = ?) AS user_liked
              FROM `comment` c
              LEFT JOIN `user` u ON c.UserEmail = u.Email
              WHERE c.PostID = ?
              ORDER BY c.CommentID ASC
          ");
          $cStmt->execute([$email, $postId]);
          $allComments = $cStmt->fetchAll(PDO::FETCH_ASSOC);

          $commentsByParent = [];
          foreach ($allComments as $comm) {
              $pId = !empty($comm['ParentCommentID']) ? (int)$comm['ParentCommentID'] : 0;
              $commentsByParent[$pId][] = $comm;
          }
          ?>
          <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px; margin-bottom: 20px; background: #fff;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                  <div>
                      <strong><?= htmlspecialchars(($user['FirstName'] ?? '') . ' ' . ($user['LastName'] ?? '')) ?></strong>
                      <br><small style="color: #777;"><?= htmlspecialchars($user['Email']) ?></small>
                  </div>
                  <form method="post" style="margin: 0;" onsubmit="return confirm('Delete this post?');">
                      <input type="hidden" name="action" value="delete_post">
                      <input type="hidden" name="post_id" value="<?= $postId ?>">
                      <button type="submit" style="background: #e74c3c; color: white; border: none; padding: 4px 8px; font-size: 11px; border-radius: 3px; cursor: pointer;">Delete</button>
                  </form>
              </div>

              <p style="margin: 12px 0; font-size: 15px; white-space: pre-wrap;"><?= htmlspecialchars($postText) ?></p>

              <div style="border-top: 1px solid #eee; border-bottom: 1px solid #eee; padding: 6px 0; margin-bottom: 10px; display: flex; gap: 10px;">
                  <form method="post" style="margin: 0;">
                      <input type="hidden" name="action" value="like_post">
                      <input type="hidden" name="post_id" value="<?= $postId ?>">
                      <button type="submit" style="background: <?= $userLiked ? '#e74c3c' : '#3498db' ?>; color: white; border: none; padding: 5px 12px; border-radius: 3px; font-size: 12px; cursor: pointer;"><?= $likeLabel ?></button>
                  </form>
              </div>

              <form method="post" class="comment-form">
                  <input type="hidden" name="action" value="add_comment">
                  <input type="hidden" name="post_id" value="<?= $postId ?>">
                  <input type="text" name="comment_content" class="comment-input" placeholder="Write a comment..." required>
                  <button type="submit" class="comment-submit" style="background:#2c3e50;">Comment</button>
              </form>

              <?php renderProfileCommentTree(0, $commentsByParent, $commentIgnoredCols); ?>
          </div>
      <?php endforeach; ?>
  <?php endif; ?>
</div>
<script>
// Prevent accidental double-clicks on comment/reply submissions.
document.querySelectorAll('.comment-form, .reply-form').forEach(function (form) {
    form.addEventListener('submit', function () {
        var button = form.querySelector('.comment-submit');
        if (button) {
            button.disabled = true;
            button.textContent = 'Posting...';
        }
    });
});
</script>
</body>
</html>