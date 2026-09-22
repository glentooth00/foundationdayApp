<?php

// ============================================================
// FOUNDATION DAY 2026 - CANDIDATE MANAGEMENT
// ============================================================

session_start();

require_once "includes/db.php";
require_once "includes/functions.php";


// ============================================================
// ADD / EDIT / DELETE
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"])
        ? trim($_POST["action"])
        : "";


    // ========================================================
    // ADD CANDIDATE
    // ========================================================

    if ($action === "add") {

        $name = isset($_POST["name"])
            ? trim($_POST["name"])
            : "";

        $category = isset($_POST["category"])
            ? trim($_POST["category"])
            : "";

        $gender = isset($_POST["gender"])
            ? trim($_POST["gender"])
            : "";

        $status = isset($_POST["status"])
            ? trim($_POST["status"])
            : "active";

        $error = "";

        if (!isValidCandidateName($name)) {

            setFlashMessage(
                "danger",
                "Please enter a valid candidate name."
            );

            redirect("candidates.php");
        }

        if (!isValidCategory($category)) {

            setFlashMessage(
                "danger",
                "Please select a valid category."
            );

            redirect("candidates.php");
        }

        if (!isValidGender($gender)) {

            setFlashMessage(
                "danger",
                "Please select a valid gender."
            );

            redirect("candidates.php");
        }

        if (!isValidStatus($status)) {

            setFlashMessage(
                "danger",
                "Please select a valid status."
            );

            redirect("candidates.php");
        }


        // ----------------------------------------------------
        // PHOTO
        // ----------------------------------------------------

        $photo = null;

        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $photo = uploadCandidatePhoto(
                $_FILES["photo"],
                $error
            );

            if ($photo === false) {

                setFlashMessage(
                    "danger",
                    $error
                );

                redirect("candidates.php");
            }
        }


        // ----------------------------------------------------
        // CREATE
        // ----------------------------------------------------

        if (
            createCandidate(
                $conn,
                $name,
                $category,
                $gender,
                $photo,
                $status
            )
        ) {

            setFlashMessage(
                "success",
                "Candidate added successfully."
            );

        } else {

            if ($photo) {
                deleteCandidatePhoto($photo);
            }

            setFlashMessage(
                "danger",
                "Failed to add candidate."
            );
        }

        redirect("candidates.php");
    }


    // ========================================================
    // EDIT CANDIDATE
    // ========================================================

    if ($action === "edit") {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        $name = isset($_POST["name"])
            ? trim($_POST["name"])
            : "";

        $category = isset($_POST["category"])
            ? trim($_POST["category"])
            : "";

        $gender = isset($_POST["gender"])
            ? trim($_POST["gender"])
            : "";

        $status = isset($_POST["status"])
            ? trim($_POST["status"])
            : "active";


        $candidate = getCandidate(
            $conn,
            $id
        );

        if (!$candidate) {

            setFlashMessage(
                "danger",
                "Candidate not found."
            );

            redirect("candidates.php");
        }


        if (!isValidCandidateName($name)) {

            setFlashMessage(
                "danger",
                "Please enter a valid candidate name."
            );

            redirect("candidates.php");
        }

        if (!isValidCategory($category)) {

            setFlashMessage(
                "danger",
                "Please select a valid category."
            );

            redirect("candidates.php");
        }

        if (!isValidGender($gender)) {

            setFlashMessage(
                "danger",
                "Please select a valid gender."
            );

            redirect("candidates.php");
        }

        if (!isValidStatus($status)) {

            setFlashMessage(
                "danger",
                "Please select a valid status."
            );

            redirect("candidates.php");
        }


        // ----------------------------------------------------
        // EXISTING PHOTO
        // ----------------------------------------------------

        $photo = $candidate["photo"];


        // ----------------------------------------------------
        // NEW PHOTO
        // ----------------------------------------------------

        if (
            isset($_FILES["photo"]) &&
            $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $error = "";

            $newPhoto = uploadCandidatePhoto(
                $_FILES["photo"],
                $error
            );

            if ($newPhoto === false) {

                setFlashMessage(
                    "danger",
                    $error
                );

                redirect("candidates.php");
            }

            $photo = $newPhoto;


            if (!empty($candidate["photo"])) {
                deleteCandidatePhoto(
                    $candidate["photo"]
                );
            }
        }


        // ----------------------------------------------------
        // UPDATE
        // ----------------------------------------------------

        if (
            updateCandidate(
                $conn,
                $id,
                $name,
                $category,
                $gender,
                $photo,
                $status
            )
        ) {

            setFlashMessage(
                "success",
                "Candidate updated successfully."
            );

        } else {

            setFlashMessage(
                "danger",
                "Failed to update candidate."
            );
        }

        redirect("candidates.php");
    }


    // ========================================================
    // DELETE CANDIDATE
    // ========================================================

    if ($action === "delete") {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;


        $candidate = getCandidate(
            $conn,
            $id
        );

        if (!$candidate) {

            setFlashMessage(
                "danger",
                "Candidate not found."
            );

            redirect("candidates.php");
        }


        if (deleteCandidate($conn, $id)) {

            if (!empty($candidate["photo"])) {

                deleteCandidatePhoto(
                    $candidate["photo"]
                );
            }

            setFlashMessage(
                "success",
                "Candidate deleted successfully."
            );

        } else {

            setFlashMessage(
                "danger",
                "Failed to delete candidate."
            );
        }

        redirect("candidates.php");
    }
}


