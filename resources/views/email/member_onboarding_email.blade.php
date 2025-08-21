<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $adminName }} Team!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        .content {
            padding: 20px;
            color: #333;
        }
        .button {
            display: inline-block;
            width: 48%;
            padding: 10px 0;
            background-color: #5A9BD5;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin: 10px;
        }
        .button.download {
            background-color: #69B0AC;
        }
        .button:hover {
            opacity: 0.8;
        }
        .credentials-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .credentials-table th, .credentials-table td {
            padding: 8px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .description {
            font-size: 12px;
            color: #aaa;
            text-align: center;
            margin-top: 20px;
            padding: 0 40px;
        }
    </style>
</head>
<body>
    <table class="container">
        <tr>
            <td>
                <div class="content">
                    <h3>Welcome, {{ $name }}! You've been invited to join Backlsh.</h3>
                    <p>Use the credentials below to log in:</p>
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
                    <p>Log in or download our app:</p>
                    <a href="{{ config('app.website_url').'/login' }}" class="button">Login</a>
                    <a href="{{ config('app.website_url').'/download-nonauth' }}" class="button download">Download App</a>
                    <p>Need help? Contact us at <a href="mailto:support@backlsh.com">support@backlsh.com</a>.</p>
                    <p>Best regards,<br>{{ $adminName }}</p>
                </div>
                <div class="description">
                    Backlsh helps you improve productivity with powerful tools and insights.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
