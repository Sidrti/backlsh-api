<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $adminName }} Team!</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            background-color: #696cff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px 30px;
            color: #374151;
            line-height: 1.6;
        }
        .content h3 {
            color: #111827;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #696cff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            margin: 0 10px;
            transition: background-color 0.2s;
        }
        .button.download {
            background-color: #10b981;
        }
        .credentials-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .credentials-table {
            width: 100%;
            border-collapse: collapse;
        }
        .credentials-table th {
            text-align: left;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
            padding-bottom: 8px;
            width: 80px;
        }
        .credentials-table td {
            color: #111827;
            font-weight: 600;
            padding-bottom: 8px;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #6b7280;
            font-size: 13px;
            margin: 0;
        }
        .footer a {
            color: #696cff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Backlsh</h1>
            </div>
            <div class="content">
                <h3>Welcome, {{ $name }}!</h3>
                <p>You've been invited by <strong>{{ $adminName }}</strong> to join their team on Backlsh.</p>
                
                <div class="credentials-card">
                    <p style="margin-top: 0; margin-bottom: 15px; font-size: 14px; color: #6b7280;">Your login credentials:</p>
                    <table class="credentials-table">
                        <tr>
                            <th>Email</th>
                            <td>{{ $email }}</td>
                        </tr>
                        <tr>
                            <th>Password</th>
                            <td>{{ $password }}</td>
                        </tr>
                    </table>
                </div>

                <p style="text-align: center; margin-top: 30px; color: #4b5563;">Get started by logging in or downloading our desktop app:</p>
                <div class="button-container">
                    <a href="{{ config('app.website_url').'/login' }}" class="button">Log In Now</a>
                    <a href="{{ config('app.website_url').'/download-nonauth' }}" class="button download">Download App</a>
                </div>
            </div>
            <div class="footer">
                <p>Need help? Contact our support team at <a href="mailto:support@backlsh.com">support@backlsh.com</a></p>
                <p style="margin-top: 10px; font-size: 12px;">&copy; {{ date('Y') }} Backlsh. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