// ============================================================
// FILTERS
// ============================================================

$categoryFilter = isset($_GET["category"])
    ? strtolower(trim($_GET["category"]))
    : "";

$genderFilter = isset($_GET["gender"])
    ? strtolower(trim($_GET["gender"]))
    : "";


if (
    $categoryFilter !== "" &&
    !isValidCategory($categoryFilter)
) {
    $categoryFilter = "";
}


if (
    $genderFilter !== "" &&
    !isValidGender($genderFilter)
) {
    $genderFilter = "";
}


// ============================================================
// CANDIDATES
// ============================================================

$candidates = getCandidates(
    $conn,
    $categoryFilter,
    $genderFilter
);


// ============================================================
// COUNTS
// ============================================================

$totalCandidates = countCandidates(
    $conn
);

$faceMale = countCandidates(
    $conn,
    "face_of_the_night",
    "male"
);

$faceFemale = countCandidates(
    $conn,
    "face_of_the_night",
    "female"
);

$starMale = countCandidates(
    $conn,
    "star_of_the_night",
    "male"
);

$starFemale = countCandidates(
    $conn,
    "star_of_the_night",
    "female"
);

$darlingMale = countCandidates(
    $conn,
    "darling_of_the_crowd",
    "male"
);

$darlingFemale = countCandidates(
    $conn,
    "darling_of_the_crowd",
    "female"
);


// ============================================================
// FLASH
// ============================================================

$flash = getFlashMessage();


// ============================================================
// CATEGORY LABELS
// ============================================================

$categoryLabels = [
    "face_of_the_night" => "Face of the Night",
    "star_of_the_night" => "Star of the Night",
    "darling_of_the_crowd" => "Darling of the Crowd"
];


// ============================================================
// PAGE FILTER LABEL
// ============================================================

$currentFilterLabel = "All Candidates";

