// Application State
let appState = {
    currentUser: null,
    currentPage: 'home',
    workshops: [],
    users: [],
    registrations: [],
    program: [],
    faq: []
};

// Festival Data
const festivalData = {
    "festival_info": {
        "name": "Improtřesk 2026",
        "date": "15-17. července 2026",
        "location": "Kulturní centrum Praha",
        "description": "Mezinárodní festival improvizačního divadla",
        "ticket_price": "1200 Kč"
    },
    "workshops": [
        {"id": 1, "name": "Základy improvizace", "description": "Úvodní workshop pro začátečníky", "instructor": "Jana Nováková", "capacity": 12, "current_enrolled": 8, "price": 500},
        {"id": 2, "name": "Scénická improvizace", "description": "Pokročilé techniky pro zkušené hráče", "instructor": "Tomáš Svoboda", "capacity": 12, "current_enrolled": 4, "price": 600},
        {"id": 3, "name": "Improvizace v angličtině", "description": "Workshop v anglickém jazyce", "instructor": "Mark Johnson", "capacity": 12, "current_enrolled": 9, "price": 700},
        {"id": 4, "name": "Hudební improvizace", "description": "Kombinace hudby a divadla", "instructor": "Pavel Hudeček", "capacity": 12, "current_enrolled": 6, "price": 550},
        {"id": 5, "name": "Komediální improvizace", "description": "Techniky pro humor a smích", "instructor": "Eva Veselá", "capacity": 12, "current_enrolled": 11, "price": 500},
        {"id": 6, "name": "Dlouhá forma", "description": "Vytváření delších improvizačních příběhů", "instructor": "Martin Kratochvíl", "capacity": 12, "current_enrolled": 3, "price": 650},
        {"id": 7, "name": "Improvizace pro děti", "description": "Speciálně navržený workshop pro mladé účastníky", "instructor": "Alena Dětinská", "capacity": 12, "current_enrolled": 7, "price": 400},
        {"id": 8, "name": "Tělová improvizace", "description": "Práce s tělem a pohybem", "instructor": "David Tanečník", "capacity": 12, "current_enrolled": 5, "price": 580},
        {"id": 9, "name": "Storytelling", "description": "Umění vyprávění příběhů", "instructor": "Klára Vypravěčka", "capacity": 12, "current_enrolled": 2, "price": 520},
        {"id": 10, "name": "Improvizační bojové umění", "description": "Bezpečné scénické boje v improvizaci", "instructor": "Jakub Bojovník", "capacity": 12, "current_enrolled": 10, "price": 600}
    ],
    "program": [
        {"time": "Pátek 15.7. - 19:00", "event": "Zahajovací ceremoniál", "description": "Oficiální zahájení festivalu s účastí všech skupin"},
        {"time": "Sobota 16.7. - 10:00", "event": "Workshop bloky", "description": "První kolo workshopů dle výběru účastníků"},
        {"time": "Sobota 16.7. - 14:00", "event": "Mezinárodní představení", "description": "Vystoupení zahraničních skupin"},
        {"time": "Sobota 16.7. - 20:00", "event": "Improvizační souboje", "description": "Soutěžní zápasy mezi týmy"},
        {"time": "Neděle 17.7. - 11:00", "event": "Závěrečné workshopy", "description": "Druhé kolo workshopů"},
        {"time": "Neděle 17.7. - 16:00", "event": "Závěrečné představení", "description": "Společné vystoupení všech účastníků"}
    ],
    "faq": [
        {"question": "Jak se registrovat na festival?", "answer": "Registrace probíhá online přes náš systém. Stačí si vytvořit účet a vybrat si jeden workshop."},
        {"question": "Můžu si vybrat více workshopů?", "answer": "Každý účastník si může vybrat pouze jeden workshop kvůli omezenému počtu míst."},
        {"question": "Co když je workshop plný?", "answer": "V takovém případě si můžete vybrat jiný workshop nebo se přidat na čekací listinu."},
        {"question": "Jak probíhá platba?", "answer": "Po registraci vám bude vygenerován QR kód pro bankovní převod s konkrétními platebními údaji."}
    ],
    "admin_credentials": {"email": "admin@improtresk.cz", "password": "admin123"},
    "bank_info": {"account": "1234567890/0100", "bank_name": "Komerční banka"}
};

// Utility Functions
function generateId() {
    return Date.now() + Math.random().toString(36).substr(2, 9);
}

