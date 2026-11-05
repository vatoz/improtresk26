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

const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            const navbarLinks = document.getElementById('navbar-links');
            if (navbarLinks) {
                navbarLinks.classList.toggle('show');
            }
        });
    }
 

// Cart icon: gold blink + star instead of the item count after the cart content changed.
// Called directly after AJAX actions (with the new count) and on page load when the server
// flagged a change (data-cart-changed on the icon, set after form POST + redirect).
(function () {
    var timer = null;

    window.cartChanged = function (newCount) {
        var link  = document.querySelector('.nav-cart');
        if (!link) return;
        var badge = link.querySelector('.nav-cart__count');
        var toggle = document.getElementById('menu-toggle'); // on mobile the icon hides in the menu
        if (typeof newCount === 'number') link.dataset.count = newCount;

        [link, toggle].forEach(function (el) {
            if (!el) return;
            el.classList.remove('nav-cart--changed');
            void el.offsetWidth; // restart the animation when triggered repeatedly
            el.classList.add('nav-cart--changed');
        });
        badge.textContent = '★';
        badge.hidden = false;

        clearTimeout(timer);
        timer = setTimeout(function () {
            var count = parseInt(link.dataset.count, 10) || 0;
            badge.textContent = count;
            badge.hidden = count === 0;
            link.classList.remove('nav-cart--changed');
            if (toggle) toggle.classList.remove('nav-cart--changed');
        }, 1800);
    };

    var link = document.querySelector('.nav-cart[data-cart-changed]');
    if (link) window.cartChanged();
}());
