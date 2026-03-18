<!-- Login Modal -->
<link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">

<div id="loginModal" class="login-overlay" style="display: none;">
    <div class="login-modal">
        <div class="login-header" style="position: relative;">
            <h2 id="modal-title">Login</h2>
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

            <!-- Login View -->
            <div id="view-login">
                <div class="login-section">
                    <?php
                    if (!isset($steam)) {
                        require_once ROOT_PATH . '/includes/steam_auth.php';
                        $apiKey = 'B94B9BCB6D873EDCE062274CC3E52AA9';
                        $domain = 'http://' . $_SERVER['HTTP_HOST'] . '/';
                        $steam = new SteamAuth($apiKey, $domain);
                    }
                    ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 0;">
                        <a href="<?php echo $steam->loginUrl(); ?>" class="btn-steam">
                            <i class="fab fa-steam"></i>
                            <span>Sign in with Steam</span>
                        </a>
                    </div>
                    <a href="login_google.php" class="btn-google" style="
                        background: linear-gradient(135deg, #131314 0%, #323335 100%);
                        color: #e3e3e3; 
                        border: 1.5px solid #8e918f;
                        font-family: 'Roboto', arial, sans-serif;
                        font-weight: 600;
                        position: relative;
                        overflow: hidden;
                        margin-top: 10px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        border-radius: 12px; /* Match Steam btn radius */
                        font-size: 1rem;
                        padding: 1rem 1.5rem; /* Match Steam btn padding */
                        width: 100%; 
                        box-sizing: border-box;
                        text-decoration: none;
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                        letter-spacing: 0.5px;
                    " onmouseover="this.style.background='linear-gradient(135deg, #2d2e30 0%, #454649 100%)'; this.style.borderColor='#d2e3fc'; this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 20px rgba(255, 255, 255, 0.1), 0 0 30px rgba(255, 255, 255, 0.05)';"
                        onmouseout="this.style.background='linear-gradient(135deg, #131314 0%, #323335 100%)'; this.style.borderColor='#8e918f'; this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)';">
                        <i class="fab fa-google" style="margin-right: 12px; color: #fff; font-size: 1.2rem;"></i>
                        <span>Sign in with Google</span>
                    </a>
                </div>

                <div class="login-divider">
                    <span>OR LOGIN WITH</span>
                </div>

                <div class="login-section">
                    <form action="login.php" method="POST">
                        <div class="form-group">
                            <label for="username">Name:</label>
                            <input type="text" id="username" name="username" class="login-input" required
                                autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" class="login-input" required>
                        </div>
                        <button type="submit" name="login_submit" class="btn-login">Login</button>
                    </form>
                </div>

                <div
                    style="margin-top: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <span style="color: #888;">Don't have an account?</span>
                    <a href="javascript:void(0)" onclick="toggleView('register')"
                        style="color: #fff; font-weight: bold; margin-left: 5px; text-decoration: none;">Register</a>
                </div>
            </div>

            <!-- Register View -->
            <div id="view-register" style="display: none;">
                <div class="login-section">
                    <form id="registerForm" onsubmit="handleRegister(event)">
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="username" class="login-input" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label>Password:</label>
                            <input type="password" name="password" class="login-input" required>
                        </div>
                        <div class="form-group">
                            <label>Steam ID (64-bit):</label>
                            <input type="text" name="steamid" class="login-input" required placeholder="7656119..."
                                autocomplete="off">
                            <small style="color: #666; font-size: 0.8em;">We verify this ID exists.</small>
                        </div>
                        <button type="submit" class="btn-login"
                            style="background: var(--accent-color, #4CAF50); color: white;">Register</button>
                    </form>
                    <div id="register-msg" style="margin-top: 10px; text-align: center;"></div>
                </div>

                <div
                    style="margin-top: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <span style="color: #888;">Already have an account?</span>
                    <a href="javascript:void(0)" onclick="toggleView('login')"
                        style="color: #fff; font-weight: bold; margin-left: 5px; text-decoration: none;">Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleView(view) {
        if (view === 'login') {
            document.getElementById('view-login').style.display = 'block';
            document.getElementById('view-register').style.display = 'none';
            document.getElementById('modal-title').textContent = 'Login';
        } else {
            document.getElementById('view-login').style.display = 'none';
            document.getElementById('view-register').style.display = 'block';
            document.getElementById('modal-title').textContent = 'Register';
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
