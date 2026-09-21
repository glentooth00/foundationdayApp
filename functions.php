<?php

/**
 * ============================================================
 * FOUNDATION DAY VOTING SYSTEM
 * Common Functions
 * ============================================================
 */

/**
 * Escape HTML output
 */
function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Redirect helper
 */
function redirect($url)
{
    header("Location: " . $url);
    exit;
}


/**
 * Get readable category name
 */
function categoryLabel($category)
{
    $categories = [
        'face_of_the_night'    => 'Face of the Night',
        'star_of_the_night'    => 'Star of the Night',
        'darling_of_the_crowd' => 'Darling of the Crowd'
    ];

    return isset($categories[$category])
        ? $categories[$category]
        : $category;
}


/**
 * Get Bootstrap badge class for candidate status
 */
function statusBadgeClass($status)
{
    switch ($status) {
        case 'active':
            return 'bg-success';

        case 'inactive':
            return 'bg-secondary';

        default:
            return 'bg-secondary';
    }
}


/**
 * Get readable status name
 */
function statusLabel($status)
{
    return ucfirst($status);
}


/**
 * Get all candidates
 */
function getCandidates($conn, $category = null)
{
    if ($category !== null) {

        $sql = "
            SELECT
                id,
                name,
                category,
                photo,
                status,
                created_at,
                updated_at
            FROM candidates
            WHERE category = ?
            ORDER BY name ASC
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return [];
        }

        $stmt->bind_param("s", $category);
        $stmt->execute();

        $result = $stmt->get_result();

        $candidates = [];

        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row;
        }

        $stmt->close();

        return $candidates;
    }


    $sql = "
        SELECT
            id,
            name,
            category,
            photo,
            status,
            created_at,
            updated_at
        FROM candidates
        ORDER BY category ASC, name ASC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    $candidates = [];

    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }

    return $candidates;
}


/**
 * Get one candidate
 */
function getCandidate($conn, $id)
{
    $sql = "
        SELECT
            id,
            name,
            category,
            photo,
            status,
            created_at,
            updated_at
        FROM candidates
        WHERE id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    $candidate = $result->fetch_assoc();

    $stmt->close();

    return $candidate ?: null;
}


/**
 * Validate candidate category
 */
function isValidCategory($category)
{
    $allowed = [
        'face_of_the_night',
        'star_of_the_night',
        'darling_of_the_crowd'
    ];

    return in_array($category, $allowed, true);
}


/**
 * Validate candidate status
 */
function isValidStatus($status)
{
    return in_array(
        $status,
        ['active', 'inactive'],
        true
    );
}


/**
 * Validate candidate name
 */
function isValidCandidateName($name)
{
    $name = trim($name);

    if ($name === '') {
        return false;
    }

    if (strlen($name) > 150) {
        return false;
    }

    return true;
}


/**
 * Upload candidate photo
 *
 * Returns:
 *     relative uploaded path on success
 *     false on failure
 *
 * Errors are returned through $error.
 */
function uploadCandidatePhoto($file, &$error = null)
{
    $error = null;

    if (
        !isset($file) ||
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }


    if ($file['error'] !== UPLOAD_ERR_OK) {

        $error = "There was a problem uploading the photo.";

        return false;
    }


    /**
     * Maximum file size: 5MB
     */
    $maxSize = 5 * 1024 * 1024;

    if ($file['size'] > $maxSize) {

        $error = "Candidate photo must not exceed 5MB.";

        return false;
    }


    /**
     * Verify actual MIME type
     */
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mime = $finfo->file($file['tmp_name']);


    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];


    if (!isset($allowedMimeTypes[$mime])) {

        $error = "Only JPG, PNG, and WEBP images are allowed.";

        return false;
    }


    /**
     * Verify that the uploaded file is actually an image
     */
    if (@getimagesize($file['tmp_name']) === false) {

        $error = "The uploaded file is not a valid image.";

        return false;
    }


    /**
     * Upload directory
     */
    $uploadDirectory = dirname(__DIR__) . "/uploads/candidates/";


    /**
     * Create directory if it does not exist
     */
    if (!is_dir($uploadDirectory)) {

        if (!mkdir($uploadDirectory, 0755, true)) {

            $error = "Unable to create the candidate upload directory.";

            return false;
        }
    }


    /**
     * Generate unique filename
     */
    $extension = $allowedMimeTypes[$mime];

    $filename = uniqid(
        'candidate_',
        true
    ) . '.' . $extension;


    $destination = $uploadDirectory . $filename;


    /**
     * Move uploaded file
     */
    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {

        $error = "Unable to save the uploaded photo.";

        return false;
    }


    /**
     * Path stored in database
     */
    return "uploads/candidates/" . $filename;
}


/**
 * Delete candidate photo
 */
function deleteCandidatePhoto($photo)
{
    if (!$photo) {
        return true;
    }

    $file = dirname(__DIR__) . "/" . $photo;

    if (file_exists($file)) {
        return unlink($file);
    }

    return true;
}


/**
 * Add candidate
 */
function createCandidate(
    $conn,
    $name,
    $category,
    $photo = null,
    $status = 'active'
) {
    $sql = "
        INSERT INTO candidates
        (
            name,
            category,
            photo,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "ssss",
        $name,
        $category,
        $photo,
        $status
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}


/**
 * Update candidate
 */
function updateCandidate(
    $conn,
    $id,
    $name,
    $category,
    $photo = null,
    $status = 'active'
) {
    /**
     * If a new photo was uploaded
     */
    if ($photo !== null) {

        $sql = "
            UPDATE candidates
            SET
                name = ?,
                category = ?,
                photo = ?,
                status = ?
            WHERE id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "ssssi",
            $name,
            $category,
            $photo,
            $status,
            $id
        );

    } else {

        /**
         * Keep existing photo
         */
        $sql = "
            UPDATE candidates
            SET
                name = ?,
                category = ?,
                status = ?
            WHERE id = ?
        ";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param(
            "sssi",
            $name,
            $category,
            $status,
            $id
        );
    }


    $success = $stmt->execute();

    $stmt->close();

    return $success;
}


/**
 * Delete candidate
 */
function deleteCandidate($conn, $id)
{
    /**
     * Get candidate first
     * so we can remove the photo.
     */
    $candidate = getCandidate(
        $conn,
        $id
    );

    if (!$candidate) {
        return false;
    }


    $sql = "
        DELETE FROM candidates
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        "i",
        $id
    );

    $success = $stmt->execute();

    $stmt->close();


    /**
     * Delete photo after successful
     * database deletion.
     */
    if ($success && !empty($candidate['photo'])) {

        deleteCandidatePhoto(
            $candidate['photo']
        );
    }


    return $success;
}


/**
 * Count candidates
 */
function countCandidates(
    $conn,
    $category = null,
    $status = null
) {
    $conditions = [];
    $params = [];
    $types = "";


    if ($category !== null) {

        $conditions[] = "category = ?";
        $params[] = $category;
        $types .= "s";
    }


    if ($status !== null) {

        $conditions[] = "status = ?";
        $params[] = $status;
        $types .= "s";
    }


    $sql = "
        SELECT COUNT(*) AS total
        FROM candidates
    ";


    if (!empty($conditions)) {

        $sql .= " WHERE " .
            implode(
                " AND ",
                $conditions
            );
    }


    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }


    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }


    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();


    return (int) ($row['total'] ?? 0);
}


/**
 * Flash message
 */
function setFlashMessage(
    $type,
    $message
) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


/**
 * Get and remove flash message
 */
function getFlashMessage()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];

    unset($_SESSION['flash']);

    return $flash;
}
