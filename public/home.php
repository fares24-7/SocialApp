<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$currentEmail = current_user_email();
$error = '';
$success = '';
$redirectAfterPost = false;

// Dynamically locate post text column by filtering out structural columns
$postCols = $pdo->query('SHOW COLUMNS FROM `text_posts`')->fetchAll(PDO::FETCH_COLUMN);
$ignoredPostCols = ['postid', 'useremail', 'createdat', 'timestamp', 'date', 'created_at', 'likes'];
$postTextCol = '';
foreach ($postCols as $col) {
    if (!in_array(strtolower($col), $ignoredPostCols)) {
        $postTextCol = $col;
        break;
    }
}
if (!$postTextCol) {
    $postTextCol = $postCols[count($postCols) - 1];
}

// Dynamically locate comment text column by filtering out structural columns
$commCols = $pdo->query('SHOW COLUMNS FROM `comment`')->fetchAll(PDO::FETCH_COLUMN);
$ignoredCommCols = ['commentid', 'postid', 'useremail', 'parentcommentid', 'createdat', 'timestamp', 'date', 'created_at', 'likes'];
$commentTextCol = '';
foreach ($commCols as $col) {
    if (!in_array(strtolower($col), $ignoredCommCols)) {
        $commentTextCol = $col;
        break;
    }
}
if (!$commentTextCol) {
    $commentTextCol = $commCols[count($commCols) - 1];
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create New Post
    if ($action === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        if (!empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO `text_posts` (UserEmail, `$postTextCol`) VALUES (?, ?)");
            $stmt->execute([$currentEmail, $content]);
            $redirectAfterPost = true;
        } else {
            $error = 'Post content cannot be empty.';
        }
    }

    // Delete Own Post
    elseif ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM `text_posts` WHERE PostID = ? AND UserEmail = ?');
        $stmt->execute([$postId, $currentEmail]);
        $redirectAfterPost = true;
    }

    // Like / Unlike Post
    elseif ($action === 'like_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        $checkStmt = $pdo->prepare('SELECT * FROM `post_likes` WHERE PostID = ? AND UserEmail = ?');
        $checkStmt->execute([$postId, $currentEmail]);

        if ($checkStmt->fetch()) {
            $delStmt = $pdo->prepare('DELETE FROM `post_likes` WHERE PostID = ? AND UserEmail = ?');
            $delStmt->execute([$postId, $currentEmail]);
        } else {
            $insStmt = $pdo->prepare('INSERT INTO `post_likes` (PostID, UserEmail) VALUES (?, ?)');
            $insStmt->execute([$postId, $currentEmail]);
        }
        $redirectAfterPost = true;
    }

    // Add Comment or Reply
    elseif ($action === 'add_comment') {
        $postId   = (int)($_POST['post_id'] ?? 0);
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $content  = trim($_POST['comment_content'] ?? '');

        if (!empty($content) && $postId > 0) {
            $stmt = $pdo->prepare("INSERT INTO `comment` (PostID, UserEmail, ParentCommentID, `$commentTextCol`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$postId, $currentEmail, $parentId, $content]);
            $redirectAfterPost = true;
        }
    }

    // Like / Unlike Comment
    elseif ($action === 'like_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $checkStmt = $pdo->prepare('SELECT * FROM `comment_likes` WHERE CommentID = ? AND UserEmail = ?');
        $checkStmt->execute([$commentId, $currentEmail]);

        if ($checkStmt->fetch()) {
            $delStmt = $pdo->prepare('DELETE FROM `comment_likes` WHERE CommentID = ? AND UserEmail = ?');
            $delStmt->execute([$commentId, $currentEmail]);
        } else {
            $insStmt = $pdo->prepare('INSERT INTO `comment_likes` (CommentID, UserEmail) VALUES (?, ?)');
            $insStmt->execute([$commentId, $currentEmail]);
        }
        $redirectAfterPost = true;
    }
}

// PRG (Post/Redirect/Get): prevents browser refresh from submitting the same action again.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $redirectAfterPost) {
    header('Location: home.php');
    exit;
}

