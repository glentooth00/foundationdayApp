<?php

require_once "includes/db.php";

/*
|--------------------------------------------------------------------------
| WINNER REVEAL API
|--------------------------------------------------------------------------
|
| Vote totals and voter information are NOT loaded during normal
| page viewing.
|
| They are only retrieved when the operator clicks
| "Shuffle & Reveal Winner".
|
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["action"]) &&
    $_GET["action"] === "reveal"
) {

    header("Content-Type: application/json; charset=utf-8");

    $tab = $_GET["tab"] ?? "face_male";

    $allowedTabs = [
        "face_male",
        "face_female",
        "star_male",
        "star_female",
        "crowd"
    ];

    if (!in_array($tab, $allowedTabs, true)) {

        echo json_encode([
            "success" => false,
            "message" => "Invalid category."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | CATEGORY CONFIGURATION
    |--------------------------------------------------------------------------
    */

    $category = "";
    $gender = "all";


    switch ($tab) {

        case "face_male":

            $category = "face_of_the_night";
            $gender = "male";

            break;


        case "face_female":

            $category = "face_of_the_night";
            $gender = "female";

            break;


        case "star_male":

            $category = "star_of_the_night";
            $gender = "male";

            break;


        case "star_female":

            $category = "star_of_the_night";
            $gender = "female";

            break;


        case "crowd":

            $category = "crowd_favorite";
            $gender = "all";

            break;

    }


    /*
    |--------------------------------------------------------------------------
    | VOTER TURNOUT
    |--------------------------------------------------------------------------
    |
    | This is calculated only when the operator reveals the winner.
    |
    | total_registered = all non-rejected employees
    | total_voters     = employees whose voting_status is "voted"
    |
    |--------------------------------------------------------------------------
    */

    $turnoutSql = "
        SELECT
            COUNT(*) AS total_registered,
            COALESCE(
                SUM(
                    CASE
                        WHEN voting_status = 'voted'
                        THEN 1
                        ELSE 0
                    END
                ),
                0
            ) AS total_voters

        FROM employees

        WHERE registration_status <> 'rejected'
    ";


    $turnoutResult =
        $conn->query($turnoutSql);


    if (!$turnoutResult) {

        echo json_encode([
            "success" => false,
            "message" => "Unable to calculate voter turnout."
        ]);

        exit;
    }


    $turnoutRow =
        $turnoutResult->fetch_assoc();


    $totalRegistered =
        (int) $turnoutRow["total_registered"];


    $totalVoters =
        (int) $turnoutRow["total_voters"];


    $voterPercentage = 0;


    if ($totalRegistered > 0) {

        $voterPercentage =
            round(
                ($totalVoters / $totalRegistered) * 100,
                1
            );

    }


    /*
    |--------------------------------------------------------------------------
    | FIND WINNER
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            e.id,
            e.full_name,
            e.gender,
            e.selfie,
            COUNT(v.id) AS vote_count

        FROM employees e

        LEFT JOIN votes v
            ON v.candidate_id = e.id
            AND v.category = ?

        WHERE e.registration_status <> 'rejected'
    ";


    if ($gender !== "all") {

        $sql .= "
            AND e.gender = ?
        ";

    }


    $sql .= "
        GROUP BY
            e.id,
            e.full_name,
            e.gender,
            e.selfie

        ORDER BY
            vote_count DESC
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        echo json_encode([
            "success" => false,
            "message" => "Unable to prepare winner query."
        ]);

        exit;
    }


    if ($gender !== "all") {

        $stmt->bind_param(
            "ss",
            $category,
            $gender
        );

    } else {

        $stmt->bind_param(
            "s",
            $category
        );

    }


    $stmt->execute();


    $result =
        $stmt->get_result();


    $rows = [];


    while ($row = $result->fetch_assoc()) {

        $rows[] = $row;

    }


    $stmt->close();


    if (empty($rows)) {

        echo json_encode([
            "success" => false,
            "message" => "No participants found."
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DETERMINE HIGHEST VOTE COUNT
    |--------------------------------------------------------------------------
    */

    $highestVotes =
        (int) $rows[0]["vote_count"];


    $winners = [];


    foreach ($rows as $row) {

        if (
            (int) $row["vote_count"]
            ===
            $highestVotes
        ) {

            $winners[] = [

                "id" =>
                    (int) $row["id"],

                "name" =>
                    $row["full_name"],

                "gender" =>
                    $row["gender"],

                "photo" =>
                    $row["selfie"],

                "votes" =>
                    $highestVotes,

                "voters" =>
                    []

            ];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | LOAD VOTERS FOR EACH WINNER
    |--------------------------------------------------------------------------
    |
    | employee_id = the employee who voted
    | candidate_id = the employee who received the vote
    |
    |--------------------------------------------------------------------------
    */

    foreach (
        $winners as $winnerIndex => $winner
    ) {

        $voterSql = "
            SELECT
                e.id,
                e.full_name,
                e.selfie

            FROM votes v

            INNER JOIN employees e
                ON e.id = v.employee_id

            WHERE v.candidate_id = ?
            AND v.category = ?

            ORDER BY v.id ASC
        ";


        $voterStmt =
            $conn->prepare(
                $voterSql
            );


        if ($voterStmt) {

            $winnerId =
                (int) $winner["id"];


            $voterStmt->bind_param(
                "is",
                $winnerId,
                $category
            );


            $voterStmt->execute();


            $voterResult =
                $voterStmt->get_result();


            while (
                $voter =
                    $voterResult->fetch_assoc()
            ) {

                $winners[$winnerIndex]["voters"][] = [

                    "id" =>
                        (int) $voter["id"],

                    "name" =>
                        $voter["full_name"],

                    "photo" =>
                        $voter["selfie"]

                ];

            }


            $voterStmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | RETURN WINNER DATA
    |--------------------------------------------------------------------------
    */

    echo json_encode([

        "success" => true,

        "category" => $category,

        /*
        |--------------------------------------------------------------------------
        | VOTER TURNOUT
        |--------------------------------------------------------------------------
        */

        "total_registered" =>
            $totalRegistered,

        "total_voters" =>
            $totalVoters,

        "voter_percentage" =>
            $voterPercentage,

        /*
        |--------------------------------------------------------------------------
        | WINNERS
        |--------------------------------------------------------------------------
        */

        "winner_count" =>
            count($winners),

        "winners" =>
            $winners

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| NORMAL PAGE
|--------------------------------------------------------------------------
*/

$tab =
    $_GET["tab"] ?? "face_male";


$allowedTabs = [
    "face_male",
    "face_female",
    "star_male",
    "star_female",
    "crowd"
];


if (!in_array($tab, $allowedTabs, true)) {

    $tab = "face_male";

}


/*
|--------------------------------------------------------------------------
| TAB CONFIGURATION
|--------------------------------------------------------------------------
*/

$tabs = [

    "face_male" => [

        "title" =>
            "Face of the Night",

        "subtitle" =>
            "Male",

        "category" =>
            "face_of_the_night",

        "gender" =>
            "male",

        "icon" =>
            "bi-person-standing"

    ],


    "face_female" => [

        "title" =>
            "Face of the Night",

        "subtitle" =>
            "Female",

        "category" =>
            "face_of_the_night",

        "gender" =>
            "female",

        "icon" =>
            "bi-person-standing-dress"

    ],


    "star_male" => [

        "title" =>
            "Star of the Night",

        "subtitle" =>
            "Male",

        "category" =>
            "star_of_the_night",

        "gender" =>
            "male",

        "icon" =>
            "bi-star"

    ],


    "star_female" => [

        "title" =>
            "Star of the Night",

        "subtitle" =>
            "Female",

        "category" =>
            "star_of_the_night",

        "gender" =>
            "female",

        "icon" =>
            "bi-star-fill"

    ],


    "crowd" => [

        "title" =>
            "Crowd Favorite",

        "subtitle" =>
            "All Participants",

        "category" =>
            "crowd_favorite",

        "gender" =>
            "all",

        "icon" =>
            "bi-people-fill"

    ]

];


$currentTab =
    $tabs[$tab];


/*
|--------------------------------------------------------------------------
| LOAD PARTICIPANTS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| No vote information is loaded here.
|
|--------------------------------------------------------------------------
*/

$employees = [];


$sql = "
    SELECT
        id,
        full_name,
        gender,
        selfie,
        department

    FROM employees

    WHERE registration_status <> 'rejected'
";


if (
    $currentTab["gender"] !== "all"
) {

    $sql .= "
        AND gender = ?
    ";

}


$sql .= "
    ORDER BY RAND()
";


if (
    $currentTab["gender"] !== "all"
) {

    $stmt =
        $conn->prepare($sql);


    if ($stmt) {

        $stmt->bind_param(
            "s",
            $currentTab["gender"]
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        while (
            $row =
                $result->fetch_assoc()
        ) {

            $employees[] =
                $row;

        }


        $stmt->close();

    }

} else {

    $result =
        $conn->query($sql);


    if ($result) {

        while (
            $row =
                $result->fetch_assoc()
        ) {

            $employees[] =
                $row;

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
        Foundation Day 2026 | Live Voting
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

            --gold: #d97706;

        }


        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: var(--background);

            color: var(--text);

            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                Arial,
                sans-serif;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .top-header {

            background: #ffffff;

            border-bottom: 1px solid var(--border);

            padding: 22px 35px;

        }


        .header-inner {

            max-width: 1700px;

            margin: auto;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

        }


        .brand {

            display: flex;

            align-items: center;

            gap: 15px;

        }


        .brand-icon {

            width: 55px;

            height: 55px;

            border-radius: 13px;

            background: var(--primary);

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 26px;

        }


        .brand-title {

            font-size: 24px;

            font-weight: 750;

            line-height: 1.1;

        }


        .brand-subtitle {

            color: var(--muted);

            font-size: 14px;

            margin-top: 4px;

        }


        .header-actions {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .header-reveal-button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            padding: 10px 17px;

            border: none;

            border-radius: 9px;

            background: var(--primary);

            color: #ffffff;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            transition: .2s ease;

        }


        .header-reveal-button:hover {

            background: var(--primary-dark);

            transform: translateY(-1px);

        }


        .header-reveal-button:disabled {

            opacity: .65;

            cursor: not-allowed;

            transform: none;

        }


        /*
        |--------------------------------------------------------------------------
        | LIVE BADGE
        |--------------------------------------------------------------------------
        */

        .live-badge {

            display: inline-flex;

            align-items: center;

            gap: 9px;

            padding: 9px 17px;

            border-radius: 999px;

            background: #ecfdf5;

            color: #15803d;

            font-size: 13px;

            font-weight: 700;

            letter-spacing: .5px;

            text-transform: uppercase;

        }


        .live-dot {

            width: 10px;

            height: 10px;

            border-radius: 50%;

            background: #22c55e;

            animation: pulse 1.5s infinite;

        }


        @keyframes pulse {

            0% {
                opacity: 1;
            }

            50% {
                opacity: .35;
            }

            100% {
                opacity: 1;
            }

        }


        /*
        |--------------------------------------------------------------------------
        | MAIN
        |--------------------------------------------------------------------------
        */

        .main-container {

            max-width: 1700px;

            margin: auto;

            padding: 30px 35px 50px;

        }


        /*
        |--------------------------------------------------------------------------
        | PAGE TITLE
        |--------------------------------------------------------------------------
        */

        .page-title {

            text-align: center;

            margin-bottom: 25px;

        }


        .page-title h1 {

            margin: 0;

            font-size: 36px;

            font-weight: 750;

        }


        .page-title p {

            margin: 7px 0 0;

            color: var(--muted);

            font-size: 15px;

        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORY TABS
        |--------------------------------------------------------------------------
        */

        .category-tabs {

            display: flex;

            justify-content: center;

            gap: 8px;

            flex-wrap: wrap;

            margin-bottom: 30px;

        }


        .category-tab {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 9px;

            padding: 12px 18px;

            background: #ffffff;

            border: 1px solid var(--border);

            border-radius: 10px;

            color: var(--text);

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

            transition: .2s ease;

        }


        .category-tab:hover {

            color: var(--primary);

            border-color: var(--primary);

        }


        .category-tab.active {

            background: var(--primary);

            border-color: var(--primary);

            color: #ffffff;

        }


        .category-tab i {

            font-size: 17px;

        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORY HEADING
        |--------------------------------------------------------------------------
        */

        .category-heading {

            text-align: center;

            margin-bottom: 25px;

        }


        .category-heading-icon {

            width: 58px;

            height: 58px;

            margin: 0 auto 12px;

            border-radius: 50%;

            background: #eff6ff;

            color: var(--primary);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

        }


        .category-heading h2 {

            margin: 0;

            font-size: 28px;

            font-weight: 750;

        }


        .category-heading span {

            display: block;

            margin-top: 4px;

            color: var(--muted);

            font-size: 15px;

        }


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE GRID
        |--------------------------------------------------------------------------
        */

        .employee-grid {

            display: grid;

            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | EMPLOYEE CARD
        |--------------------------------------------------------------------------
        */

        .employee-card {

            background: #ffffff;

            border: 1px solid var(--border);

            border-radius: 14px;

            overflow: hidden;

            transition:
                transform .25s ease,
                opacity .25s ease,
                box-shadow .25s ease;

        }


        .employee-card.shuffle {

            animation:
                shuffleCard .45s ease-in-out
                infinite alternate;

        }


        @keyframes shuffleCard {

            from {

                transform:
                    translateY(-8px)
                    rotate(-1deg);

            }

            to {

                transform:
                    translateY(8px)
                    rotate(1deg);

            }

        }


        /*
        |--------------------------------------------------------------------------
        | WINNING CARD
        |--------------------------------------------------------------------------
        */

        .employee-card.winner {

            border: 4px solid var(--gold);

            box-shadow:
                0 0 0 6px rgba(217, 119, 6, .12),
                0 20px 50px rgba(0, 0, 0, .15);

            transform: scale(1.03);

            z-index: 5;

        }


        .employee-card.winner .employee-name {

            color: var(--gold);

        }


        /*
        |--------------------------------------------------------------------------
        | PHOTO
        |--------------------------------------------------------------------------
        */

        .employee-photo {

            width: 100%;

            aspect-ratio: 1 / 1;

            background: #eef2f7;

            overflow: hidden;

        }


        .employee-photo img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;

        }


        .photo-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #9ca3af;

            font-size: 65px;

        }


        /*
        |--------------------------------------------------------------------------
        | NAME
        |--------------------------------------------------------------------------
        */

        .employee-name {

            padding: 17px 15px 19px;

            text-align: center;

            font-size: 17px;

            font-weight: 700;

            line-height: 1.3;

        }


        /*
        |--------------------------------------------------------------------------
        | WINNER OVERLAY
        |--------------------------------------------------------------------------
        */

        .winner-overlay {

            position: fixed;

            inset: 0;

            z-index: 9999;

            background:
                rgba(15, 23, 42, .94);

            display: none;

            align-items: center;

            justify-content: center;

            padding: 25px;

            overflow-y: auto;

        }


        .winner-overlay.show {

            display: flex;

            animation: fadeIn .35s ease;

        }


        @keyframes fadeIn {

            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }

        }


        .winner-content {

            width: 100%;

            max-width: 650px;

            text-align: center;

            color: #ffffff;

            padding: 20px 0;

        }


        .winner-trophy {

            font-size: 65px;

            color: #fbbf24;

            margin-bottom: 10px;

            animation: trophyPop .6s ease;

        }


        @keyframes trophyPop {

            0% {

                transform:
                    scale(.2)
                    rotate(-15deg);

            }

            70% {

                transform:
                    scale(1.15)
                    rotate(5deg);

            }

            100% {

                transform:
                    scale(1)
                    rotate(0);

            }

        }


        .winner-label {

            font-size: 18px;

            font-weight: 700;

            letter-spacing: 3px;

            text-transform: uppercase;

            color: #bfdbfe;

        }


        .winner-title {

            margin-top: 5px;

            font-size: 42px;

            font-weight: 800;

        }


        .winner-photo {

            width: 240px;

            height: 240px;

            margin: 25px auto 18px;

            border-radius: 50%;

            overflow: hidden;

            border: 6px solid #fbbf24;

            background: #ffffff;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .35);

        }


        .winner-photo img {

            width: 100%;

            height: 100%;

            object-fit: cover;

        }


        .winner-name {

            font-size: 36px;

            font-weight: 800;

            margin-top: 10px;

        }


        .winner-votes {

            margin-top: 10px;

            font-size: 25px;

            font-weight: 700;

            color: #fbbf24;

        }


        /*
        |--------------------------------------------------------------------------
        | VOTER TURNOUT
        |--------------------------------------------------------------------------
        */

        .winner-turnout {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            margin-top: 14px;

            padding: 9px 17px;

            border-radius: 999px;

            background: rgba(255, 255, 255, .10);

            border: 1px solid rgba(255, 255, 255, .16);

            color: #e2e8f0;

            font-size: 14px;

            font-weight: 600;

        }


        .winner-turnout i {

            color: #60a5fa;

        }


        .winner-turnout strong {

            color: #ffffff;

            font-weight: 800;

        }


        .winner-turnout-details {

            margin-top: 7px;

            color: #94a3b8;

            font-size: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | VOTER GROUP
        |--------------------------------------------------------------------------
        */

        .voter-section {

            margin-top: 20px;

        }


        .voter-label {

            color: #cbd5e1;

            font-size: 12px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 10px;

        }


        .voter-group {

            display: flex;

            align-items: center;

            justify-content: center;

            min-height: 48px;

        }


        .voter-avatar {

            position: relative;

            width: 48px;

            height: 48px;

            border-radius: 50%;

            overflow: hidden;

            background: #e5e7eb;

            border: 3px solid #0f172a;

            margin-left: -8px;

            flex-shrink: 0;

            box-shadow:
                0 3px 8px rgba(0, 0, 0, .25);

            transition:
                transform .2s ease,
                z-index .2s ease;

        }


        .voter-avatar:first-child {

            margin-left: 0;

        }


        .voter-avatar:hover {

            transform:
                translateY(-5px)
                scale(1.08);

            z-index: 20;

        }


        .voter-avatar img {

            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;

        }


        .voter-avatar-placeholder {

            width: 100%;

            height: 100%;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #64748b;

            font-size: 21px;

        }


        .voter-more {

            width: 48px;

            height: 48px;

            margin-left: -8px;

            border-radius: 50%;

            border: 3px solid #0f172a;

            background: #334155;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            font-weight: 700;

            flex-shrink: 0;

        }


        .voter-names {

            margin-top: 8px;

            color: #94a3b8;

            font-size: 12px;

        }


        .winner-tie {

            font-size: 20px;

            color: #e5e7eb;

            margin-top: 10px;

        }


        .close-winner {

            margin-top: 30px;

            padding: 10px 22px;

            border: 1px solid rgba(255,255,255,.3);

            background: transparent;

            color: #ffffff;

            border-radius: 8px;

            font-weight: 600;

            cursor: pointer;

        }


        .close-winner:hover {

            background: rgba(255,255,255,.1);

        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .screen-footer {

            text-align: center;

            margin-top: 25px;

            color: var(--muted);

            font-size: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 1350px) {

            .employee-grid {

                grid-template-columns:
                    repeat(4, minmax(0, 1fr));

            }

        }


        @media (max-width: 1050px) {

            .employee-grid {

                grid-template-columns:
                    repeat(3, minmax(0, 1fr));

            }

        }


        @media (max-width: 768px) {

            .header-inner {

                flex-direction: column;

                align-items: flex-start;

            }


            .header-actions {

                width: 100%;

                flex-wrap: wrap;

            }


            .header-reveal-button {

                flex: 1;

            }


            .main-container {

                padding: 25px 15px 40px;

            }


            .page-title h1 {

                font-size: 28px;

            }


            .category-tabs {

                display: grid;

                grid-template-columns: 1fr 1fr;

            }


            .category-tab {

                font-size: 12px;

                padding: 11px 8px;

            }


            .employee-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }


            .winner-title {

                font-size: 30px;

            }


            .winner-name {

                font-size: 28px;

            }


            .winner-photo {

                width: 190px;

                height: 190px;

            }


            .winner-turnout {

                font-size: 13px;

            }


            .voter-avatar,
            .voter-more {

                width: 42px;

                height: 42px;

            }

        }


        @media (max-width: 480px) {

            .employee-grid {

                grid-template-columns: 1fr;

            }


            .category-tabs {

                grid-template-columns: 1fr;

            }


            .winner-photo {

                width: 165px;

                height: 165px;

            }


            .winner-name {

                font-size: 25px;

            }


            .winner-votes {

                font-size: 22px;

            }


            .winner-turnout {

                padding: 8px 13px;

                font-size: 12px;

            }

        }

    </style>

</head>


<body>


<!-- ============================================================
     HEADER
============================================================ -->

<header class="top-header">

    <div class="header-inner">


        <div class="brand">

            <div class="brand-icon">

                <i class="bi bi-stars"></i>

            </div>


            <div>

                <div class="brand-title">

                    Foundation Day 2026

                </div>


                <div class="brand-subtitle">

                    Live Voting

                </div>

            </div>

        </div>


        <div class="header-actions">


            <div class="live-badge">

                <span class="live-dot"></span>

                Voting Live

            </div>


            <?php if (!empty($employees)): ?>

                <button
                    type="button"
                    class="header-reveal-button"
                    id="revealButton"
                    onclick="revealWinner()"
                >

                    <i class="bi bi-shuffle"></i>

                    Shuffle & Reveal Winner

                </button>

            <?php endif; ?>


        </div>


    </div>

</header>



<!-- ============================================================
     MAIN
============================================================ -->

<main class="main-container">


    <div class="page-title">

        <h1>

            Foundation Day Voting

        </h1>


        <p>

            Explore our participants by category

        </p>

    </div>



    <!-- ========================================================
         CATEGORY TABS
    ======================================================== -->

    <div class="category-tabs">


        <a
            href="results.php?tab=face_male"
            class="category-tab
            <?= $tab === 'face_male' ? 'active' : ''; ?>"
        >

            <i class="bi bi-person-standing"></i>

            Face of the Night — Male

        </a>


        <a
            href="results.php?tab=face_female"
            class="category-tab
            <?= $tab === 'face_female' ? 'active' : ''; ?>"
        >

            <i class="bi bi-person-standing-dress"></i>

            Face of the Night — Female

        </a>


        <a
            href="results.php?tab=star_male"
            class="category-tab
            <?= $tab === 'star_male' ? 'active' : ''; ?>"
        >

            <i class="bi bi-star"></i>

            Star of the Night — Male

        </a>


        <a
            href="results.php?tab=star_female"
            class="category-tab
            <?= $tab === 'star_female' ? 'active' : ''; ?>"
        >

            <i class="bi bi-star-fill"></i>

            Star of the Night — Female

        </a>


        <a
            href="results.php?tab=crowd"
            class="category-tab
            <?= $tab === 'crowd' ? 'active' : ''; ?>"
        >

            <i class="bi bi-people-fill"></i>

            Crowd Favorite

        </a>


    </div>



    <!-- ========================================================
         CATEGORY HEADING
    ======================================================== -->

    <div class="category-heading">


        <div class="category-heading-icon">

            <i class="bi <?= htmlspecialchars(
                $currentTab["icon"]
            ); ?>"></i>

        </div>


        <h2>

            <?= htmlspecialchars(
                $currentTab["title"]
            ); ?>

            —

            <?= htmlspecialchars(
                $currentTab["subtitle"]
            ); ?>

        </h2>


        <span>

            <?= count($employees); ?> participants

        </span>


    </div>



    <!-- ========================================================
         EMPLOYEE GRID
    ======================================================== -->

    <div
        class="employee-grid"
        id="employeeGrid"
    >


        <?php if (empty($employees)): ?>


            <div
                style="
                    grid-column:1/-1;
                    background:#ffffff;
                    border:1px solid #e5e7eb;
                    border-radius:14px;
                    padding:60px 20px;
                    text-align:center;
                    color:#6b7280;
                "
            >

                <i
                    class="bi bi-people"
                    style="
                        font-size:45px;
                        display:block;
                        margin-bottom:12px;
                    "
                ></i>


                No participants available.

            </div>


        <?php else: ?>


            <?php foreach ($employees as $employee): ?>


                <div
                    class="employee-card"
                    data-employee-id="<?= (int) $employee["id"]; ?>"
                >


                    <div class="employee-photo">


                        <?php if (!empty($employee["selfie"])): ?>


                            <img
                                src="../<?= htmlspecialchars(
                                    $employee["selfie"]
                                ); ?>"
                                alt="<?= htmlspecialchars(
                                    $employee["full_name"]
                                ); ?>"
                                onerror="
                                    this.style.display='none';
                                    this.nextElementSibling.style.display='flex';
                                "
                            >


                            <div
                                class="photo-placeholder"
                                style="display:none;"
                            >

                                <i class="bi bi-person-fill"></i>

                            </div>


                        <?php else: ?>


                            <div class="photo-placeholder">

                                <i class="bi bi-person-fill"></i>

                            </div>


                        <?php endif; ?>


                    </div>


                    <div class="employee-name">

                        <?= htmlspecialchars(
                            $employee["full_name"]
                        ); ?>

                    </div>


                </div>


            <?php endforeach; ?>


        <?php endif; ?>


    </div>



    <div class="screen-footer">

        <i class="bi bi-info-circle"></i>

        Vote results remain hidden until the winner is revealed.

    </div>


</main>



<!-- ============================================================
     WINNER OVERLAY
============================================================ -->

<div
    class="winner-overlay"
    id="winnerOverlay"
>


    <div class="winner-content">


        <div class="winner-trophy">

            <i class="bi bi-trophy-fill"></i>

        </div>


        <div class="winner-label">

            Winner

        </div>


        <div
            class="winner-title"
            id="winnerCategory"
        >

            <?= htmlspecialchars(
                $currentTab["title"]
            ); ?>

            —

            <?= htmlspecialchars(
                $currentTab["subtitle"]
            ); ?>

        </div>


        <div
            class="winner-photo"
            id="winnerPhoto"
        ></div>


        <div
            class="winner-name"
            id="winnerName"
        ></div>


        <div
            class="winner-votes"
            id="winnerVotes"
        ></div>


        <!-- ====================================================
             VOTER TURNOUT
        ==================================================== -->

        <div
            class="winner-turnout"
            id="winnerTurnout"
        >

            <i class="bi bi-people-fill"></i>

            <strong id="voterPercentage">
                0%
            </strong>

            Voter Turnout

        </div>


        <div
            class="winner-turnout-details"
            id="voterTurnoutDetails"
        ></div>


        <!-- ====================================================
             VOTER PHOTOS
        ==================================================== -->

        <div
            class="voter-section"
            id="voterSection"
            style="display:none;"
        >

            <div class="voter-label">

                Voted for this winner

            </div>


            <div
                class="voter-group"
                id="voterGroup"
            ></div>


            <div
                class="voter-names"
                id="voterNames"
            ></div>

        </div>


        <div
            class="winner-tie"
            id="winnerTie"
        ></div>


        <button
            type="button"
            class="close-winner"
            onclick="closeWinner()"
        >

            Back to Participants

        </button>


    </div>

</div>



<script>

/*
|--------------------------------------------------------------------------
| WINNER REVEAL
|--------------------------------------------------------------------------
*/

function revealWinner() {

    const button =
        document.getElementById(
            "revealButton"
        );


    const grid =
        document.getElementById(
            "employeeGrid"
        );


    const cards =
        Array.from(
            grid.querySelectorAll(
                ".employee-card"
            )
        );


    if (!cards.length) {

        return;

    }


    button.disabled =
        true;


    button.innerHTML = `
        <i class="bi bi-arrow-repeat"></i>
        Shuffling...
    `;


    /*
    |--------------------------------------------------------------------------
    | Shuffle cards visually
    |--------------------------------------------------------------------------
    */

    let shuffleCount = 0;


    const shuffleInterval =
        setInterval(
            function () {

                shuffleCards();

                shuffleCount++;


                if (shuffleCount >= 12) {

                    clearInterval(
                        shuffleInterval
                    );

                }

            },
            350
        );


    /*
    |--------------------------------------------------------------------------
    | Get actual winner from server
    |--------------------------------------------------------------------------
    */

    fetch(
        "results.php?action=reveal&tab=<?= urlencode($tab); ?>",
        {
            method: "GET",
            cache: "no-store"
        }
    )

    .then(
        function (response) {

            return response.json();

        }
    )

    .then(
        function (data) {

            setTimeout(
                function () {

                    if (!data.success) {

                        alert(
                            data.message ||
                            "Unable to determine winner."
                        );

                        resetRevealButton();

                        return;

                    }


                    showWinner(data);

                },
                4500
            );

        }
    )

    .catch(
        function () {

            clearInterval(
                shuffleInterval
            );


            alert(
                "Unable to retrieve the voting result."
            );


            resetRevealButton();

        }
    );

}


/*
|--------------------------------------------------------------------------
| VISUAL SHUFFLE
|--------------------------------------------------------------------------
*/

function shuffleCards() {

    const grid =
        document.getElementById(
            "employeeGrid"
        );


    const cards =
        Array.from(
            grid.querySelectorAll(
                ".employee-card"
            )
        );


    for (
        let i = cards.length - 1;
        i > 0;
        i--
    ) {

        const j =
            Math.floor(
                Math.random() * (i + 1)
            );


        [
            cards[i],
            cards[j]
        ] =
        [
            cards[j],
            cards[i]
        ];

    }


    cards.forEach(
        function (card) {

            card.classList.add(
                "shuffle"
            );


            grid.appendChild(
                card
            );

        }
    );


    setTimeout(
        function () {

            cards.forEach(
                function (card) {

                    card.classList.remove(
                        "shuffle"
                    );

                }
            );

        },
        300
    );

}


/*
|--------------------------------------------------------------------------
| SHOW WINNER
|--------------------------------------------------------------------------
*/

function showWinner(data) {

    const overlay =
        document.getElementById(
            "winnerOverlay"
        );


    const winnerName =
        document.getElementById(
            "winnerName"
        );


    const winnerVotes =
        document.getElementById(
            "winnerVotes"
        );


    const winnerPhoto =
        document.getElementById(
            "winnerPhoto"
        );


    const winnerTie =
        document.getElementById(
            "winnerTie"
        );


    const voterSection =
        document.getElementById(
            "voterSection"
        );


    const voterGroup =
        document.getElementById(
            "voterGroup"
        );


    const voterNames =
        document.getElementById(
            "voterNames"
        );


    const voterPercentage =
        document.getElementById(
            "voterPercentage"
        );


    const voterTurnoutDetails =
        document.getElementById(
            "voterTurnoutDetails"
        );


    const winners =
        data.winners || [];


    if (!winners.length) {

        alert(
            "No winner data was returned."
        );


        resetRevealButton();

        return;

    }


    /*
    |--------------------------------------------------------------------------
    | VOTER TURNOUT
    |--------------------------------------------------------------------------
    */

    const percentage =
        Number(
            data.voter_percentage || 0
        );


    const totalVoters =
        Number(
            data.total_voters || 0
        );


    const totalRegistered =
        Number(
            data.total_registered || 0
        );


    voterPercentage.textContent =
        percentage.toFixed(
            percentage % 1 === 0
                ? 0
                : 1
        ) + "%";


    voterTurnoutDetails.textContent =
        totalVoters +
        " of " +
        totalRegistered +
        " registered employees voted";


    /*
    |--------------------------------------------------------------------------
    | Clear Previous Winner State
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            ".employee-card.winner"
        )
        .forEach(
            function (card) {

                card.classList.remove(
                    "winner"
                );

            }
        );


    voterSection.style.display =
        "none";


    voterGroup.innerHTML =
        "";


    voterNames.textContent =
        "";


    winnerTie.textContent =
        "";


    /*
    |--------------------------------------------------------------------------
    | SINGLE WINNER
    |--------------------------------------------------------------------------
    */

    if (winners.length === 1) {

        const winner =
            winners[0];


        winnerName.textContent =
            winner.name;


        winnerVotes.textContent =
            winner.votes +
            (
                winner.votes === 1
                    ? " Vote"
                    : " Votes"
            );


        /*
        |--------------------------------------------------------------------------
        | Winner Photo
        |--------------------------------------------------------------------------
        */

        if (winner.photo) {

            winnerPhoto.innerHTML = `
                <img
                    src="../${escapeHtml(
                        winner.photo
                    )}"
                    alt="${escapeHtml(
                        winner.name
                    )}"
                >
            `;

        } else {

            winnerPhoto.innerHTML = `
                <div
                    style="
                        width:100%;
                        height:100%;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:#9ca3af;
                        font-size:70px;
                    "
                >
                    <i class="bi bi-person-fill"></i>
                </div>
            `;

        }


        /*
        |--------------------------------------------------------------------------
        | Show Voters
        |--------------------------------------------------------------------------
        */

        renderVoters(
            winner.voters || [],
            voterSection,
            voterGroup,
            voterNames
        );


        /*
        |--------------------------------------------------------------------------
        | Highlight Winning Card
        |--------------------------------------------------------------------------
        */

        const winningCard =
            document.querySelector(
                '[data-employee-id="' +
                winner.id +
                '"]'
            );


        if (winningCard) {

            winningCard.classList.add(
                "winner"
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | TIE
    |--------------------------------------------------------------------------
    */

    else {

        winnerName.textContent =
            winners
                .map(
                    function (winner) {

                        return winner.name;

                    }
                )
                .join(" • ");


        winnerVotes.textContent =
            winners[0].votes +
            " Votes Each";


        winnerTie.textContent =
            "The category is tied between " +
            winners.length +
            " participants.";


        /*
        |--------------------------------------------------------------------------
        | Main Photo
        |--------------------------------------------------------------------------
        */

        const firstWinner =
            winners[0];


        if (firstWinner.photo) {

            winnerPhoto.innerHTML = `
                <img
                    src="../${escapeHtml(
                        firstWinner.photo
                    )}"
                    alt="${escapeHtml(
                        firstWinner.name
                    )}"
                >
            `;

        } else {

            winnerPhoto.innerHTML = `
                <div
                    style="
                        width:100%;
                        height:100%;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        color:#9ca3af;
                        font-size:70px;
                    "
                >
                    <i class="bi bi-people-fill"></i>
                </div>
            `;

        }


        /*
        |--------------------------------------------------------------------------
        | Highlight All Tied Candidates
        |--------------------------------------------------------------------------
        */

        winners.forEach(
            function (winner) {

                const card =
                    document.querySelector(
                        '[data-employee-id="' +
                        winner.id +
                        '"]'
                    );


                if (card) {

                    card.classList.add(
                        "winner"
                    );

                }

            }
        );


        /*
        |--------------------------------------------------------------------------
        | TIED WINNERS VOTERS
        |--------------------------------------------------------------------------
        */

        renderTieVoters(
            winners,
            voterSection,
            voterGroup,
            voterNames
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW OVERLAY
    |--------------------------------------------------------------------------
    */

    overlay.classList.add(
        "show"
    );

}


/*
|--------------------------------------------------------------------------
| RENDER VOTER PHOTOS
|--------------------------------------------------------------------------
*/

function renderVoters(
    voters,
    voterSection,
    voterGroup,
    voterNames
) {

    if (!voters.length) {

        voterSection.style.display =
            "none";

        return;

    }


    voterSection.style.display =
        "block";


    const maxVisible =
        8;


    const visibleVoters =
        voters.slice(
            0,
            maxVisible
        );


    const remaining =
        voters.length -
        visibleVoters.length;


    voterGroup.innerHTML =
        "";


    /*
    |--------------------------------------------------------------------------
    | Voter Avatars
    |--------------------------------------------------------------------------
    */

    visibleVoters.forEach(
        function (voter) {

            const avatar =
                document.createElement(
                    "div"
                );


            avatar.className =
                "voter-avatar";


            avatar.title =
                voter.name;


            if (voter.photo) {

                avatar.innerHTML = `
                    <img
                        src="../${escapeHtml(
                            voter.photo
                        )}"
                        alt="${escapeHtml(
                            voter.name
                        )}"
                        onerror="
                            this.style.display='none';
                            this.nextElementSibling.style.display='flex';
                        "
                    >

                    <div
                        class="voter-avatar-placeholder"
                        style="display:none;"
                    >
                        <i class="bi bi-person-fill"></i>
                    </div>
                `;

            } else {

                avatar.innerHTML = `
                    <div
                        class="voter-avatar-placeholder"
                    >
                        <i class="bi bi-person-fill"></i>
                    </div>
                `;

            }


            voterGroup.appendChild(
                avatar
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Remaining Count
    |--------------------------------------------------------------------------
    */

    if (remaining > 0) {

        const more =
            document.createElement(
                "div"
            );


        more.className =
            "voter-more";


        more.textContent =
            "+" + remaining;


        more.title =
            remaining +
            " more voter" +
            (
                remaining === 1
                    ? ""
                    : "s"
            );


        voterGroup.appendChild(
            more
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Names
    |--------------------------------------------------------------------------
    */

    const names =
        visibleVoters
            .slice(0, 3)
            .map(
                function (voter) {

                    return voter.name;

                }
            );


    if (names.length) {

        voterNames.textContent =
            names.join(", ") +
            (
                voters.length > 3
                    ? " and others"
                    : ""
            );

    }

}


/*
|--------------------------------------------------------------------------
| RENDER TIED WINNER VOTERS
|--------------------------------------------------------------------------
*/

function renderTieVoters(
    winners,
    voterSection,
    voterGroup,
    voterNames
) {

    const allVoters =
        [];


    winners.forEach(
        function (winner) {

            (
                winner.voters || []
            ).forEach(
                function (voter) {

                    allVoters.push({

                        winnerName:
                            winner.name,

                        voter:
                            voter

                    });

                }
            );

        }
    );


    if (!allVoters.length) {

        voterSection.style.display =
            "none";

        return;

    }


    voterSection.style.display =
        "block";


    voterGroup.innerHTML =
        "";


    /*
    |--------------------------------------------------------------------------
    | For ties, show a compact combined group
    |--------------------------------------------------------------------------
    */

    const maxVisible =
        8;


    const visible =
        allVoters.slice(
            0,
            maxVisible
        );


    visible.forEach(
        function (item) {

            const avatar =
                document.createElement(
                    "div"
                );


            avatar.className =
                "voter-avatar";


            avatar.title =
                item.voter.name +
                " → " +
                item.winnerName;


            if (item.voter.photo) {

                avatar.innerHTML = `
                    <img
                        src="../${escapeHtml(
                            item.voter.photo
                        )}"
                        alt="${escapeHtml(
                            item.voter.name
                        )}"
                    >
                `;

            } else {

                avatar.innerHTML = `
                    <div
                        class="voter-avatar-placeholder"
                    >
                        <i class="bi bi-person-fill"></i>
                    </div>
                `;

            }


            voterGroup.appendChild(
                avatar
            );

        }
    );


    const remaining =
        allVoters.length -
        visible.length;


    if (remaining > 0) {

        const more =
            document.createElement(
                "div"
            );


        more.className =
            "voter-more";


        more.textContent =
            "+" + remaining;


        voterGroup.appendChild(
            more
        );

    }


    voterNames.textContent =
        "Voters shown for the tied participants.";

}


/*
|--------------------------------------------------------------------------
| CLOSE WINNER
|--------------------------------------------------------------------------
*/

function closeWinner() {

    const overlay =
        document.getElementById(
            "winnerOverlay"
        );


    overlay.classList.remove(
        "show"
    );


    document
        .querySelectorAll(
            ".employee-card.winner"
        )
        .forEach(
            function (card) {

                card.classList.remove(
                    "winner"
                );

            }
        );


    resetRevealButton();

}


/*
|--------------------------------------------------------------------------
| RESET BUTTON
|--------------------------------------------------------------------------
*/

function resetRevealButton() {

    const button =
        document.getElementById(
            "revealButton"
        );


    if (!button) {

        return;

    }


    button.disabled =
        false;


    button.innerHTML = `
        <i class="bi bi-shuffle"></i>
        Shuffle & Reveal Winner
    `;

}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return String(value)

        .replace(
            /&/g,
            "&amp;"
        )

        .replace(
            /</g,
            "&lt;"
        )

        .replace(
            />/g,
            "&gt;"
        )

        .replace(
            /"/g,
            "&quot;"
        )

        .replace(
            /'/g,
            "&#039;"
        );

}

</script>


</body>

</html>