function hashPassword(password) {
    // Simple hash simulation for demo
    return btoa(password + 'salt').replace(/[+/=]/g, '');
}

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const messageEl = document.getElementById('notification-message');
    
    if (notification && messageEl) {
        messageEl.textContent = message;
        notification.className = `notification ${type}`;
        notification.classList.remove('hidden');
        
        setTimeout(() => {
            notification.classList.add('hidden');
        }, 5000);
    }
}

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

function showPage(pageId) {
    // Hide all pages
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
    });
    
    // Show selected page
    const targetPage = document.getElementById(pageId + '-page');
    if (targetPage) {
        targetPage.classList.add('active');
        appState.currentPage = pageId;
    }
}

// Data Persistence
function saveData() {
    try {
        localStorage.setItem('impotresk_data', JSON.stringify({
            users: appState.users,
            registrations: appState.registrations,
            workshops: appState.workshops
        }));
    } catch (e) {
        console.warn('Failed to save data to localStorage:', e);
    }
}

function loadData() {
    try {
        const saved = localStorage.getItem('impotresk_data');
        if (saved) {
            const data = JSON.parse(saved);
            appState.users = data.users || [];
            appState.registrations = data.registrations || [];
            appState.workshops = data.workshops || [...festivalData.workshops];
        } else {
            appState.workshops = [...festivalData.workshops];
        }
    } catch (e) {
        console.warn('Failed to load data from localStorage:', e);
        appState.workshops = [...festivalData.workshops];
    }
    appState.program = [...festivalData.program];
    appState.faq = [...festivalData.faq];
}

// Authentication
function login(email, password) {
    const user = appState.users.find(u => u.email === email && u.password === hashPassword(password));
    if (user) {
        appState.currentUser = user;
        try {
            sessionStorage.setItem('currentUser', JSON.stringify(user));
        } catch (e) {
            console.warn('Failed to save user to sessionStorage:', e);
        }
        updateUI();
        hideModal('login-modal');
        showNotification('Úspěšně přihlášen!');
        return true;
    }
    
    // Check admin credentials
    if (email === festivalData.admin_credentials.email && password === festivalData.admin_credentials.password) {
        const adminUser = { id: 'admin', name: 'Administrator', email: email, role: 'admin' };
        appState.currentUser = adminUser;
        try {
            sessionStorage.setItem('currentUser', JSON.stringify(adminUser));
        } catch (e) {
            console.warn('Failed to save admin to sessionStorage:', e);
        }
        updateUI();
        hideModal('login-modal');
        showNotification('Přihlášen jako administrátor!');
        return true;
    }
    
    showNotification('Neplatné přihlašovací údaje!', 'error');
    return false;
}

function register(name, email, password) {
    if (appState.users.some(u => u.email === email)) {
        showNotification('Email již existuje!', 'error');
        return false;
    }
    
    const user = {
        id: generateId(),
        name: name,
        email: email,
        password: hashPassword(password),
        role: 'user',
        created_at: new Date().toISOString()
    };
    
    appState.users.push(user);
    saveData();
    showNotification('Registrace úspěšná! Můžete se přihlásit.');
    hideModal('register-modal');
    
    // Clear form
    const form = document.getElementById('register-form');
    if (form) form.reset();
    
    return true;
}

function logout() {
    appState.currentUser = null;
    try {
        sessionStorage.removeItem('currentUser');
    } catch (e) {
        console.warn('Failed to remove user from sessionStorage:', e);
    }
    updateUI();
    showPage('home');
    showNotification('Odhlášen!');
}

function updateUI() {
    const loginBtn = document.getElementById('login-btn');
    const registerBtn = document.getElementById('register-btn');
    const profileBtn = document.getElementById('profile-btn');
    const adminBtn = document.getElementById('admin-btn');
    const logoutBtn = document.getElementById('logout-btn');
    
    if (appState.currentUser) {
        if (loginBtn) loginBtn.classList.add('hidden');
        if (registerBtn) registerBtn.classList.add('hidden');
        if (profileBtn) profileBtn.classList.remove('hidden');
        if (logoutBtn) logoutBtn.classList.remove('hidden');
        
        if (appState.currentUser.role === 'admin') {
            if (adminBtn) adminBtn.classList.remove('hidden');
        } else {
            if (adminBtn) adminBtn.classList.add('hidden');
        }
    } else {
        if (loginBtn) loginBtn.classList.remove('hidden');
        if (registerBtn) registerBtn.classList.remove('hidden');
        if (profileBtn) profileBtn.classList.add('hidden');
        if (adminBtn) adminBtn.classList.add('hidden');
        if (logoutBtn) logoutBtn.classList.add('hidden');
    }
}

