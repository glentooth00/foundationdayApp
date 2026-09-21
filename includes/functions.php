
<?php

/**
 * Escape HTML output.
 */
function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/**
 * Redirect to another page.
 */
function redirect($url)
{
    header("Location: " . $url);
    exit;
}


/**
 * Store a flash message.
 */
function setFlashMessage($type, $message)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}


/**
 * Retrieve and remove flash message.
 */
function getFlashMessage()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $message = $_SESSION['flash_message'];

    unset($_SESSION['flash_message']);

    return $message;
}


/**
 * Validate candidate name.
 */
function isValidCandidateName($name)
{
    $name = trim($name);

    if ($name === '') {
        return false;
    }

    if (strlen($name) < 2) {
        return false;
    }

    if (strlen($name) > 150) {
        return false;
    }

    return true;
}


/**
 * Validate candidate category.
 */
function isValidCategory($category)
{
    $allowed = [
        'face_of_the_night',
        'star_of_the_night',
        'darling_of_the_crowd'
    ];

    return in_array(
        $category,
        $allowed,
        true
    );
}


/**
 * Validate candidate gender.
 */
function isValidGender($gender)
{
    $allowed = [
        'male',
        'female'
    ];

    return in_array(
        $gender,
        $allowed,
        true
    );
}


/**
 * Validate candidate status.
 */
function isValidStatus($status)
{
    $allowed = [
        'active',
        'inactive'
    ];

    return in_array(
        $status,
        $allowed,
        true
    );
}


/**
 * Get human-readable category name.
 */
function categoryLabel($category)
{
    $labels = [
        'face_of_the_night' => 'Face of the Night',
        'star_of_the_night' => 'Star of the Night',
        'darling_of_the_crowd' => 'Darling of the Crowd'
    ];

    return isset($labels[$category])
        ? $labels[$category]
        : $category;
}


/**
 * Get human-readable gender name.
 */
function genderLabel($gender)
{
    $labels = [
        'male' => 'Male',
        'female' => 'Female'
    ];

    return isset($labels[$gender])
        ? $labels[$gender]
        : $gender;
}


/**
 * Get human-readable status.
 */
function statusLabel($status)
{
    return $status === 'active'
        ? 'Active'
        : 'Inactive';
}


/**
 * Get Bootstrap badge class for status.
 */
function statusBadgeClass($status)
{
    return $status === 'active'
        ? 'bg-success'
        : 'bg-secondary';
}


/**
 * Upload candidate photo.
 *
 * @param array  $file
 * @param string $error
 *
 * @return string|null
 */
function uploadCandidatePhoto($file, &$error)
{
    $error = '';

    if (
        !isset($file) ||
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Failed to upload the candidate photo.';
        return null;
    }

    /*
     * Maximum file size: 5 MB
     */
    $maxSize = 5 * 1024 * 1024;

    if ($file['size'] > $maxSize) {
        $error = 'Candidate photo must not exceed 5 MB.';
        return null;
    }

    /*
     * Validate actual MIME type.
     */
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if (!$finfo) {
        $error = 'Unable to validate the uploaded photo.';
        return null;
    }

    $mimeType = finfo_file(
        $finfo,
        $file['tmp_name']
    );

    finfo_close($finfo);

    if (!isset($allowedMimeTypes[$mimeType])) {
        $error = 'Only JPG, PNG, and WEBP images are allowed.';
        return null;
    }

    /*
     * Verify that the uploaded file is a real image.
     */
    if (@getimagesize($file['tmp_name']) === false) {
        $error = 'The uploaded file is not a valid image.';
        return null;
    }

    /*
     * Create upload directory.
     */
    $uploadDirectory = dirname(__DIR__)
        . DIRECTORY_SEPARATOR
        . 'uploads'
        . DIRECTORY_SEPARATOR
        . 'candidates';

    if (!is_dir($uploadDirectory)) {

        if (!mkdir(
            $uploadDirectory,
            0755,
            true
        )) {

            $error = 'Unable to create the candidate upload directory.';

            return null;
        }
    }

    /*
     * Generate unique filename.
     */
    try {

        $randomName = bin2hex(
            random_bytes(16)
        );

    } catch (Exception $exception) {

        $randomName = uniqid(
            '',
            true
        );
    }

    $extension = $allowedMimeTypes[$mimeType];

    $filename = $randomName . '.' . $extension;

    $destination = $uploadDirectory
        . DIRECTORY_SEPARATOR
        . $filename;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {

        $error = 'Unable to save the candidate photo.';

        return null;
    }

    /*
     * Store relative path in database.
     */
    return 'uploads/candidates/' . $filename;
}


