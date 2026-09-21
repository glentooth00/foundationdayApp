
<?php

session_start();

require_once "includes/db.php";
require_once "includes/functions.php";


/*
|--------------------------------------------------------------------------
| Require Registration
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["employee_id"])) {
    header("Location: index.php");
    exit;
}


$employeeId = (int) $_SESSION["employee_id"];

$employeeName = $_SESSION["employee_name"] ?? "";
$employeeDepartment = $_SESSION["employee_department"] ?? "";


/*
|--------------------------------------------------------------------------
| Get Employee
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT
        id,
        full_name,
        department,
        voting_status
     FROM employees
     WHERE id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $employeeId);
$stmt->execute();

$result = $stmt->get_result();

$employee = $result->fetch_assoc();

$stmt->close();


if (!$employee) {

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit;
}


$employeeName = $employee["full_name"];
$employeeDepartment = $employee["department"];

$alreadyVoted =
    ($employee["voting_status"] === "voted");


/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = [

    "face_of_the_night" => [
        "title" => "Face of the Night",
        "description" => "Choose one male and one female."
    ],

    "star_of_the_night" => [
        "title" => "Star of the Night",
        "description" => "Choose one male and one female."
    ],

    "darling_of_the_crowd" => [
        "title" => "Darling of the Crowd",
        "description" => "Choose one male and one female."
    ]

];


$genders = [

    "male" => "Male",

    "female" => "Female"

];


/*
|--------------------------------------------------------------------------
| Candidate Array
|--------------------------------------------------------------------------
*/

$candidates = [

    "face_of_the_night" => [
        "male" => [],
        "female" => []
    ],

    "star_of_the_night" => [
        "male" => [],
        "female" => []
    ],

    "darling_of_the_crowd" => [
        "male" => [],
        "female" => []
    ]

];


/*
|--------------------------------------------------------------------------
| Load Active Candidates
|--------------------------------------------------------------------------
*/

if (!$alreadyVoted) {

    $stmt = $conn->prepare(
        "SELECT
            id,
            name,
            category,
            gender,
            photo
         FROM candidates
         WHERE status = 'active'
         ORDER BY name ASC"
    );

    $stmt->execute();

    $result = $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $category = $row["category"];
        $gender = $row["gender"];


        if (
            isset($candidates[$category]) &&
            isset($candidates[$category][$gender])
        ) {

            $candidates[$category][$gender][] = $row;

        }
    }


    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Errors
|--------------------------------------------------------------------------
*/

$errors = [];


