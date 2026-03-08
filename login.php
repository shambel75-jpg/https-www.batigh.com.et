<?php
// db.php  session_start() 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'db.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    
    $password = $_POST['password']; 

    $stmt = $conn->prepare("SELECT * FROM staff WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['staff'] = $username;
        header("Location: index.php"); 
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>


<?php
/*session_start();
require 'db.php';

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = md5($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM staff WHERE username=? AND password=?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['staff'] = $username;
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}

*/


?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bati Hospital | Patient Queue & Service Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        body { font-family: Arial; background:#f2f2f2; margin:0; }
        .login-box {
            width: 350px;
            margin: 120px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        input {
            width:100%;
            padding:10px;
            margin:10px 0;
            color:#333333;
            border:1px solid #ccc;
        }
        button {
            width:100%;
            padding:10px;
            background:#1a73e8;
            color:white;
            border:none;
            cursor:pointer;
        }
        .error { color:red; text-align:center; }

        /* header styles copied from index.php */

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        header {
            background-color: #6e8996;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 0 80px;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            color: #1a73e8;
            font-size: 2.2rem;
        }

        .logo-text h1 {
            font-size: 1.8rem;
            color: #202124;
            margin-bottom: 5px;
        }

        .logo-text p {
            color: #0f1dd8;
            font-size: 20px;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        nav a {
            text-decoration: none;
            color: #242022;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        nav a:hover {
            color: #1a73e8;
        }

        nav a.active {
            color: #0104a7;
            font-size: 25px;
        }

        nav a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: #1902e6;
            border-radius: 3px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #202124;
            cursor: pointer;
        }
        
    </style>
</head>
<body>

    <header>
        <div class="container">
            <div class="container header-container">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-hospital-alt"></i>
                    </div>
                    <div class="logo-text">
                        <h1>Bati General Hospital</h1>
                        <p>Patient Queue Registration & Service Management Modern website</p>
                    </div>
                </div>

                <nav id="mainNav">
                    <ul>
                        <li><a href="index.php" class="active">Home</a></li>
                    </ul>
                </nav>
            </div>
        </div> 
    </header>

    <div class="login-box">
        <div style="text-align:center; margin-bottom:20px;">
            <i class="fas fa-hospital-alt" style="font-size:2.5rem; color:#1a73e8;"></i>
            <h1 style="margin:0; font-size:1.5rem;">Bati General Hospital</h1>
            
        </div>
        <h2 style="text-align:center;">Staff Login</h2>

        <?php if(isset($error)) echo "<p class='error'>$error</p>"; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
    </div>

        <script>
            document.getElementById('mobileMenuBtn').addEventListener('click', function(){
                const nav = document.getElementById('mainNav');
                nav.classList.toggle('active');
                this.innerHTML = nav.classList.contains('active') ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
            });
        </script>
 
</div>

</body>
</html>