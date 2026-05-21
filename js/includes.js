// Menu Toggle Variables
const menuToggle = document.querySelector(".menu-toggle");
const nav = document.querySelector("nav");

// Theme Switch Variables
let darkmode = localStorage.getItem("darkmode");
const themeSwitch = document.getElementById("theme-switch");

// Profile Dropdown Menu Variables
const profileBtn = document.getElementById('profileDropdownBtn');
const profileMenu = document.getElementById('profileDropdownMenu');

// Darkmode Enable Function
const enableDarkmode = () => {
    document.body.classList.add("darkmode");
    localStorage.setItem("darkmode", "active");
}

// Darkmode Disable Function
const disableDarkmode = () => {
    document.body.classList.remove("darkmode");
    localStorage.setItem("darkmode", null);
}

// Menu Toggle Event
if (menuToggle && nav) {
    menuToggle.addEventListener("click", () => {
        nav.classList.toggle("open");
        menuToggle.classList.toggle("open");
    });
}

if (darkmode === "active") enableDarkmode();

// Theme Switch Event
if (themeSwitch) {
    themeSwitch.addEventListener("click", () => {
        darkmode = localStorage.getItem("darkmode");
        darkmode !== "active" ? enableDarkmode() : disableDarkmode();
    });
}

// Profile Dropdown Event
profileBtn.addEventListener('click', function (e) {
    e.preventDefault();
    profileMenu.classList.toggle('show');
});

document.addEventListener('click', function (e) {
    if (
        !profileBtn.contains(e.target) &&
        !profileMenu.contains(e.target)
    ) {
        profileMenu.classList.remove('show');
    }
});

// Notification Bell
const notifBell = document.getElementById('notif-bell');
const notifDropdown = document.getElementById('notifDropdown');
const notifList = document.getElementById('notifList');
const notifBadge = notifBell ? notifBell.querySelector('.notif-bell-badge') : null;

let notifLoaded = false;
let notifOpen = false;
let currentFilter = 'all';
let allNotifs = [];

function updateBadge(count) {
    if (!notifBell) return;
    let badge = notifBell.querySelector('.notif-bell-badge');
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'notif-bell-badge';
            notifBell.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
    } else if (badge) {
        badge.remove();
    }
}

function timeAgo(dateStr) {
    const date = new Date(dateStr.replace(' ', 'T'));
    const diff = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function renderNotifList(filter) {
    if (!notifList) return;
    currentFilter = filter;
    const items = filter === 'unread' ? allNotifs.filter(function(n){ return !n.is_read; }) : allNotifs;

    if (items.length === 0) {
        notifList.innerHTML = '<p class="notif-empty">No notifications.</p>';
        return;
    }

    const typeIcons = {
        reaction: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m305-704 112-145q12-16 28.5-23.5T480-880q18 0 34.5 7.5T543-849l112 145 170 57q26 8 41 29.5t15 47.5q0 12-3.5 24T866-523L756-367l4 164q1 35-23 59t-56 24q-2 0-22-3l-179-50-179 50q-5 2-11 2.5t-11 .5q-32 0-56-24t-23-59l4-165L95-523q-8-11-11.5-23T80-570q0-25 14.5-46.5T135-647l170-57Zm49 69-194 64 124 179-4 191 200-55 200 56-4-192 124-177-194-66-126-165-126 165Zm126 135Z"/></svg>',
        comment: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M280-400h400q17 0 28.5-11.5T720-440q0-17-11.5-28.5T680-480H280q-17 0-28.5 11.5T240-440q0 17 11.5 28.5T280-400Zm0-120h400q17 0 28.5-11.5T720-560q0-17-11.5-28.5T680-600H280q-17 0-28.5 11.5T240-560q0 17 11.5 28.5T280-520Zm0-120h400q17 0 28.5-11.5T720-680q0-17-11.5-28.5T680-720H280q-17 0-28.5 11.5T240-680q0 17 11.5 28.5T280-640ZM160-240q-33 0-56.5-23.5T80-320v-480q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v623q0 27-24.5 37.5T812-148l-92-92H160Zm594-80 46 45v-525H160v480h594Zm-594 0v-480 480Z"/></svg>',
        reply: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="m273-480 116 116q12 12 11.5 28T388-308q-12 11-28 11.5T332-308L148-492q-12-12-12-28t12-28l184-184q11-11 27.5-11t28.5 11q12 12 12 28.5T388-675L273-560h367q83 0 141.5 58.5T840-360v120q0 17-11.5 28.5T800-200q-17 0-28.5-11.5T760-240v-120q0-50-35-85t-85-35H273Z"/></svg>',
        message: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M80-80v-720q0-33 23.5-56.5T160-880h640q33 0 56.5 23.5T880-800v480q0 33-23.5 56.5T800-240H240L80-80Zm126-240h594v-480H160v525l46-45Zm-46 0v-480 480Z"/></svg>',
        transaction: '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#434343"><path d="M475-160q4 0 8-2t6-4l328-328q12-12 17.5-27t5.5-30q0-16-5.5-30.5T817-607L647-777q-11-12-25.5-17.5T591-800q-15 0-30 5.5T534-777l-11 11 74 75q15 14 22 32t7 38q0 42-28.5 70.5T527-522q-20 0-38.5-7T456-550l-75-74-175 175q-3 3-4.5 6.5T200-435q0 8 6 14.5t14 6.5q4 0 8-2t6-4l136-136 56 56-135 136q-3 3-4.5 6.5T285-350q0 8 6 14t14 6q4 0 8-2t6-4l136-135 56 56-135 136q-3 2-4.5 6t-1.5 8q0 8 6 14t14 6q4 0 7.5-1.5t6.5-4.5l136-135 56 56-136 136q-3 3-4.5 6.5T454-180q0 8 6.5 14t14.5 6Zm-1 80q-37 0-65.5-24.5T375-166q-34-5-57-28t-28-57q-34-5-56.5-28.5T206-336q-38-5-62-33t-24-66q0-20 7.5-38.5T149-506l232-231 131 131q2 3 6 4.5t8 1.5q9 0 15-5.5t6-14.5q0-4-1.5-8t-4.5-6L398-777q-11-12-25.5-17.5T342-800q-15 0-30 5.5T285-777L144-635q-9 9-15 21t-8 24q-2 12 0 24.5t8 23.5l-58 58q-17-23-25-50.5T40-590q2-28 14-54.5T87-692l141-141q24-23 53.5-35t60.5-12q31 0 60.5 12t52.5 35l11 11 11-11q24-23 53.5-35t60.5-12q31 0 60.5 12t52.5 35l169 169q23 23 35 53t12 61q0 31-12 60.5T873-437L545-110q-14 14-32.5 22T474-80Zm-99-560Z"/></svg>',
    };

    notifList.innerHTML = items.map(function(n) {
        return `
        <div class="notif-item ${n.is_read ? '' : 'notif-item--unread'}" id="notif-drop-${n.notif_id}">
            <span class="notif-item-icon">${typeIcons[n.type] || '<?= $notificationBell ?>'}</span>
            <a href="${escHtml(n.link || '#')}" class="notif-item-content" data-id="${n.notif_id}">
                <p class="notif-item-msg">${formatNotifMsg(n.message)}</p>
                <span class="notif-item-time">${timeAgo(n.created_at)}</span>
            </a>
            ${n.is_read ? '' : '<div class="notif-dot"></div>'}
            <button class="notif-item-delete" data-id="${n.notif_id}" title="Delete">✕</button>
        </div>`;
    }).join('');

    // Mark as read on click
    notifList.querySelectorAll('.notif-item-content').forEach(function(a) {
        a.addEventListener('click', function(e) {
            e.preventDefault();
            const id   = parseInt(a.dataset.id);
            const href = a.getAttribute('href');
            markOne(id, function() { window.location.href = href; });
        });
    });

    // Delete individual
    notifList.querySelectorAll('.notif-item-delete').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = parseInt(btn.dataset.id);
            deleteOne(id);
        });
    });
}