if (
    $categoryFilter !== "" &&
    isset($categoryLabels[$categoryFilter])
) {

    $currentFilterLabel =
        $categoryLabels[$categoryFilter];

    if ($genderFilter !== "") {

        $currentFilterLabel .=
            " - " .
            genderLabel($genderFilter);
    }
}

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
    Candidates | Foundation Day Voting
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
       SECTION CARD
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

    .category-tabs {

        display: flex;

        gap: 6px;

        padding: 15px 20px;

        border-bottom:
            1px solid var(--border);

        overflow-x: auto;
    }


    .category-tab {

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


    .category-tab:hover {

        background: #f3f4f6;

        color: var(--text);
    }


    .category-tab.active {

        background: #eff6ff;

        color: var(--primary);
    }


    .category-count {

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


    .category-tab.active .category-count {

        background: #dbeafe;

        color: var(--primary);
    }


    /* =====================================================
       CANDIDATE LIST
    ===================================================== */

    .candidate-list {

        padding: 20px;
    }


    .candidate-card {

        height: 100%;

        background: #fff;

        border: 1px solid var(--border);

        border-radius: 12px;

        overflow: hidden;

        transition: .2s;
    }


    .candidate-card:hover {

        border-color: #cbd5e1;

        transform: translateY(-1px);
    }


    .candidate-photo {

        width: 100%;

        height: 230px;

        background: #eef2f7;

        overflow: hidden;

        border-bottom:
            1px solid var(--border);
    }


    .candidate-photo img {

        width: 100%;

        height: 100%;

        object-fit: cover;

        display: block;
    }


    .candidate-no-photo {

        width: 100%;

        height: 100%;

        display: flex;

        align-items: center;

        justify-content: center;

        color: #9ca3af;

        font-size: 55px;
    }


    .candidate-body {

        padding: 18px;
    }


    .candidate-name {

        font-size: 16px;

        font-weight: 700;

        margin-bottom: 8px;

        color: var(--text);
    }


    .candidate-meta {

        display: flex;

        flex-wrap: wrap;

        gap: 6px;

        margin-bottom: 15px;
    }


    .candidate-badge {

        display: inline-flex;

        align-items: center;

        gap: 5px;

        padding: 5px 8px;

        border-radius: 20px;

        font-size: 10px;

        font-weight: 700;
    }


    .category-badge {

        background: #eff6ff;

        color: var(--primary);
    }


    .gender-badge {

        background: #f3f4f6;

        color: #4b5563;
    }


    .active-badge {

        background: #dcfce7;

        color: #166534;
    }


    .inactive-badge {

        background: #fee2e2;

        color: #991b1b;
    }


    .candidate-actions {

        display: flex;

        gap: 7px;
    }


    .candidate-actions form {

        margin: 0;

        flex: 1;
    }


    .candidate-action {

        width: 100%;

        border: 0;

        border-radius: 7px;

        padding: 9px 12px;

        font-size: 12px;

        font-weight: 600;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 5px;
    }


    .edit-btn {

        background: #eff6ff;

        color: var(--primary);
    }


    .edit-btn:hover {

        background: #dbeafe;
    }


    .delete-btn {

        background: #fee2e2;

        color: #991b1b;
    }


    .delete-btn:hover {

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


        .candidate-photo {

            height: 210px;
        }
    }


    @media (max-width: 480px) {

        .candidate-photo {

            height: 200px;
        }


        .candidate-actions {

            flex-direction: column;
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


    <a
        href="candidates.php"
        class="active"
    >

        <i class="bi bi-person-badge-fill"></i>

        <span>
            Candidates
        </span>

    </a>


    <a href="registrations.php">

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
            Candidates
        </h1>

        <p>
            Manage Foundation Day voting candidates
        </p>

    </div>


    <div class="topbar-actions">

        <button
            class="notification"
            type="button"
            title="Notifications"
        >

            <i class="bi bi-bell"></i>

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
            Candidate Management
        </h2>

        <p>
            Manage candidates, categories, gender assignments, photos, and voting availability.
        </p>

    </div>


    <?php if ($flash): ?>

        <div
            class="alert alert-<?php echo e($flash["type"]); ?> alert-dismissible fade show mb-4"
            role="alert"
        >

            <?php echo e($flash["message"]); ?>


            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- ====================================================
         STATISTICS
    ===================================================== -->

    <div class="row g-3 mb-4">


        <!-- TOTAL -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-people-fill"></i>

                    </div>

                </div>


                <div class="stat-label">
                    Total Candidates
                </div>


                <div class="stat-value">

                    <?php
                    echo number_format(
                        $totalCandidates
                    );
                    ?>

                </div>


                <div class="stat-change text-primary">

                    <i class="bi bi-person-badge"></i>

                    All registered candidates

                </div>

            </div>

        </div>


        <!-- FACE -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-stars"></i>

                    </div>

                </div>


                <div class="stat-label">
                    Face of the Night
                </div>


                <div class="stat-value">

                    <?php
                    echo number_format(
                        $faceMale + $faceFemale
                    );
                    ?>

                </div>


                <div class="stat-change text-primary">

                    <i class="bi bi-gender-ambiguous"></i>

                    <?php echo $faceMale; ?> Male /
                    <?php echo $faceFemale; ?> Female

                </div>

            </div>

        </div>


        <!-- STAR -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-award-fill"></i>

                    </div>

                </div>


                <div class="stat-label">
                    Star of the Night
                </div>


                <div class="stat-value">

                    <?php
                    echo number_format(
                        $starMale + $starFemale
                    );
                    ?>

                </div>


                <div class="stat-change text-primary">

                    <i class="bi bi-gender-ambiguous"></i>

                    <?php echo $starMale; ?> Male /
                    <?php echo $starFemale; ?> Female

                </div>

            </div>

        </div>


        <!-- DARLING -->

        <div class="col-12 col-sm-6 col-xl-3">

            <div class="stat-card">

                <div class="stat-top">

                    <div class="stat-icon">

                        <i class="bi bi-heart-fill"></i>

                    </div>

                </div>


                <div class="stat-label">
                    Darling of the Crowd
                </div>


                <div class="stat-value">

                    <?php
                    echo number_format(
                        $darlingMale + $darlingFemale
                    );
                    ?>

                </div>


                <div class="stat-change text-primary">

                    <i class="bi bi-gender-ambiguous"></i>

                    <?php echo $darlingMale; ?> Male /
                    <?php echo $darlingFemale; ?> Female

                </div>

            </div>

        </div>


    </div>


    <!-- ====================================================
         CANDIDATE SECTION
    ===================================================== -->

    <div class="section-card">


        <!-- SECTION HEADER -->

        <div class="section-header">

            <h3>
                Candidate List
            </h3>


            <button
                type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#addCandidateModal"
            >

                <i class="bi bi-plus-lg me-1"></i>

                Add Candidate

            </button>

        </div>


        <!-- =================================================
             FILTER TABS
        ================================================== -->

        <div class="category-tabs">


            <!-- ALL -->

            <a
                href="candidates.php"
                class="category-tab <?php echo ($categoryFilter === "" && $genderFilter === "") ? "active" : ""; ?>"
            >

                <i class="bi bi-grid-fill"></i>

                All

                <span class="category-count">
                    <?php echo $totalCandidates; ?>
                </span>

            </a>


            <!-- FACE MALE -->

            <a
                href="candidates.php?category=face_of_the_night&gender=male"
                class="category-tab <?php echo ($categoryFilter === "face_of_the_night" && $genderFilter === "male") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Face Male

                <span class="category-count">
                    <?php echo $faceMale; ?>
                </span>

            </a>


            <!-- FACE FEMALE -->

            <a
                href="candidates.php?category=face_of_the_night&gender=female"
                class="category-tab <?php echo ($categoryFilter === "face_of_the_night" && $genderFilter === "female") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Face Female

                <span class="category-count">
                    <?php echo $faceFemale; ?>
                </span>

            </a>


            <!-- STAR MALE -->

            <a
                href="candidates.php?category=star_of_the_night&gender=male"
                class="category-tab <?php echo ($categoryFilter === "star_of_the_night" && $genderFilter === "male") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Star Male

                <span class="category-count">
                    <?php echo $starMale; ?>
                </span>

            </a>


            <!-- STAR FEMALE -->

            <a
                href="candidates.php?category=star_of_the_night&gender=female"
                class="category-tab <?php echo ($categoryFilter === "star_of_the_night" && $genderFilter === "female") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Star Female

                <span class="category-count">
                    <?php echo $starFemale; ?>
                </span>

            </a>


            <!-- DARLING MALE -->

            <a
                href="candidates.php?category=darling_of_the_crowd&gender=male"
                class="category-tab <?php echo ($categoryFilter === "darling_of_the_crowd" && $genderFilter === "male") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Darling Male

                <span class="category-count">
                    <?php echo $darlingMale; ?>
                </span>

            </a>


            <!-- DARLING FEMALE -->

            <a
                href="candidates.php?category=darling_of_the_crowd&gender=female"
                class="category-tab <?php echo ($categoryFilter === "darling_of_the_crowd" && $genderFilter === "female") ? "active" : ""; ?>"
            >

                <i class="bi bi-person-fill"></i>

                Darling Female

                <span class="category-count">
                    <?php echo $darlingFemale; ?>
                </span>

            </a>


        </div>


        <!-- =================================================
             CANDIDATES
        ================================================== -->

        <?php if (empty($candidates)): ?>


            <div class="empty-state">


                <div class="empty-icon">

                    <i class="bi bi-person-x"></i>

                </div>


                <h4>
                    No candidates found
                </h4>


                <p>
                    There are currently no candidates in this section.
                </p>


            </div>


        <?php else: ?>


            <div class="candidate-list">


                <div class="row g-3">


                    <?php foreach ($candidates as $candidate): ?>


                        <div class="col-12 col-md-6 col-xl-4">


                            <div class="candidate-card">


                                <!-- PHOTO -->

                                <div class="candidate-photo">


                                    <?php if (!empty($candidate["photo"])): ?>

                                        <img
                                            src="../uploads/candidates/<?php echo e(basename($candidate["photo"])); ?>"
                                            alt="<?php echo e($candidate["name"]); ?>"
                                        >

                                    <?php else: ?>

                                        <div class="candidate-no-photo">

                                            <i class="bi bi-person-circle"></i>

                                        </div>

                                    <?php endif; ?>


                                </div>


                                <!-- BODY -->

                                <div class="candidate-body">


                                    <!-- NAME -->

                                    <div class="candidate-name">

                                        <?php
                                        echo e(
                                            $candidate["name"]
                                        );
                                        ?>

                                    </div>


                                    <!-- META -->

                                    <div class="candidate-meta">


                                        <!-- CATEGORY -->

                                        <span class="candidate-badge category-badge">

                                            <i class="bi bi-award-fill"></i>

                                            <?php
                                            echo e(
                                                categoryLabel(
                                                    $candidate["category"]
                                                )
                                            );
                                            ?>

                                        </span>


                                        <!-- GENDER -->

                                        <span class="candidate-badge gender-badge">

                                            <i class="bi bi-person-fill"></i>

                                            <?php
                                            echo e(
                                                genderLabel(
                                                    $candidate["gender"]
                                                )
                                            );
                                            ?>

                                        </span>


                                        <!-- STATUS -->

                                        <?php if ($candidate["status"] === "active"): ?>

                                            <span class="candidate-badge active-badge">

                                                <i class="bi bi-check-circle-fill"></i>

                                                Active

                                            </span>

                                        <?php else: ?>

                                            <span class="candidate-badge inactive-badge">

                                                <i class="bi bi-x-circle-fill"></i>

                                                Inactive

                                            </span>

                                        <?php endif; ?>


                                    </div>


                                    <!-- ACTIONS -->

                                    <div class="candidate-actions">


                                        <!-- EDIT -->

                                        <button
                                            type="button"
                                            class="candidate-action edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editCandidateModal"
                                            data-id="<?php echo (int) $candidate["id"]; ?>"
                                            data-name="<?php echo e($candidate["name"]); ?>"
                                            data-category="<?php echo e($candidate["category"]); ?>"
                                            data-gender="<?php echo e($candidate["gender"]); ?>"
                                            data-status="<?php echo e($candidate["status"]); ?>"
                                        >

                                            <i class="bi bi-pencil"></i>

                                            Edit

                                        </button>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this candidate?');"
                                        >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >


                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?php echo (int) $candidate["id"]; ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="candidate-action delete-btn"
                                            >

                                                <i class="bi bi-trash"></i>

                                                Delete

                                            </button>

                                        </form>


                                    </div>


                                </div>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            </div>


        <?php endif; ?>


    </div>


</div>


</main>

<!-- ============================================================
     ADD CANDIDATE MODAL
============================================================ -->

<div
    class="modal fade"
    id="addCandidateModal"
    tabindex="-1"
    aria-hidden="true"
>


<div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="modal-header">

                <h5 class="modal-title">
                    Add Candidate
                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <!-- NAME -->

                <div class="mb-3">

                    <label class="form-label">
                        Candidate Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        class="form-control"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- GENDER -->

                <div class="mb-3">

                    <label class="form-label">
                        Gender
                    </label>

                    <select
                        name="gender"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select Gender
                        </option>

                        <option value="male">
                            Male
                        </option>

                        <option value="female">
                            Female
                        </option>

                    </select>

                </div>


                <!-- CATEGORY -->

                <div class="mb-3">

                    <label class="form-label">
                        Category
                    </label>

                    <select
                        name="category"
                        class="form-select"
                        required
                    >

                        <option value="">
                            Select Category
                        </option>

                        <option value="face_of_the_night">
                            Face of the Night
                        </option>

                        <option value="star_of_the_night">
                            Star of the Night
                        </option>

                        <option value="darling_of_the_crowd">
                            Darling of the Crowd
                        </option>

                    </select>

                </div>


                <!-- STATUS -->

                <div class="mb-3">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        class="form-select"
                        required
                    >

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- PHOTO -->

                <div class="mb-3">

                    <label class="form-label">
                        Candidate Photo
                    </label>

                    <input
                        type="file"
                        name="photo"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="form-text">

                        JPG, PNG, or WEBP.
                        Maximum 5MB.

                    </div>

                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-plus-lg me-1"></i>

                    Add Candidate

                </button>


            </div>


        </form>


    </div>

</div>


</div>

<!-- ============================================================
     EDIT CANDIDATE MODAL
============================================================ -->

<div
    class="modal fade"
    id="editCandidateModal"
    tabindex="-1"
    aria-hidden="true"
>


<div class="modal-dialog modal-dialog-centered">

    <div class="modal-content">


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <input
                type="hidden"
                name="action"
                value="edit"
            >


            <input
                type="hidden"
                name="id"
                id="editCandidateId"
            >


            <div class="modal-header">

                <h5 class="modal-title">
                    Edit Candidate
                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <div class="modal-body">


                <!-- NAME -->

                <div class="mb-3">

                    <label class="form-label">
                        Candidate Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="editCandidateName"
                        class="form-control"
                        maxlength="150"
                        required
                    >

                </div>


                <!-- GENDER -->

                <div class="mb-3">

                    <label class="form-label">
                        Gender
                    </label>

                    <select
                        name="gender"
                        id="editCandidateGender"
                        class="form-select"
                        required
                    >

                        <option value="male">
                            Male
                        </option>

                        <option value="female">
                            Female
                        </option>

                    </select>

                </div>


                <!-- CATEGORY -->

                <div class="mb-3">

                    <label class="form-label">
                        Category
                    </label>

                    <select
                        name="category"
                        id="editCandidateCategory"
                        class="form-select"
                        required
                    >

                        <option value="face_of_the_night">
                            Face of the Night
                        </option>

                        <option value="star_of_the_night">
                            Star of the Night
                        </option>

                        <option value="darling_of_the_crowd">
                            Darling of the Crowd
                        </option>

                    </select>

                </div>


                <!-- STATUS -->

                <div class="mb-3">

                    <label class="form-label">
                        Status
                    </label>

                    <select
                        name="status"
                        id="editCandidateStatus"
                        class="form-select"
                        required
                    >

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- PHOTO -->

                <div class="mb-3">

                    <label class="form-label">
                        Replace Candidate Photo
                    </label>

                    <input
                        type="file"
                        name="photo"
                        class="form-control"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <div class="form-text">

                        Leave empty to keep the current photo.

                    </div>

                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="bi bi-check-lg me-1"></i>

                    Save Changes

                </button>


            </div>


        </form>


    </div>

</div>


</div>

<!-- ============================================================
     BOOTSTRAP JS
============================================================ -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

<!-- ============================================================
     EDIT MODAL DATA
============================================================ -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const editModal =
            document.getElementById(
                "editCandidateModal"
            );

        if (!editModal) {
            return;
        }


        editModal.addEventListener(
            "show.bs.modal",
            function (event) {

                const button =
                    event.relatedTarget;

                if (!button) {
                    return;
                }


                document.getElementById(
                    "editCandidateId"
                ).value =
                    button.getAttribute(
                        "data-id"
                    );


                document.getElementById(
                    "editCandidateName"
                ).value =
                    button.getAttribute(
                        "data-name"
                    );


                document.getElementById(
                    "editCandidateCategory"
                ).value =
                    button.getAttribute(
                        "data-category"
                    );


                document.getElementById(
                    "editCandidateGender"
                ).value =
                    button.getAttribute(
                        "data-gender"
                    );


                document.getElementById(
                    "editCandidateStatus"
                ).value =
                    button.getAttribute(
                        "data-status"
                    );

            }
        );

    }
);

</script>

</body>

</html>
