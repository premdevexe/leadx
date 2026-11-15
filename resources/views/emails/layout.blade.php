<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'LeadX'))</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; color: #171717; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .card { background: #fff; border-radius: 8px; border: 1px solid #e5e5e5; padding: 32px; }
        .logo { display: flex; align-items: center; gap: 8px; margin-bottom: 24px; }
        .logo-icon { width: 32px; height: 32px; background: #171717; color: #fff; display: flex; align-items: center; justify-content: center; border-radius: 6px; font-weight: 700; font-size: 14px; }
        .logo-text { font-weight: 600; font-size: 16px; }
        h1 { font-size: 20px; font-weight: 600; margin: 0 0 16px 0; }
        p { font-size: 14px; line-height: 1.6; color: #404040; margin: 0 0 16px 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
        th { text-align: left; padding: 8px 12px; border-bottom: 2px solid #171717; font-weight: 600; color: #171717; }
        td { padding: 8px 12px; border-bottom: 1px solid #e5e5e5; color: #404040; }
        tr:last-child td { border-bottom: none; }
        .btn { display: inline-block; padding: 10px 20px; background: #171717; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: 500; margin: 16px 0; }
        .footer { font-size: 12px; color: #a3a3a3; text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #e5e5e5; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; background: #ecfdf5; color: #065f46; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <div class="logo-icon">L</div>
                <div class="logo-text">{{ config('app.name', 'LeadX') }}</div>
            </div>

            @yield('content')

            <div class="footer">
                <p>This email was sent by {{ config('app.name', 'LeadX') }}. If you did not expect this, please ignore it.</p>
            </div>
        </div>
    </div>
</body>
</html>
