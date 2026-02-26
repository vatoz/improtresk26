// Registration countdown
(function () {
    const el = document.getElementById('countdown');
    if (!el) return;

    const target = new Date(el.dataset.target.replace(' ', 'T'));
    if (isNaN(target)) return;

    const days    = document.getElementById('cd-days');
    const hours   = document.getElementById('cd-hours');
    const minutes = document.getElementById('cd-minutes');
    const seconds = document.getElementById('cd-seconds');

    function pad(n) { return String(n).padStart(2, '0'); }

    function tick() {
        const diff = target - Date.now();
        if (diff <= 0) {
            days.textContent = hours.textContent = minutes.textContent = seconds.textContent = '00';
            clearInterval(timer);
            return;
        }
        const totalSeconds = Math.floor(diff / 1000);
        days.textContent    = pad(Math.floor(totalSeconds / 86400));
        hours.textContent   = pad(Math.floor((totalSeconds % 86400) / 3600));
        minutes.textContent = pad(Math.floor((totalSeconds % 3600) / 60));
        seconds.textContent = pad(totalSeconds % 60);
    }

    tick();
    const timer = setInterval(tick, 1000);
}());

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Password reset form handler
document.addEventListener('DOMContentLoaded', function() {
    const resetForm = document.getElementById('password-reset-form');
    if (resetForm) {
        resetForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('reset-email').value;
            const messageDiv = document.getElementById('reset-message');
            const submitBtn = resetForm.querySelector('button[type="submit"]');

            // Disable submit button
            submitBtn.disabled = true;
            submitBtn.textContent = 'Odesílání...';

            try {
                const response = await fetch('/api/request-password-reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                // Show message
                messageDiv.classList.remove('hidden', 'alert-error', 'alert-success');
                messageDiv.classList.add(data.success ? 'alert-success' : 'alert-error');
                messageDiv.textContent = data.message;

                if (data.success) {
                    resetForm.reset();
                }
            } catch (error) {
                messageDiv.classList.remove('hidden', 'alert-success');
                messageDiv.classList.add('alert-error');
                messageDiv.textContent = 'Chyba při odesílání požadavku. Zkuste to prosím později.';
            } finally {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Odeslat odkaz';
            }
        });
    }
});