// Fetch All Feed Posts
$postsStmt = $pdo->prepare("
    SELECT p.*, p.`$postTextCol` AS post_text_value, u.FirstName, u.LastName,
           (SELECT COUNT(*) FROM `post_likes` WHERE PostID = p.PostID) AS like_count,
           (SELECT COUNT(*) FROM `post_likes` WHERE PostID = p.PostID AND UserEmail = ?) AS user_liked
    FROM `text_posts` p
    LEFT JOIN `user` u ON p.UserEmail = u.Email
    WHERE p.UserEmail = ?
       OR EXISTS (
           SELECT 1 FROM `friends_with` f
           WHERE f.Status = 'accepted'
             AND ((f.RequesterEmail = ? AND f.ReceiverEmail = p.UserEmail)
               OR (f.ReceiverEmail = ? AND f.RequesterEmail = p.UserEmail))
       )
    ORDER BY p.P_UploadTime DESC, p.PostID DESC
");
$postsStmt->execute([$currentEmail, $currentEmail, $currentEmail, $currentEmail]);
$posts = $postsStmt->fetchAll();

// Comment Tree Rendering Function
function renderCommentTree($parentId, $commentsByParent, $currentEmail) {
    if (!isset($commentsByParent[$parentId])) {
        return;
    }

    $indentStyle = $parentId === 0 ? 'list-style:none; padding:0; margin-top:10px;' : 'list-style:none; padding-left:18px; margin-top:8px; border-left:2px solid #3498db;';
    echo "<ul style='{$indentStyle}'>";

    foreach ($commentsByParent[$parentId] as $comment) {
        $commentId   = $comment['CommentID'];
        $author      = htmlspecialchars(($comment['FirstName'] ?? 'User') . ' ' . ($comment['LastName'] ?? ''));
        $content     = htmlspecialchars($comment['comment_text_value'] ?? '');
        $likeCount   = (int)$comment['like_count'];
        $userLiked   = (int)$comment['user_liked'] > 0;
        $likeBtnText = $userLiked ? "Unlike ({$likeCount})" : "Like ({$likeCount})";
        $formId      = "reply-form-" . $commentId;

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

        renderCommentTree($commentId, $commentsByParent, $currentEmail);

        echo "</li>";
    }

    echo "</ul>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home - Feed</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 580px; max-width: 100%;">
    <?php include '../includes/nav.php'; ?>

    <h2>Home Feed</h2>

    <?php if ($error): ?><p style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <!-- Create Post Form -->
    <div style="background: #ecf0f1; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
        <h4 style="margin: 0 0 10px 0;">Create a Post</h4>
        <form method="post">
            <input type="hidden" name="action" value="create_post">
            <textarea name="content" rows="3" placeholder="What's on your mind?" required style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; resize: vertical;"></textarea>
            <button type="submit" style="margin-top: 8px; background: #27ae60; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold;">Publish Post</button>
        </form>
    </div>

    <!-- Feed Posts List -->
    <h3 style="border-bottom: 1px solid #eee; padding-bottom: 5px;">Recent Posts</h3>
    <?php if (empty($posts)): ?>
        <p style="color: #777;">No posts available yet. Be the first to publish one!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <?php
            $postId     = $post['PostID'];
            $isOwner    = ($post['UserEmail'] === $currentEmail);
            $likeCount  = (int)$post['like_count'];
            $userLiked  = (int)$post['user_liked'] > 0;
            $likeLabel  = $userLiked ? "Unlike ({$likeCount})" : "Like ({$likeCount})";

            // Fetch Comments for this Post
            $cStmt = $pdo->prepare("
                SELECT c.*, c.`$commentTextCol` AS comment_text_value, u.FirstName, u.LastName,
                       (SELECT COUNT(*) FROM `comment_likes` WHERE CommentID = c.CommentID) AS like_count,
                       (SELECT COUNT(*) FROM `comment_likes` WHERE CommentID = c.CommentID AND UserEmail = ?) AS user_liked
                FROM `comment` c
                LEFT JOIN `user` u ON c.UserEmail = u.Email
                WHERE c.PostID = ?
                ORDER BY c.CommentID ASC
            ");
            $cStmt->execute([$currentEmail, $postId]);
            $allComments = $cStmt->fetchAll();

            $commentsByParent = [];
            foreach ($allComments as $comm) {
                $pId = !empty($comm['ParentCommentID']) ? (int)$comm['ParentCommentID'] : 0;
                $commentsByParent[$pId][] = $comm;
            }
            ?>
            <div style="border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px; margin-bottom: 20px; background: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <strong><?= htmlspecialchars(($post['FirstName'] ?? 'User') . ' ' . ($post['LastName'] ?? '')) ?></strong>
                        <br><small style="color: #777;"><?= htmlspecialchars($post['UserEmail']) ?></small>
                    </div>
                    <?php if ($isOwner): ?>
                        <form method="post" style="margin: 0;" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= $postId ?>">
                            <button type="submit" style="background: #e74c3c; color: white; border: none; padding: 4px 8px; font-size: 11px; border-radius: 3px; cursor: pointer;">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>

                <p style="margin: 12px 0; font-size: 15px; white-space: pre-wrap;"><?= htmlspecialchars($post['post_text_value'] ?? '') ?></p>

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

                <?php renderCommentTree(0, $commentsByParent, $currentEmail); ?>
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