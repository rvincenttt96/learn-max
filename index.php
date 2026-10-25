<?php
session_start();
if (is_file(__DIR__ . '/includes/panel-robots.php')) {
    require_once __DIR__ . '/includes/panel-robots.php';
}
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    require_once __DIR__ . '/includes/german.php';
    $home = ghazal_dashboard_home($_SESSION['role'] ?? '');
    if ($home !== '') {
        header("Location: " . $home);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="ورود دانش‌آموز و استاد به پنل آموزشگاه زبان غزال. این صفحه برای گوگل نیست.">
    <title>ورود به پنل آموزشگاه زبان غزال</title>
    <link href="assets/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="assets/css/liquid-glass.css?v=lg21" rel="stylesheet">
    <style>
        @font-face {
            font-family: "YekanBakhFaNum";
            src: url("fonts/YekanBakhFaNum-Regular.ttf") format("truetype");
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, input, button, select, textarea, .form-control {
            font-family: "YekanBakhFaNum", Tahoma, Arial, sans-serif !important;
        }
        
        :root {
            --primary: #dc2626;
            --primary-dark: #be123c;
            --primary-light: #f87171;
            --secondary: #e2e8f0;
            --accent: #fca5a5;
            --dark-bg: #08080C;
            --darker-bg: #08080C;
        }
        
        body {
            background: #08080C;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            position: relative;
            overflow: hidden;
            font-family: "YekanBakhFaNum", Tahoma, Arial, sans-serif;
        }
        
        .language-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        
        .letter-particle {
            position: absolute;
            color: rgb(205 47 47 / 76%);
            font-size: 20px;
            font-weight: bold;
            opacity: 0.5;
            animation: float-letter 15s infinite ease-in-out;
        }
        .letter-particle::after {
            content: attr(data-letter);
        }
        
        @keyframes float-letter {
            0%, 100% { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0.3; }
            25% { transform: translateY(-25px) translateX(15px) rotate(90deg); opacity: 0.6; }
            50% { transform: translateY(-40px) translateX(-10px) rotate(180deg); opacity: 0.4; }
            75% { transform: translateY(-25px) translateX(-15px) rotate(270deg); opacity: 0.7; }
        }
        
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 920px;
            min-height: 540px;
        }
        
        .login-card {
            background: rgba(11, 11, 16, 0.82);
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            border-radius: 32px;
            border: 1px solid rgba(127, 29, 29, 0.32);
            overflow: hidden;
            box-shadow: 
                0 30px 70px rgba(0, 0, 0, 0.65),
                0 0 40px rgba(220, 38, 38, 0.18);
            display: flex;
            height: 100%;
            position: relative;
        }
        
        .info-section {
            flex: 1;
            background: linear-gradient(160deg, #dc2626, #9f1239 58%, #08080C);
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .info-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.15) 0%, transparent 70%);
            z-index: -1;
            animation: pulse 8s infinite ease-in-out;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; }
            50% { opacity: 0.5; }
        }
        
        .logo-container {
            margin-bottom: 2.5rem;
            text-align: center;
        }
        
        .logo-placeholder {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: var(--primary);
            box-shadow: 
                0 12px 30px rgba(0, 0, 0, 0.3),
                0 0 0 4px rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
        }
        
        .logo-placeholder:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 
                0 15px 35px rgba(0, 0, 0, 0.4),
                0 0 0 6px rgba(255, 255, 255, 0.3);
        }
        
        .info-title {
            font-size: 2.4rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1.2rem;
            text-align: center;
            text-shadow: 0 3px 15px rgba(0, 0, 0, 0.3);
            letter-spacing: 1px;
        }
        
        .info-description {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.15rem;
            line-height: 1.7;
            text-align: center;
            max-width: 350px;
            margin: 0 auto;
        }
        
        .form-section {
            flex: 1;
            padding: 3.5rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2.8rem;
        }
        
        .form-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 0.8rem;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 10px rgba(211, 47, 47, 0.2);
        }
        
        .form-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.05rem;
            font-weight: 400;
        }
        
        .form-field {
            margin-bottom: 1.8rem;
            position: relative;
        }
        
        .field-label {
            display: block;
            color: var(--secondary);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.8rem;
            padding-right: 30px;
        }
        
        .field-input {
            width: 100%;
            padding: 18px 24px;
            background: #12121C;
            border: 1px solid rgba(127, 29, 29, 0.4);
            border-radius: 999px;
            color: white;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            outline: none;
            position: relative;
        }
        
        .field-input:focus {
            background: rgba(0, 0, 0, 0.45);
            border-color: var(--primary-light);
            box-shadow: 
                0 0 0 0.4rem rgba(211, 47, 47, 0.2),
                0 5px 15px rgba(211, 47, 47, 0.15);
            transform: translateY(-2px);
        }
        
        .field-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
            font-weight: 400;
        }
        
        .remember-field {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 1.8rem 0;
        }
        
        .checkbox-container {
            position: relative;
            display: inline-block;
        }
        
        .checkbox-input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .checkbox-custom {
            position: relative;
            display: inline-block;
            width: 24px;
            height: 24px;
            background: rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(211, 47, 47, 0.4);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .checkbox-input:checked + .checkbox-custom {
            background: var(--primary);
            border-color: var(--primary-light);
            box-shadow: 0 0 12px rgba(211, 47, 47, 0.5);
        }
        
        .checkbox-custom::after {
            content: "";
            position: absolute;
            display: none;
            left: 8px;
            top: 4px;
            width: 6px;
            height: 12px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .checkbox-input:checked + .checkbox-custom::after {
            display: block;
        }
        
        .checkbox-label {
            color: var(--secondary);
            font-size: 1.05rem;
            cursor: pointer;
            font-weight: 500;
        }
        
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(90deg, #dc2626, #e11d48, #be123c);
            background-size: 200% 200%;
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-radius: 999px;
            color: white;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 
                0 8px 25px rgba(211, 47, 47, 0.4),
                0 0 15px rgba(211, 47, 47, 0.2);
        }
        
        .submit-btn:hover {
            background-position: right center;
            transform: translateY(-4px) scale(1.02);
            box-shadow: 
                0 12px 35px rgba(211, 47, 47, 0.6),
                0 0 25px rgba(211, 47, 47, 0.4);
        }
        
        .submit-btn:active {
            transform: translateY(-2px) scale(1.01);
        }
        
        .alert {
            padding: 18px;
            border-radius: 16px;
            margin-bottom: 2rem;
            font-weight: 500;
            border-left: 4px solid;
            backdrop-filter: blur(10px);
            background: rgba(0, 0, 0, 0.3);
        }
        
        .alert-danger {
            border-left-color: #f44336;
            color: var(--secondary);
        }
        
        .alert-success {
            border-left-color: #4caf50;
            color: var(--secondary);
        }
        .logo-image {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    transition: all 0.4s ease;
}
        .login-card {
            animation: card-enter 0.9s cubic-bezier(0.23, 1, 0.32, 1) forwards;
            opacity: 0;
            transform: translateX(60px);
        }
        
        @keyframes card-enter {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @media (max-width: 992px) {
            .login-container {
                max-width: 95%;
                min-height: 95vh;
            }
            
            .login-card {
                flex-direction: column;
                min-height: 90vh;
                height: auto;
                overflow-y: auto;
            }
            
            .info-section {
                padding: 2.5rem 2rem;
                min-height: 320px;
            }
            
            .form-section {
                padding: 2.5rem 2rem;
            }
            
            .logo-placeholder {
                width: 100px;
                height: 100px;
                font-size: 2.8rem;
            }
            
            .info-title {
                font-size: 2rem;
            }
            
            .form-title {
                font-size: 1.7rem;
            }
        }
        
        @media (max-width: 576px) {
			.logo-container {
			margin-bottom: 0.5rem;
			text-align: center;
			}
			.logo-image {
				width: 60px;
				height: 60px;
				}
			.info-section {
				display: flex;
				padding: 0.7rem 1.5rem;
				min-height: 125px;
				flex-direction: row;
				align-content: center;
				align-items: center;
			}
            
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .logo-placeholder {
                width: 80px;
                height: 80px;
                font-size: 2.2rem;
            }
            
			.info-title {
				margin: 0px;
			    padding: 6px;
				font-size: 1.7rem;
			}
            
            .info-description {
                font-size: 1rem;
                line-height: 1.6;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
            
            .field-input {
                padding: 16px 20px;
                font-size: 1.05rem;
            }
            
            .submit-btn {
                padding: 16px;
                font-size: 1.15rem;
            }
        }
        
        .login-card:hover {
            transform: translateY(-8px);
            box-shadow: 
                0 40px 80px rgba(211, 47, 47, 0.4),
                inset 0 0 35px rgba(255, 255, 255, 0.12);
        }
    </style>
</head>
<body class="lg-app">
    <div class="lg-orbs" aria-hidden="true">
        <span class="lg-orb lg-orb-a"></span>
        <span class="lg-orb lg-orb-b"></span>
        <span class="lg-orb lg-orb-c"></span>
    </div>
    <div class="language-particles" id="languageParticles" aria-hidden="true"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="info-section">
                <div class="logo-container">
                    <img src="assets/icons/android-chrome-192x192.png" alt="" class="logo-image">
                </div>
                <h2 class="info-title">پنل آموزشی غزال</h2>

            </div>
            
            <div class="form-section">
                <div class="form-header">
                    <h3 class="form-title">ورود به سیستم</h3>
                    <p class="form-subtitle">لطفاً اطلاعات خود را وارد کنید</p>
                </div>
                
                <?php if (!empty($_GET['error'])): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($_GET['error']) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_GET['message'])): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($_GET['message']) ?>
                    </div>
                <?php endif; ?>

                <form action="login-process.php" method="POST">
                    <div class="form-field">
                        <label class="field-label">نام کاربری</label>
                        <input type="text" 
                               name="username" 
                               class="field-input" 
                               placeholder="مثال: teacher_john" 
                               
                               required>
                    </div>
                    
                    <div class="form-field">
                        <label class="field-label">رمز عبور</label>
                        <input type="password" 
                               name="password" 
                               class="field-input" 
                               placeholder="••••••••" 
                               required>
                    </div>
                    
                    <div class="remember-field">
                        <div class="checkbox-container">
                            <input type="checkbox" 
                                   id="remember_me" 
                                   name="remember_me" 
                                   value="1" 
                                   class="checkbox-input"
                                   <?= !empty($remember_username) ? 'checked' : '' ?>>
                            <span class="checkbox-custom"></span>
                        </div>
                        <label for="remember_me" class="checkbox-label">مرا به خاطر بسپار</label>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        ورود به سیستم
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const languageParticles = document.getElementById('languageParticles');
            const letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'λ', 'μ', 'π', 'σ', 'φ', 'ψ', 'ω'];
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'letter-particle';
                
                const randomLetter = letters[Math.floor(Math.random() * letters.length)];
                particle.setAttribute('data-letter', randomLetter);
                particle.setAttribute('aria-hidden', 'true');
                
                const size = Math.random() * 12 + 16;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const delay = Math.random() * 15;
                const duration = 10 + Math.random() * 20;
                const opacity = 0.2 + Math.random() * 0.4;
                
                particle.style.fontSize = size + 'px';
                particle.style.left = posX + '%';
                particle.style.top = posY + '%';
                particle.style.animationDelay = delay + 's';
                particle.style.animationDuration = duration + 's';
                particle.style.opacity = opacity;
                
                languageParticles.appendChild(particle);
            }
            
            document.querySelector('.field-input').focus();
        });
    </script>
    <script src="assets/js/liquid-glass.js?v=lg19"></script>
</body>
</html>