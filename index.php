<?php
session_start();
require 'db.php';

/* =========================
   STAFF LOGIN
========================= */

if (isset($_POST['staff_login'])) {
    if ($_POST['username'] === "admin" && $_POST['password'] === "1234") {
        $_SESSION['staff'] = true;
    } else {
        $login_error = "Invalid login!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

/* =========================
   PATIENT REGISTRATION
========================= */
if (isset($_POST['add_patient'])) {

    $name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $department = $_POST['department'];
    $service = $_POST['service_type'];

    $result = $conn->query("SELECT COUNT(*) as total FROM patients");
    $row = $result->fetch_assoc();
    $next = $row['total'] + 1;

    $queue_number = "A-" . str_pad($next, 3, "0", STR_PAD_LEFT);

    // Ensure the 'queue_number' column exists before inserting (prevents SQL errors)
    $colCheck = $conn->query("SHOW COLUMNS FROM patients LIKE 'queue_number'");
    if (!$colCheck || $colCheck->num_rows == 0) {
        // attempt to add the column (VARCHAR(16) to be safe)
        $alterSql = "ALTER TABLE patients ADD COLUMN queue_number VARCHAR(16) AFTER service_type";
        $conn->query($alterSql);
    }

    $stmt = $conn->prepare("INSERT INTO patients (full_name, phone, department, service_type, queue_number) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        // fallback: try inserting without queue_number if prepare still fails
        $stmt2 = $conn->prepare("INSERT INTO patients (full_name, phone, department, service_type) VALUES (?, ?, ?, ?)");
        if ($stmt2) {
            $stmt2->bind_param("ssss", $name, $phone, $department, $service);
            $stmt2->execute();
            $stmt2->close();
        } else {
            // show DB error for debugging
            error_log('DB prepare error: ' . $conn->error);
        }
    } else {
        $stmt->bind_param("sssss", $name, $phone, $department, $service, $queue_number);
        $stmt->execute();
        $stmt->close();
    }

    $generated_queue = $queue_number;
    $generated_department = $department;
}

/* =========================
   GET PATIENTS FOR STAFF
========================= */
// Handle deletion and status update actions (e.g., set waiting, in-progress, completed)
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $del = $conn->prepare("DELETE FROM patients WHERE id=?");
    if ($del) {
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
    }
    header("Location: index.php");
    exit();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $allowed = ['waiting', 'in-progress', 'completed'];
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if (in_array($action, $allowed)) {
        // ensure status column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM patients LIKE 'status'");
        if (!$colCheck || $colCheck->num_rows == 0) {
            $conn->query("ALTER TABLE patients ADD COLUMN status VARCHAR(20) DEFAULT 'waiting'");
        }

        $up = $conn->prepare("UPDATE patients SET status=? WHERE id=?");
        if ($up) {
            $up->bind_param("si", $action, $id);
            $up->execute();
            $up->close();
        }
    }

    header("Location: index.php");
    exit();
}
if (isset($_SESSION['staff'])) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;

    // total patients for pagination
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM patients");
    $totalRow = $totalResult->fetch_assoc();
    $totalPatients = isset($totalRow['total']) ? intval($totalRow['total']) : 0;

    // order: in-progress first, then waiting, then completed; then by id
    $patients = $conn->query("SELECT * FROM patients ORDER BY FIELD(status,'in-progress','waiting','completed'), id ASC LIMIT $limit OFFSET $offset");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bati Hospital | Patient Queue & Service Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a73e8;
            --primary-blue: #0d47a1;
            --secondary: #34a853;
            --accent: #ea4335;
            --warning: #fbbc05;
            --light: #5f6e7c;
            --dark: #202124;
            --gray: #5f6368;
            --light-gray: #525a69;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --radius: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            color: var(--pink);
            background-color: #bfc6d3;
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: lime;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
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
            color: var(--primary);
            font-size: 2.2rem;
        }

        .logo-text h1 {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .logo-text p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        nav a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            font-size: 1rem;
            transition: var(--transition);
            position: relative;
        }

        nav a:hover {
            color: var(--primary);
        }

        nav a.active {
            color: var(--primary);
        }

        nav a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 3px;
            background-color: var(--primary);
            border-radius: 3px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-blue);
            transform: translateY(-2px);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }

        .btn-accent {
            background-color: var(--secondary);
            color: white;
        }

        .btn-accent:hover {
            background-color: #2d9249;
        }

        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            padding: 80px 0;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero-text h2 {
            font-size: 2.8rem;
            line-height: 1.2;
            margin-bottom: 20px;
            color: var(--dark);
        }

        .hero-text p {
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 30px;
        }

        .hero-image {
            position: relative;
        }

        .hero-image img {
            width: 100%;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        /* Features Section */
        .features {
            padding: 80px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .section-title p {
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background-color: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-10px);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background-color: #e8f0fe;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon i {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .feature-card h3 {
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        .feature-card p {
            color: var(--gray);
        }

        /* Queue Management */
        .queue-section {
            padding: 80px 0;
            background-color: #f0f7ff;
        }

        .queue-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 40px;
        }

        .queue-display {
            background-color: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }

        .current-queue {
            text-align: center;
            margin-bottom: 40px;
        }

        .queue-number {
            font-size: 4rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .queue-patient {
            font-size: 1.2rem;
            color: var(--gray);
        }

        .queue-list {
            margin-top: 30px;
        }

        .queue-list h3 {
            margin-bottom: 20px;
        }

        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: var(--radius);
            margin-bottom: 10px;
            transition: var(--transition);
        }

        .queue-item:hover {
            background-color: #e8f0fe;
        }

        .queue-item.active {
            background-color: #e8f0fe;
            border-left: 4px solid var(--primary);
        }

        .queue-form {
            background-color: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.2);
        }

        /* Dashboard */
        .dashboard {
            padding: 80px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background-color: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary {
            background-color: #e8f0fe;
            color: var(--primary);
        }

        .stat-icon.secondary {
            background-color: #e6f4ea;
            color: var(--secondary);
        }

        .stat-icon.accent {
            background-color: #fce8e6;
            color: var(--accent);
        }

        .stat-icon.warning {
            background-color: #fef7e0;
            color: var(--warning);
        }

        .stat-info h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .appointments {
            background-color: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-top: 40px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th {
            text-align: left;
            padding: 15px;
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 1px solid var(--light-gray);
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status.waiting {
            background-color: #fef7e0;
            color: #f6b100;
        }

        .status.in-progress {
            background-color: #e8f0fe;
            color: var(--primary);
        }

        .status.completed {
            background-color: #e6f4ea;
            color: var(--secondary);
        }

        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 60px 0 30px;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-column h3 {
            font-size: 1.3rem;
            margin-bottom: 25px;
            position: relative;
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 40px;
            height: 3px;
            background-color: var(--primary);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #bdc1c6;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .contact-info {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
            color: #bdc1c6;
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #3c4043;
            color: #9aa0a6;
            font-size: 0.9rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .hero-content {
                grid-template-columns: 1fr;
            }

            .queue-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
            }

            nav {
                order: 3;
                width: 100%;
                margin-top: 20px;
                display: none;
            }

            nav.active {
                display: block;
            }

            nav ul {
                flex-direction: column;
                gap: 15px;
            }

            .header-actions {
                margin-left: auto;
            }

            .mobile-menu-btn {
                display: block;
            }

            .hero-text h2 {
                font-size: 2.2rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .hero {
                padding: 50px 0;
            }

            .features, .queue-section, .dashboard {
                padding: 50px 0;
            }

            .section-title h2 {
                font-size: 1.8rem;
            }

            .btn {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container header-container">
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-hospital-alt"></i>
                </div>
                <div class="logo-text">
                    <h1>Bati General Hospital</h1>
                    <p>Patient Queue Registration & Service Management Modern Web-site</p>
                </div>
            </div>

            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>

            <nav id="mainNav">
                <ul>
                    <li><a href="#home" class="active">Home</a></li>
                    <li><a href="#queue">Queue</a></li>
                    <li><a href="#appointments">Appointments</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>

            <div class="header-actions">
                <?php if(isset($_SESSION['staff'])): ?>
                    <!-- when logged in go to dedicated logout script -->
                    <a href="logout.php" class="btn btn-outline" style="text-decoration:none;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <!-- direct staff to login page -->
                    <a href="login.php" class="btn btn-outline" style="text-decoration:none;">
                        <i class="fas fa-sign-in-alt"></i> Staff Login
                    </a>
                <?php endif; ?>
                <button class="btn btn-primary" id="joinQueueBtn">
                    <i class="fas fa-plus-circle"></i> Join Queue
                </button>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h2>Streamlined Patient Queue & Service Management</h2>
                    <p>Bati General Hospital's modern digital solution for efficient patient queue registration, appointment scheduling, and customer service management. Reduce wait times and enhance patient experience with our advanced system.</p>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <button class="btn btn-primary">
                            <i class="fas fa-calendar-check"></i> Appointment
                        </button>
                        <button class="btn btn-outline">
                            <i class="fas fa-video"></i> Virtual Consultation
                        </button>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Bati Hospital Modern Facility">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Our Service Features</h2>
                <p>Designed to improve patient experience and streamline hospital operations</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-list-ol"></i>
                    </div>
                    <h3>Digital Queue Management</h3>
                    <p>Real-time queue tracking with estimated wait times and notifications to keep patients informed.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>Online Appointment Booking</h3>
                    <p>Schedule appointments with specialists online, choose preferred time slots and receive reminders.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Patient Communication</h3>
                    <p>Secure messaging system for patients to communicate with healthcare providers and receive updates.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Analytics Dashboard</h3>
                    <p>Comprehensive analytics for hospital administration to monitor queue performance and service efficiency.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Queue Management Section -->
    <section class="queue-section" id="queue">
        <div class="container">
            <div class="section-title">
                <h2>Live Queue Management</h2>
                <p>Real-time tracking of patient queues across different departments</p>
            </div>

            <div class="queue-container">
                <div class="queue-display">
                    <div class="queue-header">
                        <h3>Cardiology Department</h3>
                        <div class="queue-status">
                            <span class="status in-progress">Active</span>
                        </div>
                    </div>

                    <div class="current-queue">
                        <p>Now Serving</p>
                        <div class="queue-number" id="currentQueue">A-045</div>
                        <p class="queue-patient">Mr. Ali Seid</p>
                    </div>

                    <div class="queue-list">
                        <h3>Upcoming Patients</h3>
                        <div class="queue-item active">
                            <div>
                                <strong>A-046</strong>
                                <p>Kebede faris</p>
                            </div>
                            <div>Estimated: 5 min</div>
                        </div>
                        <div class="queue-item">
                            <div>
                                <strong>A-047</strong>
                                <p>Tamrat Gobena</p>
                            </div>
                            <div>Estimated: 15 min</div>
                        </div>
                        <div class="queue-item">
                            <div>
                                <strong>A-048</strong>
                                <p>Helen kassa</p>
                            </div>
                            <div>Estimated: 25 min</div>
                        </div>
                        <div class="queue-item">
                            <div>
                                <strong>A-049</strong>
                                <p>Demeke Tesema</p>
                            </div>
                            <div>Estimated: 35 min</div>
                        </div>
                    </div>
                </div>

                <div class="queue-form">
                    <h3>Join Queue</h3>
                    <p style="margin-bottom: 20px; color: var(--gray);">Register for queue or book an appointment</p>
                    <form method="POST">
                        <div class="form-group">
                            <label for="patientName">Full Name</label>
                            <input type="text" name="full_name" id="patientName" class="form-control" placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label for="patientID">Patient ID / Phone</label>
                            <input type="text" name="phone" id="patientID" class="form-control" placeholder="Patient ID or Phone Number">
                        </div>

                        <div class="form-group">
                            <label for="department">Select Department</label>
                            <select id="department" name="department" class="form-control">
                                <option value="">Choose a department</option>
                                <option value="cardiology">Cardiology</option>
                                <option value="pediatrics">Pediatrics</option>
                                <option value="orthopedics">Orthopedics</option>
                                <option value="general">General Medicine</option>
                                <option value="dental">Dental</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="serviceType">Service Type</label>
                            <select id="serviceType" name="service_type" class="form-control">
                                <option value="">Select service type</option>
                                <option value="consultation">Consultation</option>
                                <option value="followup">Follow-up</option>
                                <option value="test">Lab Test</option>
                                <option value="procedure">Minor Procedure</option>
                            </select>
                        </div>

                        <button type="submit" name="add_patient" class="btn btn-accent" style="width: 100%; margin-top: 10px;">
                            <i class="fas fa-ticket-alt"></i> Register & Get Queue Ticket
                        </button>

                        <div style="text-align: center; margin-top: 20px; color: var(--gray);">
                            <p>Or</p>
                            <button class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-calendar-plus"></i> Schedule for Later
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Dashboard Section -->
    <section class="dashboard" id="appointments">
        <div class="container">
            <div class="section-title">
                <h2>Service Dashboard</h2>
                <p>Overview of hospital queue statistics and patient appointments</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>42</h3>
                        <p>Patients in Queue</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3>15 min</h3>
                        <p>Average Wait Time</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon accent">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3>17</h3>
                        <p>Today's Appointments</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-info">
                        <h3>12</h3>
                        <p>Doctors Available</p>
                    </div>
                </div>
            </div>

            <div class="appointments">
                <h3>Today's Appointments</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                    <th>Patient Name</th>
                                    <th>Time</th>
                                    <th>Department</th>
                                    <th>Service Type</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                        </thead>
                        <tbody>
                            <?php if(isset($_SESSION['staff'])): ?>
                            <?php while($row = $patients->fetch_assoc()): ?>
                            <tr>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['queue_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                            <td><?php echo htmlspecialchars($row['service_type']); ?></td>
                            <?php
                                $status = isset($row['status']) ? $row['status'] : 'waiting';
                                $statusClass = 'waiting';
                                if ($status === 'in-progress') $statusClass = 'in-progress';
                                if ($status === 'completed') $statusClass = 'completed';
                            ?>
                            <td><span class="status <?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span></td>
                            <td>
                                <?php
                                    // show in-progress when waiting (not when already in-progress or completed)
                                    if ($status === 'waiting'): ?>
                                        <a href="index.php?action=in-progress&id=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding:6px 8px; font-size:0.85rem;" title="Set in-progress">
                                            <i class="fas fa-spinner"></i>
                                        </a>
                                <?php endif; ?>
                                <?php
                                    // show complete unless already completed
                                    if ($status !== 'completed'): ?>
                                        <a href="index.php?action=completed&id=<?php echo $row['id']; ?>" class="btn btn-accent" style="padding:6px 8px; font-size:0.85rem;" title="Mark complete">
                                            <i class="fas fa-check"></i>
                                        </a>
                                <?php endif; ?>
                                <!-- delete always available -->
                                <a href="index.php?delete=1&id=<?php echo $row['id']; ?>" class="btn btn-outline" style="padding:6px 8px; font-size:0.85rem; color:#e74c3c;" title="Delete patient" onclick="return confirm('Delete this patient?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php else: ?>
                            <tr>
                            <td colspan="5" style="text-align:center;">Staff login required</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if(isset($_SESSION['staff'])): ?>
                <div style="display:flex; justify-content:space-between; margin-top:15px; align-items:center;">
                    <?php if($page > 1): ?>
                        <a href="index.php?page=<?php echo $page-1; ?>" class="btn btn-outline">Previous</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <?php if(isset($patients) && ($offset + $patients->num_rows) < $totalPatients): ?>
                        <a href="index.php?page=<?php echo $page+1; ?>" class="btn btn-primary">Next</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-container">
                <div class="footer-column">
                    <h3>Bati General Hospital Dessie, Ethiopia </h3>
                    <p style="color: #dadfe6; margin-bottom: 20px;">Providing exceptional healthcare services with modern patient management solutions.</p>
                    <div class="contact-info">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>Menafesha</div>
                    </div>
                    <div class="contact-info">
                        <i class="fas fa-phone"></i>
                        <div>+251 33 111 5522</div>
                    </div>
                    <div class="contact-info">
                        <i class="fas fa-envelope"></i>
                        <div>info@batihospital.com.et</div>
                    </div>
                </div>

                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#queue">Queue Management</a></li>
                        <li><a href="#appointments">Appointments</a></li>
                        <li><a href="#services">Our Services</a></li>
                        <li><a href="#contact">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Services</h3>
                    <ul class="footer-links">
                        <li><a href="#">Emergency Care</a></li>
                        <li><a href="#">Outpatient Services</a></li>
                        <li><a href="#">Specialist Consultations</a></li>
                        <li><a href="#">Diagnostic Services</a></li>
                        <li><a href="#">Health Check-ups</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Connect With Us</h3>
                    <p style="color: #bdc1c6; margin-bottom: 20px;">Follow us on social media for updates and health tips.</p>
                    <div style="display: flex; gap: 15px; font-size: 1.3rem;">
                        <a href="#" style="color: #bdc1c6;"><i class="fab fa-facebook"></i></a>
                        <a href="#" style="color: #bdc1c6;"><i class="fab fa-twitter"></i></a>
                        <a href="#" style="color: #bdc1c6;"><i class="fab fa-instagram"></i></a>
                        <a href="#" style="color: #bdc1c6;"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2026 Bati General Hospital. All rights reserved. | Patient Queue & Service Management System v2.1 | Developed By: Shambel Belete Abate phone 0914343495</p>
            </div>
        </div>
    </footer>

    <!-- Join Queue Modal -->
    <div id="queueModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(41, 63, 185, 0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background-color: white; border-radius: var(--radius); padding: 30px; max-width: 500px; width: 90%;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="font-size: 1.5rem;">Queue Registration</h3>
                <button id="closeModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray);">&times;</button>
            </div>
            <p style="margin-bottom: 20px; color: var(--gray);">Your queue ticket has been generated successfully.</p>
            <div style="text-align: center; background-color: #f0f7ff; padding: 20px; border-radius: var(--radius); margin-bottom: 20px;">
                <p style="color: var(--gray); margin-bottom: 10px;">Your Queue Number</p>
                <div style="font-size: 3rem; font-weight: 700; color: var(--primary); margin: 10px 0;">A-052</div>
                <p>Cardiology Department</p>
                <p style="color: var(--gray);">Estimated wait time: <strong>25 minutes</strong></p>
            </div>
            <p style="color: var(--gray); font-size: 0.9rem; margin-bottom: 20px;">You will receive SMS notifications about your queue status. Please arrive 10 minutes before your estimated time.</p>
            <button class="btn btn-primary" style="width: 100%;" id="closeModalBtn">
                <i class="fas fa-print"></i> Print Ticket & Close
            </button>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const nav = document.getElementById('mainNav');
            nav.classList.toggle('active');
            this.innerHTML = nav.classList.contains('active') ? 
                '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        });

        // Join Queue Button now scrolls to queue registration section
        document.getElementById('joinQueueBtn').addEventListener('click', function() {
            const section = document.getElementById('queue');
            if (section) {
                window.scrollTo({ top: section.offsetTop - 80, behavior: 'smooth' });
            }
        });

        // Close Modal Buttons
        document.getElementById('closeModal').addEventListener('click', function() {
            document.getElementById('queueModal').style.display = 'none';
        });

        document.getElementById('closeModalBtn').addEventListener('click', function() {
            document.getElementById('queueModal').style.display = 'none';
        });

        // Close modal when clicking outside
        document.getElementById('queueModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });

        // Update current queue number every 30 seconds (simulation)
        let queueNumbers = ['A-045', 'A-046', 'A-047', 'A-048', 'A-049'];
        let currentIndex = 0;
        
        function updateQueueNumber() {
            const queueElement = document.getElementById('currentQueue');
            currentIndex = (currentIndex + 1) % queueNumbers.length;
            queueElement.textContent = queueNumbers[currentIndex];
            
            // Update queue items
            const queueItems = document.querySelectorAll('.queue-item');
            queueItems.forEach((item, index) => {
                const numberElement = item.querySelector('strong');
                if (numberElement) {
                    const nextNum = parseInt(queueNumbers[currentIndex].split('-')[1]) + index;
                    numberElement.textContent = `A-${nextNum.toString().padStart(3, '0')}`;
                }
            });
        }
        
        // Simulate queue updates every 30 seconds
        setInterval(updateQueueNumber, 30000);

        // Smooth scrolling for navigation links
        document.querySelectorAll('nav a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId.startsWith('#')) {
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                }
                
                // Update active nav link
                document.querySelectorAll('nav a').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
                
                // Close mobile menu if open
                if (window.innerWidth <= 768) {
                    document.getElementById('mainNav').classList.remove('active');
                    document.getElementById('mobileMenuBtn').innerHTML = '<i class="fas fa-bars"></i>';
                }
            });
        });

        // Form submission for queue registration
        document.querySelector('.queue-form .btn-accent').addEventListener('click', function() {
            const name = document.getElementById('patientName').value;
            const department = document.getElementById('department').value;
            
            if (!name || !department) {
                alert('Please fill in all required fields');
                return;
            }
            
            document.getElementById('queueModal').style.display = 'flex';
        });
    </script>
</body>
</html>