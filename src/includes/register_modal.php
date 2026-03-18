<!-- Register Modal -->
<div id="registerModal" class="login-overlay" style="display: none;">
    <div class="login-modal" style="max-width: 800px; width: 95%; height: 90vh; display: flex; flex-direction: column;">
        <div class="login-header" style="position: relative; flex-shrink: 0;">
            <h2>Application</h2>
            <span class="close-modal" onclick="closeRegisterModal()"
                style="position: absolute; right: 20px; top: 20px; cursor: pointer; font-size: 1.5rem; color: #aaa;">&times;</span>
        </div>

        <div class="login-body" style="flex-grow: 1; padding: 0; overflow: hidden;">
            <iframe
                src="https://docs.google.com/forms/d/e/1FAIpQLSf8EEIObtrRDUOL6khSA-5-HZdgZSTuuJcIA4L3PAETEwXqFA/viewform?embedded=true"
                width="100%" height="100%" frameborder="0" marginheight="0" marginwidth="0"
                style="border: none; display: block;">
                Loading…
            </iframe>
        </div>
    </div>
</div>

<script>
    function openRegisterModal() {
        document.getElementById('registerModal').style.display = 'flex';
    }

    function closeRegisterModal() {
        document.getElementById('registerModal').style.display = 'none';
    }

    // Close on outside click (merging with login modal logic if possible, or separate)
    window.addEventListener('click', function (event) {
        var modal = document.getElementById('registerModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
</script>