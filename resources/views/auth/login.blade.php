<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GRMS</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>
        body.login-body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .login-card {
            background-color: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            padding: 40px;
            transition: var(--transition);
        }
        
        .login-card:hover {
            transform: translateY(-2px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        
        .login-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary));
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 24px;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2);
        }
        
        .login-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }
        
        .login-subtitle {
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .form-group {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .remember-me input {
            cursor: pointer;
        }
        
        .alert-error {
            background-color: var(--danger-light);
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: var(--radius-md);
            padding: 12px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 500;
        }
    </style>
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">G</div>
            <h2 class="login-title">Selamat Datang Kembali</h2>
            <p class="login-subtitle">Masuk untuk mengelola ulasan Google Maps</p>
        </div>
        
        @if ($errors->any())
            <div class="alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        
        <form action="{{ url('/login') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label class="form-label" for="email">Alamat Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus placeholder="admin@company.com" class="form-control">
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi</label>
                <input type="password" name="password" id="password" required placeholder="••••••••" class="form-control">
            </div>
            
            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" id="remember">
                    <span>Ingat Saya</span>
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; height: 44px; font-size: 14px;">
                Masuk ke Dashboard
            </button>
        </form>
    </div>
</body>
</html>
