<?php

session_start();

require_once "includes/db.php";
require_once "includes/functions.php";

$error = "";
$success = false;


/*
|--------------------------------------------------------------------------
| Registration
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validate Name
    |--------------------------------------------------------------------------
    */

    if ($fullName === "") {

        $error = "Please enter your full name.";

    } elseif (!isValidCandidateName($fullName)) {

        $error = "Please enter a valid full name.";
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Selfie
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        if (
            !isset($_FILES["selfie"]) ||
            $_FILES["selfie"]["error"] !== UPLOAD_ERR_OK
        ) {

            $error =
                "A selfie is required. Please take your selfie before registering.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Selfie Size
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        if ($_FILES["selfie"]["size"] > 5 * 1024 * 1024) {

            $error =
                "Your selfie must not exceed 5MB.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Actual Image
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = $finfo->file(
            $_FILES["selfie"]["tmp_name"]
        );

        $allowedTypes = [
            "image/jpeg" => "jpg",
            "image/png"  => "png",
            "image/webp" => "webp"
        ];

        if (!isset($allowedTypes[$mime])) {

            $error =
                "Please provide a valid JPG, PNG, or WEBP selfie.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Name
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $normalizedName = strtolower(
            preg_replace(
                "/\s+/",
                " ",
                trim($fullName)
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Check Existing Employee
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare(
            "SELECT
                id,
                registration_status,
                voting_status
             FROM employees
             WHERE normalized_name = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $error =
                "Unable to process registration.";

        } else {

            $stmt->bind_param(
                "s",
                $normalizedName
            );

            $stmt->execute();

            $result = $stmt->get_result();


            if ($result->num_rows > 0) {

                $existingEmployee =
                    $result->fetch_assoc();

                $stmt->close();


                if (
                    $existingEmployee["registration_status"]
                    === "pending"
                ) {

                    $error =
                        "This employee is already registered and is waiting for administrator approval.";

                } elseif (
                    $existingEmployee["registration_status"]
                    === "approved"
                    &&
                    $existingEmployee["voting_status"]
                    === "not_voted"
                ) {

                    $error =
                        "This employee is already registered and approved. You may proceed to voting.";

                } elseif (
                    $existingEmployee["voting_status"]
                    === "voted"
                ) {

                    $error =
                        "This employee has already completed voting.";

                } else {

                    $error =
                        "This name is already registered. Please contact the administrator.";
                }

            } else {

                $stmt->close();
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Upload Directory
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $uploadDirectory =
            __DIR__ . "/uploads/employees/";


        if (!is_dir($uploadDirectory)) {

            if (!mkdir($uploadDirectory, 0755, true)) {

                $error =
                    "Unable to create the selfie upload directory.";
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Save Selfie
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $extension =
            $allowedTypes[$mime];

        $filename =
            bin2hex(random_bytes(16)) .
            "." .
            $extension;

        $destination =
            $uploadDirectory .
            $filename;


        if (
            !move_uploaded_file(
                $_FILES["selfie"]["tmp_name"],
                $destination
            )
        ) {

            $error =
                "Unable to save your selfie. Please try again.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create Employee
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $selfiePath =
            "uploads/employees/" .
            $filename;


        $stmt = $conn->prepare(
            "INSERT INTO employees
            (
                full_name,
                normalized_name,
                selfie,
                registration_status,
                voting_status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                'pending',
                'not_voted'
            )"
        );


        if (!$stmt) {

            if (file_exists($destination)) {
                unlink($destination);
            }

            $error =
                "Unable to complete registration.";

        } else {

            $stmt->bind_param(
                "sss",
                $fullName,
                $normalizedName,
                $selfiePath
            );


            if ($stmt->execute()) {

                $employeeId =
                    $stmt->insert_id;

                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Store Session
                |--------------------------------------------------------------------------
                */

                $_SESSION["employee_id"] =
                    $employeeId;

                $_SESSION["employee_name"] =
                    $fullName;


                $success = true;

            } else {

                if (file_exists($destination)) {
                    unlink($destination);
                }


                if ($conn->errno === 1062) {

                    $error =
                        "This employee name is already registered.";

                } else {

                    $error =
                        "Unable to complete registration. Please try again.";
                }


                $stmt->close();
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

<meta
    name="theme-color"
    content="#0d6efd"
>

<title>
    Foundation Day 2026 - Registration
</title>


<style>

    * {
        box-sizing: border-box;
    }


    html,
    body {

        margin: 0;
        padding: 0;

        min-height: 100%;

        font-family:
            Inter,
            -apple-system,
            BlinkMacSystemFont,
            "Segoe UI",
            sans-serif;

        background: #f4f7fb;

        color: #172033;
    }


    body {
        min-height: 100vh;
    }


    .page {

        min-height: 100vh;

        display: flex;

        align-items: center;

        justify-content: center;

        padding: 20px 14px;
    }


    .registration-card {

        width: 100%;

        max-width: 460px;

        background: #ffffff;

        border-radius: 20px;

        box-shadow:
            0 12px 35px
            rgba(0, 0, 0, 0.08);

        overflow: hidden;
    }


    /*
    |--------------------------------------------------------------------------
    | Header
    |--------------------------------------------------------------------------
    */

    .header {

        background: #0d6efd;

        color: #ffffff;

        padding: 28px 22px;

        text-align: center;
    }


    .event-icon {

        width: 64px;
        height: 64px;

        margin: 0 auto 14px;

        border-radius: 50%;

        background:
            rgba(255, 255, 255, 0.18);

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 30px;
    }


    .header h1 {

        margin: 0;

        font-size: 24px;

        font-weight: 800;
    }


    .header p {

        margin: 7px 0 0;

        font-size: 14px;

        opacity: 0.9;
    }


    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */

    .content {

        padding: 24px 20px 28px;
    }


    .notice {

        padding: 13px 14px;

        border-radius: 10px;

        margin-bottom: 20px;

        font-size: 14px;

        line-height: 1.5;
    }


    .notice.error {

        background: #fff1f2;

        color: #b42318;

        border:
            1px solid #fecdd3;
    }


    /*
    |--------------------------------------------------------------------------
    | Fields
    |--------------------------------------------------------------------------
    */

    .field {

        margin-bottom: 22px;
    }


    .field-label {

        display: block;

        margin-bottom: 8px;

        font-size: 14px;

        font-weight: 700;

        color: #344054;
    }


    .required {

        color: #dc2626;
    }


    .name-input {

        width: 100%;

        height: 50px;

        padding: 0 14px;

        border:
            1px solid #d0d5dd;

        border-radius: 10px;

        font-size: 16px;

        outline: none;

        transition: 0.2s;
    }


    .name-input:focus {

        border-color: #0d6efd;

        box-shadow:
            0 0 0 3px
            rgba(13, 110, 253, 0.12);
    }


    /*
    |--------------------------------------------------------------------------
    | Selfie Area
    |--------------------------------------------------------------------------
    */

    .selfie-wrapper {

        width: 100%;
    }


    .camera-button {

        width: 100%;

        min-height: 270px;

        border:
            2px dashed #b8c4d4;

        border-radius: 16px;

        background: #f8fafc;

        display: flex;

        flex-direction: column;

        align-items: center;

        justify-content: center;

        text-align: center;

        padding: 25px;

        cursor: pointer;

        transition: 0.2s;

        position: relative;

        overflow: hidden;
    }


    .camera-button:active {

        background: #eef5ff;

        border-color: #0d6efd;
    }


    .camera-icon {

        width: 72px;
        height: 72px;

        border-radius: 50%;

        background: #e8f1ff;

        color: #0d6efd;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 34px;

        margin-bottom: 15px;
    }


    .camera-button strong {

        display: block;

        font-size: 17px;

        margin-bottom: 6px;

        color: #172033;
    }


    .camera-button span {

        color: #667085;

        font-size: 13px;

        line-height: 1.5;

        max-width: 290px;
    }


    /*
    |--------------------------------------------------------------------------
    | Hidden Camera Input
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | capture="user"
    |
    | tells the mobile browser to use the
    | front-facing camera.
    |
    */

    #selfie {

        display: none;
    }


    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

    .preview-container {

        display: none;

        width: 100%;

        border-radius: 16px;

        overflow: hidden;

        background: #101828;

        position: relative;
    }


    #selfiePreview {

        display: block;

        width: 100%;

        max-height: 420px;

        object-fit: cover;
    }


    .selfie-status {

        padding: 11px 13px;

        background: #ecfdf3;

        border:
            1px solid #abefc6;

        border-radius: 9px;

        color: #067647;

        font-size: 13px;

        margin-top: 10px;
    }


    .retake-button {

        width: 100%;

        height: 46px;

        margin-top: 10px;

        border:
            1px solid #d0d5dd;

        border-radius: 9px;

        background: #ffffff;

        color: #344054;

        font-size: 14px;

        font-weight: 700;

        cursor: pointer;
    }


    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    .submit-button {

        width: 100%;

        height: 52px;

        border: 0;

        border-radius: 11px;

        background: #0d6efd;

        color: #ffffff;

        font-size: 16px;

        font-weight: 800;

        cursor: pointer;

        transition: 0.2s;
    }


    .submit-button:active {

        transform: scale(0.98);
    }


    .submit-button:disabled {

        opacity: 0.6;

        cursor: not-allowed;
    }


    /*
    |--------------------------------------------------------------------------
    | Requirements
    |--------------------------------------------------------------------------
    */

    .requirements {

        margin-top: 20px;

        padding: 14px;

        border-radius: 10px;

        background: #f8fafc;
    }


    .requirements-title {

        font-size: 13px;

        font-weight: 800;

        margin-bottom: 9px;

        color: #344054;
    }


    .requirement {

        display: flex;

        gap: 8px;

        align-items: flex-start;

        font-size: 12px;

        color: #667085;

        margin-top: 7px;

        line-height: 1.4;
    }


    .requirement-check {

        color: #12b76a;

        font-weight: 800;
    }


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    .success-screen {

        text-align: center;

        padding: 10px 0;
    }


    .success-icon {

        width: 72px;
        height: 72px;

        margin: 0 auto 18px;

        border-radius: 50%;

        background: #dcfae6;

        color: #067647;

        display: flex;

        align-items: center;

        justify-content: center;

        font-size: 36px;
    }


    .success-screen h2 {

        margin: 0 0 8px;

        font-size: 22px;
    }


    .success-screen p {

        margin: 0 0 20px;

        color: #667085;

        line-height: 1.6;

        font-size: 14px;
    }


    .status-box {

        background: #fff8e7;

        border:
            1px solid #f5d98a;

        border-radius: 12px;

        padding: 14px;

        text-align: left;

        font-size: 13px;

        line-height: 1.5;

        color: #795900;
    }


    @media (min-width: 600px) {

        .page {
            padding: 40px 20px;
        }

        .content {
            padding: 30px;
        }

        .header {
            padding: 32px;
        }
    }

</style>


</head>

<body>

<div class="page">


<div class="registration-card">


    <!-- ======================================================
         HEADER
    ======================================================= -->

    <div class="header">

        <div class="event-icon">
            🎉
        </div>

        <h1>
            Foundation Day 2026
        </h1>

        <p>
            Employee Registration
        </p>

    </div>


    <!-- ======================================================
         CONTENT
    ======================================================= -->

    <div class="content">


        <?php if ($success): ?>


            <!-- ==================================================
                 SUCCESS
            =================================================== -->

            <div class="success-screen">

                <div class="success-icon">
                    ✓
                </div>


                <h2>
                    Registration Submitted
                </h2>


                <p>

                    Thank you,

                    <strong>
                        <?= e($_SESSION["employee_name"]) ?>
                    </strong>.

                </p>


                <div class="status-box">

                    <strong>
                        Your registration is pending approval.
                    </strong>

                    <br>
                    <br>

                    The event administrator will verify
                    your name and selfie.

                    <br>
                    <br>

                    You will only be allowed to vote after
                    your registration has been approved.

                </div>

            </div>


        <?php else: ?>


            <?php if ($error !== ""): ?>

                <div class="notice error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 REGISTRATION FORM
            =================================================== -->

            <form
                method="POST"
                enctype="multipart/form-data"
                id="registrationForm"
            >


                <!-- NAME -->

                <div class="field">

                    <label
                        class="field-label"
                        for="full_name"
                    >

                        Full Name

                        <span class="required">
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="name-input"
                        placeholder="Enter your full name"
                        maxlength="150"
                        autocomplete="name"
                        required
                        value="<?= e($_POST["full_name"] ?? "") ?>"
                    >

                </div>


                <!-- SELFIE -->

                <div class="field">

                    <label class="field-label">

                        Selfie

                        <span class="required">
                            *
                        </span>

                    </label>


                    <div class="selfie-wrapper">


                        <!--
                        =================================================
                        IMPORTANT MOBILE CAMERA BUTTON
                        =================================================

                        The actual file input uses:

                        accept="image/*"
                        capture="user"

                        On supported phones, tapping the button
                        opens the FRONT CAMERA directly.
                        -->

                        <label
                            for="selfie"
                            class="camera-button"
                            id="cameraButton"
                        >

                            <div class="camera-icon">
                                📷
                            </div>


                            <strong>
                                Take Your Selfie
                            </strong>


                            <span>

                                Tap here to open your
                                phone camera and take a selfie.

                            </span>

                        </label>


                        <input
                            type="file"
                            id="selfie"
                            name="selfie"
                            accept="image/*"
                            capture="user"
                            required
                        >


                        <!-- PREVIEW -->

                        <div
                            class="preview-container"
                            id="previewContainer"
                        >

                            <img
                                id="selfiePreview"
                                alt="Selfie preview"
                            >

                        </div>


                        <div
                            class="selfie-status"
                            id="selfieStatus"
                            style="display:none;"
                        >

                            ✓ Selfie captured successfully.

                        </div>


                        <button
                            type="button"
                            class="retake-button"
                            id="retakeButton"
                            style="display:none;"
                        >

                            🔄 Retake Selfie

                        </button>


                    </div>

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="submit-button"
                    id="submitButton"
                >

                    Submit Registration

                </button>


            </form>


            <!-- ==================================================
                 REQUIREMENTS
            =================================================== -->

            <div class="requirements">

                <div class="requirements-title">

                    Registration Requirements

                </div>


                <div class="requirement">

                    <span class="requirement-check">
                        ✓
                    </span>

                    <span>
                        Enter your real full name.
                    </span>

                </div>


                <div class="requirement">

                    <span class="requirement-check">
                        ✓
                    </span>

                    <span>
                        A selfie is required.
                    </span>

                </div>


                <div class="requirement">

                    <span class="requirement-check">
                        ✓
                    </span>

                    <span>
                        Your selfie will be reviewed
                        by the event administrator.
                    </span>

                </div>


                <div class="requirement">

                    <span class="requirement-check">
                        ✓
                    </span>

                    <span>
                        Only approved employees can vote.
                    </span>

                </div>

            </div>


        <?php endif; ?>


    </div>

</div>


</div>

<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const selfieInput =
    document.getElementById("selfie");

const cameraButton =
    document.getElementById("cameraButton");

const previewContainer =
    document.getElementById("previewContainer");

const selfiePreview =
    document.getElementById("selfiePreview");

const selfieStatus =
    document.getElementById("selfieStatus");

const retakeButton =
    document.getElementById("retakeButton");

const registrationForm =
    document.getElementById("registrationForm");

const submitButton =
    document.getElementById("submitButton");


/*
|--------------------------------------------------------------------------
| Selfie Selected
|--------------------------------------------------------------------------
*/

if (selfieInput) {

    selfieInput.addEventListener(
        "change",
        function () {


            if (
                !this.files ||
                this.files.length === 0
            ) {

                return;
            }


            const file =
                this.files[0];


            /*
            |--------------------------------------------------------------------------
            | Validate image
            |--------------------------------------------------------------------------
            */

            if (
                !file.type.startsWith("image/")
            ) {

                alert(
                    "Please take a valid photo."
                );

                this.value = "";

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Validate size
            |--------------------------------------------------------------------------
            */

            if (
                file.size >
                5 * 1024 * 1024
            ) {

                alert(
                    "Your selfie must not exceed 5MB."
                );

                this.value = "";

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Preview
            |--------------------------------------------------------------------------
            */

            const reader =
                new FileReader();


            reader.onload =
                function(event) {

                    selfiePreview.src =
                        event.target.result;


                    previewContainer.style.display =
                        "block";


                    cameraButton.style.display =
                        "none";


                    selfieStatus.style.display =
                        "block";


                    retakeButton.style.display =
                        "block";
                };


            reader.readAsDataURL(file);

        }
    );
}


/*
|--------------------------------------------------------------------------
| Retake Selfie
|--------------------------------------------------------------------------
*/

if (retakeButton) {

    retakeButton.addEventListener(
        "click",
        function() {


            /*
             * Clear previous photo.
             */

            selfieInput.value = "";


            selfiePreview.src = "";


            previewContainer.style.display =
                "none";


            selfieStatus.style.display =
                "none";


            retakeButton.style.display =
                "none";


            cameraButton.style.display =
                "flex";


            /*
             * Open camera again.
             */

            setTimeout(
                function() {

                    selfieInput.click();

                },
                100
            );

        }
    );
}


/*
|--------------------------------------------------------------------------
| Form Submit
|--------------------------------------------------------------------------
*/

if (registrationForm) {

    registrationForm.addEventListener(
        "submit",
        function(event) {


            /*
             * No selfie = no registration.
             */

            if (
                !selfieInput.files ||
                selfieInput.files.length === 0
            ) {

                event.preventDefault();


                alert(
                    "Please take your selfie before submitting your registration."
                );


                return;
            }


            /*
             * Prevent double submission.
             */

            submitButton.disabled =
                true;


            submitButton.textContent =
                "Submitting Registration...";

        }
    );
}

</script>

</body>

</html>
