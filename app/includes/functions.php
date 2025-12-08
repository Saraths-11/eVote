<?php

define('DATA_DIR', __DIR__ . '/../data/');

function getJSONData($filename)
{
    $path = DATA_DIR . $filename;
    if (!file_exists($path)) {
        return [];
    }
    $json = file_get_contents($path);
    return json_decode($json, true) ?? [];
}

function saveJSONData($filename, $data)
{
    $path = DATA_DIR . $filename;
    // Ensure directory exists
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0777, true);
    }
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url, $message = "", $type = "success")
{
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

function displayFlashMessage()
{
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] == 'error' ? 'danger' : 'success';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $_SESSION['flash_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}
?>