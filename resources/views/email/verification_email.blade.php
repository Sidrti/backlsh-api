<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification for Backlsh</title>
    <style>
        .email-header {
            background-color: #696cff;
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .email-header img {
            max-width: 60px;
            height: auto;
            margin-right: auto;
            border-radius:50%;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            color: #FFFFFF;
            flex-grow: 1;
            text-align: center;
        }
         .email-footer {
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .email-header img {
            max-width: 60px;
            height: auto;
            margin-right: auto;
        }
    </style>
</head>
<body style="font-family: 'Arial', sans-serif;">
    <div class="email-header">
        <img src="https://backlsh.com/wp-content/uploads/2023/09/logo.jpg" alt="Backlsh Logo" >
        <h1>Email Verification for Backlsh</h1>
    </div>

    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">

        <h2 style="color: #333; text-align: center;">Email Verification for Backlsh</h2>

        <p>Dear {{ $name }},</p>

        <p>Click on the link to verify your email: <br>{{ $link }}</p> <br>

        <p>Thank you for choosing Backlsh! We're excited to have you as part of our community.</p>

        <p>Best regards,<br>
        The Backlsh Team</p>

    </div>
    <footer class="email-footer">
        <img src="https://backlsh.com/wp-content/uploads/2024/05/2024-05-06_23-01-12-300x57.png" cover>
    </footer>
</body>
</html>
