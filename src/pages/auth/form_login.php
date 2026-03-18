<?php
// src/form_login.php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$redirect = $_GET['redirect'] ?? 'index.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Form Access</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/forms.css">
    <meta name="referrer" content="origin">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        body {
            background-color: var(--form-bg);
            background-image: radial-gradient(circle at 50% 50%, rgba(197, 160, 89, 0.1), rgba(15, 15, 15, 0));
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: var(--form-card-bg);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
        }

        .brand-icon {
            font-size: 2rem;
            color: var(--action-color);
            margin-bottom: 20px;
        }

        h1 {
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        p {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="brand-icon">⚡</div>
        <h1>Sign In Required</h1>
        <p>Please sign in with your Google account to access this form.</p>

        <!-- Google Button Container -->
        <div id="g_id_onload" data-client_id="923314009608-jvtn1svgun0eo7bgie8ubln4rp99pt7p.apps.googleusercontent.com"
            data-context="signin" data-ux_mode="popup" data-callback="handleCredentialResponse"
            data-auto_prompt="false">
        </div>

        <div class="g_id_signin" data-type="standard" data-shape="rectangular" data-theme="outline"
            data-text="sign_in_with" data-size="large" data-logo_alignment="left" data-width="320">
        </div>
    </div>

    <script>
        function handleCredentialResponse(response) {
            // Send token to backend
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'form_google_callback.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'credential';
            input.value = response.credential;
            form.appendChild(input);

            const redirect = document.createElement('input');
            redirect.type = 'hidden';
            redirect.name = 'redirect';
            redirect.value = '<?php echo htmlspecialchars($redirect); ?>';
            form.appendChild(redirect);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>

</html>
