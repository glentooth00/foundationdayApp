
<?php
// ============================================================
// FOUNDATION DAY 2026 - ADMIN DASHBOARD
// ============================================================

// Temporary dashboard data.
// We will replace these with database queries later.

$totalEmployees = 152;
$totalCandidates = 24;
$totalVotes = 381;
$votedEmployees = 127;

$participation = $totalEmployees > 0
    ? round(($votedEmployees / $totalEmployees) * 100, 1)
    : 0;

$categories = [
    [
        'name' => 'Face of the Night',
        'slug' => 'face_of_the_night',
        'candidates' => 8,
        'votes' => 127
    ],
    [
        'name' => 'Star of the Night',
        'slug' => 'star_of_the_night',
        'candidates' => 8,
        'votes' => 128
    ],
    [
        'name' => 'Darling of the Crowd',
        'slug' => 'darling_of_the_crowd',
        'candidates' => 8,
        'votes' => 126
    ]
];

$recentActivity = [
    [
        'name' => 'Juan Dela Cruz',
        'action' => 'Candidate added',
        'category' => 'Face of the Night',
        'time' => '5 minutes ago'
    ],
    [
        'name' => 'Maria Santos',
        'action' => 'Candidate added',
        'category' => 'Star of the Night',
        'time' => '18 minutes ago'
    ],
    [
        'name' => 'Ana Reyes',
        'action' => 'Candidate updated',
        'category' => 'Darling of the Crowd',
        'time' => '32 minutes ago'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Foundation Day Voting</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #1d4ed8;
            --primary-dark: #1e3a8a;
            --background: #f5f7fb;
            --sidebar: #111827;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--background);
            color: var(--text);
            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: var(--sidebar);
            color: #fff;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .brand {
            height: 75px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 19px;
        }

        .brand-text strong {
            display: block;
            font-size: 15px;
            line-height: 1.2;
        }

        .brand-text span {
            display: block;
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .nav-section {
            padding: 24px 14px;
            flex: 1;
        }

        .nav-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6b7280;
            padding: 0 12px;
            margin-bottom: 8px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 12px;
            margin-bottom: 3px;
            border-radius: 8px;
            color: #9ca3af;
            text-decoration: none;
            font-size: 14px;
            transition: .2s;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,.06);
            color: #fff;
        }

        .sidebar a.active {
            background: #1d4ed8;
            color: #fff;
        }

        .sidebar a i {
            width: 18px;
            font-size: 16px;
        }

        .sidebar-footer {
            padding: 15px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .admin-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
        }

        .admin-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .admin-info strong {
            display: block;
            font-size: 13px;
        }

        .admin-info span {
            display: block;
            color: #9ca3af;
            font-size: 11px;
        }

        /* =====================================================
           MAIN
        ===================================================== */

        .main {
            margin-left: 250px;
            min-height: 100vh;
        }

        /* =====================================================
           TOPBAR
        ===================================================== */

        .topbar {
            height: 75px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
        }

        .page-title h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }

        .page-title p {
            margin: 3px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification {
            width: 38px;
            height: 38px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            position: relative;
        }

        .notification .dot {
            position: absolute;
            width: 7px;
            height: 7px;
            background: #ef4444;
            border-radius: 50%;
            top: 7px;
            right: 7px;
        }

        /* =====================================================
           CONTENT
        ===================================================== */

        .content {
            padding: 30px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .welcome p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        /* =====================================================
           STAT CARDS
        ===================================================== */

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            height: 100%;
        }

        .stat-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: var(--primary);
            font-size: 19px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-top: 15px;
        }

        .stat-value {
            font-size: 27px;
            font-weight: 700;
            margin-top: 3px;
        }

        .stat-change {
            margin-top: 7px;
            font-size: 11px;
            color: #16a34a;
        }

        /* =====================================================
           SECTION
        ===================================================== */

        .section-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .section-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h3 {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }

        .section-header a {
            color: var(--primary);
            font-size: 12px;
            text-decoration: none;
        }

        /* =====================================================
           CATEGORY
        ===================================================== */

        .category-item {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
        }

        .category-item:last-child {
            border-bottom: 0;
        }

        .category-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .category-name {
            font-size: 13px;
            font-weight: 600;
        }

        .category-stats {
            color: var(--muted);
            font-size: 12px;
        }

        .progress {
            height: 7px;
            background: #eef2f7;
            border-radius: 20px;
        }

        .progress-bar {
            background: var(--primary);
            border-radius: 20px;
        }

        /* =====================================================
           ACTIVITY
        ===================================================== */

        .activity {
            display: flex;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }

        .activity:last-child {
            border-bottom: 0;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #eff6ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .activity-content {
            min-width: 0;
        }

        .activity-content strong {
            display: block;
            font-size: 13px;
        }

        .activity-content span {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-top: 2px;
        }

        .activity-time {
            display: block;
            font-size: 10px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* =====================================================
           QUICK ACTIONS
        ===================================================== */

        .quick-action {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 15px 20px;
            text-decoration: none;
            color: var(--text);
            border-bottom: 1px solid var(--border);
            transition: .2s;
        }

        .quick-action:last-child {
            border-bottom: 0;
        }

        .quick-action:hover {
            background: #f8fafc;
        }

        .quick-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: #eff6ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quick-text strong {
            display: block;
            font-size: 13px;
        }

        .quick-text span {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        .quick-arrow {
            margin-left: auto;
            color: #9ca3af;
        }

        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 991px) {

            .sidebar {
                width: 70px;
            }

            .brand {
                padding: 0;
                justify-content: center;
            }

            .brand-text,
            .nav-label,
            .sidebar a span,
            .admin-info {
                display: none;
            }

            .sidebar a {
                justify-content: center;
                padding: 13px;
            }

            .sidebar a i {
                width: auto;
            }

            .admin-user {
                justify-content: center;
            }

            .main {
                margin-left: 70px;
            }
        }

        @media (max-width: 767px) {

            .topbar {
                padding: 0 18px;
            }

            .content {
                padding: 20px 15px;
            }

            .welcome h2 {
                font-size: 21px;
            }

            .topbar .page-title p {
                display: none;
            }
        }

    </style>
