// ========================================================
// Campus Event Registration System - Frontend Application Logic
// Features: Passwordless Email OTP Auth, 1-Click Secure Registration,
// Dynamic Remaining Seat Engine, and Live Cancellations
// ========================================================

document.addEventListener('DOMContentLoaded', () => {
    // State Management
    let allEvents = [];
    let currentUser = null; // null when guest, object when logged in
    let currentCategory = 'all';
    let searchQuery = '';
    let pendingEventIdToRegister = null;

    // DOM Elements - Navigation & Auth
    const navGuestView      = document.getElementById('nav-guest-view');
    const navUserView       = document.getElementById('nav-user-view');
    const userDisplayName   = document.getElementById('user-display-name');
    const btnOpenLogin      = document.getElementById('btn-open-login');
    const btnLogout         = document.getElementById('btn-logout');

    // DOM Elements - Events & Filters
    const eventsGrid        = document.getElementById('events-grid');
    const noEventsState     = document.getElementById('no-events-state');
    const eventsCountLabel  = document.getElementById('events-count-label');
    const searchInput       = document.getElementById('search-input');
    const categoryPills     = document.querySelectorAll('.category-pills .pill');
    const resetFiltersBtn   = document.getElementById('btn-reset-filters');
    const toastContainer    = document.getElementById('toast-container');

    // DOM Elements - Hero Stats
    const statTotalEvents   = document.getElementById('stat-total-events');
    const statOpenSeats     = document.getElementById('stat-open-seats');

    // DOM Elements - OTP Modal
    const authModal         = document.getElementById('auth-modal');
    const btnCloseAuth      = document.getElementById('btn-close-auth-modal');
    const requestOtpForm    = document.getElementById('request-otp-form');
    const verifyOtpForm     = document.getElementById('verify-otp-form');
    const authEmailInput    = document.getElementById('auth-email');
    const authNameInput     = document.getElementById('auth-name');
    const authStudentIdInput= document.getElementById('auth-student-id');
    const btnSendOtp        = document.getElementById('btn-send-otp');
    const btnVerifyOtp      = document.getElementById('btn-verify-otp');
    const otpTargetEmail    = document.getElementById('otp-target-email');
    const linkChangeEmail   = document.getElementById('link-change-email');
    const otpInput          = document.getElementById('otp-input');

    // DOM Elements - My Bookings Modal

    const bookingsModal     = document.getElementById('bookings-modal');
    const btnOpenMyBookings = document.getElementById('btn-open-my-bookings');
    const btnCloseBookings  = document.getElementById('btn-close-bookings-modal');
    const bookingsList      = document.getElementById('bookings-list');

    // ----------------------------------------------------
    // 1. Initial Setup: Check Active Session & Fetch Events
    // ----------------------------------------------------
    init();

    async function init() {
        await checkAuthStatus();
        await fetchEvents();
    }

    // ----------------------------------------------------
    // 2. Auth Session Check (Keeps user logged in on refresh)
    // ----------------------------------------------------
    async function checkAuthStatus() {
        try {
            const response = await fetch('api/get_current_user.php');
            const data = await response.json();

            if (data.success && data.logged_in) {
                setLoggedInUser(data.user);
            } else {
                setLoggedOutUser();
            }
        } catch (err) {
            console.error('Session check error:', err);
            setLoggedOutUser();
        }
    }

    function setLoggedInUser(user) {
        currentUser = user;
        userDisplayName.textContent = user.name.split(' ')[0]; // First name
        userDisplayName.title = `${user.name} (${user.student_id}) - ${user.email}`;
        navGuestView.classList.add('hidden');
        navUserView.classList.remove('hidden');
    }

    function setLoggedOutUser() {
        currentUser = null;
        navUserView.classList.add('hidden');
        navGuestView.classList.remove('hidden');
    }

    // ----------------------------------------------------
    // 3. Fetch All Events from Database
    // ----------------------------------------------------
    async function fetchEvents() {
        try {
            const response = await fetch('api/get_events.php');
            const data = await response.json();

            if (data.success) {
                allEvents = data.events;
                updateStats();
                renderEvents();
            } else {
                showToast(data.error || 'Failed to load events', 'error');
            }
        } catch (err) {
            console.error('Fetch error:', err);
            showToast('Unable to connect to server. Check Apache & MySQL.', 'error');
        }
    }

    // ----------------------------------------------------
    // 4. Render Event Cards in Grid
    // ----------------------------------------------------
    function renderEvents() {
        const filtered = allEvents.filter(event => {
            const matchesCategory = (currentCategory === 'all') ||
                (event.category.toLowerCase() === currentCategory.toLowerCase());

            const matchesSearch = searchQuery === '' ||
                event.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
                event.venue.toLowerCase().includes(searchQuery.toLowerCase()) ||
                event.description.toLowerCase().includes(searchQuery.toLowerCase());

            return matchesCategory && matchesSearch;
        });

        eventsCountLabel.textContent = `Showing ${filtered.length} of ${allEvents.length} events`;

        if (filtered.length === 0) {
            eventsGrid.innerHTML = '';
            noEventsState.classList.remove('hidden');
            return;
        }

        noEventsState.classList.add('hidden');
        eventsGrid.innerHTML = filtered.map(event => createEventCardHTML(event)).join('');

        // Attach Register button listeners
        document.querySelectorAll('.btn-register-trigger').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const eventId = parseInt(e.currentTarget.getAttribute('data-event-id'));
                handleEventRegistrationClick(eventId);
            });
        });
    }

    // ----------------------------------------------------
    // 5. Generate Event Card HTML
    // ----------------------------------------------------
    function createEventCardHTML(event) {
        const remaining = event.remaining_seats;
        const capacity  = event.capacity;
        const percentFilled = Math.min(100, Math.round(((capacity - remaining) / capacity) * 100));


let badgeClass = 'badge-available';
let badgeText = `${remaining} seats left`;
let progressClass = 'progress-green';
let btnDisabled = '';
let btnText = currentUser ? 'Register Pass →' : 'Login to Register →';

if (remaining <= 0) {
    badgeClass = 'badge-full';
    badgeText = 'House Full';
    progressClass = 'progress-red';
    btnDisabled = 'disabled';
    btnText = 'Sold Out';
} else if (remaining <= 5) {
    badgeClass = 'badge-warning';
    badgeText = `${remaining} seats left (Filling Fast!)`;
    progressClass = 'progress-orange';
}

        return `

<article class="event-card" id="event-card-${event.id}">
    <div class="card-top">
        <span class="card-category">${escapeHTML(event.category)}</span>
        <span class="seat-badge ${badgeClass}" id="card-badge-${event.id}">
            ${badgeText}
        </span>
    </div>

    <div class="card-content">
        <h3 class="card-title">${escapeHTML(event.title)}</h3>
        <p class="card-desc">${escapeHTML(event.description)}</p>

        <div class="card-meta">
            <div class="meta-item">
                <span>${formatEventDate(event.event_date)}</span>
            </div>
            <div class="meta-item">
                <span>${escapeHTML(event.venue)}</span>
            </div>
        </div>


                    <!-- Live Capacity Progress Bar -->
                    <div class="capacity-container">
                        <div class="capacity-info">
                            <span>Capacity: <strong>${capacity}</strong></span>
                            <span id="capacity-text-${event.id}">Booked: <strong>${capacity - remaining}</strong> (${percentFilled}%)</span>
                        </div>
                        <div class="capacity-bar">
                            <div class="capacity-progress ${progressClass}"
                                 id="capacity-progress-${event.id}"
                                 style="width: ${percentFilled}%"></div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button class="btn btn-primary btn-card-action btn-register-trigger"
                            id="btn-register-${event.id}"
                            data-event-id="${event.id}"
                            ${btnDisabled}>
                        ${btnText}
                    </button>
                </div>
            </article>
        `;
    }

    // ----------------------------------------------------
    // 6. Registration Flow Handler
    // ----------------------------------------------------
    function handleEventRegistrationClick(eventId) {
        if (!currentUser) {
            // Not logged in -> Open OTP modal with pending intent
            pendingEventIdToRegister = eventId;
            openAuthModal();
            showToast('Please verify your email with OTP to confirm your booking.', 'warning');
            return;
        }

        // Authenticated -> 1-Click Registration!
        registerForEvent(eventId);
    }

    async function registerForEvent(eventId) {
        const actionBtn = document.getElementById(`btn-register-${eventId}`);
        if (actionBtn) {
            actionBtn.disabled = true;
            actionBtn.textContent = 'Booking seat...';
        }

        try {
            const response = await fetch('api/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_id: eventId })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                showToast(`🎉 ${result.message}`, 'success');
                // DYNAMIC UPDATE: Update remaining seat counter on card instantly
                updateEventSeatsLocally(eventId, result.remaining_seats);
            } else if (response.status === 409) {
                showToast(`⚠️ ${result.message}`, 'warning');
                if (actionBtn) actionBtn.textContent = 'Already Registered';
            } else if (result.auth_required) {
                pendingEventIdToRegister = eventId;
                openAuthModal();
                showToast(result.message, 'warning');
            } else {
                showToast(`❌ ${result.message}`, 'error');
                if (actionBtn) actionBtn.textContent = 'Register Pass →';
            }
        } catch (err) {
            console.error('Registration error:', err);
            showToast('Network error while processing registration.', 'error');
            if (actionBtn) actionBtn.textContent = 'Register Pass →';
        } finally {
            if (actionBtn && actionBtn.textContent === 'Booking seat...') {
                actionBtn.disabled = false;
                actionBtn.textContent = 'Register Pass →';
            }
        }
    }

    // ----------------------------------------------------
    // 7. Dynamic Seat Update Engine (No Page Reload!)
    // ----------------------------------------------------
    function updateEventSeatsLocally(eventId, newRemainingSeats) {
        const event = allEvents.find(e => e.id === eventId);
        if (!event) return;

        event.remaining_seats = newRemainingSeats;
        event.registered_count = event.capacity - newRemainingSeats;

        const badgeElem    = document.getElementById(`card-badge-${eventId}`);
        const capacityText = document.getElementById(`capacity-text-${eventId}`);
        const progressElem = document.getElementById(`capacity-progress-${eventId}`);
        const actionBtn    = document.getElementById(`btn-register-${eventId}`);

        if (!badgeElem) return;

        const capacity = event.capacity;
        const percentFilled = Math.min(100, Math.round(((capacity - newRemainingSeats) / capacity) * 100));


if (newRemainingSeats <= 0) {
    badgeElem.className = 'seat-badge badge-full';
    badgeElem.textContent = 'House Full';
    progressElem.className = 'capacity-progress progress-red';

    if (actionBtn) {
        actionBtn.disabled = true;
        actionBtn.textContent = 'Sold Out';
    }
} else if (newRemainingSeats <= 5) {
    badgeElem.className = 'seat-badge badge-warning';
    badgeElem.textContent = `${newRemainingSeats} seats left (Filling Fast!)`;
    progressElem.className = 'capacity-progress progress-orange';

    if (actionBtn) {
        actionBtn.disabled = false;
        actionBtn.textContent = currentUser ? 'Register Pass →' : 'Login to Register →';
    }
} else {
    badgeElem.className = 'seat-badge badge-available';
    badgeElem.textContent = `${newRemainingSeats} seats left`;
    progressElem.className = 'capacity-progress progress-green';

    if (actionBtn) {
        actionBtn.disabled = false;
        actionBtn.textContent = currentUser ? 'Register Pass →' : 'Login to Register →';
    }
}

        capacityText.innerHTML = `Booked: <strong>${capacity - newRemainingSeats}</strong> (${percentFilled}%)`;
        progressElem.style.width = `${percentFilled}%`;

        updateStats();
    }

    // ----------------------------------------------------
    // 8. Passwordless OTP Modal Handlers
    // ----------------------------------------------------
    function openAuthModal() {
        authModal.classList.remove('hidden');
        showStep1();
    }

    function closeAuthModal() {
        authModal.classList.add('hidden');
        requestOtpForm.reset();
        verifyOtpForm.reset();
    }

    function showStep1() {
        requestOtpForm.classList.remove('hidden');
        verifyOtpForm.classList.add('hidden');
    }

    function showStep2(email) {
        requestOtpForm.classList.add('hidden');
        verifyOtpForm.classList.remove('hidden');
        otpTargetEmail.textContent = email;
        otpInput.value = '';
        otpInput.focus();
    }

    btnOpenLogin.addEventListener('click', openAuthModal);
    btnCloseAuth.addEventListener('click', closeAuthModal);
    linkChangeEmail.addEventListener('click', (e) => {
        e.preventDefault();
        showStep1();
    });

    authModal.addEventListener('click', (e) => {
        if (e.target === authModal) closeAuthModal();
    });

    // Step 1: Send OTP
    requestOtpForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = authEmailInput.value.trim();
        const submitBtnText = btnSendOtp.querySelector('.btn-text');

        btnSendOtp.disabled = true;
        submitBtnText.textContent = 'Sending OTP to email...';

        try {
            const response = await fetch('api/send_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`📬 ${result.message}`, 'success');
                showStep2(email);
            } else {
                showToast(`❌ ${result.message}`, 'error');
            }
        } catch (err) {

            console.error('Send OTP error:', err);
            showToast('Failed to send OTP. Server unreachable.', 'error');
        } finally {
            btnSendOtp.disabled = false;
            submitBtnText.textContent = 'Send 6-Digit OTP →';
        }
    });

    // Step 2: Verify OTP
    verifyOtpForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email     = authEmailInput.value.trim();
        const code      = otpInput.value.trim();
        const name      = authNameInput.value.trim();
        const studentId = authStudentIdInput.value.trim();
        const submitBtnText = btnVerifyOtp.querySelector('.btn-text');

        btnVerifyOtp.disabled = true;
        submitBtnText.textContent = 'Verifying...';

        try {
            const response = await fetch('api/verify_otp.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email,
                    otp_code: code,
                    name,
                    student_id: studentId
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`🎉 ${result.message}`, 'success');
                setLoggedInUser(result.user);
                closeAuthModal();

                // Re-render event card buttons to reflect logged in state
                renderEvents();

                // If user was trying to register an event before logging in, proceed with it!
                if (pendingEventIdToRegister) {
                    const eventId = pendingEventIdToRegister;
                    pendingEventIdToRegister = null;
                    await registerForEvent(eventId);
                }
            } else {
                showToast(`⚠️ ${result.message}`, 'error');
            }
        } catch (err) {
            console.error('Verify OTP error:', err);
            showToast('Verification failed. Server unreachable.', 'error');
        } finally {
            btnVerifyOtp.disabled = false;
            submitBtnText.textContent = 'Verify & Enter Portal';
        }
    });

    // ----------------------------------------------------
    // 9. Logout Handler
    // ----------------------------------------------------
    btnLogout.addEventListener('click', async () => {
        try {
            const response = await fetch('api/logout.php');
            const result = await response.json();

            if (result.success) {
                setLoggedOutUser();
                renderEvents();
                showToast('👋 You have been logged out.', 'success');
            }
        } catch (err) {
            console.error('Logout error:', err);
        }
    });

    // ----------------------------------------------------
    // 10. My Bookings & Cancellation Modal
    // ----------------------------------------------------
    btnOpenMyBookings.addEventListener('click', () => {
        bookingsModal.classList.remove('hidden');
        loadMyBookings();
    });

    btnCloseBookings.addEventListener('click', () => {
        bookingsModal.classList.add('hidden');
    });

    bookingsModal.addEventListener('click', (e) => {
        if (e.target === bookingsModal) bookingsModal.classList.add('hidden');
    });

    async function loadMyBookings() {
        bookingsList.innerHTML = '<div class="empty-bookings-hint">Fetching your registered event passes...</div>';

        try {
            const response = await fetch('api/my_registrations.php');
            const data = await response.json();

            if (data.success) {
                if (data.registrations.length === 0) {
                    bookingsList.innerHTML = `
                        <div class="empty-bookings-hint">
                            You have not registered for any events yet.
                        </div>
                    `;
                    return;
                }

                bookingsList.innerHTML = data.registrations.map(reg => `
                    <div class="booking-item" id="booking-item-${reg.registration_id}">
                        <div class="booking-info">
                            <h4>${escapeHTML(reg.title)}</h4>
                            <div class="booking-meta">
                                <span>📅 ${formatEventDate(reg.event_date)}</span>
                                <span>📍 ${escapeHTML(reg.venue)}</span>
                            </div>
                        </div>
                        <button class="btn btn-danger-outline btn-cancel-booking"
                                data-reg-id="${reg.registration_id}"
                                data-event-id="${reg.event_id}">
                            Cancel Pass
                        </button>
                    </div>
                `).join('');

                // Attach Cancel action buttons
                document.querySelectorAll('.btn-cancel-booking').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        const regId   = parseInt(e.currentTarget.getAttribute('data-reg-id'));
                        const eventId = parseInt(e.currentTarget.getAttribute('data-event-id'));

                        if (confirm('Are you sure you want to cancel this event pass? This seat will immediately become available for other students.')) {
                            await cancelRegistration(regId, eventId);
                        }
                    });
                });

            } else {
                bookingsList.innerHTML = `<div class="empty-bookings-hint">${escapeHTML(data.message)}</div>`;
            }
        } catch (err) {
            console.error('Fetch error:', err);
            bookingsList.innerHTML = `<div class="empty-bookings-hint">Failed to load bookings.</div>`;
        }
    }

    async function cancelRegistration(regId, eventId) {
        try {
            const response = await fetch('api/cancel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ registration_id: regId })
            });

            const result = await response.json();

            if (result.success) {
                showToast(`✅ ${result.message}`, 'success');

                // Remove pass from modal list
                const item = document.getElementById(`booking-item-${regId}`);
                if (item) item.remove();

                if (bookingsList.children.length === 0) {
                    bookingsList.innerHTML = '<div class="empty-bookings-hint">No active registrations remaining.</div>';
                }

                // DYNAMIC UPDATE: Increment remaining seats on main event card!
                updateEventSeatsLocally(eventId, result.remaining_seats);
            } else {
                showToast(`❌ ${result.message}`, 'error');
            }
        } catch (err) {
            console.error('Cancel error:', err);
            showToast('Failed to cancel registration.', 'error');
        }
    }

    // ----------------------------------------------------
    // 11. Search & Filter Handlers
    // ----------------------------------------------------
    categoryPills.forEach(pill => {
        pill.addEventListener('click', () => {
            categoryPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            currentCategory = pill.getAttribute('data-category');
            renderEvents();
        });
    });

    searchInput.addEventListener('input', (e) => {
        searchQuery = e.target.value.trim();
        renderEvents();
    });

    resetFiltersBtn.addEventListener('click', () => {
        searchInput.value = '';
        searchQuery = '';
        currentCategory = 'all';
        categoryPills.forEach(p => p.classList.toggle('active', p.getAttribute('data-category') === 'all'));
        renderEvents();
    });

    // ----------------------------------------------------
    // 12. Helper Utilities
    // ----------------------------------------------------
    function updateStats() {
        const total = allEvents.length;
        const totalOpenSeats = allEvents.reduce((acc, ev) => acc + Math.max(0, ev.remaining_seats), 0);

        statTotalEvents.textContent = total;
        statOpenSeats.textContent = totalOpenSeats;
    }

    function formatEventDate(dateString) {
        const options = { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' };
        const date = new Date(dateString);
        return isNaN(date) ? dateString : date.toLocaleDateString('en-US', options);
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;


const icons = {
    success: '',
    warning: '',
    error: ''
};


        toast.innerHTML = `
            <span>${icons[type] || ''}</span>
            <span>${escapeHTML(message)}</span>
        `;

        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }
});
