<?php

require_once "includes/db.php";
require_once "includes/functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Handle Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = isset($_POST['action'])
        ? trim($_POST['action'])
        : '';


    /*
    |--------------------------------------------------------------------------
    | ADD CANDIDATE
    |--------------------------------------------------------------------------
    */

    if ($action === 'add') {

        $name = isset($_POST['name'])
            ? trim($_POST['name'])
            : '';

        $category = isset($_POST['category'])
            ? trim($_POST['category'])
            : '';

        $gender = isset($_POST['gender'])
            ? trim($_POST['gender'])
            : '';

        $status = isset($_POST['status'])
            ? trim($_POST['status'])
            : 'active';


        if (!isValidCandidateName($name)) {

            setFlashMessage(
                'danger',
                'Please enter a valid candidate name.'
            );

            redirect("candidates.php");
        }


        if (!isValidCategory($category)) {

            setFlashMessage(
                'danger',
                'Please select a valid category.'
            );

            redirect("candidates.php");
        }


        if (!isValidGender($gender)) {

            setFlashMessage(
                'danger',
                'Please select Male or Female.'
            );

            redirect("candidates.php");
        }


        if (!isValidStatus($status)) {

            setFlashMessage(
                'danger',
                'Please select a valid status.'
            );

            redirect("candidates.php");
        }


        /*
        |--------------------------------------------------------------------------
        | PHOTO
        |--------------------------------------------------------------------------
        */

        $photo = null;
        $uploadError = '';


        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $photo = uploadCandidatePhoto(
                $_FILES['photo'],
                $uploadError
            );


            if (
                $photo === null &&
                $uploadError !== ''
            ) {

                setFlashMessage(
                    'danger',
                    $uploadError
                );

                redirect("candidates.php");
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */

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
                'success',
                'Candidate added successfully.'
            );

        } else {

            if ($photo !== null) {
                deleteCandidatePhoto($photo);
            }

            setFlashMessage(
                'danger',
                'Unable to add candidate.'
            );
        }


        redirect("candidates.php");
    }


    /*
    |--------------------------------------------------------------------------
    | EDIT CANDIDATE
    |--------------------------------------------------------------------------
    */

    if ($action === 'edit') {

        $id = isset($_POST['id'])
            ? (int) $_POST['id']
            : 0;

        $name = isset($_POST['name'])
            ? trim($_POST['name'])
            : '';

        $category = isset($_POST['category'])
            ? trim($_POST['category'])
            : '';

        $gender = isset($_POST['gender'])
            ? trim($_POST['gender'])
            : '';

        $status = isset($_POST['status'])
            ? trim($_POST['status'])
            : 'active';


        /*
        |--------------------------------------------------------------------------
        | GET EXISTING CANDIDATE
        |--------------------------------------------------------------------------
        */

        $candidate = getCandidate(
            $conn,
            $id
        );


        if (!$candidate) {

            setFlashMessage(
                'danger',
                'Candidate not found.'
            );

            redirect("candidates.php");
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (!isValidCandidateName($name)) {

            setFlashMessage(
                'danger',
                'Please enter a valid candidate name.'
            );

            redirect("candidates.php");
        }


        if (!isValidCategory($category)) {

            setFlashMessage(
                'danger',
                'Please select a valid category.'
            );

            redirect("candidates.php");
        }


        if (!isValidGender($gender)) {

            setFlashMessage(
                'danger',
                'Please select Male or Female.'
            );

            redirect("candidates.php");
        }


        if (!isValidStatus($status)) {

            setFlashMessage(
                'danger',
                'Please select a valid status.'
            );

            redirect("candidates.php");
        }


        /*
        |--------------------------------------------------------------------------
        | EXISTING PHOTO
        |--------------------------------------------------------------------------
        */

        $photo = $candidate['photo'];

        $newPhoto = null;
        $uploadError = '';


        if (
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $newPhoto = uploadCandidatePhoto(
                $_FILES['photo'],
                $uploadError
            );


            if (
                $newPhoto === null &&
                $uploadError !== ''
            ) {

                setFlashMessage(
                    'danger',
                    $uploadError
                );

                redirect("candidates.php");
            }


            $photo = $newPhoto;
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

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

            /*
            |--------------------------------------------------------------------------
            | DELETE OLD PHOTO
            |--------------------------------------------------------------------------
            */

            if (
                $newPhoto !== null &&
                !empty($candidate['photo'])
            ) {

                deleteCandidatePhoto(
                    $candidate['photo']
                );
            }


            setFlashMessage(
                'success',
                'Candidate updated successfully.'
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | REMOVE NEW PHOTO IF UPDATE FAILED
            |--------------------------------------------------------------------------
            */

            if ($newPhoto !== null) {

                deleteCandidatePhoto(
                    $newPhoto
                );
            }


            setFlashMessage(
                'danger',
                'Unable to update candidate.'
            );
        }


        redirect("candidates.php");
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE CANDIDATE
    |--------------------------------------------------------------------------
    */

    if ($action === 'delete') {

        $id = isset($_POST['id'])
            ? (int) $_POST['id']
            : 0;


        if ($id <= 0) {

            setFlashMessage(
                'danger',
                'Invalid candidate.'
            );

            redirect("candidates.php");
        }


        if (
            deleteCandidate(
                $conn,
                $id
            )
        ) {

            setFlashMessage(
                'success',
                'Candidate deleted successfully.'
            );

        } else {

            setFlashMessage(
                'danger',
                'Unable to delete candidate.'
            );
        }


        redirect("candidates.php");
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$category = isset($_GET['category'])
    ? trim($_GET['category'])
    : '';

$gender = isset($_GET['gender'])
    ? trim($_GET['gender'])
    : '';


if (
    $category !== '' &&
    !isValidCategory($category)
) {
    $category = '';
}


if (
    $gender !== '' &&
    !isValidGender($gender)
) {
    $gender = '';
}


/*
|--------------------------------------------------------------------------
| LOAD CANDIDATES
|--------------------------------------------------------------------------
*/

$candidates = getCandidates(
    $conn,
    $category,
    $gender
);


/*
|--------------------------------------------------------------------------
| TOTAL COUNTS
|--------------------------------------------------------------------------
*/

$totalCandidates = countCandidates(
    $conn
);


/*
|--------------------------------------------------------------------------
| CATEGORY COUNTS
|--------------------------------------------------------------------------
*/

$faceMaleCount = countCandidates(
    $conn,
    'face_of_the_night',
    'male'
);

$faceFemaleCount = countCandidates(
    $conn,
    'face_of_the_night',
    'female'
);


$starMaleCount = countCandidates(
    $conn,
    'star_of_the_night',
    'male'
);

$starFemaleCount = countCandidates(
    $conn,
    'star_of_the_night',
    'female'
);


$darlingMaleCount = countCandidates(
    $conn,
    'darling_of_the_crowd',
    'male'
);

$darlingFemaleCount = countCandidates(
    $conn,
    'darling_of_the_crowd',
    'female'
);


/*
|--------------------------------------------------------------------------
| FLASH MESSAGE
|--------------------------------------------------------------------------
*/

$flash = getFlashMessage();

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
        Candidates | Foundation Day 2026
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <link
        rel="stylesheet"
        href="../assets/css/candidates.css"
    >

</head>


<body>

<div class="app-wrapper">


    <!-- ==========================================================
         SIDEBAR
    =========================================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-icon">
                <i class="bi bi-stars"></i>
            </div>


            <div>

                <h5>
                    Foundation Day
                </h5>

                <small>
                    Voting System
                </small>

            </div>

        </div>


        <nav class="sidebar-nav">

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

                <i class="bi bi-people-fill"></i>

                <span>
                    Candidates
                </span>

            </a>


            <a href="employees.php">

                <i class="bi bi-person-badge-fill"></i>

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


            <a href="settings.php">

                <i class="bi bi-gear-fill"></i>

                <span>
                    Settings
                </span>

            </a>

        </nav>


        <div class="sidebar-footer">

            <div class="system-status">

                <span class="status-dot"></span>

                System Online

            </div>

        </div>

    </aside>


    <!-- ==========================================================
         MAIN CONTENT
    =========================================================== -->

    <main class="main-content">


        <!-- TOPBAR -->

        <header class="topbar">

            <div>

                <h1>
                    Candidates
                </h1>

                <p>
                    Manage Foundation Day voting candidates
                </p>

            </div>


            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addCandidateModal"
            >

                <i class="bi bi-plus-lg"></i>

                Add Candidate

            </button>

        </header>


        <!-- CONTENT -->

        <section class="content">


            <!-- ==================================================
                 FLASH MESSAGE
            =================================================== -->

            <?php if ($flash): ?>

                <div
                    class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible fade show"
                    role="alert"
                >

                    <?php echo e($flash['message']); ?>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <!-- ==================================================
                 FILTERS
            =================================================== -->

            <div class="category-tabs">


                <!-- ALL -->

                <a
                    href="candidates.php"
                    class="category-tab <?php echo (
                        $category === '' &&
                        $gender === ''
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        All Candidates
                    </span>

                    <strong>
                        <?php echo $totalCandidates; ?>
                    </strong>

                </a>


                <!-- FACE MALE -->

                <a
                    href="candidates.php?category=face_of_the_night&gender=male"
                    class="category-tab <?php echo (
                        $category === 'face_of_the_night' &&
                        $gender === 'male'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Face — Male
                    </span>

                    <strong>
                        <?php echo $faceMaleCount; ?>
                    </strong>

                </a>


                <!-- FACE FEMALE -->

                <a
                    href="candidates.php?category=face_of_the_night&gender=female"
                    class="category-tab <?php echo (
                        $category === 'face_of_the_night' &&
                        $gender === 'female'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Face — Female
                    </span>

                    <strong>
                        <?php echo $faceFemaleCount; ?>
                    </strong>

                </a>


                <!-- STAR MALE -->

                <a
                    href="candidates.php?category=star_of_the_night&gender=male"
                    class="category-tab <?php echo (
                        $category === 'star_of_the_night' &&
                        $gender === 'male'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Star — Male
                    </span>

                    <strong>
                        <?php echo $starMaleCount; ?>
                    </strong>

                </a>


                <!-- STAR FEMALE -->

                <a
                    href="candidates.php?category=star_of_the_night&gender=female"
                    class="category-tab <?php echo (
                        $category === 'star_of_the_night' &&
                        $gender === 'female'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Star — Female
                    </span>

                    <strong>
                        <?php echo $starFemaleCount; ?>
                    </strong>

                </a>


                <!-- DARLING MALE -->

                <a
                    href="candidates.php?category=darling_of_the_crowd&gender=male"
                    class="category-tab <?php echo (
                        $category === 'darling_of_the_crowd' &&
                        $gender === 'male'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Darling — Male
                    </span>

                    <strong>
                        <?php echo $darlingMaleCount; ?>
                    </strong>

                </a>


                <!-- DARLING FEMALE -->

                <a
                    href="candidates.php?category=darling_of_the_crowd&gender=female"
                    class="category-tab <?php echo (
                        $category === 'darling_of_the_crowd' &&
                        $gender === 'female'
                    ) ? 'active' : ''; ?>"
                >

                    <span>
                        Darling — Female
                    </span>

                    <strong>
                        <?php echo $darlingFemaleCount; ?>
                    </strong>

                </a>

            </div>


            <!-- ==================================================
                 CANDIDATE GRID
            =================================================== -->

            <?php if (empty($candidates)): ?>


                <div class="empty-state">

                    <div class="empty-icon">

                        <i class="bi bi-person-x"></i>

                    </div>


                    <h3>
                        No Candidates Found
                    </h3>


                    <p>
                        There are no candidates in this category yet.
                    </p>


                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#addCandidateModal"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Add Candidate

                    </button>

                </div>


            <?php else: ?>


                <div class="candidate-grid">


                    <?php foreach ($candidates as $candidate): ?>


                        <div class="candidate-card">


                            <!-- PHOTO -->

                            <div class="candidate-photo">

                                <?php if (!empty($candidate['photo'])): ?>

                                    <img
                                        src="../<?php echo e($candidate['photo']); ?>"
                                        alt="<?php echo e($candidate['name']); ?>"
                                    >

                                <?php else: ?>

                                    <div class="candidate-photo-placeholder">

                                        <i class="bi bi-person-fill"></i>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- BODY -->

                            <div class="candidate-body">


                                <div class="candidate-category">

                                    <?php echo e(
                                        categoryLabel(
                                            $candidate['category']
                                        )
                                    ); ?>

                                </div>


                                <h3>
                                    <?php echo e(
                                        $candidate['name']
                                    ); ?>
                                </h3>


                                <div class="candidate-meta">

                                    <span class="gender-badge">

                                        <i
                                            class="bi <?php echo $candidate['gender'] === 'male'
                                                ? 'bi-gender-male'
                                                : 'bi-gender-female'; ?>"
                                        ></i>

                                        <?php echo e(
                                            genderLabel(
                                                $candidate['gender']
                                            )
                                        ); ?>

                                    </span>


                                    <span
                                        class="badge <?php echo e(
                                            statusBadgeClass(
                                                $candidate['status']
                                            )
                                        ); ?>"
                                    >

                                        <?php echo e(
                                            statusLabel(
                                                $candidate['status']
                                            )
                                        ); ?>

                                    </span>

                                </div>

                            </div>


                            <!-- FOOTER -->

                            <div class="candidate-footer">


                                <button
                                    type="button"
                                    class="btn btn-outline-primary btn-sm edit-candidate-btn"
                                    data-candidate='<?php echo e(
                                        json_encode($candidate)
                                    ); ?>'
                                >

                                    <i class="bi bi-pencil"></i>

                                    Edit

                                </button>


                                <form
                                    method="POST"
                                    class="delete-candidate-form"
                                >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >


                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo (int) $candidate['id']; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                    >

                                        <i class="bi bi-trash"></i>

                                        Delete

                                    </button>

                                </form>

                            </div>

                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>

    </main>

</div>


<!-- ==============================================================
     ADD CANDIDATE MODAL
=============================================================== -->

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


                    <!-- PHOTO -->

                    <div class="mb-3">

                        <label
                            for="candidatePhoto"
                            class="form-label"
                        >
                            Candidate Photo
                        </label>


                        <input
                            type="file"
                            name="photo"
                            id="candidatePhoto"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp"
                        >


                        <small class="text-muted">
                            JPG, PNG, or WEBP. Maximum 5 MB.
                        </small>

                    </div>


                    <div
                        id="photoPreview"
                        class="photo-preview"
                    ></div>


                    <!-- NAME -->

                    <div class="mb-3">

                        <label
                            for="candidateName"
                            class="form-label"
                        >
                            Candidate Name
                        </label>


                        <input
                            type="text"
                            name="name"
                            id="candidateName"
                            class="form-control"
                            maxlength="150"
                            required
                        >

                    </div>


                    <!-- GENDER -->

                    <div class="mb-3">

                        <label
                            for="candidateGender"
                            class="form-label"
                        >
                            Gender
                        </label>


                        <select
                            name="gender"
                            id="candidateGender"
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

                        <label
                            for="candidateCategory"
                            class="form-label"
                        >
                            Category
                        </label>


                        <select
                            name="category"
                            id="candidateCategory"
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

                        <label
                            for="candidateStatus"
                            class="form-label"
                        >
                            Status
                        </label>


                        <select
                            name="status"
                            id="candidateStatus"
                            class="form-select"
                        >

                            <option value="active">
                                Active
                            </option>


                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

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

                        <i class="bi bi-check-lg"></i>

                        Save Candidate

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==============================================================
     EDIT CANDIDATE MODAL
=============================================================== -->

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


                    <!-- CURRENT PHOTO -->

                    <div
                        id="editCandidatePhoto"
                        class="edit-photo-preview"
                    ></div>


                    <!-- NEW PHOTO -->

                    <div class="mb-3">

                        <label
                            for="editCandidatePhotoInput"
                            class="form-label"
                        >
                            Change Photo
                        </label>


                        <input
                            type="file"
                            name="photo"
                            id="editCandidatePhotoInput"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp"
                        >


                        <small class="text-muted">
                            Leave empty to keep the current photo.
                        </small>

                    </div>


                    <!-- NAME -->

                    <div class="mb-3">

                        <label
                            for="editCandidateName"
                            class="form-label"
                        >
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

                        <label
                            for="editCandidateGender"
                            class="form-label"
                        >
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

                        <label
                            for="editCandidateCategory"
                            class="form-label"
                        >
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

                        <label
                            for="editCandidateStatus"
                            class="form-label"
                        >
                            Status
                        </label>


                        <select
                            name="status"
                            id="editCandidateStatus"
                            class="form-select"
                        >

                            <option value="active">
                                Active
                            </option>


                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

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

                        <i class="bi bi-check-lg"></i>

                        Update Candidate

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==============================================================
     SCRIPTS
=============================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<script
    src="../assets/js/candidates.js"
></script>


</body>

</html>
