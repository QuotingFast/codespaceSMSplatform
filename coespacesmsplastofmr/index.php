<?php
// Dynamic router for short URLs like /123456 -> /quote.php?id=123456
if (preg_match('~^/(\d{6})$~', $_SERVER['REQUEST_URI'], $matches)) {
    // Track page view in the database
    include_once('includes/config.php');
    $lead_id = $matches[1];
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("UPDATE leads SET page_views = page_views + 1, last_viewed = NOW() WHERE id = ?");
        $stmt->bind_param("i", $lead_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    }
    
    // Redirect to the quote page
    header('Location: /quote.php?id='.$matches[1]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuotingFast.io - Get Your Insurance Quote Fast</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="icon" href="/favicon.ico">
</head>
<body class="home-page">
    <div class="container text-center py-5">
        <img src="/images/logo.png" alt="QuotingFast.io Logo" class="img-fluid mb-4" style="max-width: 250px;">
        <h1 class="display-4 fw-bold">Insurance Quotes Fast</h1>
        <p class="lead mb-4">The fastest way to get your personalized insurance quote</p>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h2 class="card-title">Looking for your quote?</h2>
                        <p class="card-text">If you received a text message with your quote link, please use that link to access your personalized quote.</p>
                        <p class="fw-bold">Or contact us:</p>
                        <a href="tel:+18889711908" class="btn btn-primary btn-lg px-5 py-3 call-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone-fill me-2" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                            </svg>
                            Call Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="text-center py-4">
        <p class="mb-0 text-muted">&copy; <?= date('Y') ?> QuotingFast.io. All rights reserved.</p>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/js/ringba-integration.js"></script>
</body>
</html>