// Workshop Functions
function getWorkshopById(id) {
    return appState.workshops.find(w => w.id === parseInt(id));
}

function isWorkshopFull(workshopId) {
    const workshop = getWorkshopById(workshopId);
    return workshop && workshop.current_enrolled >= workshop.capacity;
}

function getUserRegistration(userId) {
    return appState.registrations.find(r => r.user_id === userId);
}

function registerForWorkshop(userId, workshopId, companionProgram = false) {
    if (!appState.currentUser) {
        showNotification('Musíte být přihlášeni!', 'error');
        return false;
    }
    
    const existingRegistration = getUserRegistration(userId);
    if (existingRegistration) {
        showNotification('Už jste registrováni na festival!', 'warning');
        return false;
    }
    
    const workshop = getWorkshopById(workshopId);
    if (!workshop) {
        showNotification('Workshop nenalezen!', 'error');
        return false;
    }
    
    if (isWorkshopFull(workshopId)) {
        showNotification('Workshop je plný!', 'error');
        return false;
    }
    
    const registration = {
        id: generateId(),
        user_id: userId,
        workshop_id: workshopId,
        companion_program: companionProgram,
        payment_status: 'pending',
        variable_symbol: generateId().substr(0, 8),
        created_at: new Date().toISOString()
    };
    
    appState.registrations.push(registration);
    workshop.current_enrolled++;
    saveData();
    
    return registration;
}

// QR Code Generation (Mock)
function generateQRCode(data) {
    const qrSize = 200;
    const qrElement = document.createElement('div');
    qrElement.className = 'qr-code';
    qrElement.style.width = qrSize + 'px';
    qrElement.style.height = qrSize + 'px';
    qrElement.style.background = `url("data:image/svg+xml,${encodeURIComponent(`
        <svg xmlns="http://www.w3.org/2000/svg" width="${qrSize}" height="${qrSize}" viewBox="0 0 ${qrSize} ${qrSize}">
            <rect width="${qrSize}" height="${qrSize}" fill="white"/>
            <text x="50%" y="45%" text-anchor="middle" font-size="12" fill="black">QR kód pro</text>
            <text x="50%" y="55%" text-anchor="middle" font-size="12" fill="black">platbu ${data.amount} Kč</text>
            <text x="50%" y="65%" text-anchor="middle" font-size="10" fill="gray">VS: ${data.variableSymbol}</text>
        </svg>
    `)}")`;
    return qrElement;
}

// Render Functions
function renderProgram() {
    const container = document.getElementById('program-grid');
    if (container) {
        container.innerHTML = appState.program.map(item => `
            <div class="program-item">
                <div class="program-time">${item.time}</div>
                <div class="program-event">${item.event}</div>
                <div class="program-description">${item.description}</div>
            </div>
        `).join('');
    }
}

