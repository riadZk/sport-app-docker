<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Notification System Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }

        .container { max-width: 600px; margin: 0 auto; padding: 24px; }

        h1 { font-size: 1.5rem; margin-bottom: 8px; color: #fff; }
        .subtitle { color: #94a3b8; margin-bottom: 24px; font-size: 0.9rem; }

        /* Status Badge */
        .status-bar {
            display: flex; align-items: center; gap: 8px;
            padding: 12px 16px; border-radius: 10px;
            background: #1e293b; margin-bottom: 20px; font-size: 0.85rem;
        }
        .status-dot {
            width: 10px; height: 10px; border-radius: 50%;
            background: #ef4444; flex-shrink: 0;
        }
        .status-dot.connected { background: #22c55e; }
        .status-dot.connecting { background: #eab308; animation: pulse 1s infinite; }
        @keyframes pulse { 50% { opacity: 0.4; } }

        /* Send Form */
        .send-card {
            background: #1e293b; border-radius: 12px;
            padding: 20px; margin-bottom: 20px;
        }
        .send-card h2 { font-size: 1rem; margin-bottom: 14px; color: #cbd5e1; }

        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 0.8rem; color: #94a3b8; margin-bottom: 4px; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px 12px; border-radius: 8px;
            border: 1px solid #334155; background: #0f172a; color: #e2e8f0;
            font-size: 0.9rem; outline: none;
        }
        .form-group input:focus, .form-group select:focus { border-color: #3b82f6; }

        .btn {
            width: 100%; padding: 10px; border: none; border-radius: 8px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-primary { background: #3b82f6; color: #fff; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        /* Notifications List */
        .notifications-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 12px;
        }
        .notifications-header h2 { font-size: 1rem; color: #cbd5e1; }
        .badge {
            background: #3b82f6; color: #fff; font-size: 0.75rem;
            padding: 2px 8px; border-radius: 10px; font-weight: 600;
        }

        .notification-list { display: flex; flex-direction: column; gap: 10px; }

        .notification-item {
            background: #1e293b; border-radius: 10px; padding: 14px 16px;
            border-left: 3px solid #3b82f6;
            animation: slideIn 0.3s ease-out;
        }
        .notification-item.whatsapp { border-left-color: #22c55e; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

        .notif-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .notif-type {
            font-size: 0.7rem; text-transform: uppercase; font-weight: 700;
            letter-spacing: 0.5px; color: #60a5fa;
        }
        .notif-type.whatsapp { color: #4ade80; }
        .notif-time { font-size: 0.7rem; color: #64748b; }

        .notif-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 2px; }
        .notif-message { font-size: 0.85rem; color: #94a3b8; }

        .empty-state {
            text-align: center; padding: 40px; color: #475569;
            font-size: 0.85rem;
        }

        .toast {
            position: fixed; top: 16px; right: 16px;
            background: #22c55e; color: #fff; padding: 10px 16px;
            border-radius: 8px; font-size: 0.85rem; font-weight: 600;
            transform: translateX(120%); transition: transform 0.3s;
            z-index: 100;
        }
        .toast.error { background: #ef4444; }
        .toast.show { transform: translateX(0); }
    </style>
</head>
<body>

<div class="container">
    <h1>🔔 Notification System</h1>
    <p class="subtitle">Realtime notifications via Laravel Reverb</p>

    <!-- Connection Status -->
    <div class="status-bar">
        <div class="status-dot connecting" id="statusDot"></div>
        <span id="statusText">Connecting...</span>
    </div>

    <!-- Send Test Notification -->
    <div class="send-card">
        <h2>Send Test Notification</h2>
        <div class="form-group">
            <label>User ID (receiver)</label>
            <input type="number" id="userId" placeholder="e.g. 1" />
        </div>
        <div class="form-group">
            <label>Team Name</label>
            <input type="text" id="teamName" placeholder="e.g. FC Barcelona" />
        </div>
        <div class="form-group">
            <label>Channels</label>
            <select id="channels">
                <option value="all">Realtime + WhatsApp</option>
                <option value="realtime">Realtime only</option>
                <option value="whatsapp">WhatsApp only</option>
            </select>
        </div>
        <button class="btn btn-primary" id="sendBtn" onclick="sendNotification()">
            Send Notification
        </button>
    </div>

    <!-- Received Notifications -->
    <div class="notifications-header">
        <h2>Received Notifications</h2>
        <span class="badge" id="countBadge">0</span>
    </div>
    <div class="notification-list" id="notificationList">
        <div class="empty-state" id="emptyState">Waiting for notifications...</div>
    </div>
</div>

<div class="toast" id="toast"></div>

@vite(['resources/js/app.js'])

<script>
    // ============================================================
    //  CONFIG — set the logged-in user ID here
    // ============================================================
    const AUTH_USER_ID = {{ auth()->id() ?? 1 }};
    const API_BASE     = '/api';
    const AUTH_TOKEN    = ''; // paste your Sanctum token here for testing

    // ============================================================
    //  REVERB — listen on private channel: user.{id}
    // ============================================================
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const dot  = document.getElementById('statusDot');
            const text = document.getElementById('statusText');

            try {
                // Subscribe to the private channel
                window.Echo.private(`user.${AUTH_USER_ID}`)
                    .listen('.notification.received', (data) => {
                        console.log('🔔 Notification received:', data);
                        addNotification('realtime', data);
                        showToast(`${data.title || 'New notification'}`);
                    });

                // Mark connected
                dot.className  = 'status-dot connected';
                text.innerText = `Connected — listening on user.${AUTH_USER_ID}`;
            } catch (e) {
                dot.className  = 'status-dot';
                text.innerText = 'Connection failed: ' + e.message;
            }
        }, 500);
    });

    // ============================================================
    //  SEND — call your API endpoint to trigger the notification
    // ============================================================
    async function sendNotification() {
        const userId   = document.getElementById('userId').value;
        const teamName = document.getElementById('teamName').value;
        const channels = document.getElementById('channels').value;

        if (!userId || !teamName) {
            showToast('Fill all fields', true);
            return;
        }

        const btn = document.getElementById('sendBtn');
        btn.disabled  = true;
        btn.innerText = 'Sending...';

        try {
            // --------------------------------------------------------
            // This calls your controller endpoint.
            // Example route:  POST /api/teams/{team}/join
            // Adapt the URL to match your actual route.
            // --------------------------------------------------------
            const res = await fetch(`${API_BASE}/notifications/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    ...(AUTH_TOKEN ? { 'Authorization': `Bearer ${AUTH_TOKEN}` } : {}),
                },
                body: JSON.stringify({
                    user_id: userId,
                    team_name: teamName,
                    channels: channels,
                }),
            });

            const json = await res.json();
            if (res.ok) {
                showToast('Notification sent!');
            } else {
                showToast(json.message || 'Failed', true);
            }
        } catch (e) {
            showToast('Network error', true);
        }

        btn.disabled  = false;
        btn.innerText = 'Send Notification';
    }

    // ============================================================
    //  UI HELPERS
    // ============================================================
    let count = 0;

    function addNotification(type, data) {
        const list  = document.getElementById('notificationList');
        const empty = document.getElementById('emptyState');
        if (empty) empty.remove();

        count++;
        document.getElementById('countBadge').innerText = count;

        const time = new Date().toLocaleTimeString();
        const item = document.createElement('div');
        item.className = `notification-item ${type === 'whatsapp' ? 'whatsapp' : ''}`;
        item.innerHTML = `
            <div class="notif-top">
                <span class="notif-type ${type}">${type}</span>
                <span class="notif-time">${time}</span>
            </div>
            <div class="notif-title">${data.title || data.type || 'Notification'}</div>
            <div class="notif-message">${data.message || ''}</div>
        `;

        list.prepend(item);
    }

    function showToast(msg, isError = false) {
        const t = document.getElementById('toast');
        t.innerText  = msg;
        t.className  = `toast ${isError ? 'error' : ''} show`;
        setTimeout(() => t.classList.remove('show'), 3000);
    }
</script>

</body>
</html>
