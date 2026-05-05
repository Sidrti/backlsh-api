<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
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
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px 30px;
            color: #374151;
            line-height: 1.6;
            text-align: center;
        }
        .content h2 {
            color: #111827;
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .content p {
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            padding: 14px 32px;
            background-color: #696cff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: background-color 0.2s;
            box-shadow: 0 2px 4px rgba(105, 108, 255, 0.3);
        }
        .button:hover {
            background-color: #5f61e6;
        }
        .support-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 25px;
            margin: 30px 30px 40px;
            text-align: center;
        }
        .support-card h3 {
            color: #1e293b;
            margin-top: 0;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .support-card p {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .support-button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #ffffff;
            color: #3b82f6 !important;
            border: 1px solid #3b82f6;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
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
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Backlsh</h1>
            </div>
            <div class="content">
                <h2>Welcome to Backlsh!</h2>
                <p>We're excited to have you on board. To get started and secure your account, please verify your email address by clicking the button below.</p>
                <a href="{{ $link }}" class="button">Verify Email Address</a>
            </div>

            <div class="support-card">
                <h3>Need some help?</h3>
                <p>Our support team is always ready to assist you with any questions or issues.</p>
                <a href="mailto:support@backlsh.com" class="support-button">Contact Support</a>
            </div>

            <div class="footer">
                <p>Backlsh helps you improve productivity with powerful tools.</p>
                <p style="margin-top: 10px; font-size: 12px;">&copy; {{ date('Y') }} Backlsh. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>
