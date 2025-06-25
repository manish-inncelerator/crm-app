<?php
// Logging function
function writeLog($message, $type = 'INFO')
{
    $logFile = __DIR__ . '/logs/auth.log';
    $logDir = dirname($logFile);

    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp][$type] $message" . PHP_EOL;

    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

/**
 * Print HTML start tags with common meta tags and CSS links
 * @param string $title Page title
 * @param array $additional_css Additional CSS files to include
 */
function html_start($title = 'CRM System', $additional_css = [])
{
    // Output HTML start tags
    ob_start(); // Start output buffering
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>

        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

        <!-- Font Awesome -->
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

        <!-- Bootstrap Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

        <!-- Custom CSS -->
        <link href="assets/css/style.css" rel="stylesheet">

        <?php
        // Add any additional CSS files
        if (!empty($additional_css)) {
            foreach ($additional_css as $css) {
                echo '<link href="' . htmlspecialchars($css) . '" rel="stylesheet">' . "\n";
            }
        }
        ?>
    </head>

    <body>
    <?php
    ob_end_flush(); // Flush the output buffer
}

/**
 * Print HTML end tags and include common JavaScript files
 * @param array $additional_js Additional JavaScript files to include
 */
function html_end($additional_js = [])
{
    // Output HTML end tags
    ob_start(); // Start output buffering
    ?>
        <!-- jQuery -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- Custom JavaScript -->
        <!-- <script src="assets/js/main.js"></script> -->

        <?php
        // Add any additional JavaScript files
        if (!empty($additional_js)) {
            foreach ($additional_js as $js) {
                echo '<script src="' . htmlspecialchars($js) . '"></script>' . "\n";
            }
        }
        ?>
    </body>

    </html>
<?php
    ob_end_flush(); // Flush the output buffer
}
