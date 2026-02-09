<?php
/**
 * Global Error Handler
 */

// Custom error handler function
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_types = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    
    $error_type = $error_types[$errno] ?? 'Unknown Error';
    
    // Log error (in production, log to file)
    error_log("[$error_type] $errstr in $errfile on line $errline");
    
    // Don't display errors in production
    if (DISPLAY_ERRORS) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>$error_type:</strong> $errstr<br>";
        echo "<small>File: $errfile (Line: $errline)</small>";
        echo "</div>";
    }
    
    return true;
}

// Custom exception handler
function customExceptionHandler($exception) {
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
    
    if (DISPLAY_ERRORS) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Exception:</strong> " . $exception->getMessage() . "<br>";
        echo "<small>File: " . $exception->getFile() . " (Line: " . $exception->getLine() . ")</small><br>";
        echo "<pre>" . $exception->getTraceAsString() . "</pre>";
        echo "</div>";
    } else {
        // Redirect to generic error page
        header("Location: error.php?code=500&message=" . urlencode("An unexpected error occurred."));
        exit;
    }
}

// Set error handlers
set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");

// Error handling function for redirects
function redirectToError($code = '500', $message = 'An error occurred') {
    header("Location: error.php?code=" . urlencode($code) . "&message=" . urlencode($message));
    exit;
}

// Function to handle 404 errors
function handle404() {
    redirectToError('404', 'Page Not Found');
}

// Function to handle 403 errors
function handle403() {
    redirectToError('403', 'Access Denied');
}

// Function to handle 500 errors
function handle500($message = 'Internal Server Error') {
    redirectToError('500', $message);
}
?>