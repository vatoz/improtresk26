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