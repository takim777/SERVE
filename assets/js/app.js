document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alertEl) {
        alertEl.setAttribute('data-serve-alert', 'ready');
    });

    const toggleButtons = document.querySelectorAll('[data-toggle-password]');
    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.getAttribute('data-toggle-password');
            if (!targetId) {
                return;
            }
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');

            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('bi-eye', !isPassword);
                icon.classList.toggle('bi-eye-slash', isPassword);
            }
        });
    });

    const registerForm = document.querySelector('form[data-auth-register="true"]');
    if (registerForm) {
        const passwordInput = registerForm.querySelector('#password');
        const confirmInput = registerForm.querySelector('#konfirmasi_password');

        function validatePasswordMatch() {
            if (!passwordInput || !confirmInput) {
                return;
            }
            if (confirmInput.value !== '' && passwordInput.value !== confirmInput.value) {
                confirmInput.setCustomValidity('Konfirmasi password tidak cocok dengan password.');
            } else {
                confirmInput.setCustomValidity('');
            }
        }

        if (passwordInput && confirmInput) {
            passwordInput.addEventListener('input', validatePasswordMatch);
            confirmInput.addEventListener('input', validatePasswordMatch);
        }
    }

    // Toggle sidebar responsif di layar HP/tablet
    const sidebar = document.getElementById('serveSidebar');
    const backdrop = document.getElementById('serveSidebarBackdrop');
    const openBtn = document.getElementById('serveSidebarToggle');
    const closeBtn = document.getElementById('serveSidebarClose');

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (backdrop) backdrop.classList.remove('show');
    }

    if (openBtn && sidebar && backdrop) {
        openBtn.addEventListener('click', function () {
            sidebar.classList.add('show');
            backdrop.classList.add('show');
        });
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    // Konfirmasi aksi berbahaya / penghapusan
    const confirmForms = document.querySelectorAll('form[data-confirm-action]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const msg = form.getAttribute('data-confirm-action') || 'Yakin ingin melanjutkan aksi ini?';
            if (!window.confirm(msg)) {
                event.preventDefault();
            }
        });
    });
});