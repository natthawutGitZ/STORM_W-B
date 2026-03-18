<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404: This page could not be found.</title>
    <style>
        body {
            background-color: #000;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }

        .error-container {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        .error-code {
            font-size: 24px;
            font-weight: 500;
            padding-right: 20px;
            border-right: 1px solid rgba(255, 255, 255, 0.3);
            margin-right: 20px;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .error-message {
            font-size: 14px;
            font-weight: 400;
            color: rgba(255, 255, 255, 0.8);
            letter-spacing: 0.2px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Removed link to keep it clean like Next.js, 
           but can add an invisible one if needed for extreme accessibility */
    </style>
</head>

<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-message">This page could not be found.</div>
    </div>
</body>

</html>