</head>

<body>

<!-- ============================================================
     SIDEBAR
============================================================ -->

<aside class="sidebar">

    <div class="brand">

        <div class="brand-icon">
            <i class="bi bi-ballot-fill"></i>
        </div>

        <div class="brand-text">
            <strong>Foundation Day</strong>
            <span>Voting System</span>
        </div>

    </div>

    <div class="nav-section">

        <div class="nav-label">
            Administration
        </div>

        <a href="dashboard.php" class="active">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Dashboard</span>
        </a>

        <a href="candidates.php">
            <i class="bi bi-person-badge-fill"></i>
            <span>Candidates</span>
        </a>

        <a href="employees.php">
            <i class="bi bi-people-fill"></i>
            <span>Employees</span>
        </a>

        <a href="results.php">
            <i class="bi bi-bar-chart-fill"></i>
            <span>Results</span>
        </a>

        <div class="nav-label mt-4">
            System
        </div>

        <a href="settings.php">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>

    </div>

    <div class="sidebar-footer">

        <div class="admin-user">

            <div class="admin-avatar">
                <i class="bi bi-person-fill"></i>
            </div>

            <div class="admin-info">
                <strong>Administrator</strong>
                <span>System Admin</span>
            </div>

        </div>

    </div>

</aside>


<!-- ============================================================
     MAIN
============================================================ -->

