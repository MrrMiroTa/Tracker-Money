<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រើប្រាស់ប្រព័ន្ធ - Payment Tracker</title>
    <link rel="stylesheet" href="admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600;700&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f3f4f6;
            margin: 0;
            font-family: 'Kantumruy Pro', 'Inter', sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .step-container {
            display: none;
        }
        .step-active {
            display: block;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .mfa-icon {
            font-size: 3rem;
            text-align: center;
            display: block;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <span style="font-size: 2.5rem;">📊</span>
            <h2>ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ</h2>
            <p>Payment Tracker - ចូលប្រើប្រាស់គណនី</p>
        </div>

        <form id="secure-login-form" onsubmit="event.preventDefault(); handleLoginSubmit();">
            <!-- ជំហានទី ១៖ Username & Password -->
            <div id="login-step-1" class="step-container step-active">
                <div style="margin-bottom: 1rem;">
                    <label for="username" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">ឈ្មោះអ្នកប្រើប្រាស់ (Username)</label>
                    <input type="text" id="username" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;" placeholder="ឧទាហរណ៍៖ admin_sophors" required autocomplete="username">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label for="password" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">ពាក្យសម្ងាត់ (Password)</label>
                    <input type="password" id="password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="button" style="width: 100%; padding: 12px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;" onclick="goToMfaStep()">បន្តទៅមុខទៀត</button>
            </div>

            <!-- ជំហានទី ២៖ MFA Code -->
            <div id="login-step-2" class="step-container">
                <span class="mfa-icon">🛡️</span>
                <h3 style="text-align: center; font-size: 1.15rem; margin-bottom: 0.5rem;">ការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA)</h3>
                <p style="text-align: center; margin-bottom: 1.5rem; font-size: 0.85rem; color: #6b7280;">
                    សូមបើកកម្មវិធី <strong>Google Authenticator</strong> រួចបញ្ចូលលេខកូដសម្ងាត់ ៦ ខ្ទង់។
                </p>

                <div style="margin-bottom: 1.5rem;">
                    <label for="mfa-code" style="text-align: center; display: block; font-weight: 600; margin-bottom: 0.5rem;">លេខកូដ MFA ៦ ខ្ទង់</label>
                    <input type="text" id="mfa-code" style="width: 100%; padding: 10px; text-align: center; font-size: 1.5rem; letter-spacing: 0.3rem; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box;" placeholder="000000" maxlength="6" inputmode="numeric">
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="button" style="flex: 1; padding: 10px; background: #e5e7eb; border: none; border-radius: 6px; cursor: pointer;" onclick="backToStep1()">ត្រឡប់ក្រោយ</button>
                    <button type="submit" style="flex: 1; padding: 10px; background: #10b981; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">ចូលប្រព័ន្ធ</button>
                </div>
            </div>

            <div id="login-feedback" style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem;"></div>
        </form>
    </div>

    <script>
        function goToMfaStep() {
            const usernameInput = document.getElementById('username').value.trim();
            const passwordInput = document.getElementById('password').value;
            const feedback = document.getElementById('login-feedback');

            if (!usernameInput || !passwordInput) {
                feedback.style.color = "red";
                feedback.innerText = "សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់ និងពាក្យសម្ងាត់។";
                return;
            }

            feedback.innerText = "";
            document.getElementById('login-step-1').classList.remove('step-active');
            document.getElementById('login-step-2').classList.add('step-active');
            document.getElementById('mfa-code').focus();
        }

        function backToStep1() {
            document.getElementById('login-step-2').classList.remove('step-active');
            document.getElementById('login-step-1').classList.add('step-active');
            document.getElementById('login-feedback').innerText = "";
        }

        async function handleLoginSubmit() {
            const usernameVal = document.getElementById('username').value.trim();
            const passwordVal = document.getElementById('password').value;
            const mfaCodeVal = document.getElementById('mfa-code').value.trim();
            const feedback = document.getElementById('login-feedback');

            feedback.style.color = "blue";
            feedback.innerText = "កំពុងចូលប្រើប្រាស់ប្រព័ន្ធ...";

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
                    feedback.style.color = "green";
                    feedback.innerText = "ចូលប្រើប្រាស់ជោគជ័យ! កំពុងទៅកាន់ Dashboard...";
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    feedback.style.color = "red";
                    feedback.innerText = result.message || "ការចូលប្រើប្រាស់បរាជ័យ។";
                }
            } catch (error) {
                feedback.style.color = "red";
                feedback.innerText = "មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ម៉ាស៊ីនបម្រើ (Server Error)។";
            }
        }
    </script>
</body>
</html>