/*
|--------------------------------------------------------------------------
| Process Vote
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !$alreadyVoted
) {

    $selectedVotes = $_POST["votes"] ?? [];


    /*
    |--------------------------------------------------------------------------
    | Validate All Six Selections
    |--------------------------------------------------------------------------
    */

    foreach ($categories as $category => $categoryData) {

        foreach ($genders as $gender => $genderLabel) {

            if (
                !isset($selectedVotes[$category]) ||
                !isset($selectedVotes[$category][$gender]) ||
                !is_numeric($selectedVotes[$category][$gender])
            ) {

                $errors[] =
                    $categoryData["title"] .
                    " - " .
                    $genderLabel .
                    " selection is required.";

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Save Votes
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            /*
            --------------------------------------------------------------
            Start transaction
            --------------------------------------------------------------
            */

            $conn->begin_transaction();


            /*
            --------------------------------------------------------------
            Lock employee row
            --------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                "SELECT voting_status
                 FROM employees
                 WHERE id = ?
                 FOR UPDATE"
            );

            $stmt->bind_param(
                "i",
                $employeeId
            );

            $stmt->execute();

            $statusResult = $stmt->get_result();

            $statusRow = $statusResult->fetch_assoc();

            $stmt->close();


            if (
                !$statusRow ||
                $statusRow["voting_status"] === "voted"
            ) {

                throw new Exception(
                    "Your vote has already been submitted."
                );

            }


            /*
            --------------------------------------------------------------
            Validate candidates
            --------------------------------------------------------------
            */

            $candidateStmt = $conn->prepare(
                "SELECT id
                 FROM candidates
                 WHERE id = ?
                   AND category = ?
                   AND gender = ?
                   AND status = 'active'
                 LIMIT 1"
            );


            /*
            --------------------------------------------------------------
            Insert votes
            --------------------------------------------------------------
            */

            $voteStmt = $conn->prepare(
                "INSERT INTO votes
                (
                    employee_id,
                    candidate_id,
                    category,
                    gender
                )
                VALUES (?, ?, ?, ?)"
            );


            foreach ($categories as $category => $categoryData) {

                foreach ($genders as $gender => $genderLabel) {

                    $candidateId =
                        (int) $selectedVotes[$category][$gender];


                    /*
                    ------------------------------------------------------
                    Check candidate
                    ------------------------------------------------------
                    */

                    $candidateStmt->bind_param(
                        "iss",
                        $candidateId,
                        $category,
                        $gender
                    );

                    $candidateStmt->execute();

                    $candidateResult =
                        $candidateStmt->get_result();

                    $candidate =
                        $candidateResult->fetch_assoc();


                    if (!$candidate) {

                        throw new Exception(
                            "One of your selected candidates is not available."
                        );

                    }


                    /*
                    ------------------------------------------------------
                    Save vote
                    ------------------------------------------------------
                    */

                    $voteStmt->bind_param(
                        "iiss",
                        $employeeId,
                        $candidateId,
                        $category,
                        $gender
                    );


                    if (!$voteStmt->execute()) {

                        throw new Exception(
                            "Unable to save your vote."
                        );

                    }

                }

            }


            $candidateStmt->close();

            $voteStmt->close();


            /*
            --------------------------------------------------------------
            Mark employee as voted
            --------------------------------------------------------------
            */

            $updateStmt = $conn->prepare(
                "UPDATE employees
                 SET voting_status = 'voted'
                 WHERE id = ?
                   AND voting_status = 'not_voted'"
            );

            $updateStmt->bind_param(
                "i",
                $employeeId
            );

            $updateStmt->execute();


            if ($updateStmt->affected_rows !== 1) {

                $updateStmt->close();

                throw new Exception(
                    "Unable to complete your voting session."
                );

            }


            $updateStmt->close();


            /*
            --------------------------------------------------------------
            Commit
            --------------------------------------------------------------
            */

            $conn->commit();


            /*
            --------------------------------------------------------------
            Redirect
            --------------------------------------------------------------
            */

            header(
                "Location: voting.php?success=1"
            );

            exit;


        } catch (Throwable $e) {

            /*
            --------------------------------------------------------------
            Rollback
            --------------------------------------------------------------
            */

            try {

                $conn->rollback();

            } catch (Throwable $rollbackError) {

                // Ignore rollback error.

            }


            $errors[] = $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

$showSuccess =
    isset($_GET["success"]) &&
    $_GET["success"] === "1";

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#0f172a"
    >

    <title>
        Foundation Day 2026 | Voting
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        html,
        body {
            width: 100%;
            min-height: 100%;
        }


        body {

            margin: 0;

            background: #f4f7fb;

            color: #172033;

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            -webkit-font-smoothing: antialiased;

        }


        /* =====================================================
           HEADER
        ====================================================== */

        .voting-header {

            background: #0f172a;

            color: #ffffff;

            padding: 16px 20px;

        }


        .header-inner {

            width: 100%;

            max-width: 1100px;

            margin: auto;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 15px;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 11px;

        }


        .brand-icon {

            width: 42px;

            height: 42px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 10px;

            background: #2563eb;

            font-size: 19px;

            flex-shrink: 0;

        }


        .brand-title {

            font-size: 16px;

            font-weight: 700;

        }


        .brand-subtitle {

            font-size: 11px;

            color: #94a3b8;

            margin-top: 2px;

        }


        .employee-info {

            text-align: right;

            min-width: 0;

        }


        .employee-name {

            font-size: 14px;

            font-weight: 600;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .employee-department {

            font-size: 11px;

            color: #94a3b8;

            margin-top: 2px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        /* =====================================================
           MAIN
        ====================================================== */

        .voting-wrapper {

            width: 100%;

            max-width: 1100px;

            margin: auto;

            padding: 30px 20px 110px;

        }


        .voting-intro {

            margin-bottom: 25px;

        }


        .voting-intro h1 {

            font-size: 30px;

            font-weight: 700;

            margin: 0 0 7px;

        }


        .voting-intro p {

            margin: 0;

            color: #64748b;

            line-height: 1.6;

        }


        .vote-counter {

            display: flex;

            align-items: center;

            gap: 9px;

            margin-top: 16px;

            padding: 12px 15px;

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            color: #475569;

            font-size: 14px;

        }


        .vote-counter i {

            color: #2563eb;

        }


        /* =====================================================
           ALERT
        ====================================================== */

        .voting-alert {

            border-radius: 10px;

        }


        /* =====================================================
           CATEGORY
        ====================================================== */

        .category-section {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 15px;

            overflow: hidden;

            margin-bottom: 22px;

        }


        .category-header {

            padding: 19px 20px;

            background: #f8fafc;

            border-bottom: 1px solid #e2e8f0;

        }


        .category-number {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 31px;

            height: 31px;

            margin-right: 8px;

            border-radius: 8px;

            background: #2563eb;

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            vertical-align: middle;

        }


        .category-title {

            display: inline;

            font-size: 20px;

            font-weight: 700;

            vertical-align: middle;

        }


        .category-description {

            margin-top: 8px;

            color: #64748b;

            font-size: 13px;

        }


        /* =====================================================
           GENDER
        ====================================================== */

        .gender-section {

            padding: 20px;

        }


        .gender-section + .gender-section {

            border-top: 1px solid #e2e8f0;

        }


        .gender-title {

            margin: 0 0 14px;

            font-size: 15px;

            font-weight: 700;

            color: #334155;

        }


        .gender-title i {

            margin-right: 5px;

            color: #64748b;

        }


        /* =====================================================
           CANDIDATES
        ====================================================== */

        .candidate-grid {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 14px;

        }


        .candidate-option {

            position: relative;

            display: block;

            cursor: pointer;

        }


        .candidate-option input {

            position: absolute;

            opacity: 0;

            pointer-events: none;

        }


        .candidate-card {

            height: 100%;

            overflow: hidden;

            border: 2px solid #e2e8f0;

            border-radius: 12px;

            background: #ffffff;

            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;

        }


        .candidate-option:hover
        .candidate-card {

            border-color: #93c5fd;

        }


        .candidate-option
        input:checked
        + .candidate-card {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, 0.10);

        }


        /* =====================================================
           PHOTO
        ====================================================== */

        .candidate-photo {

            width: 100%;

            aspect-ratio: 1 / 1;

            background: #f1f5f9;

            overflow: hidden;

        }


        .candidate-photo img {

            display: block;

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .candidate-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #94a3b8;

            font-size: 38px;

        }


        /* =====================================================
           CANDIDATE INFO
        ====================================================== */

        .candidate-body {

            padding: 12px;

        }


        .candidate-name {

            color: #172033;

            font-size: 14px;

            font-weight: 700;

            line-height: 1.35;

        }


        .candidate-select {

            display: flex;

            align-items: center;

            gap: 6px;

            margin-top: 8px;

            color: #64748b;

            font-size: 12px;

        }


        .candidate-radio {

            width: 16px;

            height: 16px;

            display: inline-flex;

            align-items: center;

            justify-content: center;

            border: 2px solid #cbd5e1;

            border-radius: 50%;

            flex-shrink: 0;

        }


        .candidate-option
        input:checked
        + .candidate-card
        .candidate-radio {

            border-color: #2563eb;

        }


        .candidate-option
        input:checked
        + .candidate-card
        .candidate-radio::after {

            content: "";

            width: 7px;

            height: 7px;

            background: #2563eb;

            border-radius: 50%;

        }


        /* =====================================================
           EMPTY
        ====================================================== */

        .no-candidates {

            padding: 16px;

            border: 1px dashed #cbd5e1;

            border-radius: 10px;

            background: #f8fafc;

            color: #64748b;

            font-size: 13px;

        }


        /* =====================================================
           SUBMIT BAR
        ====================================================== */

        .submit-section {

            position: fixed;

            left: 0;

            right: 0;

            bottom: 0;

            z-index: 100;

            padding: 12px 20px;

            background: rgba(255, 255, 255, 0.96);

            border-top: 1px solid #e2e8f0;

            backdrop-filter: blur(8px);

        }


        .submit-inner {

            max-width: 1100px;

            margin: auto;

        }


        .submit-button {

            width: 100%;

            min-height: 55px;

            border: 0;

            border-radius: 10px;

            background: #2563eb;

            color: #ffffff;

            font-size: 16px;

            font-weight: 700;

        }


        .submit-button:hover {

            background: #1d4ed8;

        }


        .submit-button:disabled {

            opacity: 0.7;

        }


        /* =====================================================
           SUCCESS
        ====================================================== */

        .success-page {

            min-height: calc(100vh - 74px);

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 25px 15px;

        }


        .success-card {

            width: 100%;

            max-width: 500px;

            padding: 40px 30px;

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 18px;

            text-align: center;

        }


        .success-icon {

            width: 72px;

            height: 72px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #dcfce7;

            color: #16a34a;

            font-size: 34px;

        }


        .success-card h1 {

            font-size: 27px;

            font-weight: 700;

            margin-bottom: 10px;

        }


        .success-card p {

            margin: 0;

            color: #64748b;

            line-height: 1.7;

        }


        .success-note {

            margin-top: 22px;

            padding: 13px;

            border-radius: 9px;

            background: #f8fafc;

            color: #64748b;

            font-size: 13px;

        }


        /* =====================================================
           TABLET
        ====================================================== */

        @media (max-width: 900px) {

            .candidate-grid {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

            }

        }


        /* =====================================================
           PHONE
        ====================================================== */

        @media (max-width: 600px) {

            .voting-header {

                padding: 13px 14px;

            }


            .brand-icon {

                width: 38px;

                height: 38px;

                font-size: 17px;

            }


            .brand-title {

                font-size: 14px;

            }


            .brand-subtitle {

                font-size: 10px;

            }


            .employee-info {

                max-width: 135px;

            }


            .employee-name {

                font-size: 12px;

            }


            .employee-department {

                font-size: 10px;

            }


            .voting-wrapper {

                padding: 20px 12px 95px;

            }


            .voting-intro {

                margin-bottom: 18px;

            }


            .voting-intro h1 {

                font-size: 24px;

            }


            .voting-intro p {

                font-size: 13px;

            }


            .vote-counter {

                font-size: 12px;

            }


            .category-section {

                border-radius: 13px;

                margin-bottom: 18px;

            }


            .category-header {

                padding: 16px;

            }


            .category-title {

                font-size: 18px;

            }


            .category-description {

                font-size: 12px;

            }


            .gender-section {

                padding: 15px;

            }


            .candidate-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

                gap: 9px;

            }


            .candidate-card {

                border-radius: 10px;

            }


            .candidate-body {

                padding: 9px;

            }


            .candidate-name {

                font-size: 13px;

            }


            .candidate-select {

                font-size: 11px;

                margin-top: 7px;

            }


            .candidate-radio {

                width: 15px;

                height: 15px;

            }


            .submit-section {

                padding: 10px 12px;

            }


            .submit-button {

                min-height: 54px;

            }


            .success-card {

                padding: 34px 22px;

            }

        }


        /* =====================================================
           SMALL PHONE
        ====================================================== */

        @media (max-width: 360px) {

            .candidate-grid {

                gap: 7px;

            }


            .candidate-name {

                font-size: 12px;

            }


            .candidate-body {

                padding: 8px;

            }


            .employee-info {

                max-width: 105px;

            }

        }

    </style>

</head>


<body>


<!-- ============================================================
     HEADER
============================================================= -->

<header class="voting-header">

    <div class="header-inner">


        <div class="brand">

            <div class="brand-icon">

                <i class="bi bi-trophy-fill"></i>

            </div>


            <div>

                <div class="brand-title">
                    Foundation Day 2026
                </div>

                <div class="brand-subtitle">
                    Employee Voting
                </div>

            </div>

        </div>


        <div class="employee-info">

            <div class="employee-name">

                <?= htmlspecialchars($employeeName) ?>

            </div>


            <div class="employee-department">

                <?= htmlspecialchars($employeeDepartment) ?>

            </div>

        </div>

    </div>

</header>


<?php if ($showSuccess): ?>


<!-- ============================================================
     SUCCESS
============================================================= -->

<main class="success-page">

    <div class="success-card">

        <div class="success-icon">

            <i class="bi bi-check-lg"></i>

        </div>


        <h1>
            Thank You!
        </h1>


        <p>
            Your Foundation Day 2026 votes have
            been successfully recorded.
        </p>


        <div class="success-note">

            <i class="bi bi-shield-check me-1"></i>

            Your vote has been submitted and cannot
            be changed.

        </div>

    </div>

</main>


<?php elseif ($alreadyVoted): ?>


<!-- ============================================================
     ALREADY VOTED
============================================================= -->

<main class="success-page">

    <div class="success-card">

        <div class="success-icon">

            <i class="bi bi-check-lg"></i>

        </div>


        <h1>
            Vote Already Submitted
        </h1>


        <p>
            Your Foundation Day 2026 vote has
            already been recorded.
        </p>


        <div class="success-note">

            <i class="bi bi-lock-fill me-1"></i>

            Each employee may vote only once.

        </div>

    </div>

</main>


<?php else: ?>


<!-- ============================================================
     VOTING
============================================================= -->

<main class="voting-wrapper">


    <div class="voting-intro">

        <h1>
            Cast Your Votes
        </h1>


        <p>
            Select one male and one female candidate
            for each award category.
        </p>


        <div class="vote-counter">

            <i class="bi bi-check2-circle"></i>

            <span>
                Select
                <strong>6 candidates</strong>
                in total.
            </span>

        </div>

    </div>


    <?php if (!empty($errors)): ?>

        <div
            class="alert alert-danger voting-alert"
            role="alert"
        >

            <strong>
                Please check your selections.
            </strong>


            <ul class="mb-0 mt-2">

                <?php foreach ($errors as $error): ?>

                    <li>
                        <?= htmlspecialchars($error) ?>
                    </li>

                <?php endforeach; ?>

            </ul>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        id="votingForm"
    >


        <?php

        $categoryNumber = 1;

        ?>


        <?php foreach ($categories as $category => $categoryData): ?>


            <!-- =================================================
                 CATEGORY
            ================================================== -->

            <section class="category-section">


                <div class="category-header">

                    <span class="category-number">

                        <?= $categoryNumber ?>

                    </span>


                    <h2 class="category-title">

                        <?= htmlspecialchars(
                            $categoryData["title"]
                        ) ?>

                    </h2>


                    <div class="category-description">

                        <?= htmlspecialchars(
                            $categoryData["description"]
                        ) ?>

                    </div>

                </div>


                <?php foreach ($genders as $gender => $genderLabel): ?>


                    <!-- =============================================
                         GENDER
                    ============================================== -->

                    <div class="gender-section">


                        <h3 class="gender-title">

                            <?php if ($gender === "male"): ?>

                                <i class="bi bi-gender-male"></i>

                            <?php else: ?>

                                <i class="bi bi-gender-female"></i>

                            <?php endif; ?>


                            <?= htmlspecialchars(
                                $genderLabel
                            ) ?>

                        </h3>


                        <?php if (
                            empty(
                                $candidates[$category][$gender]
                            )
                        ): ?>


                            <div class="no-candidates">

                                <i class="bi bi-info-circle me-1"></i>

                                No active
                                <?= htmlspecialchars(
                                    strtolower($genderLabel)
                                ) ?>
                                candidates are available
                                for this category.

                            </div>


                        <?php else: ?>


                            <div class="candidate-grid">


                                <?php foreach (
                                    $candidates[$category][$gender]
                                    as $candidate
                                ): ?>


                                    <label
                                        class="candidate-option"
                                    >


                                        <input
                                            type="radio"
                                            name="votes[<?= htmlspecialchars($category) ?>][<?= htmlspecialchars($gender) ?>]"
                                            value="<?= (int) $candidate["id"] ?>"
                                            required
                                        >


                                        <div class="candidate-card">


                                            <div class="candidate-photo">


                                                <?php if (
                                                    !empty(
                                                        $candidate["photo"]
                                                    )
                                                ): ?>


                                                    <img
                                                        src="<?= htmlspecialchars(
                                                            $candidate["photo"]
                                                        ) ?>"
                                                        alt="<?= htmlspecialchars(
                                                            $candidate["name"]
                                                        ) ?>"
                                                        loading="lazy"
                                                    >


                                                <?php else: ?>


                                                    <div
                                                        class="candidate-placeholder"
                                                    >

                                                        <i class="bi bi-person-fill"></i>

                                                    </div>


                                                <?php endif; ?>


                                            </div>


                                            <div class="candidate-body">


                                                <div class="candidate-name">

                                                    <?= htmlspecialchars(
                                                        $candidate["name"]
                                                    ) ?>

                                                </div>


                                                <div class="candidate-select">

                                                    <span
                                                        class="candidate-radio"
                                                    ></span>


                                                    <span>
                                                        Select
                                                    </span>

                                                </div>


                                            </div>

                                        </div>

                                    </label>


                                <?php endforeach; ?>


                            </div>


                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </section>


            <?php

            $categoryNumber++;

            ?>


        <?php endforeach; ?>


    </form>


</main>


<!-- ============================================================
     SUBMIT BAR
============================================================= -->

<div class="submit-section">

    <div class="submit-inner">

        <button
            type="submit"
            form="votingForm"
            class="submit-button"
            id="submitVotes"
        >

            <i class="bi bi-check2-circle me-2"></i>

            Submit My Votes

        </button>

    </div>

</div>


<?php endif; ?>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const form =
            document.getElementById("votingForm");

        const submitButton =
            document.getElementById("submitVotes");


        if (!form || !submitButton) {
            return;
        }


        form.addEventListener(
            "submit",
            function (event) {

                /*
                |--------------------------------------------------------------------------
                | Check six selections
                |--------------------------------------------------------------------------
                */

                const requiredInputs =
                    form.querySelectorAll(
                        "input[type='radio'][required]"
                    );


                const groupNames = [];


                requiredInputs.forEach(
                    function (input) {

                        if (
                            groupNames.indexOf(
                                input.name
                            ) === -1
                        ) {

                            groupNames.push(
                                input.name
                            );

                        }

                    }
                );


                let missing = false;


                groupNames.forEach(
                    function (name) {

                        const selected =
                            form.querySelector(
                                "input[name='" +
                                CSS.escape(name) +
                                "']:checked"
                            );


                        if (!selected) {

                            missing = true;

                        }

                    }
                );


                if (missing) {

                    event.preventDefault();


                    alert(
                        "Please select one Male and one Female candidate for every category."
                    );


                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | Confirmation
                |--------------------------------------------------------------------------
                */

                const confirmed =
                    window.confirm(
                        "Are you sure you want to submit your votes?\n\nYou can only vote once, and your selections cannot be changed."
                    );


                if (!confirmed) {

                    event.preventDefault();

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | Prevent double click
                |--------------------------------------------------------------------------
                */

                submitButton.disabled = true;


                submitButton.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>' +
                    'Submitting Votes...';

            }
        );

    }
);

</script>


</body>

</html>
