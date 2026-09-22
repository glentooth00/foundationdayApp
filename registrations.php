<?php

// ============================================================
// FOUNDATION DAY 2026 - EMPLOYEE REGISTRATIONS
// ============================================================

session_start();

require_once "includes/db.php";
require_once "includes/functions.php";


// ============================================================
// STATUS FILTER
// ============================================================

$status = isset($_GET["status"])
    ? strtolower(trim($_GET["status"]))
    : "pending";

$allowedStatuses = [
    "pending",
    "approved",
    "rejected"
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = "pending";
}


// ============================================================
// APPROVE / REJECT
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"])
        ? trim($_POST["action"])
        : "";

    $employeeId = isset($_POST["employee_id"])
        ? (int) $_POST["employee_id"]
        : 0;

    if ($employeeId <= 0) {

        setFlashMessage(
            "danger",
            "Invalid employee registration."
        );

        redirect("registrations.php");
    }


    // --------------------------------------------------------
    // APPROVE
    // --------------------------------------------------------

    if ($action === "approve") {

        $stmt = $conn->prepare("
            UPDATE employees
            SET registration_status = 'approved'
            WHERE id = ?
            AND registration_status = 'pending'
        ");

        if ($stmt) {

            $stmt->bind_param("i", $employeeId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {

                setFlashMessage(
                    "success",
                    "Employee registration approved successfully."
                );

            } else {

                setFlashMessage(
                    "warning",
                    "This registration has already been processed."
                );
            }

            $stmt->close();

        } else {

            setFlashMessage(
                "danger",
                "Unable to process the approval request."
            );
        }

        redirect("registrations.php");
    }


    // --------------------------------------------------------
    // REJECT
    // --------------------------------------------------------

    if ($action === "reject") {

        $stmt = $conn->prepare("
            UPDATE employees
            SET registration_status = 'rejected'
            WHERE id = ?
            AND registration_status = 'pending'
        ");

        if ($stmt) {

            $stmt->bind_param("i", $employeeId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {

                setFlashMessage(
                    "success",
                    "Employee registration rejected."
                );

            } else {

                setFlashMessage(
                    "warning",
                    "This registration has already been processed."
                );
            }

            $stmt->close();

        } else {

            setFlashMessage(
                "danger",
                "Unable to process the rejection request."
            );
        }

        redirect("registrations.php");
    }
}


// ============================================================
// REGISTRATION COUNTS
// ============================================================

$countPending = 0;
$countApproved = 0;
$countRejected = 0;

$result = $conn->query("
    SELECT
        registration_status,
        COUNT(*) AS total
    FROM employees
    GROUP BY registration_status
");

if ($result) {

    while ($row = $result->fetch_assoc()) {

        if ($row["registration_status"] === "pending") {
            $countPending = (int) $row["total"];
        }

        if ($row["registration_status"] === "approved") {
            $countApproved = (int) $row["total"];
        }

        if ($row["registration_status"] === "rejected") {
            $countRejected = (int) $row["total"];
        }
    }

    $result->free();
}


// ============================================================
// REGISTRATIONS
// ============================================================

$registrations = [];

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        selfie,
        department,
        registration_status,
        voting_status,
        created_at
    FROM employees
    WHERE registration_status = ?
    ORDER BY created_at DESC
");

if ($stmt) {

    $stmt->bind_param("s", $status);

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
    }

    $stmt->close();
}


// ============================================================
// FLASH MESSAGE
// ============================================================

$flash = getFlashMessage();


// ============================================================
// STATUS INFORMATION
// ============================================================

$statusLabels = [
    "pending" => "Pending",
    "approved" => "Approved",
    "rejected" => "Rejected"
];

$statusIcons = [
    "pending" => "bi-hourglass-split",
    "approved" => "bi-check-circle-fill",
    "rejected" => "bi-x-circle-fill"
];

?>

<!DOCTYPE html>

<html lang="en">

<head>


<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Employee Registrations | Foundation Day Voting
</title>


<!-- Bootstrap -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- Bootstrap Icons -->

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

        border-bottom:
            1px solid rgba(255,255,255,.08);

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

        background:
            rgba(255,255,255,.06);

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

        border-top:
            1px solid rgba(255,255,255,.08);

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

        border-bottom:
            1px solid var(--border);

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

    }


    /* =====================================================
       REGISTRATION SECTION
    ===================================================== */

    .section-card {

        background: #fff;

        border: 1px solid var(--border);

        border-radius: 12px;

        overflow: hidden;

    }


    .section-header {

        padding: 18px 20px;

        border-bottom:
            1px solid var(--border);

        display: flex;

        justify-content: space-between;

        align-items: center;

    }


    .section-header h3 {

        font-size: 15px;

        font-weight: 700;

        margin: 0;

    }


    /* =====================================================
       FILTER TABS
    ===================================================== */

    .status-tabs {

        display: flex;

        gap: 6px;

        padding: 15px 20px;

        border-bottom:
            1px solid var(--border);

        overflow-x: auto;

    }


    .status-tab {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        white-space: nowrap;

        padding: 8px 12px;

        border-radius: 7px;

        color: var(--muted);

        text-decoration: none;

        font-size: 12px;

        font-weight: 600;

        transition: .2s;

    }


    .status-tab:hover {

        background: #f3f4f6;

        color: var(--text);

    }


    .status-tab.active {

        background: #eff6ff;

        color: var(--primary);

    }


    .status-count {

        min-width: 20px;

        height: 19px;

        padding: 0 5px;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        border-radius: 20px;

        background: #e5e7eb;

        font-size: 10px;

    }


    .status-tab.active .status-count {

        background: #dbeafe;

        color: var(--primary);

    }


    /* =====================================================
       REGISTRATION LIST
    ===================================================== */

    .registration-list {

        display: flex;

        flex-direction: column;

    }


    .registration-item {

        display: flex;

        align-items: center;

        gap: 18px;

        padding: 18px 20px;

        border-bottom:
            1px solid var(--border);

    }


    .registration-item:last-child {

        border-bottom: 0;

    }


    /* =====================================================
       SELFIE
    ===================================================== */

    .employee-photo {

        width: 90px;

        height: 90px;

        flex-shrink: 0;

        border-radius: 10px;

        overflow: hidden;

        background: #eef2f7;

        border: 1px solid var(--border);

    }


    .employee-photo img {

        width: 100%;

        height: 100%;

        object-fit: cover;

        display: block;

    }


    .no-photo {

        width: 100%;

        height: 100%;

        display: flex;

        align-items: center;

        justify-content: center;

        color: #9ca3af;

        font-size: 30px;

    }


    /* =====================================================
       EMPLOYEE INFORMATION
    ===================================================== */

    .employee-info {

        min-width: 0;

        flex: 1;

    }


    .employee-name {

        font-size: 15px;

        font-weight: 700;

        margin-bottom: 4px;

    }


    .employee-department {

        color: var(--muted);

        font-size: 12px;

        margin-bottom: 7px;

    }


    .employee-date {

        color: #9ca3af;

        font-size: 11px;

    }


    /* =====================================================
       STATUS
    ===================================================== */

    .registration-status {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 5px 8px;

        border-radius: 20px;

        font-size: 10px;

        font-weight: 700;

        margin-top: 7px;

    }


    .registration-status.pending {

        background: #fef3c7;

        color: #92400e;

    }


    .registration-status.approved {

        background: #dcfce7;

        color: #166534;

    }


    .registration-status.rejected {

        background: #fee2e2;

        color: #991b1b;

    }


    /* =====================================================
       ACTIONS
    ===================================================== */

    .registration-actions {

        display: flex;

        gap: 7px;

        flex-shrink: 0;

    }


    .registration-actions form {

        margin: 0;

    }


    .action-btn {

        border: 0;

        border-radius: 7px;

        padding: 9px 13px;

        font-size: 12px;

        font-weight: 600;

        display: inline-flex;

        align-items: center;

        gap: 5px;

    }


    .approve-btn {

        background: #dcfce7;

        color: #166534;

    }


    .approve-btn:hover {

        background: #bbf7d0;

    }


    .reject-btn {

        background: #fee2e2;

        color: #991b1b;

    }


    .reject-btn:hover {

        background: #fecaca;

    }


    /* =====================================================
       EMPTY STATE
    ===================================================== */

    .empty-state {

        text-align: center;

        padding: 65px 20px;

    }


    .empty-icon {

        width: 50px;

        height: 50px;

        margin: 0 auto 13px;

        border-radius: 10px;

        background: #eff6ff;

        color: var(--primary);

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 21px;

    }


    .empty-state h4 {

        margin: 0 0 5px;

        font-size: 15px;

        font-weight: 700;

    }


    .empty-state p {

        margin: 0;

        color: var(--muted);

        font-size: 12px;

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


        .registration-item {

            align-items: flex-start;

            flex-wrap: wrap;

        }


        .employee-photo {

            width: 75px;

            height: 75px;

        }


        .employee-info {

            width: calc(100% - 95px);

            flex: none;

        }


        .registration-actions {

            width: 100%;

            margin-left: 93px;

        }


        .registration-actions .action-btn {

            flex: 1;

            justify-content: center;

        }

    }


    @media (max-width: 480px) {

        .registration-item {

            gap: 12px;

        }


        .employee-photo {

            width: 65px;

            height: 65px;

        }


        .employee-info {

            width: calc(100% - 77px);

        }


        .registration-actions {

            margin-left: 0;

        }

    }

</style>


</head>

<body>

<!-- ============================================================
     SIDEBAR
============================================================ -->

<aside class="sidebar">


<!-- BRAND -->

<div class="brand">

    <div class="brand-icon">

        <i class="bi bi-ballot-fill"></i>

    </div>


    <div class="brand-text">

        <strong>
            Foundation Day
        </strong>

        <span>
            Voting System
        </span>

    </div>

</div>


<!-- NAVIGATION -->

<div class="nav-section">


    <div class="nav-label">

        Administration

    </div>


    <a href="dashboard.php">

        <i class="bi bi-grid-1x2-fill"></i>

        <span>
            Dashboard
        </span>

    </a>


    <a href="candidates.php">

        <i class="bi bi-person-badge-fill"></i>

        <span>
            Candidates
        </span>

    </a>


    <a
        href="registrations.php"
        class="active"
    >

        <i class="bi bi-people-fill"></i>

        <span>
            Employees
        </span>

    </a>


    <a href="results.php">

        <i class="bi bi-bar-chart-fill"></i>

        <span>
            Results
        </span>

    </a>


    <div class="nav-label mt-4">

        System

    </div>


    <a href="settings.php">

        <i class="bi bi-gear-fill"></i>

        <span>
            Settings
        </span>

    </a>


</div>


<!-- SIDEBAR FOOTER -->

<div class="sidebar-footer">

    <div class="admin-user">


        <div class="admin-avatar">

            <i class="bi bi-person-fill"></i>

        </div>


        <div class="admin-info">

            <strong>
                Administrator
            </strong>

            <span>
                System Admin
            </span>

        </div>


    </div>

</div>


</aside>

<!-- ============================================================
     MAIN
============================================================ -->

<main class="main">


<!-- ========================================================
     TOPBAR
======================================================== -->

<header class="topbar">


    <div class="page-title">

        <h1>
            Employee Registrations
        </h1>

        <p>
            Review and approve Foundation Day voters
        </p>

    </div>


    <div class="topbar-actions">

        <button
            class="notification"
            type="button"
            title="Notifications"
        >

            <i class="bi bi-bell"></i>

            <?php if ($countPending > 0): ?>

                <span class="dot"></span>

            <?php endif; ?>

        </button>

    </div>


</header>


<!-- ========================================================
     CONTENT
======================================================== -->

<div class="content">


    <!-- PAGE INTRO -->

    <div class="welcome">

        <h2>
            Employee Registrations
        </h2>

        <p>
            Review employee information and selfie submissions before allowing them to vote.
        </p>

    </div>


    <!-- ====================================================
         STATISTICS
    ===================================================== -->

    <div class="row g-3 mb-4">


        <!-- PENDING -->

        <div class="col-12 col-sm-6 col-xl-4">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-hourglass-split"></i>

                    </div>

                </div>


                <div class="stat-label">

                    Pending Registrations

                </div>


                <div class="stat-value">

                    <?= number_format($countPending) ?>

                </div>


                <div class="stat-change text-warning">

                    <i class="bi bi-clock"></i>

                    Waiting for review

                </div>

            </div>

        </div>


        <!-- APPROVED -->

        <div class="col-12 col-sm-6 col-xl-4">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-person-check-fill"></i>

                    </div>

                </div>


                <div class="stat-label">

                    Approved Employees

                </div>


                <div class="stat-value">

                    <?= number_format($countApproved) ?>

                </div>


                <div class="stat-change text-success">

                    <i class="bi bi-check-circle"></i>

                    Allowed to vote

                </div>

            </div>

        </div>


        <!-- REJECTED -->

        <div class="col-12 col-sm-6 col-xl-4">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-person-x-fill"></i>

                    </div>

                </div>


                <div class="stat-label">

                    Rejected Registrations

                </div>


                <div class="stat-value">

                    <?= number_format($countRejected) ?>

                </div>


                <div class="stat-change text-danger">

                    <i class="bi bi-x-circle"></i>

                    Not allowed to vote

                </div>

            </div>

        </div>


    </div>


    <!-- ====================================================
         REGISTRATION LIST
    ===================================================== -->

    <div class="section-card">


        <!-- HEADER -->

        <div class="section-header">

            <h3>

                Registration Requests

            </h3>

        </div>


        <!-- STATUS TABS -->

        <div class="status-tabs">


            <?php foreach ($statusLabels as $statusKey => $statusLabel): ?>


                <?php

                $statusCount = 0;

                if ($statusKey === "pending") {
                    $statusCount = $countPending;
                }

                if ($statusKey === "approved") {
                    $statusCount = $countApproved;
                }

                if ($statusKey === "rejected") {
                    $statusCount = $countRejected;
                }

                ?>


                <a
                    href="registrations.php?status=<?= e($statusKey) ?>"
                    class="status-tab <?= $status === $statusKey ? "active" : "" ?>"
                >

                    <i
                        class="bi <?= e($statusIcons[$statusKey]) ?>"
                    ></i>


                    <?= e($statusLabel) ?>


                    <span class="status-count">

                        <?= $statusCount ?>

                    </span>

                </a>


            <?php endforeach; ?>


        </div>


        <!-- =================================================
             REGISTRATION ITEMS
        ================================================== -->

        <?php if (empty($registrations)): ?>


            <div class="empty-state">


                <div class="empty-icon">

                    <i class="bi bi-person-check"></i>

                </div>


                <h4>

                    No <?= e(strtolower($statusLabels[$status])) ?> registrations

                </h4>


                <p>

                    There are currently no employee registrations in this section.

                </p>


            </div>


        <?php else: ?>


            <div class="registration-list">


                <?php foreach ($registrations as $employee): ?>


                    <div class="registration-item">


                        <!-- SELFIE -->

                        <div class="employee-photo">


                            <?php if (!empty($employee["selfie"])): ?>

                                <img
                                    src="../<?= e($employee["selfie"]) ?>"
                                    alt="<?= e($employee["full_name"]) ?>"
                                >

                            <?php else: ?>

                                <div class="no-photo">

                                    <i class="bi bi-person-circle"></i>

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- INFORMATION -->

                        <div class="employee-info">


                            <div class="employee-name">

                                <?= e($employee["full_name"]) ?>

                            </div>


                            <div class="employee-department">

                                <i class="bi bi-building"></i>

                                <?php if (!empty($employee["department"])): ?>

                                    <?= e($employee["department"]) ?>

                                <?php else: ?>

                                    No department provided

                                <?php endif; ?>

                            </div>


                            <div class="employee-date">

                                <i class="bi bi-calendar3"></i>

                                Registered

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime($employee["created_at"])
                                ) ?>

                            </div>


                            <div
                                class="registration-status <?= e($employee["registration_status"]) ?>"
                            >

                                <i
                                    class="bi <?= e($statusIcons[$employee["registration_status"]]) ?>"
                                ></i>

                                <?= e(
                                    $statusLabels[
                                        $employee["registration_status"]
                                    ]
                                ) ?>

                            </div>


                        </div>


                        <!-- ACTIONS -->

                        <?php if ($employee["registration_status"] === "pending"): ?>


                            <div class="registration-actions">


                                <!-- APPROVE -->

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Approve this employee registration?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="approve"
                                    >


                                    <input
                                        type="hidden"
                                        name="employee_id"
                                        value="<?= (int) $employee["id"] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="action-btn approve-btn"
                                    >

                                        <i class="bi bi-check-lg"></i>

                                        Approve

                                    </button>

                                </form>


                                <!-- REJECT -->

                                <form
                                    method="POST"
                                    onsubmit="return confirm(
                                        'Reject this employee registration?'
                                    );"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="reject"
                                    >


                                    <input
                                        type="hidden"
                                        name="employee_id"
                                        value="<?= (int) $employee["id"] ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="action-btn reject-btn"
                                    >

                                        <i class="bi bi-x-lg"></i>

                                        Reject

                                    </button>

                                </form>


                            </div>


                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </div>


</div>


</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