/**
 * Delete candidate photo.
 */
function deleteCandidatePhoto($photo)
{
    if (empty($photo)) {
        return true;
    }

    $baseDirectory = realpath(
        dirname(__DIR__)
        . DIRECTORY_SEPARATOR
        . 'uploads'
        . DIRECTORY_SEPARATOR
        . 'candidates'
    );

    if ($baseDirectory === false) {
        return false;
    }

    $filename = basename($photo);

    $filePath = $baseDirectory
        . DIRECTORY_SEPARATOR
        . $filename;

    if (file_exists($filePath)) {
        return unlink($filePath);
    }

    return true;
}


/**
 * Create candidate.
 */
function createCandidate(
    $conn,
    $name,
    $category,
    $gender,
    $photo,
    $status
) {
    $sql = "
        INSERT INTO candidates
        (
            name,
            category,
            gender,
            photo,
            status
        )
        VALUES
        (
            ?,
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
        "sssss",
        $name,
        $category,
        $gender,
        $photo,
        $status
    );

    $result = $stmt->execute();

    $stmt->close();

    return $result;
}


/**
 * Get one candidate by ID.
 */
function getCandidate($conn, $id)
{
    $id = (int) $id;

    $sql = "
        SELECT
            id,
            name,
            category,
            gender,
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

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $candidate = $result->fetch_assoc();

    $stmt->close();

    return $candidate
        ? $candidate
        : null;
}


/**
 * Get candidates.
 *
 * Optional filters:
 * - category
 * - gender
 */
function getCandidates(
    $conn,
    $category = '',
    $gender = ''
) {
    $conditions = [];
    $params = [];
    $types = '';

    if (
        $category !== '' &&
        isValidCategory($category)
    ) {

        $conditions[] = 'category = ?';

        $params[] = $category;

        $types .= 's';
    }

    if (
        $gender !== '' &&
        isValidGender($gender)
    ) {

        $conditions[] = 'gender = ?';

        $params[] = $gender;

        $types .= 's';
    }

    $sql = "
        SELECT
            id,
            name,
            category,
            gender,
            photo,
            status,
            created_at,
            updated_at
        FROM candidates
    ";

    if (!empty($conditions)) {

        $sql .= ' WHERE '
            . implode(
                ' AND ',
                $conditions
            );
    }

    $sql .= "
        ORDER BY
            category ASC,
            gender ASC,
            created_at DESC,
            id DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $candidates = [];

    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }

    $stmt->close();

    return $candidates;
}


/**
 * Update candidate.
 */
function updateCandidate(
    $conn,
    $id,
    $name,
    $category,
    $gender,
    $photo,
    $status
) {
    $sql = "
        UPDATE candidates
        SET
            name = ?,
            category = ?,
            gender = ?,
            photo = ?,
            status = ?
        WHERE id = ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $id = (int) $id;

    $stmt->bind_param(
        "sssssi",
        $name,
        $category,
        $gender,
        $photo,
        $status,
        $id
    );

    $result = $stmt->execute();

    $stmt->close();

    return $result;
}


/**
 * Delete candidate.
 */
function deleteCandidate($conn, $id)
{
    $candidate = getCandidate(
        $conn,
        $id
    );

    if (!$candidate) {
        return false;
    }

    $id = (int) $id;

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

    $result = $stmt->execute();

    $stmt->close();

    if (
        $result &&
        !empty($candidate['photo'])
    ) {

        deleteCandidatePhoto(
            $candidate['photo']
        );
    }

    return $result;
}


/**
 * Count candidates.
 *
 * Optional filters:
 * - category
 * - gender
 */
function countCandidates(
    $conn,
    $category = '',
    $gender = ''
) {
    $conditions = [];
    $params = [];
    $types = '';

    if (
        $category !== '' &&
        isValidCategory($category)
    ) {

        $conditions[] = 'category = ?';

        $params[] = $category;

        $types .= 's';
    }

    if (
        $gender !== '' &&
        isValidGender($gender)
    ) {

        $conditions[] = 'gender = ?';

        $params[] = $gender;

        $types .= 's';
    }

    $sql = "
        SELECT COUNT(*) AS total
        FROM candidates
    ";

    if (!empty($conditions)) {

        $sql .= ' WHERE '
            . implode(
                ' AND ',
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

    return isset($row['total'])
        ? (int) $row['total']
        : 0;
}