<main class="main">

    <!-- TOPBAR -->

    <header class="topbar">

        <div class="page-title">

            <h1>Dashboard</h1>

            <p>
                Foundation Day 2026 Voting System
            </p>

        </div>

        <div class="topbar-actions">

            <button class="notification border">
                <i class="bi bi-bell"></i>
                <span class="dot"></span>
            </button>

        </div>

    </header>


    <!-- CONTENT -->

    <div class="content">

        <div class="welcome">

            <h2>
                Good morning, Administrator
            </h2>

            <p>
                Here's an overview of your Foundation Day voting system.
            </p>

        </div>


        <!-- ====================================================
             STATISTICS
        ===================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Total Employees
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalEmployees) ?>
                    </div>

                    <div class="stat-change">
                        <i class="bi bi-arrow-up"></i>
                        Registered voters
                    </div>

                </div>

            </div>


            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Total Candidates
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalCandidates) ?>
                    </div>

                    <div class="stat-change">
                        <i class="bi bi-check-circle"></i>
                        Across all categories
                    </div>

                </div>

            </div>


            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon">
                            <i class="bi bi-check2-square"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Total Votes
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalVotes) ?>
                    </div>

                    <div class="stat-change">
                        <i class="bi bi-bar-chart"></i>
                        Votes recorded
                    </div>

                </div>

            </div>


            <div class="col-12 col-sm-6 col-xl-3">

                <div class="stat-card">

                    <div class="stat-top">

                        <div class="stat-icon">
                            <i class="bi bi-pie-chart-fill"></i>
                        </div>

                    </div>

                    <div class="stat-label">
                        Participation
                    </div>

                    <div class="stat-value">
                        <?= $participation ?>%
                    </div>

                    <div class="stat-change">
                        <?= $votedEmployees ?> of <?= $totalEmployees ?> employees voted
                    </div>

                </div>

            </div>

        </div>


        <!-- ====================================================
             LOWER CONTENT
        ===================================================== -->

        <div class="row g-4">

            <!-- CATEGORY OVERVIEW -->

            <div class="col-12 col-lg-7">

                <div class="section-card">

                    <div class="section-header">

                        <h3>
                            Voting Categories
                        </h3>

                        <a href="candidates.php">
                            Manage Candidates
                            <i class="bi bi-arrow-right"></i>
                        </a>

                    </div>


                    <?php foreach ($categories as $category): ?>

                        <?php
                        $percentage = $totalVotes > 0
                            ? round(($category['votes'] / $totalVotes) * 100)
                            : 0;
                        ?>

                        <div class="category-item">

                            <div class="category-info">

                                <div class="category-name">
                                    <?= htmlspecialchars($category['name']) ?>
                                </div>

                                <div class="category-stats">
                                    <?= $category['candidates'] ?> candidates
                                    ·
                                    <?= $category['votes'] ?> votes
                                </div>

                            </div>

                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width: <?= $percentage ?>%"
                                ></div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- QUICK ACTIONS -->

            <div class="col-12 col-lg-5">

                <div class="section-card">

                    <div class="section-header">

                        <h3>
                            Quick Actions
                        </h3>

                    </div>


                    <a href="candidates.php" class="quick-action">

                        <div class="quick-icon">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>

                        <div class="quick-text">

                            <strong>
                                Add Candidate
                            </strong>

                            <span>
                                Register a new candidate
                            </span>

                        </div>

                        <i class="bi bi-chevron-right quick-arrow"></i>

                    </a>


                    <a href="employees.php" class="quick-action">

                        <div class="quick-icon">
                            <i class="bi bi-person-plus"></i>
                        </div>

                        <div class="quick-text">

                            <strong>
                                Add Employee
                            </strong>

                            <span>
                                Register a new voter
                            </span>

                        </div>

                        <i class="bi bi-chevron-right quick-arrow"></i>

                    </a>


                    <a href="results.php" class="quick-action">

                        <div class="quick-icon">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>

                        <div class="quick-text">

                            <strong>
                                View Results
                            </strong>

                            <span>
                                Monitor current voting results
                            </span>

                        </div>

                        <i class="bi bi-chevron-right quick-arrow"></i>

                    </a>


                    <a href="settings.php" class="quick-action">

                        <div class="quick-icon">
                            <i class="bi bi-gear-fill"></i>
                        </div>

                        <div class="quick-text">

                            <strong>
                                Voting Settings
                            </strong>

                            <span>
                                Configure the voting system
                            </span>

                        </div>

                        <i class="bi bi-chevron-right quick-arrow"></i>

                    </a>

                </div>

            </div>


            <!-- RECENT ACTIVITY -->

            <div class="col-12">

                <div class="section-card">

                    <div class="section-header">

                        <h3>
                            Recent Activity
                        </h3>

                        <a href="#">
                            View All
                        </a>

                    </div>


                    <?php foreach ($recentActivity as $activity): ?>

                        <div class="activity">

                            <div class="activity-icon">
                                <i class="bi bi-person-check-fill"></i>
                            </div>

                            <div class="activity-content">

                                <strong>
                                    <?= htmlspecialchars($activity['action']) ?>
                                </strong>

                                <span>
                                    <?= htmlspecialchars($activity['name']) ?>
                                    ·
                                    <?= htmlspecialchars($activity['category']) ?>
                                </span>

                                <small class="activity-time">
                                    <?= htmlspecialchars($activity['time']) ?>
                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>

    </div>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
</script>

</body>
</html>
