<?php

session_start();

require_once "includes/db.php";

/*
|--------------------------------------------------------------------------
| Device Token
|--------------------------------------------------------------------------
*/

if (empty($_COOKIE["fd_device_token"])) {

    try {
        $deviceToken = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $deviceToken = hash(
            "sha256",
            uniqid(mt_rand(), true)
        );
    }

    setcookie(
        "fd_device_token",
        $deviceToken,
        array(
            "expires"  => time() + (365 * 24 * 60 * 60),
            "path"     => "/",
            "secure"   => (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"),
            "httponly" => true,
            "samesite" => "Lax"
        )
    );

} else {

    $deviceToken = $_COOKIE["fd_device_token"];
}


/*
|--------------------------------------------------------------------------
| Check Existing Device
|--------------------------------------------------------------------------
*/

$existingEmployee = null;

if (!empty($deviceToken)) {

    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            voting_status
        FROM employees
        WHERE device_token = ?
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "s",
            $deviceToken
        );

        $stmt->execute();

        $result =
            $stmt->get_result();

        $existingEmployee =
            $result->fetch_assoc();

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Existing Employee
|--------------------------------------------------------------------------
|
| If this device has already registered:
|
| - Already voted  -> show registration block
| - Not voted      -> show registration block
|
| We do NOT redirect to voting.php anymore.
|
*/

$existingRegistration = false;

if ($existingEmployee) {

    $existingRegistration = true;
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$errors = array();

$fullName = "";
$gender = "";

$registrationSuccess = false;


/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !$existingRegistration
) {

    $fullName = trim(
        isset($_POST["full_name"])
            ? $_POST["full_name"]
            : ""
    );

    $gender = isset($_POST["gender"])
        ? strtolower(trim($_POST["gender"]))
        : "";


    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */

    if ($fullName === "") {

        $errors[] =
            "Please enter your full name.";

    } elseif (strlen($fullName) < 3) {

        $errors[] =
            "Please enter a valid full name.";

    } elseif (strlen($fullName) > 150) {

        $errors[] =
            "Full name must not exceed 150 characters.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Gender
    |--------------------------------------------------------------------------
    */

    if (
        !in_array(
            $gender,
            array("male", "female"),
            true
        )
    ) {

        $errors[] =
            "Please select your gender.";
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Name
    |--------------------------------------------------------------------------
    */

    $normalizedName =
        preg_replace(
            "/\s+/",
            " ",
            strtolower($fullName)
        );

    $normalizedName =
        trim($normalizedName);


    /*
    |--------------------------------------------------------------------------
    | Duplicate Name Check
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $conn->prepare("
            SELECT
                id,
                full_name,
                voting_status
            FROM employees
            WHERE normalized_name = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "s",
                $normalizedName
            );

            $stmt->execute();

            $result =
                $stmt->get_result();

            $duplicate =
                $result->fetch_assoc();

            if ($duplicate) {

                $errors[] =
                    "This employee is already registered.";

            }

            $stmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Selfie Validation
    |--------------------------------------------------------------------------
    */

    $selfiePath = null;

    if (
        empty($errors) &&
        (
            !isset($_FILES["selfie"]) ||
            $_FILES["selfie"]["error"] !== UPLOAD_ERR_OK
        )
    ) {

        $errors[] =
            "Please take a selfie or upload a photo.";
    }


    /*
    |--------------------------------------------------------------------------
    | Process Image
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $file =
            $_FILES["selfie"];


        /*
        |--------------------------------------------------------------------------
        | Maximum Original Upload
        |--------------------------------------------------------------------------
        */

        if (
            $file["size"] >
            (5 * 1024 * 1024)
        ) {

            $errors[] =
                "The photo must not exceed 5 MB.";
        }


        /*
        |--------------------------------------------------------------------------
        | Validate MIME Type
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );

            $mimeType =
                $finfo->file(
                    $file["tmp_name"]
                );


            $allowedTypes = array(
                "image/jpeg",
                "image/png",
                "image/webp"
            );


            if (
                !in_array(
                    $mimeType,
                    $allowedTypes,
                    true
                )
            ) {

                $errors[] =
                    "Please upload a JPG, PNG, or WEBP image.";
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Save Image
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            $uploadDirectory =
                __DIR__ .
                "/uploads/employees/";


            if (!is_dir($uploadDirectory)) {

                mkdir(
                    $uploadDirectory,
                    0775,
                    true
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Generate Filename
            |--------------------------------------------------------------------------
            */

            try {

                $fileName =
                    bin2hex(
                        random_bytes(16)
                    ) .
                    ".jpg";

            } catch (Exception $e) {

                $fileName =
                    sha1(
                        uniqid(
                            mt_rand(),
                            true
                        )
                    ) .
                    ".jpg";
            }


            $targetPath =
                $uploadDirectory .
                $fileName;


            /*
            |--------------------------------------------------------------------------
            | Image Processing
            |--------------------------------------------------------------------------
            */

            $saved = false;


            if (
                function_exists(
                    "imagecreatefromjpeg"
                )
            ) {

                $sourceImage = false;


                switch ($mimeType) {

                    case "image/jpeg":

                        $sourceImage =
                            @imagecreatefromjpeg(
                                $file["tmp_name"]
                            );

                        break;


                    case "image/png":

                        $sourceImage =
                            @imagecreatefrompng(
                                $file["tmp_name"]
                            );

                        break;


                    case "image/webp":

                        if (
                            function_exists(
                                "imagecreatefromwebp"
                            )
                        ) {

                            $sourceImage =
                                @imagecreatefromwebp(
                                    $file["tmp_name"]
                                );
                        }

                        break;
                }


                if (
                    $sourceImage !== false
                ) {

                    $originalWidth =
                        imagesx(
                            $sourceImage
                        );

                    $originalHeight =
                        imagesy(
                            $sourceImage
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Maximum Dimensions
                    |--------------------------------------------------------------------------
                    */

                    $maxWidth = 1000;
                    $maxHeight = 1000;


                    $scale =
                        min(
                            1,
                            $maxWidth / $originalWidth,
                            $maxHeight / $originalHeight
                        );


                    $newWidth =
                        max(
                            1,
                            (int) round(
                                $originalWidth * $scale
                            )
                        );


                    $newHeight =
                        max(
                            1,
                            (int) round(
                                $originalHeight * $scale
                            )
                        );


                    $outputImage =
                        imagecreatetruecolor(
                            $newWidth,
                            $newHeight
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | White Background
                    |--------------------------------------------------------------------------
                    */

                    $white =
                        imagecolorallocate(
                            $outputImage,
                            255,
                            255,
                            255
                        );


                    imagefill(
                        $outputImage,
                        0,
                        0,
                        $white
                    );


                    imagecopyresampled(
                        $outputImage,
                        $sourceImage,
                        0,
                        0,
                        0,
                        0,
                        $newWidth,
                        $newHeight,
                        $originalWidth,
                        $originalHeight
                    );


                    $saved =
                        imagejpeg(
                            $outputImage,
                            $targetPath,
                            82
                        );


                    imagedestroy(
                        $sourceImage
                    );

                    imagedestroy(
                        $outputImage
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | GD Fallback
            |--------------------------------------------------------------------------
            */

            if (!$saved) {

                $saved =
                    move_uploaded_file(
                        $file["tmp_name"],
                        $targetPath
                    );
            }


            if (!$saved) {

                $errors[] =
                    "Unable to save the uploaded photo.";

            } else {

                $selfiePath =
                    "uploads/employees/" .
                    $fileName;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Employee
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        $stmt = $conn->prepare("
            INSERT INTO employees (
                full_name,
                normalized_name,
                gender,
                device_token,
                selfie,
                registration_status,
                voting_status
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                'approved',
                'not_voted'
            )
        ");


        if (!$stmt) {

            if ($selfiePath) {

                @unlink(
                    __DIR__ .
                    "/" .
                    $selfiePath
                );
            }


            $errors[] =
                "Unable to prepare registration.";

        } else {

            $stmt->bind_param(
                "sssss",
                $fullName,
                $normalizedName,
                $gender,
                $deviceToken,
                $selfiePath
            );


            if ($stmt->execute()) {

                $employeeId =
                    $stmt->insert_id;


                /*
                |--------------------------------------------------------------------------
                | Store Session
                |--------------------------------------------------------------------------
                */

                $_SESSION["voting_employee_id"] =
                    (int) $employeeId;

                $_SESSION["voting_device_token"] =
                    $deviceToken;


                $_SESSION["registration_success"] =
                    true;


                /*
                |--------------------------------------------------------------------------
                | Registration Successful
                |--------------------------------------------------------------------------
                */

                $registrationSuccess =
                    true;


            } else {

                /*
                |--------------------------------------------------------------------------
                | Remove Image If DB Insert Failed
                |--------------------------------------------------------------------------
                */

                if ($selfiePath) {

                    @unlink(
                        __DIR__ .
                        "/" .
                        $selfiePath
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Duplicate Registration
                |--------------------------------------------------------------------------
                */

                if (
                    $conn->errno === 1062
                ) {

                    $errors[] =
                        "This employee is already registered.";

                } else {

                    $errors[] =
                        "Registration failed. Please try again.";
                }
            }


            $stmt->close();
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
        Foundation Day 2026 | Registration
    </title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

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

            min-height: 100vh;

            background:
                var(--background);

            color:
                var(--text);

            font-family:
                Inter,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        .page {

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 30px 15px;
        }


        .registration-card {

            width: 100%;

            max-width: 560px;

            background: #ffffff;

            border:
                1px solid var(--border);

            border-radius: 16px;

            box-shadow:
                0 12px 35px
                rgba(15, 23, 42, 0.08);

            overflow: hidden;
        }


        /* =========================================================
           HEADER
        ========================================================= */

        .card-header {

            padding:
                28px 30px 22px;

            background: #ffffff;

            border-bottom:
                1px solid var(--border);
        }


        .brand {

            display: flex;

            align-items: center;

            gap: 14px;
        }


        .brand-icon {

            width: 48px;

            height: 48px;

            border-radius: 12px;

            background:
                var(--primary);

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 22px;
        }


        .title {

            margin: 0;

            font-size: 22px;

            font-weight: 800;

            color: #111827;
        }


        .subtitle {

            margin-top: 3px;

            font-size: 13px;

            color:
                var(--muted);
        }


        /* =========================================================
           BODY
        ========================================================= */

        .card-body {

            padding: 30px;
        }


        .section-title {

            margin-bottom: 22px;
        }


        .section-title h2 {

            margin: 0;

            font-size: 20px;

            font-weight: 750;
        }


        .section-title p {

            margin:
                5px 0 0;

            color:
                var(--muted);

            font-size: 14px;
        }


        /* =========================================================
           FORM
        ========================================================= */

        .form-label {

            font-weight: 650;

            font-size: 14px;

            margin-bottom: 7px;
        }


        .form-control,
        .form-select {

            min-height: 46px;

            border-color:
                var(--border);

            border-radius: 9px;
        }


        .form-control:focus,
        .form-select:focus {

            border-color:
                var(--primary);

            box-shadow:
                0 0 0 3px
                rgba(29, 78, 216, 0.10);
        }


        /* =========================================================
           PHOTO
        ========================================================= */

        .photo-section {

            margin-top: 22px;
        }


        .camera-container {

            width: 100%;

            height: 320px;

            background:
                #f8fafc;

            border:
                1px solid var(--border);

            border-radius: 12px;

            overflow: hidden;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        #camera,
        #preview {

            width: 100%;

            height: 100%;

            object-fit: cover;
        }


        .camera-placeholder {

            text-align: center;

            color:
                var(--muted);

            padding: 25px;
        }


        .camera-placeholder i {

            display: block;

            font-size: 44px;

            margin-bottom: 10px;

            color:
                #94a3b8;
        }


        .camera-loading {

            position: absolute;

            inset: 0;

            background:
                rgba(248, 250, 252, 0.95);

            display: none;

            align-items: center;

            justify-content: center;

            gap: 9px;

            z-index: 5;

            color:
                var(--muted);

            font-size: 14px;
        }


        .camera-controls {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 12px;
        }


        .camera-controls .btn {

            flex: 1 1 auto;

            min-height: 43px;
        }


        .photo-note {

            margin-top: 9px;

            font-size: 12px;

            color:
                var(--muted);
        }


        /* =========================================================
           REGISTER BUTTON
        ========================================================= */

        .btn-register {

            width: 100%;

            min-height: 48px;

            margin-top: 24px;

            border-radius: 9px;

            background:
                var(--primary);

            border-color:
                var(--primary);

            font-weight: 700;
        }


        .btn-register:hover {

            background:
                var(--primary-dark);

            border-color:
                var(--primary-dark);
        }


        /* =========================================================
           SUCCESS
        ========================================================= */

        .success-container {

            text-align: center;

            padding:
                20px 0 10px;
        }


        .success-icon {

            width: 82px;

            height: 82px;

            margin:
                0 auto 22px;

            border-radius: 50%;

            background:
                #dcfce7;

            color:
                #16a34a;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 42px;
        }


        .success-title {

            margin: 0;

            font-size: 25px;

            font-weight: 800;

            color:
                #111827;
        }


        .success-message {

            margin:
                10px auto 0;

            max-width: 420px;

            color:
                var(--muted);

            font-size: 15px;

            line-height: 1.6;
        }


        .success-status {

            margin-top: 25px;

            padding: 15px;

            border:
                1px solid #dcfce7;

            border-radius: 10px;

            background:
                #f0fdf4;
        }


        .success-status-label {

            font-size: 12px;

            color:
                var(--muted);

            margin-bottom: 4px;
        }


        .success-status-value {

            color:
                #15803d;

            font-weight: 700;

            font-size: 14px;
        }


        .success-instruction {

            margin-top: 22px;

            color:
                #64748b;

            font-size: 13px;

            line-height: 1.6;
        }


        /* =========================================================
           FOOTER
        ========================================================= */

        .footer-note {

            text-align: center;

            padding:
                18px 30px;

            border-top:
                1px solid var(--border);

            color:
                var(--muted);

            font-size: 12px;
        }


        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 576px) {

            .page {

                padding: 15px;
            }


            .card-header,
            .card-body {

                padding: 22px;
            }


            .camera-container {

                height: 280px;
            }


            .camera-controls {

                flex-direction: column;
            }


            .success-title {

                font-size: 22px;
            }

        }

    </style>

</head>


<body>

<div class="page">

    <div class="registration-card">


        <!-- =====================================================
             HEADER
        ====================================================== -->

        <div class="card-header">

            <div class="brand">

                <div class="brand-icon">

                    <i class="bi bi-stars"></i>

                </div>


                <div>

                    <h1 class="title">
                        Foundation Day 2026
                    </h1>

                    <div class="subtitle">
                        Employee Registration
                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             BODY
        ====================================================== -->

        <div class="card-body">


            <?php if ($registrationSuccess): ?>


                <!-- =================================================
                     REGISTRATION SUCCESS
                ================================================== -->

                <div class="success-container">


                    <div class="success-icon">

                        <i class="bi bi-check-lg"></i>

                    </div>


                    <h2 class="success-title">

                        Registration Successful

                    </h2>


                    <p class="success-message">

                        Thank you,
                        <strong>
                            <?php
                            echo htmlspecialchars(
                                $fullName,
                                ENT_QUOTES,
                                "UTF-8"
                            );
                            ?>
                        </strong>.

                        Your registration has been
                        successfully recorded.

                    </p>


                    <div class="success-status">

                        <div class="success-status-label">

                            Registration Status

                        </div>


                        <div class="success-status-value">

                            <i
                                class="bi bi-check-circle-fill me-1"
                            ></i>

                            Registered Successfully

                        </div>

                    </div>


                    <div class="success-instruction">

                        Please wait for further instructions
                        regarding the Foundation Day voting.

                    </div>


                </div>


            <?php else: ?>


                <!-- =================================================
                     REGISTRATION FORM
                ================================================== -->

                <div class="section-title">

                    <h2>
                        Register to Vote
                    </h2>

                    <p>
                        Enter your information to participate
                        in the Foundation Day voting.
                    </p>

                </div>


                <!-- ERRORS -->

                <?php if (!empty($errors)): ?>

                    <div
                        class="alert alert-danger"
                        role="alert"
                    >

                        <div class="fw-semibold mb-1">

                            Registration could not
                            be completed.

                        </div>


                        <ul class="mb-0 ps-3">

                            <?php foreach (
                                $errors
                                as $error
                            ): ?>

                                <li>

                                    <?php
                                    echo htmlspecialchars(
                                        $error,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    );
                                    ?>

                                </li>

                            <?php endforeach; ?>

                        </ul>

                    </div>

                <?php endif; ?>


                <!-- EXISTING REGISTRATION -->

                <?php if ($existingRegistration): ?>

                    <div
                        class="alert alert-warning"
                        role="alert"
                    >

                        <div class="fw-semibold mb-1">

                            Already Registered

                        </div>


                        <div>

                            This device has already been
                            registered for Foundation Day 2026.

                        </div>

                    </div>

                <?php else: ?>


                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="registrationForm"
                    >


                        <!-- FULL NAME -->

                        <div class="mb-3">

                            <label
                                for="full_name"
                                class="form-label"
                            >

                                Full Name

                            </label>


                            <input
                                type="text"
                                class="form-control"
                                id="full_name"
                                name="full_name"
                                value="<?php
                                echo htmlspecialchars(
                                    $fullName,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );
                                ?>"
                                placeholder="Enter your full name"
                                maxlength="150"
                                autocomplete="name"
                                required
                            >

                        </div>


                        <!-- GENDER -->

                        <div class="mb-3">

                            <label
                                for="gender"
                                class="form-label"
                            >

                                Gender

                            </label>


                            <select
                                class="form-select"
                                id="gender"
                                name="gender"
                                required
                            >

                                <option value="">

                                    Select gender

                                </option>


                                <option
                                    value="male"
                                    <?php
                                    echo $gender === "male"
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    Male

                                </option>


                                <option
                                    value="female"
                                    <?php
                                    echo $gender === "female"
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    Female

                                </option>

                            </select>

                        </div>


                        <!-- PHOTO -->

                        <div class="photo-section">

                            <label class="form-label">

                                Profile Photo

                            </label>


                            <div
                                class="camera-container"
                            >


                                <!-- PLACEHOLDER -->

                                <div
                                    class="camera-placeholder"
                                    id="cameraPlaceholder"
                                >

                                    <i
                                        class="bi bi-person-circle"
                                    ></i>


                                    <div class="fw-semibold">

                                        No photo selected

                                    </div>


                                    <div class="small mt-1">

                                        Take a selfie or
                                        upload a photo.

                                    </div>

                                </div>


                                <!-- CAMERA LOADING -->

                                <div
                                    class="camera-loading"
                                    id="cameraLoading"
                                >

                                    <div
                                        class="spinner-border spinner-border-sm"
                                        role="status"
                                    ></div>


                                    Opening camera...

                                </div>


                                <!-- CAMERA -->

                                <video
                                    id="camera"
                                    autoplay
                                    playsinline
                                    muted
                                    style="display:none;"
                                ></video>


                                <!-- PREVIEW -->

                                <img
                                    id="preview"
                                    alt="Photo Preview"
                                    style="display:none;"
                                >

                            </div>


                            <!-- FILE -->

                            <input
                                type="file"
                                id="selfie"
                                name="selfie"
                                accept="image/jpeg,image/png,image/webp"
                                hidden
                            >


                            <!-- CONTROLS -->

                            <div class="camera-controls">


                                <button
                                    type="button"
                                    class="btn btn-outline-primary"
                                    id="takeSelfieBtn"
                                >

                                    <i
                                        class="bi bi-camera me-1"
                                    ></i>

                                    Take Selfie

                                </button>


                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    id="captureBtn"
                                    style="display:none;"
                                >

                                    <i
                                        class="bi bi-camera-fill me-1"
                                    ></i>

                                    Capture Photo

                                </button>


                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    id="uploadBtn"
                                >

                                    <i
                                        class="bi bi-upload me-1"
                                    ></i>

                                    Upload Photo

                                </button>


                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    id="retakeBtn"
                                    style="display:none;"
                                >

                                    <i
                                        class="bi bi-arrow-counterclockwise me-1"
                                    ></i>

                                    Change Photo

                                </button>

                            </div>


                            <div class="photo-note">

                                JPG, PNG, or WEBP.
                                Maximum original upload size:
                                5 MB.

                            </div>

                        </div>


                        <!-- SUBMIT -->

                        <button
                            type="submit"
                            class="btn btn-primary btn-register"
                            id="registerBtn"
                        >

                            <i
                                class="bi bi-check-circle me-1"
                            ></i>

                            Register

                        </button>


                    </form>

                <?php endif; ?>

            <?php endif; ?>


        </div>


        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="footer-note">

            Foundation Day 2026
            &nbsp;•&nbsp;
            Employee Voting System

        </div>


    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| Image Settings
|--------------------------------------------------------------------------
*/

const MAX_IMAGE_WIDTH = 800;
const MAX_IMAGE_HEIGHT = 800;
const JPEG_QUALITY = 0.82;


/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const camera =
    document.getElementById("camera");

const preview =
    document.getElementById("preview");

const selfieInput =
    document.getElementById("selfie");

const cameraPlaceholder =
    document.getElementById(
        "cameraPlaceholder"
    );

const cameraLoading =
    document.getElementById(
        "cameraLoading"
    );

const takeSelfieBtn =
    document.getElementById(
        "takeSelfieBtn"
    );

const captureBtn =
    document.getElementById(
        "captureBtn"
    );

const uploadBtn =
    document.getElementById(
        "uploadBtn"
    );

const retakeBtn =
    document.getElementById(
        "retakeBtn"
    );

const registrationForm =
    document.getElementById(
        "registrationForm"
    );

const registerBtn =
    document.getElementById(
        "registerBtn"
    );


let stream = null;


/*
|--------------------------------------------------------------------------
| Stop Camera
|--------------------------------------------------------------------------
*/

function stopCamera() {

    if (stream) {

        stream
            .getTracks()
            .forEach(
                function(track) {

                    track.stop();

                }
            );

        stream = null;
    }


    if (camera) {

        camera.srcObject = null;

    }
}


/*
|--------------------------------------------------------------------------
| Show Preview
|--------------------------------------------------------------------------
*/

function showPreview(file) {

    if (!file || !preview) {

        return;

    }


    const url =
        URL.createObjectURL(file);


    preview.src = url;


    preview.onload =
        function() {

            URL.revokeObjectURL(url);

        };


    camera.style.display =
        "none";


    cameraPlaceholder.style.display =
        "none";


    preview.style.display =
        "block";


    takeSelfieBtn.style.display =
        "none";


    captureBtn.style.display =
        "none";


    uploadBtn.style.display =
        "none";


    retakeBtn.style.display =
        "inline-block";
}


/*
|--------------------------------------------------------------------------
| Compress Image
|--------------------------------------------------------------------------
*/

function compressImage(file) {

    return new Promise(
        function(resolve, reject) {

            const reader =
                new FileReader();


            reader.onload =
                function(event) {

                    const image =
                        new Image();


                    image.onload =
                        function() {

                            let width =
                                image.width;

                            let height =
                                image.height;


                            const scale =
                                Math.min(
                                    1,
                                    MAX_IMAGE_WIDTH / width,
                                    MAX_IMAGE_HEIGHT / height
                                );


                            width =
                                Math.round(
                                    width * scale
                                );


                            height =
                                Math.round(
                                    height * scale
                                );


                            const canvas =
                                document.createElement(
                                    "canvas"
                                );


                            canvas.width =
                                width;


                            canvas.height =
                                height;


                            const context =
                                canvas.getContext(
                                    "2d"
                                );


                            context.drawImage(
                                image,
                                0,
                                0,
                                width,
                                height
                            );


                            canvas.toBlob(
                                function(blob) {

                                    if (!blob) {

                                        reject(
                                            new Error(
                                                "Unable to process image."
                                            )
                                        );

                                        return;
                                    }


                                    resolve(
                                        new File(
                                            [blob],
                                            "selfie.jpg",
                                            {
                                                type:
                                                    "image/jpeg"
                                            }
                                        )
                                    );

                                },
                                "image/jpeg",
                                JPEG_QUALITY
                            );

                        };


                    image.onerror =
                        function() {

                            reject(
                                new Error(
                                    "Invalid image."
                                )
                            );

                        };


                    image.src =
                        event.target.result;
                };


            reader.onerror =
                function() {

                    reject(
                        new Error(
                            "Unable to read image."
                        )
                    );

                };


            reader.readAsDataURL(file);

        }
    );
}


/*
|--------------------------------------------------------------------------
| Set File
|--------------------------------------------------------------------------
*/

function setFile(file) {

    const dataTransfer =
        new DataTransfer();


    dataTransfer.items.add(file);


    selfieInput.files =
        dataTransfer.files;


    showPreview(file);
}


/*
|--------------------------------------------------------------------------
| Take Selfie
|--------------------------------------------------------------------------
*/

if (takeSelfieBtn) {

    takeSelfieBtn.addEventListener(
        "click",
        async function() {

            if (
                !navigator.mediaDevices ||
                !navigator.mediaDevices.getUserMedia
            ) {

                alert(
                    "Camera access is not available in this browser. Please use Upload Photo."
                );

                return;
            }


            cameraLoading.style.display =
                "flex";


            try {

                stream =
                    await navigator.mediaDevices
                        .getUserMedia({

                            video: {

                                facingMode: {
                                    ideal: "user"
                                },

                                width: {
                                    ideal: 1280
                                },

                                height: {
                                    ideal: 720
                                }

                            },

                            audio: false

                        });


                camera.srcObject =
                    stream;


                await camera.play();


                cameraPlaceholder.style.display =
                    "none";


                camera.style.display =
                    "block";


                preview.style.display =
                    "none";


                takeSelfieBtn.style.display =
                    "none";


                captureBtn.style.display =
                    "inline-block";


            } catch (error) {

                console.error(error);


                alert(
                    "Unable to access the camera. Please allow camera permission or use Upload Photo."
                );


            } finally {

                cameraLoading.style.display =
                    "none";

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Capture Photo
|--------------------------------------------------------------------------
*/

if (captureBtn) {

    captureBtn.addEventListener(
        "click",
        function() {

            if (!stream) {

                return;

            }


            const width =
                camera.videoWidth;


            const height =
                camera.videoHeight;


            if (!width || !height) {

                alert(
                    "Camera is not ready yet. Please try again."
                );

                return;

            }


            let targetWidth =
                width;


            let targetHeight =
                height;


            const scale =
                Math.min(
                    1,
                    MAX_IMAGE_WIDTH / targetWidth,
                    MAX_IMAGE_HEIGHT / targetHeight
                );


            targetWidth =
                Math.round(
                    targetWidth * scale
                );


            targetHeight =
                Math.round(
                    targetHeight * scale
                );


            const canvas =
                document.createElement(
                    "canvas"
                );


            canvas.width =
                targetWidth;


            canvas.height =
                targetHeight;


            const context =
                canvas.getContext(
                    "2d"
                );


            context.drawImage(
                camera,
                0,
                0,
                targetWidth,
                targetHeight
            );


            canvas.toBlob(
                function(blob) {

                    if (!blob) {

                        alert(
                            "Unable to capture photo."
                        );

                        return;

                    }


                    const file =
                        new File(
                            [blob],
                            "selfie.jpg",
                            {
                                type:
                                    "image/jpeg"
                            }
                        );


                    setFile(file);

                    stopCamera();

                },
                "image/jpeg",
                JPEG_QUALITY
            );

        }
    );

}


/*
|--------------------------------------------------------------------------
| Upload Photo
|--------------------------------------------------------------------------
*/

if (uploadBtn) {

    uploadBtn.addEventListener(
        "click",
        function() {

            selfieInput.click();

        }
    );

}


/*
|--------------------------------------------------------------------------
| Uploaded Image
|--------------------------------------------------------------------------
*/

if (selfieInput) {

    selfieInput.addEventListener(
        "change",
        async function() {

            const file =
                selfieInput.files[0];


            if (!file) {

                return;

            }


            try {

                const compressed =
                    await compressImage(
                        file
                    );


                setFile(
                    compressed
                );


            } catch (error) {

                console.error(error);


                alert(
                    "Unable to process the selected photo."
                );


                selfieInput.value = "";

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| Change Photo
|--------------------------------------------------------------------------
*/

if (retakeBtn) {

    retakeBtn.addEventListener(
        "click",
        function() {

            stopCamera();


            selfieInput.value =
                "";


            preview.src =
                "";


            preview.style.display =
                "none";


            camera.style.display =
                "none";


            cameraPlaceholder.style.display =
                "block";


            takeSelfieBtn.style.display =
                "inline-block";


            uploadBtn.style.display =
                "inline-block";


            captureBtn.style.display =
                "none";


            retakeBtn.style.display =
                "none";

        }
    );

}


/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

if (registrationForm) {

    registrationForm.addEventListener(
        "submit",
        function(event) {

            if (
                !selfieInput.files.length
            ) {

                event.preventDefault();


                alert(
                    "Please take a selfie or upload a photo."
                );


                return;

            }


            registerBtn.disabled =
                true;


            registerBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Registering...';


            stopCamera();

        }
    );

}


/*
|--------------------------------------------------------------------------
| Stop Camera When Leaving
|--------------------------------------------------------------------------
*/

window.addEventListener(
    "beforeunload",
    function() {

        stopCamera();

    }
);

</script>

</body>

</html>