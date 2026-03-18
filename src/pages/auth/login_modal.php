<!-- Login Modal -->
<link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">

<div id="loginModal" class="login-overlay" style="display: none;">
    <div class="login-modal">
        <div class="login-header" style="position: relative;">
            <h2>Login</h2>
            <span class="close-modal" onclick="closeLoginModal()"
                style="position: absolute; right: 20px; top: 20px; cursor: pointer; font-size: 1.5rem; color: #aaa;">&times;</span>
        </div>

        <div class="login-body">
            <?php if (isset($_GET['login_error'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($_GET['login_error']); ?></div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        document.getElementById('loginModal').style.display = 'flex';
                    });
                </script>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="login-tabs"
                style="display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px;">
                <div class="tab-item active" onclick="switchTab('login')" id="tab-login"
                    style="flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #fff; font-weight: bold; border-bottom: 2px solid var(--accent-color);">
                    Login</div>
                <div class="tab-item" onclick="switchTab('register')" id="tab-register"
                    style="flex: 1; text-align: center; padding: 10px; cursor: pointer; color: #888;">Register</div>
            </div>

            <!-- Login View -->
            <div id="view-login">
                <div class="login-section">
                    <?php
                    // Ensure $steam is available
                    if (!isset($steam)) {
                        require_once ROOT_PATH . '/includes/steam_auth.php';
                        $apiKey = 'B94B9BCB6D873EDCE062274CC3E52AA9';
                        $domain = 'http://' . $_SERVER['HTTP_HOST'] . '/';
                        $steam = new SteamAuth($apiKey, $domain);
                    }
                    ?>
                    <a href="<?php echo $steam->loginUrl(); ?>" class="btn-steam">
                        <svg width="24" height="24" viewBox="0 0 256 256" fill="currentColor">
                            <path
                                d="M127.999 0C57.421 0 0 57.42 0 127.999c0 61.103 42.937 112.214 100.352 125.049l34.513-50.371c-4.18-1.787-8.896-2.787-13.865-2.787-19.107 0-34.641 15.534-34.641 34.641 0 19.107 15.534 34.641 34.641 34.641 19.107 0 34.641-15.534 34.641-34.641 0-1.104-.052-2.195-.149-3.272l49.905-35.651c0-.104.001-.208.001-.312 0-28.612-23.229-51.841-51.841-51.841-1.234 0-2.455.044-3.664.128L128.001 71.36V71.36c0-28.612 23.229-51.841 51.841-51.841 28.612 0 51.841 23.229 51.841 51.841 0 28.612-23.229 51.841-51.841 51.841-.927 0-1.846-.025-2.757-.074l-35.797 51.206c.524 2.105.797 4.298.797 6.549 0 14.325-11.634 25.959-25.959 25.959-14.325 0-25.959-11.634-25.959-25.959 0-14.325 11.634-25.959 25.959-25.959 1.462 0 2.894.122 4.289.355l35.105-50.236c-14.686-5.487-25.195-19.509-25.195-35.848 0-21.239 17.227-38.466 38.466-38.466 21.239 0 38.466 17.227 38.466 38.466 0 21.239-17.227 38.466-38.466 38.466-.729 0-1.451-.021-2.167-.062l-35.369 50.574c12.917 6.076 21.895 19.205 21.895 34.318 0 21.239-17.227 38.466-38.466 38.466-21.239 0-38.466-17.227-38.466-38.466 0-1.733.115-3.438.337-5.109L28.801 181.38C11.279 163.208 0 138.103 0 110.359 0 49.441 49.441 0 110.359 0h17.64z" />
                        </svg>
                        Sign in with Steam
                    </a>
                    <a href="login_google.php" class="btn-steam"
                        style="background: #fff; color: #444; margin-top: 10px; justify-content: center; display: flex; align-items: center; gap: 10px;">
                        <i class="fab fa-google" style="color: #DB4437;"></i> Sign in with Google
                    </a>
                </div>

                <div class="login-divider"><span>OR</span></div>

                <div class="login-section">
                    <form action="login.php" method="POST">
                        <div class="form-group">
                            <label for="username">Name:</label>
                            <input type="text" id="username" name="username" class="login-input" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" class="login-input" required>
                        </div>
                        <button type="submit" name="login_submit" class="btn-login">Login</button>
                    </form>
                </div>
            </div>

            <!-- Register View -->
            <div id="view-register" style="display: none;">
                <div class="login-section">
                    <form id="registerForm" onsubmit="handleRegister(event)">
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="username" class="login-input" required>
                        </div>
                        <div class="form-group">
                            <label>Password:</label>
                            <input type="password" name="password" class="login-input" required>
                        </div>
                        <div class="form-group">
                            <label>Steam ID (64-bit):</label>
                            <input type="text" name="steamid" class="login-input" required placeholder="7656119...">
                            <small style="color: #666; font-size: 0.8em;">We verify this ID exists.</small>
                        </div>
                        <button type="submit" class="btn-login"
                            style="background: var(--accent-color);">Register</button>
                    </form>
                    <div id="register-msg" style="margin-top: 10px; text-align: center;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function switchTab(tab) {
        if (tab === 'login') {
            document.getElementById('view-login').style.display = 'block';
            document.getElementById('view-register').style.display = 'none';
            document.getElementById('tab-login').classList.add('active');
            document.getElementById('tab-register').classList.remove('active');
            document.getElementById('tab-login').style.borderBottom = '2px solid var(--accent-color)';
            document.getElementById('tab-login').style.color = '#fff';
            document.getElementById('tab-register').style.borderBottom = 'none';
            document.getElementById('tab-register').style.color = '#888';
        } else {
            document.getElementById('view-login').style.display = 'none';
            document.getElementById('view-register').style.display = 'block';
            document.getElementById('tab-login').classList.remove('active');
            document.getElementById('tab-register').classList.add('active');
            document.getElementById('tab-register').style.borderBottom = '2px solid var(--accent-color)';
            document.getElementById('tab-register').style.color = '#fff';
            document.getElementById('tab-login').style.borderBottom = 'none';
            document.getElementById('tab-login').style.color = '#888';
        }
    }

    async function handleRegister(e) {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const msg = document.getElementById('register-msg');
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Processing...';
        msg.textContent = '';
        msg.className = '';

        const formData = new FormData(e.target);

        try {
            const res = await fetch('register_process.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json());

            if (res.success) {
                msg.textContent = 'Registration Successful! Logging in...';
                msg.style.color = '#4CAF50';
                setTimeout(() => window.location.href = 'profile.php', 1000);
            } else {
                msg.textContent = res.message || 'Registration failed.';
                msg.style.color = '#f44336';
                btn.disabled = false;
                btn.textContent = originalText;
            }
        } catch (err) {
            msg.textContent = 'Server Error.';
            msg.style.color = '#f44336';
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    function openLoginModal() {
        document.getElementById('loginModal').style.display = 'flex';
    }

    function closeLoginModal() {
        document.getElementById('loginModal').style.display = 'none';
    }

    // Close on outside click
    window.onclick = function (event) {
        var modal = document.getElementById('loginModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>