function markOne(id, cb) {
    allNotifs = allNotifs.map(function(n) {
        return n.notif_id === id ? Object.assign({}, n, {is_read: true}) : n;
    });
    const unread = allNotifs.filter(function(n){ return !n.is_read; }).length;
    updateBadge(unread);
    renderNotifList(currentFilter);

    const fd = new FormData();
    fd.append('mode', 'one');
    fd.append('notif_id', id);
    fetch('api/notifications-mark.php', { method: 'POST', body: fd })
        .then(function(){ if (cb) cb(); })
        .catch(function(){ if (cb) cb(); });
}

function deleteOne(id) {
    allNotifs = allNotifs.filter(function(n){ return n.notif_id !== id; });
    const unread = allNotifs.filter(function(n){ return !n.is_read; }).length;
    updateBadge(unread);
    renderNotifList(currentFilter);

    const fd = new FormData();
    fd.append('mode', 'one');
    fd.append('notif_id', id);
    fetch('api/notifications-delete.php', { method: 'POST', body: fd }).catch(function(){});
}

function loadNotifications() {
    if (!notifList) return;
    fetch('api/notifications-fetch.php')
        .then(function(r){ return r.json(); })
        .then(function(data) {
            allNotifs   = data.notifications || [];
            notifLoaded = true;
            updateBadge(data.unread_count || 0);
            renderNotifList(currentFilter);
        })
        .catch(function() {
            notifList.innerHTML = '<p class="notif-empty">Failed to load.</p>';
        });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str || '')));
    return d.innerHTML;
}

if (notifBell && notifDropdown) {
    notifBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notifOpen = !notifOpen;
        notifDropdown.classList.toggle('notif-dropdown--open', notifOpen);
        if (notifOpen && !notifLoaded) loadNotifications();
    });

    document.addEventListener('click', function(e) {
        if (!notifDropdown.contains(e.target) && e.target !== notifBell) {
            notifOpen = false;
            notifDropdown.classList.remove('notif-dropdown--open');
        }
    });

    // Tab switching
    document.querySelectorAll('.notif-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.notif-tab').forEach(function(t){ t.classList.remove('active'); });
            tab.classList.add('active');
            renderNotifList(tab.dataset.tab);
        });
    });

    // Mark all read
    const markAllBtn = document.getElementById('notifMarkAll');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            allNotifs = allNotifs.map(function(n){ return Object.assign({}, n, {is_read: true}); });
            updateBadge(0);
            renderNotifList(currentFilter);
            const fd = new FormData();
            fd.append('mode', 'all');
            fetch('api/notifications-mark.php', { method: 'POST', body: fd }).catch(function(){});
        });
    }

    // Clear all
    const clearAllBtn = document.getElementById('notifClearAll');
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function() {
            allNotifs = [];
            updateBadge(0);
            renderNotifList(currentFilter);
            const fd = new FormData();
            fd.append('mode', 'all');
            fetch('api/notifications-delete.php', { method: 'POST', body: fd }).catch(function(){});
        });
    }
}

function formatNotifMsg(msg) {
    return escHtml(msg).replace(
        /\[reaction:([a-z]+)\]/g,
        function(match, type) {
            return '<img src="assets/img/' + type + '-junimo-no-bg.png" '
                + 'style="width:20px;height:20px;image-rendering:pixelated;'
                + 'vertical-align:middle;display:inline-block;" />';
        }
    );
}