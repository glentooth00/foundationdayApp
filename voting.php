<?php

session_start();

require_once "includes/db.php";
require_once "includes/functions.php";

/*
|--------------------------------------------------------------------------
| DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists("h")) {
    function h($value)
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            "UTF-8"
        );
    }
}

if (!function_exists("employeePhoto")) {
    function employeePhoto($selfie)
    {
        if (!$selfie) {
            return "assets/img/default-avatar.png";
        }

        return $selfie;
    }
}

/*
|--------------------------------------------------------------------------
| DEVICE TOKEN
|--------------------------------------------------------------------------
|
| The device token is the primary identifier for the voter.
|
*/

$deviceToken = isset($_COOKIE["fd_device_token"])
    ? trim($_COOKIE["fd_device_token"])
    : "";

/*
|--------------------------------------------------------------------------
| PAGE STATE
|--------------------------------------------------------------------------
*/

$pageError = "";
$error = "";
$success = isset($_GET["success"]) && $_GET["success"] === "1";

$employee = null;
$alreadyVoted = false;

/*
|--------------------------------------------------------------------------
| GET EMPLOYEE USING DEVICE TOKEN
|--------------------------------------------------------------------------
*/

if ($deviceToken === "") {

    $pageError =
        "No registered device was found. Please use the device where you completed your registration.";

} else {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            normalized_name,
            gender,
            device_token,
            selfie,
            department,
            registration_status,
            voting_status
        FROM employees
        WHERE device_token = ?
        LIMIT 1
    ");

    if (!$stmt) {

        $pageError = "Unable to verify the voting device.";

    } else {

        $stmt->bind_param("s", $deviceToken);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {

            $employee = $result->fetch_assoc();

            /*
            |--------------------------------------------------------------------------
            | CHECK REGISTRATION STATUS
            |--------------------------------------------------------------------------
            */

            if (
                isset($employee["registration_status"]) &&
                $employee["registration_status"] === "rejected"
            ) {

                $pageError =
                    "This registration is not eligible for voting.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | CHECK VOTING STATUS
                |--------------------------------------------------------------------------
                */

                if (
                    isset($employee["voting_status"]) &&
                    $employee["voting_status"] === "voted"
                ) {
                    $alreadyVoted = true;
                }

                /*
                |--------------------------------------------------------------------------
                | KEEP SESSION FOR CONVENIENCE
                |--------------------------------------------------------------------------
                */

                $_SESSION["employee_id"] =
                    (int) $employee["id"];

                $_SESSION["voting_employee_id"] =
                    (int) $employee["id"];

                $_SESSION["voting_device_token"] =
                    $deviceToken;
            }

        } else {

            $pageError =
                "The device token is not associated with a registered employee.";
        }

        $stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CURRENTLY REGISTERED EMPLOYEES
|--------------------------------------------------------------------------
|
| Employees who register later during the event automatically appear.
|
*/

$maleEmployees = [];
$femaleEmployees = [];
$allEmployees = [];

if ($employee && $pageError === "") {

    $candidateStmt = $conn->prepare("
        SELECT
            id,
            full_name,
            gender,
            selfie
        FROM employees
        WHERE registration_status = 'approved'
        ORDER BY full_name ASC
    ");

    if ($candidateStmt) {

        $candidateStmt->execute();

        $candidateResult =
            $candidateStmt->get_result();

        while ($row = $candidateResult->fetch_assoc()) {

            $row["id"] = (int) $row["id"];

            $allEmployees[] = $row;

            if ($row["gender"] === "male") {
                $maleEmployees[] = $row;
            }

            if ($row["gender"] === "female") {
                $femaleEmployees[] = $row;
            }
        }

        $candidateStmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS VOTE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $pageError === ""
) {

    if ($deviceToken === "") {

        $error =
            "Your voting device could not be verified.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | READ FORM VALUES
        |--------------------------------------------------------------------------
        */

        $faceMale = isset($_POST["face_male"])
            ? (int) $_POST["face_male"]
            : 0;

        $faceFemale = isset($_POST["face_female"])
            ? (int) $_POST["face_female"]
            : 0;

        $starMale = isset($_POST["star_male"])
            ? (int) $_POST["star_male"]
            : 0;

        $starFemale = isset($_POST["star_female"])
            ? (int) $_POST["star_female"]
            : 0;

        $crowdFavorite = isset($_POST["crowd_favorite"])
            ? (int) $_POST["crowd_favorite"]
            : 0;

        /*
        |--------------------------------------------------------------------------
        | VALIDATE ALL SELECTIONS
        |--------------------------------------------------------------------------
        */

        if (
            $faceMale <= 0 ||
            $faceFemale <= 0 ||
            $starMale <= 0 ||
            $starFemale <= 0 ||
            $crowdFavorite <= 0
        ) {

            $error =
                "Please select one candidate for every category.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | GET SELECTED CANDIDATE IDs
            |--------------------------------------------------------------------------
            */

            $candidateIds = [
                $faceMale,
                $faceFemale,
                $starMale,
                $starFemale,
                $crowdFavorite
            ];

            $candidateIds = array_values(
                array_unique($candidateIds)
            );

            $placeholders = implode(
                ",",
                array_fill(
                    0,
                    count($candidateIds),
                    "?"
                )
            );

            $types = str_repeat(
                "i",
                count($candidateIds)
            );

            $candidateCheck = $conn->prepare("
                SELECT
                    id,
                    full_name,
                    gender,
                    registration_status
                FROM employees
                WHERE id IN ($placeholders)
            ");

            $candidateValid = true;
            $candidateData = [];

            if (!$candidateCheck) {

                $candidateValid = false;

                $error =
                    "Unable to validate your selections.";

            } else {

                $params = [];
                $params[] = $types;

                foreach ($candidateIds as $candidateId) {
                    $params[] = $candidateId;
                }

                $refs = [];

                foreach ($params as $key => $value) {
                    $refs[$key] = &$params[$key];
                }

                call_user_func_array(
                    [$candidateCheck, "bind_param"],
                    $refs
                );

                $candidateCheck->execute();

                $candidateResult =
                    $candidateCheck->get_result();

                while (
                    $row = $candidateResult->fetch_assoc()
                ) {

                    $candidateData[
                        (int) $row["id"]
                    ] = $row;
                }

                $candidateCheck->close();
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE FACE OF THE NIGHT - MALE
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                if (
                    !isset($candidateData[$faceMale]) ||
                    $candidateData[$faceMale]["gender"] !== "male" ||
                    $candidateData[$faceMale]["registration_status"] === "rejected"
                ) {

                    $candidateValid = false;

                    $error =
                        "The selected Face of the Night Male candidate is invalid.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE FACE OF THE NIGHT - FEMALE
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                if (
                    !isset($candidateData[$faceFemale]) ||
                    $candidateData[$faceFemale]["gender"] !== "female" ||
                    $candidateData[$faceFemale]["registration_status"] === "rejected"
                ) {

                    $candidateValid = false;

                    $error =
                        "The selected Face of the Night Female candidate is invalid.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STAR OF THE NIGHT - MALE
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                if (
                    !isset($candidateData[$starMale]) ||
                    $candidateData[$starMale]["gender"] !== "male" ||
                    $candidateData[$starMale]["registration_status"] === "rejected"
                ) {

                    $candidateValid = false;

                    $error =
                        "The selected Star of the Night Male candidate is invalid.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE STAR OF THE NIGHT - FEMALE
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                if (
                    !isset($candidateData[$starFemale]) ||
                    $candidateData[$starFemale]["gender"] !== "female" ||
                    $candidateData[$starFemale]["registration_status"] === "rejected"
                ) {

                    $candidateValid = false;

                    $error =
                        "The selected Star of the Night Female candidate is invalid.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VALIDATE CROWD FAVORITE
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                if (
                    !isset($candidateData[$crowdFavorite]) ||
                    $candidateData[$crowdFavorite]["registration_status"] === "rejected"
                ) {

                    $candidateValid = false;

                    $error =
                        "The selected Crowd Favorite candidate is invalid.";
                }
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE VOTES
            |--------------------------------------------------------------------------
            */

            if ($candidateValid) {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | START TRANSACTION
                    |--------------------------------------------------------------------------
                    */

                    $conn->begin_transaction();

                    /*
                    |--------------------------------------------------------------------------
                    | LOCK VOTER USING DEVICE TOKEN
                    |--------------------------------------------------------------------------
                    */

                    $voterStmt = $conn->prepare("
                        SELECT
                            id,
                            full_name,
                            voting_status
                        FROM employees
                        WHERE device_token = ?
                        LIMIT 1
                        FOR UPDATE
                    ");

                    if (!$voterStmt) {

                        throw new Exception(
                            "Unable to verify voter."
                        );
                    }

                    $voterStmt->bind_param(
                        "s",
                        $deviceToken
                    );

                    $voterStmt->execute();

                    $voterResult =
                        $voterStmt->get_result();

                    if (
                        !$voterResult ||
                        $voterResult->num_rows !== 1
                    ) {

                        $voterStmt->close();

                        throw new Exception(
                            "Your voting device is no longer registered."
                        );
                    }

                    $voter =
                        $voterResult->fetch_assoc();

                    $voterStmt->close();

                    /*
                    |--------------------------------------------------------------------------
                    | CHECK IF ALREADY VOTED
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $voter["voting_status"] === "voted"
                    ) {

                        $conn->rollback();

                        $alreadyVoted = true;
                        $error = "";

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | PREPARE VOTE INSERT
                        |--------------------------------------------------------------------------
                        */

                        $voteStmt = $conn->prepare("
                            INSERT INTO votes (
                                employee_id,
                                candidate_id,
                                category,
                                gender
                            )
                            VALUES (?, ?, ?, ?)
                        ");

                        if (!$voteStmt) {

                            throw new Exception(
                                "Unable to prepare vote submission."
                            );
                        }

                        $employeeId =
                            (int) $voter["id"];

                        /*
                        |--------------------------------------------------------------------------
                        | FACE OF THE NIGHT - MALE
                        |--------------------------------------------------------------------------
                        */

                        $candidateId = $faceMale;
                        $category = "face_of_the_night";
                        $gender = "male";

                        $voteStmt->bind_param(
                            "iiss",
                            $employeeId,
                            $candidateId,
                            $category,
                            $gender
                        );

                        if (!$voteStmt->execute()) {

                            throw new Exception(
                                "Unable to save Face of the Night Male vote."
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | FACE OF THE NIGHT - FEMALE
                        |--------------------------------------------------------------------------
                        */

                        $candidateId = $faceFemale;
                        $gender = "female";

                        $voteStmt->bind_param(
                            "iiss",
                            $employeeId,
                            $candidateId,
                            $category,
                            $gender
                        );

                        if (!$voteStmt->execute()) {

                            throw new Exception(
                                "Unable to save Face of the Night Female vote."
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | STAR OF THE NIGHT - MALE
                        |--------------------------------------------------------------------------
                        */

                        $candidateId = $starMale;
                        $category = "star_of_the_night";
                        $gender = "male";

                        $voteStmt->bind_param(
                            "iiss",
                            $employeeId,
                            $candidateId,
                            $category,
                            $gender
                        );

                        if (!$voteStmt->execute()) {

                            throw new Exception(
                                "Unable to save Star of the Night Male vote."
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | STAR OF THE NIGHT - FEMALE
                        |--------------------------------------------------------------------------
                        */

                        $candidateId = $starFemale;
                        $gender = "female";

                        $voteStmt->bind_param(
                            "iiss",
                            $employeeId,
                            $candidateId,
                            $category,
                            $gender
                        );

                        if (!$voteStmt->execute()) {

                            throw new Exception(
                                "Unable to save Star of the Night Female vote."
                            );
                        }

                        /*
                        |--------------------------------------------------------------------------
                        | CROWD FAVORITE
                        |--------------------------------------------------------------------------
                        */

                        $candidateId = $crowdFavorite;
                        $category = "crowd_favorite";

                        $gender = isset(
                            $candidateData[$crowdFavorite]["gender"]
                        )
                            ? $candidateData[$crowdFavorite]["gender"]
                            : "";

                        $voteStmt->bind_param(
                            "iiss",
                            $employeeId,
                            $candidateId,
                            $category,
                            $gender
                        );

                        if (!$voteStmt->execute()) {

                            throw new Exception(
                                "Unable to save Crowd Favorite vote."
                            );
                        }

                        $voteStmt->close();

                        /*
                        |--------------------------------------------------------------------------
                        | MARK EMPLOYEE AS VOTED
                        |--------------------------------------------------------------------------
                        */

                        $updateStmt = $conn->prepare("
                            UPDATE employees
                            SET voting_status = 'voted'
                            WHERE id = ?
                            AND voting_status = 'not_voted'
                        ");

                        if (!$updateStmt) {

                            throw new Exception(
                                "Unable to update voting status."
                            );
                        }

                        $updateStmt->bind_param(
                            "i",
                            $employeeId
                        );

                        if (!$updateStmt->execute()) {

                            $updateStmt->close();

                            throw new Exception(
                                "Unable to update voting status."
                            );
                        }

                        if (
                            $updateStmt->affected_rows !== 1
                        ) {

                            $updateStmt->close();

                            throw new Exception(
                                "The vote could not be completed because the voter status changed."
                            );
                        }

                        $updateStmt->close();

                        /*
                        |--------------------------------------------------------------------------
                        | COMMIT
                        |--------------------------------------------------------------------------
                        */

                        $conn->commit();

                        /*
                        |--------------------------------------------------------------------------
                        | SAVE SESSION STATE
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION["employee_id"] =
                            $employeeId;

                        $_SESSION["voting_employee_id"] =
                            $employeeId;

                        $_SESSION["voting_device_token"] =
                            $deviceToken;

                        $_SESSION["voting_completed"] =
                            true;

                        /*
                        |--------------------------------------------------------------------------
                        | REDIRECT TO SUCCESS
                        |--------------------------------------------------------------------------
                        */

                        header(
                            "Location: voting.php?success=1"
                        );

                        exit;
                    }

                } catch (Throwable $e) {

                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackError) {
                        // Ignore rollback errors.
                    }

                    $error = $e->getMessage();
                }
            }
        }
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
        Foundation Day 2026 - Voting
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <style>

        :root {
            --primary: #1d4ed8;
            --primary-dark: #1e3a8a;
            --background: #f5f7fb;
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

        .page-wrapper {
            min-height: 100vh;
            padding: 30px 16px 50px;
        }

        .container-main {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .top-header {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 20px;
        }

        .event-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 4px;
        }

        .event-subtitle {
            color: var(--muted);
            font-size: 14px;
        }

        .voter-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        .voter-photo {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dbeafe;
        }

        .voter-name {
            font-size: 16px;
            font-weight: 700;
        }

        .voter-gender {
            color: var(--muted);
            font-size: 13px;
            text-transform: capitalize;
        }

        /*
        |--------------------------------------------------------------------------
        | VOTING CARD
        |--------------------------------------------------------------------------
        */

        .section-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 18px;
        }

        .section-title {
            font-size: 19px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .section-description {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 18px;
        }

        /*
        |--------------------------------------------------------------------------
        | SELECT
        |--------------------------------------------------------------------------
        */

        .vote-label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .vote-select {
            width: 100%;
            min-height: 52px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 10px 14px;
            background-color: #ffffff;
            color: #1f2937;
            font-size: 15px;
            cursor: pointer;
        }

        .vote-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, .10);
            outline: none;
        }

        /*
        |--------------------------------------------------------------------------
        | SELECTED CANDIDATE PREVIEW
        |--------------------------------------------------------------------------
        */

        .candidate-preview {
            display: none;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .candidate-preview.show {
            display: flex;
        }

        .candidate-preview-photo {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid #dbeafe;
            background: #ffffff;
        }

        .candidate-preview-content {
            min-width: 0;
        }

        .candidate-preview-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .candidate-preview-name {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            word-break: break-word;
        }

        /*
        |--------------------------------------------------------------------------
        | SUBMIT
        |--------------------------------------------------------------------------
        */

        .submit-section {
            text-align: center;
            padding: 10px 0 20px;
        }

        .submit-button {
            min-width: 240px;
            padding: 13px 24px;
            font-size: 16px;
            font-weight: 700;
            border-radius: 10px;
        }

        /*
        |--------------------------------------------------------------------------
        | ALERT
        |--------------------------------------------------------------------------
        */

        .alert {
            border-radius: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | STATUS CARD
        |--------------------------------------------------------------------------
        */

        .status-card {
            max-width: 650px;
            margin: 70px auto;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 25px;
            text-align: center;
        }

        .status-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
        }

        .status-icon.error {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-icon.success {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-title {
            font-size: 25px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .status-message {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }

        .device-info {
            margin-top: 20px;
            padding: 12px 15px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 12px;
            color: var(--muted);
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 576px) {

            .page-wrapper {
                padding: 15px 10px 30px;
            }

            .top-header,
            .section-card {
                padding: 18px;
                border-radius: 12px;
            }

            .event-title {
                font-size: 21px;
            }

            .section-title {
                font-size: 17px;
            }

            .vote-select {
                min-height: 50px;
                font-size: 14px;
            }

            .candidate-preview-photo {
                width: 52px;
                height: 52px;
            }

            .submit-button {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<div class="page-wrapper">

    <div class="container-main">

        <?php if ($pageError !== ""): ?>

            <!-- ======================================================
                 INVALID DEVICE
            ======================================================= -->

            <div class="status-card">

                <div class="status-icon error">
                    <i class="bi bi-shield-x"></i>
                </div>

                <div class="status-title">
                    Voting Session Not Found
                </div>

                <div class="status-message">
                    <?= h($pageError) ?>
                </div>

                <div class="device-info">
                    The voting page identifies your registration
                    using the device token assigned during
                    registration.
                </div>

            </div>

        <?php elseif ($alreadyVoted || $success): ?>

            <!-- ======================================================
                 ALREADY VOTED
            ======================================================= -->

            <div class="status-card">

                <div class="status-icon success">
                    <i class="bi bi-check-lg"></i>
                </div>

                <div class="status-title">
                    Your Vote Has Been Recorded
                </div>

                <div class="status-message">

                    Thank you,

                    <strong>
                        <?= h($employee["full_name"]) ?>
                    </strong>.

                    Your Foundation Day 2026 votes have been
                    successfully recorded.

                    <br>
                    <br>

                    This device and registration can no longer
                    submit another vote.

                </div>

            </div>

        <?php else: ?>

            <!-- ======================================================
                 HEADER
            ======================================================= -->

            <div class="top-header">

                <div class="event-title">
                    Foundation Day 2026
                </div>

                <div class="event-subtitle">
                    Official Voting
                </div>

                <div class="voter-info">

                    <img
                        src="<?= h(employeePhoto($employee["selfie"])) ?>"
                        alt="<?= h($employee["full_name"]) ?>"
                        class="voter-photo"
                    >

                    <div>

                        <div class="voter-name">
                            <?= h($employee["full_name"]) ?>
                        </div>

                        <div class="voter-gender">
                            <?= h($employee["gender"]) ?>
                        </div>

                    </div>

                </div>

            </div>

            <?php if ($error !== ""): ?>

                <div
                    class="alert alert-danger"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle-fill me-2"></i>

                    <?= h($error) ?>

                </div>

            <?php endif; ?>


            <!-- ======================================================
                 VOTING FORM
            ======================================================= -->

            <form
                method="POST"
                id="votingForm"
                novalidate
            >

                <!-- ==================================================
                     FACE OF THE NIGHT - MALE
                =================================================== -->

                <div class="section-card">

                    <div class="section-title">
                        Face of the Night — Male
                    </div>

                    <div class="section-description">
                        Select one male candidate.
                    </div>

                    <label
                        for="face_male"
                        class="vote-label"
                    >
                        Candidate
                    </label>

                    <select
                        name="face_male"
                        id="face_male"
                        class="form-select vote-select candidate-select"
                        data-preview="preview-face-male"
                        required
                    >

                        <option
                            value=""
                            selected
                            disabled
                        >
                            Select a male candidate
                        </option>

                        <?php foreach ($maleEmployees as $candidate): ?>

                            <option
                                value="<?= (int) $candidate["id"] ?>"
                                data-name="<?= h($candidate["full_name"]) ?>"
                                data-photo="<?= h(employeePhoto($candidate["selfie"])) ?>"
                            >
                                <?= h($candidate["full_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        class="candidate-preview"
                        id="preview-face-male"
                    >

                        <img
                            src=""
                            alt=""
                            class="candidate-preview-photo"
                        >

                        <div class="candidate-preview-content">

                            <div class="candidate-preview-label">
                                Selected Candidate
                            </div>

                            <div class="candidate-preview-name">
                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     FACE OF THE NIGHT - FEMALE
                =================================================== -->

                <div class="section-card">

                    <div class="section-title">
                        Face of the Night — Female
                    </div>

                    <div class="section-description">
                        Select one female candidate.
                    </div>

                    <label
                        for="face_female"
                        class="vote-label"
                    >
                        Candidate
                    </label>

                    <select
                        name="face_female"
                        id="face_female"
                        class="form-select vote-select candidate-select"
                        data-preview="preview-face-female"
                        required
                    >

                        <option
                            value=""
                            selected
                            disabled
                        >
                            Select a female candidate
                        </option>

                        <?php foreach ($femaleEmployees as $candidate): ?>

                            <option
                                value="<?= (int) $candidate["id"] ?>"
                                data-name="<?= h($candidate["full_name"]) ?>"
                                data-photo="<?= h(employeePhoto($candidate["selfie"])) ?>"
                            >
                                <?= h($candidate["full_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        class="candidate-preview"
                        id="preview-face-female"
                    >

                        <img
                            src=""
                            alt=""
                            class="candidate-preview-photo"
                        >

                        <div class="candidate-preview-content">

                            <div class="candidate-preview-label">
                                Selected Candidate
                            </div>

                            <div class="candidate-preview-name">
                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     STAR OF THE NIGHT - MALE
                =================================================== -->

                <div class="section-card">

                    <div class="section-title">
                        Star of the Night — Male
                    </div>

                    <div class="section-description">
                        Select one male candidate.
                    </div>

                    <label
                        for="star_male"
                        class="vote-label"
                    >
                        Candidate
                    </label>

                    <select
                        name="star_male"
                        id="star_male"
                        class="form-select vote-select candidate-select"
                        data-preview="preview-star-male"
                        required
                    >

                        <option
                            value=""
                            selected
                            disabled
                        >
                            Select a male candidate
                        </option>

                        <?php foreach ($maleEmployees as $candidate): ?>

                            <option
                                value="<?= (int) $candidate["id"] ?>"
                                data-name="<?= h($candidate["full_name"]) ?>"
                                data-photo="<?= h(employeePhoto($candidate["selfie"])) ?>"
                            >
                                <?= h($candidate["full_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        class="candidate-preview"
                        id="preview-star-male"
                    >

                        <img
                            src=""
                            alt=""
                            class="candidate-preview-photo"
                        >

                        <div class="candidate-preview-content">

                            <div class="candidate-preview-label">
                                Selected Candidate
                            </div>

                            <div class="candidate-preview-name">
                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     STAR OF THE NIGHT - FEMALE
                =================================================== -->

                <div class="section-card">

                    <div class="section-title">
                        Star of the Night — Female
                    </div>

                    <div class="section-description">
                        Select one female candidate.
                    </div>

                    <label
                        for="star_female"
                        class="vote-label"
                    >
                        Candidate
                    </label>

                    <select
                        name="star_female"
                        id="star_female"
                        class="form-select vote-select candidate-select"
                        data-preview="preview-star-female"
                        required
                    >

                        <option
                            value=""
                            selected
                            disabled
                        >
                            Select a female candidate
                        </option>

                        <?php foreach ($femaleEmployees as $candidate): ?>

                            <option
                                value="<?= (int) $candidate["id"] ?>"
                                data-name="<?= h($candidate["full_name"]) ?>"
                                data-photo="<?= h(employeePhoto($candidate["selfie"])) ?>"
                            >
                                <?= h($candidate["full_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        class="candidate-preview"
                        id="preview-star-female"
                    >

                        <img
                            src=""
                            alt=""
                            class="candidate-preview-photo"
                        >

                        <div class="candidate-preview-content">

                            <div class="candidate-preview-label">
                                Selected Candidate
                            </div>

                            <div class="candidate-preview-name">
                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     CROWD FAVORITE
                =================================================== -->

                <div class="section-card">

                    <div class="section-title">
                        Crowd Favorite
                    </div>

                    <div class="section-description">
                        Select one employee from all registered participants.
                    </div>

                    <label
                        for="crowd_favorite"
                        class="vote-label"
                    >
                        Candidate
                    </label>

                    <select
                        name="crowd_favorite"
                        id="crowd_favorite"
                        class="form-select vote-select candidate-select"
                        data-preview="preview-crowd-favorite"
                        required
                    >

                        <option
                            value=""
                            selected
                            disabled
                        >
                            Select a candidate
                        </option>

                        <?php foreach ($allEmployees as $candidate): ?>

                            <option
                                value="<?= (int) $candidate["id"] ?>"
                                data-name="<?= h($candidate["full_name"]) ?>"
                                data-photo="<?= h(employeePhoto($candidate["selfie"])) ?>"
                            >
                                <?= h($candidate["full_name"]) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div
                        class="candidate-preview"
                        id="preview-crowd-favorite"
                    >

                        <img
                            src=""
                            alt=""
                            class="candidate-preview-photo"
                        >

                        <div class="candidate-preview-content">

                            <div class="candidate-preview-label">
                                Selected Candidate
                            </div>

                            <div class="candidate-preview-name">
                            </div>

                        </div>

                    </div>

                </div>


                <!-- ==================================================
                     SUBMIT
                =================================================== -->

                <div class="submit-section">

                    <button
                        type="submit"
                        class="btn btn-primary submit-button"
                        id="submitVoteButton"
                    >

                        <i class="bi bi-check2-circle me-2"></i>

                        Submit My Vote

                    </button>

                    <div class="text-muted small mt-3">

                        Please review all five selections before
                        submitting.

                        <br>

                        Your vote cannot be changed after submission.

                    </div>

                </div>

            </form>

        <?php endif; ?>

    </div>

</div>


<script>

document.addEventListener("DOMContentLoaded", function () {

    /*
    |--------------------------------------------------------------------------
    | CANDIDATE IMAGE PREVIEW
    |--------------------------------------------------------------------------
    */

    const candidateSelects =
        document.querySelectorAll(".candidate-select");

    candidateSelects.forEach(function (select) {

        select.addEventListener("change", function () {

            const previewId =
                select.getAttribute("data-preview");

            const preview =
                document.getElementById(previewId);

            if (!preview) {
                return;
            }

            const selectedOption =
                select.options[select.selectedIndex];

            if (
                !selectedOption ||
                !selectedOption.value
            ) {

                preview.classList.remove("show");

                return;
            }

            const photo =
                selectedOption.getAttribute("data-photo");

            const name =
                selectedOption.getAttribute("data-name");

            const image =
                preview.querySelector(
                    ".candidate-preview-photo"
                );

            const nameElement =
                preview.querySelector(
                    ".candidate-preview-name"
                );

            image.src =
                photo ||
                "assets/img/default-avatar.png";

            image.alt = name;

            nameElement.textContent =
                name || "";

            preview.classList.add("show");
        });

    });


    /*
    |--------------------------------------------------------------------------
    | FORM SUBMISSION
    |--------------------------------------------------------------------------
    */

    const form =
        document.getElementById("votingForm");

    if (!form) {
        return;
    }

    const submitButton =
        document.getElementById(
            "submitVoteButton"
        );

    form.addEventListener(
        "submit",
        function (event) {

            /*
            |--------------------------------------------------------------------------
            | CHECK REQUIRED DROPDOWNS
            |--------------------------------------------------------------------------
            */

            if (!form.checkValidity()) {

                event.preventDefault();

                form.reportValidity();

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CONFIRM SUBMISSION
            |--------------------------------------------------------------------------
            */

            const confirmed = confirm(
                "Are you sure you want to submit your votes?\n\n" +
                "Once submitted, your vote cannot be changed."
            );

            if (!confirmed) {

                event.preventDefault();

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | PREVENT DOUBLE CLICK
            |--------------------------------------------------------------------------
            */

            submitButton.disabled = true;

            submitButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2" role="status"></span>' +
                "Submitting Vote...";

        }
    );

});

</script>

</body>
</html>