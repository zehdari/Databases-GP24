<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Summary</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>View Summary</h2>
    <p>This page will provide a summary of the user's investment performance.</p>
</body>
</html>
