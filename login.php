<?php
/**
 * login.php - Secure User Login Page with Multi-Factor Authentication (MFA)
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Enforces strict secure authentication, responsive layout for mobile screens,
 * and a polished multi-step user experience.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect already logged-in users to Dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រើប្រាស់ប្រព័ន្ធ - Payment Tracker</title>
    <!-- Link to Kantumruy Pro and Inter Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="icon.png">
    <style>
        :root {
            --primary: #0284c7;         /* Sky Blue */
            --primary-dark: #0369a1;
            --primary-light: #e0f2fe;
            --success: #10b981;         /* Emerald Green */
            --danger: #ef4444;          /* Crimson Red */
            --dark: #0f172a;            /* Slate Dark */
            --gray-light: #f8fafc;
            --gray-border: #e2e8f0;
            --gray-text: #64748b;
            --white: #ffffff;
            --font-khmer: 'Kantumruy Pro', 'Inter', system-ui, -apple-system, sans-serif;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 16px -6px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Reset & Base Styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-khmer);
            background-color: #f1f5f9;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            line-height: 1.6;
        }

        /* Container Card */
        .login-container {
            width: 100%;
            max-width: 440px;
            background: var(--white);
            border-radius: var(--radius-lg);
            border: 1px solid var(--gray-border);
            box-shadow: var(--shadow-lg);
            padding: 40px 32px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        /* Top Decorative Color bar */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, #3b82f6 100%);
        }

        /* Header Style */
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo {
            font-size: 3rem;
            display: inline-block;
            margin-bottom: 12px;
            animation: bounce 2s infinite;
        }

        .login-header h2 {
            font-size: 1.45rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .login-header p {
            font-size: 0.88rem;
            color: var(--gray-text);
            font-weight: 500;
        }

        /* Step Navigation container */
        .step-container {
            display: none;
        }

        .step-active {
            display: block;
            animation: slideIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-family: var(--font-khmer);
            font-size: 0.95rem;
            font-weight: 500;
            border: 1.5px solid var(--gray-border);
            border-radius: var(--radius-md);
            background-color: var(--white);
            color: var(--dark);
            transition: var(--transition);
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.12);
            background-color: var(--white);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        /* Custom MFA Input Display */
        .mfa-icon-badge {
            font-size: 2.8rem;
            text-align: center;
            display: block;
            margin-bottom: 16px;
        }

        .mfa-title {
            text-align: center;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .mfa-instruction {
            text-align: center;
            font-size: 0.85rem;
            color: var(--gray-text);
            margin-bottom: 24px;
            padding: 0 10px;
        }

        .mfa-input-control {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: 0.4rem;
            text-align: center;
            padding: 10px;
        }

        /* Buttons styles */
        .btn-block {
            width: 100%;
            padding: 13px 20px;
            font-family: var(--font-khmer);
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.25);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(1px);
        }

        .btn-success {
            background-color: var(--success);
            color: var(--white);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        .btn-success:hover {
            opacity: 0.95;
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.25);
            transform: translateY(-1px);
        }

        .btn-light {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid var(--gray-border);
        }

        .btn-light:hover {
            background-color: #e2e8f0;
            color: #1e293b;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .btn-group button {
            flex: 1;
        }

        /* Feedback/Alert Boxes */
        .feedback-message {
            margin-top: 20px;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            display: none;
            animation: slideIn 0.3s ease-out;
        }

        .feedback-error {
            background-color: #fef2f2;
            border: 1px solid #fca5a5;
            color: var(--danger);
            display: block;
        }

        .feedback-success {
            background-color: #ecfdf5;
            border: 1px solid #6ee7b7;
            color: var(--success);
            display: block;
        }

        .feedback-info {
            background-color: #eff6ff;
            border: 1px solid #93c5fd;
            color: var(--primary);
            display: block;
        }

        /* --- MOBILE PHONE OPTIMIZATION (RESPONSIVE VIEWPORTS) --- */
        @media (max-width: 480px) {
            body {
                padding: 12px;
                background-color: var(--white); /* Seamless background on mobile devices */
                display: flex;
                align-items: flex-start; /* Better alignment on mobile screens */
                padding-top: 10%;
            }

            .login-container {
                box-shadow: none; /* Flat design on mobile for cleaner look */
                border: none;
                padding: 10px 8px; /* Compact padding for small screen constraints */
            }

            .login-container::before {
                display: none; /* Remove bar to gain more screen height */
            }

            .login-header {
                margin-bottom: 24px;
            }

            .login-logo {
                font-size: 2.5rem;
                margin-bottom: 8px;
            }

            .login-header h2 {
                font-size: 1.3rem;
            }

            .form-control {
                padding: 13px 14px; /* Slightly taller inputs on mobile for touch accuracy */
                font-size: 1rem;     /* 16px prevent iOS auto-zoom on input focus */
            }

            .mfa-input-control {
                font-size: 1.5rem;
                letter-spacing: 0.3rem;
            }

            .btn-block {
                padding: 14px 20px; /* Thicker touch targets on mobile (Apple/Google design guidelines) */
                font-size: 1rem;
            }
            
            .btn-group {
                gap: 8px;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        
        <!-- Header -->
        <div class="login-header">
            <span class="login-logo">📊</span>
            <h2>ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ</h2>
            <p>Khmer Payment Tracker — ចូលប្រើប្រាស់គណនី</p>
        </div>

        <form id="secure-login-form" onsubmit="event.preventDefault(); handleLoginSubmit();">
            
            <!-- STEP 1: Username & Password -->
            <div id="login-step-1" class="step-container step-active">
                <div class="form-group">
                    <label class="form-label" for="username">ឈ្មោះអ្នកប្រើប្រាស់ (Username)</label>
                    <input class="form-control" type="text" id="username" placeholder="ឧ. admin_sophors" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">ពាក្យសម្ងាត់ (Password)</label>
                    <input class="form-control" type="password" id="password" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button class="btn-block btn-primary" type="button" onclick="goToMfaStep()">
                    <span>បន្តទៅមុខទៀត</span> →
                </button>
            </div>

            <!-- STEP 2: MFA Google Authenticator Code -->
            <div id="login-step-2" class="step-container">
                <span class="mfa-icon-badge">🛡️</span>
                <h3 class="mfa-title">ការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA)</h3>
                <p class="mfa-instruction">សូមបើកកម្មវិធី <strong>Google Authenticator</strong> រួចបញ្ចូលលេខកូដសម្ងាត់ ៦ ខ្ទង់ដែលកំពុងលោតលើទូរស័ព្ទដៃរបស់អ្នក។</p>

                <div class="form-group">
                    <label class="form-label" for="mfa-code" style="text-align: center;">លេខកូដ MFA ៦ ខ្ទង់</label>
                    <input class="form-control mfa-input-control" type="text" id="mfa-code" placeholder="000000" maxlength="6" inputmode="numeric">
                </div>

                <div class="btn-group">
                    <button class="btn-block btn-light" type="button" onclick="backToStep1()">ត្រឡប់ក្រោយ</button>
                    <button class="btn-block btn-success" type="submit">ចូលប្រព័ន្ធ</button>
                </div>
            </div>

            <!-- Feedback Notifications -->
            <div id="login-feedback" class="feedback-message"></div>

        </form>
    </div>

    <!-- Scripting Engine -->
    <script>
        /**
         * Navigate to MFA Step (Step 2)
         */
        function goToMfaStep() {
            const usernameInput = document.getElementById('username').value.trim();
            const passwordInput = document.getElementById('password').value;
            const feedback = document.getElementById('login-feedback');

            if (!usernameInput || !passwordInput) {
                feedback.className = "feedback-message feedback-error";
                feedback.innerHTML = "❌ សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់ និងពាក្យសម្ងាត់ឱ្យបានត្រឹមត្រូវជាមុនសិន។";
                return;
            }

            // Clear feedback and slide to Step 2
            feedback.className = "feedback-message";
            feedback.style.display = "none";
            
            document.getElementById('login-step-1').classList.remove('step-active');
            document.getElementById('login-step-2').classList.add('step-active');
            document.getElementById('mfa-code').focus();
        }

        /**
         * Return back to Step 1
         */
        function backToStep1() {
            document.getElementById('login-step-2').classList.remove('step-active');
            document.getElementById('login-step-1').classList.add('step-active');
            
            const feedback = document.getElementById('login-feedback');
            feedback.className = "feedback-message";
            feedback.style.display = "none";
        }

        /**
         * Handle Safe AJAX Form Submission
         */
        async function handleLoginSubmit() {
            const usernameVal = document.getElementById('username').value.trim();
            const passwordVal = document.getElementById('password').value;
            const mfaCodeVal = document.getElementById('mfa-code').value.trim();
            const feedback = document.getElementById('login-feedback');

            feedback.className = "feedback-message feedback-info";
            feedback.innerHTML = "⏳ កំពុងផ្ទៀងផ្ទាត់ព័ត៌មានគណនី...";
            feedback.style.display = "block";

            try {
                const response = await fetch('api-v2.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: usernameVal,
                        password: passwordVal,
                        mfa_code: mfaCodeVal
                    })
                });

                const result = await response.json();

                if (result.status === 'success') {
                    feedback.className = "feedback-message feedback-success";
                    feedback.innerHTML = "🎉 ចូលប្រព័ន្ធជោគជ័យ! កំពុងនាំលោកអ្នកទៅកាន់ Dashboard...";
                    
                    // Synchronize Session details to LocalStorage to prevent integration bugs
                    if (result.user) {
                        localStorage.setItem('current_user', JSON.stringify(result.user));
                    }

                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    feedback.className = "feedback-message feedback-error";
                    feedback.innerHTML = result.message || "❌ ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវឡើយ។";
                }
            } catch (error) {
                console.error("AJAX login error:", error);
                feedback.className = "feedback-message feedback-error";
                feedback.innerHTML = "❌ មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ Server! សូមប្រាកដថាប្រព័ន្ធ Database កំពុងដំណើរការធម្មតា។ (Server Connection Failed)";
            }
        }
    </script>
</body>
</html>