function renderWorkshops() {
    const container = document.getElementById('workshops-grid');
    if (container) {
        container.innerHTML = appState.workshops.map(workshop => {
            const isFull = workshop.current_enrolled >= workshop.capacity;
            const progressPercent = (workshop.current_enrolled / workshop.capacity) * 100;
            
            return `
                <div class="workshop-card ${isFull ? 'full' : ''}" data-workshop-id="${workshop.id}">
                    <div class="workshop-header">
                        <h3 class="workshop-title">${workshop.name}</h3>
                        <div class="workshop-instructor">Lektor: ${workshop.instructor}</div>
                    </div>
                    <div class="workshop-body">
                        <p class="workshop-description">${workshop.description}</p>
                        <div class="workshop-info">
                            <div class="workshop-price">${workshop.price} Kč</div>
                            <div class="capacity-info ${isFull ? 'capacity-full' : ''}">
                                ${workshop.current_enrolled}/${workshop.capacity} míst
                            </div>
                        </div>
                        <div class="workshop-status">
                            <div class="workshop-progress">
                                <div class="workshop-progress-bar ${isFull ? 'full' : ''}" style="width: ${progressPercent}%"></div>
                            </div>
                            ${isFull ? '<span class="status-badge status-badge--full">Plný</span>' : '<span class="status-badge status-badge--pending">Dostupný</span>'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
}

function renderFAQ() {
    const container = document.getElementById('faq-list');
    if (container) {
        container.innerHTML = appState.faq.map((item, index) => `
            <div class="faq-item">
                <div class="faq-question" data-faq-index="${index}">
                    <span>${item.question}</span>
                    <span>+</span>
                </div>
                <div class="faq-answer" data-faq-index="${index}">
                    ${item.answer}
                </div>
            </div>
        `).join('');
        
        // Add click handlers for FAQ
        container.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', (e) => {
                const index = e.currentTarget.getAttribute('data-faq-index');
                const answer = document.querySelector(`.faq-answer[data-faq-index="${index}"]`);
                const icon = e.currentTarget.querySelector('span:last-child');
                
                if (answer && icon) {
                    if (answer.classList.contains('show')) {
                        answer.classList.remove('show');
                        icon.textContent = '+';
                    } else {
                        // Close all other answers
                        document.querySelectorAll('.faq-answer').forEach(a => a.classList.remove('show'));
                        document.querySelectorAll('.faq-question span:last-child').forEach(i => i.textContent = '+');
                        
                        answer.classList.add('show');
                        icon.textContent = '−';
                    }
                }
            });
        });
    }
}

function renderWorkshopSelect() {
    const select = document.getElementById('workshop-select');
    if (select) {
        select.innerHTML = '<option value="">-- Vyberte workshop --</option>' +
            appState.workshops.map(workshop => {
                const isFull = workshop.current_enrolled >= workshop.capacity;
                return `<option value="${workshop.id}" ${isFull ? 'disabled' : ''}>
                    ${workshop.name} - ${workshop.price} Kč ${isFull ? '(PLNÝ)' : `(${workshop.current_enrolled}/${workshop.capacity})`}
                </option>`;
            }).join('');
    }
}

function renderUserDashboard() {
    if (!appState.currentUser) return;
    
    const userInfo = document.getElementById('user-info');
    if (userInfo) {
        userInfo.innerHTML = `
            <p><strong>Jméno:</strong> ${appState.currentUser.name}</p>
            <p><strong>Email:</strong> ${appState.currentUser.email}</p>
        `;
    }
    
    const registration = getUserRegistration(appState.currentUser.id);
    const registrationInfo = document.getElementById('user-registration');
    
    if (registrationInfo) {
        if (registration) {
            const workshop = getWorkshopById(registration.workshop_id);
            registrationInfo.innerHTML = `
                <div class="status-badge status-badge--registered">Registrován</div>
                <p><strong>Workshop:</strong> ${workshop ? workshop.name : 'Neznámý'}</p>
                <p><strong>Doprovodný program:</strong> ${registration.companion_program ? 'Ano' : 'Ne'}</p>
                <p><strong>Stav platby:</strong> ${registration.payment_status === 'pending' ? 'Čeká na platbu' : 'Zaplaceno'}</p>
                <button class="btn btn--primary" onclick="showPaymentDetails('${registration.id}')">Zobrazit platební údaje</button>
            `;
        } else {
            registrationInfo.innerHTML = `
                <p>Ještě nejste registrováni na festival.</p>
                <button class="btn btn--primary" onclick="showPage('registration-form')">Registrovat se</button>
            `;
        }
    }
}

function showPaymentDetails(registrationId) {
    const registration = appState.registrations.find(r => r.id === registrationId);
    if (!registration) return;
    
    const workshop = getWorkshopById(registration.workshop_id);
    const totalAmount = 1200 + (workshop ? workshop.price : 0);
    
    showPage('payment');
    
    const summary = document.getElementById('payment-summary');
    if (summary) {
        summary.innerHTML = `
            <p><strong>Festival:</strong> Improtřesk 2026</p>
            <p><strong>Workshop:</strong> ${workshop ? workshop.name : 'Neznámý'}</p>
            <p><strong>Doprovodný program:</strong> ${registration.companion_program ? 'Ano' : 'Ne'}</p>
            <p><strong>Celková částka:</strong> ${totalAmount} Kč</p>
        `;
    }
    
    const accountEl = document.getElementById('account-number');
    const variableEl = document.getElementById('variable-symbol');
    const amountEl = document.getElementById('payment-amount');
    
    if (accountEl) accountEl.textContent = festivalData.bank_info.account;
    if (variableEl) variableEl.textContent = registration.variable_symbol;
    if (amountEl) amountEl.textContent = totalAmount;
    
    const qrContainer = document.getElementById('qr-code');
    if (qrContainer) {
        qrContainer.innerHTML = '';
        const qrCode = generateQRCode({
            amount: totalAmount,
            variableSymbol: registration.variable_symbol
        });
        qrContainer.appendChild(qrCode);
    }
}

// Admin Functions
function renderAdminParticipants() {
    const container = document.getElementById('participants-list');
    if (!container) return;
    
    const participants = appState.registrations.map(reg => {
        const user = appState.users.find(u => u.id === reg.user_id);
        const workshop = getWorkshopById(reg.workshop_id);
        return { ...reg, user, workshop };
    });
    
    container.innerHTML = `
        <table class="participants-table">
            <thead>
                <tr>
                    <th>Jméno</th>
                    <th>Email</th>
                    <th>Workshop</th>
                    <th>Doprovodný program</th>
                    <th>Stav platby</th>
                    <th>Registrace</th>
                </tr>
            </thead>
            <tbody>
                ${participants.map(p => `
                    <tr>
                        <td>${p.user ? p.user.name : 'Neznámý'}</td>
                        <td>${p.user ? p.user.email : 'Neznámý'}</td>
                        <td>${p.workshop ? p.workshop.name : 'Neznámý'}</td>
                        <td>${p.companion_program ? 'Ano' : 'Ne'}</td>
                        <td>
                            <span class="status-badge status-badge--${p.payment_status === 'paid' ? 'registered' : 'pending'}">
                                ${p.payment_status === 'paid' ? 'Zaplaceno' : 'Čeká'}
                            </span>
                        </td>
                        <td>${new Date(p.created_at).toLocaleDateString('cs-CZ')}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

function renderAdminWorkshops() {
    const container = document.getElementById('admin-workshops-list');
    if (!container) return;
    
    container.innerHTML = appState.workshops.map(workshop => `
        <div class="admin-workshop-item">
            <div class="admin-workshop-info">
                <h4>${workshop.name}</h4>
                <p>Lektor: ${workshop.instructor} | Obsazenost: ${workshop.current_enrolled}/${workshop.capacity}</p>
            </div>
            <div class="admin-workshop-actions">
                <input type="number" class="capacity-input" value="${workshop.capacity}" 
                       onchange="updateWorkshopCapacity(${workshop.id}, this.value)" min="1" max="20">
                <span>kapacita</span>
            </div>
        </div>
    `).join('');
}

function updateWorkshopCapacity(workshopId, newCapacity) {
    const workshop = getWorkshopById(workshopId);
    if (workshop) {
        workshop.capacity = parseInt(newCapacity);
        saveData();
        renderAdminWorkshops();
        renderWorkshops();
        showNotification('Kapacita workshopu aktualizována!');
    }
}

function exportToCSV() {
    const participants = appState.registrations.map(reg => {
        const user = appState.users.find(u => u.id === reg.user_id);
        const workshop = getWorkshopById(reg.workshop_id);
        return {
            name: user ? user.name : 'Neznámý',
            email: user ? user.email : 'Neznámý',
            workshop: workshop ? workshop.name : 'Neznámý',
            companionProgram: reg.companion_program ? 'Ano' : 'Ne',
            paymentStatus: reg.payment_status === 'paid' ? 'Zaplaceno' : 'Čeká',
            registrationDate: new Date(reg.created_at).toLocaleDateString('cs-CZ')
        };
    });
    
    const csvContent = [
        ['Jméno', 'Email', 'Workshop', 'Doprovodný program', 'Stav platby', 'Datum registrace'],
        ...participants.map(p => [p.name, p.email, p.workshop, p.companionProgram, p.paymentStatus, p.registrationDate])
    ].map(row => row.join(',')).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'ucastnici_impotresk_2026.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showNotification('CSV soubor byl stažen!');
}

// Event Handlers
document.addEventListener('DOMContentLoaded', function() {
    // Load data
    loadData();
    
    // Check if user is logged in
    try {
        const savedUser = sessionStorage.getItem('currentUser');
        if (savedUser) {
            appState.currentUser = JSON.parse(savedUser);
        }
    } catch (e) {
        console.warn('Failed to load user from sessionStorage:', e);
    }
    
    updateUI();
    
    // Render initial content
    renderProgram();
    renderWorkshops();
    renderFAQ();
    renderWorkshopSelect();
    
    // Navigation
    const menuToggle = document.getElementById('menu-toggle');
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            const navbarLinks = document.getElementById('navbar-links');
            if (navbarLinks) {
                navbarLinks.classList.toggle('show');
            }
        });
    }
    
    // Navigation links
    document.querySelectorAll('[data-page]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = this.getAttribute('data-page');
            showPage(page);
            const navbarLinks = document.getElementById('navbar-links');
            if (navbarLinks) {
                navbarLinks.classList.remove('show');
            }
        });
    });
    
    // Auth buttons
    const loginBtn = document.getElementById('login-btn');
    const registerBtn = document.getElementById('register-btn');
    const heroRegisterBtn = document.getElementById('hero-register-btn');
    const logoutBtn = document.getElementById('logout-btn');
    const profileBtn = document.getElementById('profile-btn');
    const adminBtn = document.getElementById('admin-btn');
    
    if (loginBtn) {
        loginBtn.addEventListener('click', () => showModal('login-modal'));
    }
    
    if (registerBtn) {
        registerBtn.addEventListener('click', () => showModal('register-modal'));
    }
    
    if (heroRegisterBtn) {
        heroRegisterBtn.addEventListener('click', () => showModal('register-modal'));
    }
    
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }
    
    if (profileBtn) {
        profileBtn.addEventListener('click', function() {
            renderUserDashboard();
            showPage('dashboard');
        });
    }
    
    if (adminBtn) {
        adminBtn.addEventListener('click', function() {
            renderAdminParticipants();
            renderAdminWorkshops();
            showPage('admin');
        });
    }
    
    // Modal handlers
    document.querySelectorAll('.modal-close').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        });
    });
    
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    });
    
    // Forms
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            if (login(email, password)) {
                this.reset();
            }
        });
    }
    
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('register-name').value;
            const email = document.getElementById('register-email').value;
            const password = document.getElementById('register-password').value;
            const confirmPassword = document.getElementById('register-password-confirm').value;
            
            if (password !== confirmPassword) {
                showNotification('Hesla se neshodují!', 'error');
                return;
            }
            
            register(name, email, password);
        });
    }
    
    const festivalRegistrationForm = document.getElementById('festival-registration-form');
    if (festivalRegistrationForm) {
        festivalRegistrationForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!appState.currentUser) {
                showNotification('Musíte být přihlášeni!', 'error');
                return;
            }
            
            const workshopSelect = document.getElementById('workshop-select');
            const companionProgramCheckbox = document.getElementById('companion-program');
            
            if (!workshopSelect || !companionProgramCheckbox) {
                showNotification('Formulář není správně načten!', 'error');
                return;
            }
            
            const workshopId = workshopSelect.value;
            const companionProgram = companionProgramCheckbox.checked;
            
            if (!workshopId) {
                showNotification('Vyberte workshop!', 'error');
                return;
            }
            
            const registration = registerForWorkshop(appState.currentUser.id, workshopId, companionProgram);
            if (registration) {
                showNotification('Registrace úspěšná!');
                showPaymentDetails(registration.id);
            }
        });
    }
    
    const forgotPasswordBtn = document.getElementById('forgot-password-btn');
    if (forgotPasswordBtn) {
        forgotPasswordBtn.addEventListener('click', function() {
            hideModal('login-modal');
            showModal('password-reset-modal');
        });
    }
    
    const passwordResetForm = document.getElementById('password-reset-form');
    if (passwordResetForm) {
        passwordResetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            hideModal('password-reset-modal');
            showNotification('Odkaz pro obnovu hesla byl odeslán na váš email!');
        });
    }
    
    // Admin tab switching
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            
            // Update active tab button
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update active tab pane
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            const targetTab = document.getElementById(tabName + '-tab');
            if (targetTab) {
                targetTab.classList.add('active');
            }
            
            // Render content for specific tabs
            if (tabName === 'participants') {
                renderAdminParticipants();
            } else if (tabName === 'workshops-admin') {
                renderAdminWorkshops();
            }
        });
    });
    
    // Export buttons
    const exportCsvBtn = document.getElementById('export-csv');
    const exportExcelBtn = document.getElementById('export-excel');
    
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', exportToCSV);
    }
    
    if (exportExcelBtn) {
        exportExcelBtn.addEventListener('click', function() {
            showNotification('Excel export není v demo verzi dostupný, použijte CSV.', 'warning');
        });
    }
    
    // Notification close
    const notificationClose = document.getElementById('notification-close');
    if (notificationClose) {
        notificationClose.addEventListener('click', function() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.classList.add('hidden');
            }
        });
    }
    
    // Initialize with home page
    showPage('home');
});

// Global functions for inline event handlers
window.updateWorkshopCapacity = updateWorkshopCapacity;
window.showPaymentDetails = showPaymentDetails;
window.showPage = showPage;