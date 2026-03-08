<?php
session_start();
if (!isset($_SESSION['staff_logged_in'])) {
    header("location: stafflogin.php"); // Login ካላደረገ ይመልሰዋል
    exit();
}
include 'config.php';

// የታካሚዎችን ብዛት ለመቁጠር
$count_query = "SELECT COUNT(*) as total FROM patients"; // patients የሰንጠረዥህ ስም መሆኑን አረጋግጥ
$result = mysqli_query($conn, $count_query);
$row = mysqli_fetch_assoc($result);
$total_patients = $row['total'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Staff Dashboard</title>
</head>
<body>
    <h2>እንኳን ደህና መጡ!</h2>
    <div style="background: #f4f4f4; padding: 20px; border-radius: 10px; width: 300px;">
        <h3>የተመዘገቡ ታካሚዎች ብዛት</h3>
        <h1 style="color: green;"><?php echo $total_patients; ?></h1>
    </div>
    <br>
    <a href="logout.php">ውጣ (Logout)</a>
</body>
</html>