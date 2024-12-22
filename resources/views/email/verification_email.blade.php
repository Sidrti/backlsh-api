<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email</title>
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
            width: 100%;
            padding: 10px 0;
            background-color: #696cff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin: 15px;
        }
        .support-section {
            background-color: #27c26c;
            padding: 20px;
            text-align: center;
            border-radius: 5px;
            margin: 20px 15px;
            color: #fff;
        }
        .support-button {
            padding: 10px 20px;
            background-color: #eb4e29;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .description {
            font-size: 12px;
            color: #aaaaaa;
            text-align: center;
            margin-top: 20px;
            padding: 0 40px;
        }
    </style>
</head>
<body>
    <table role="presentation" class="container">
        <tr>
            <td>
                <div class="content">
                    <h2 style="text-align: center;">Welcome to Backlsh</h2>
                    <p>To get started, please verify your email address by clicking the button below.</p>
                    <a href="{{ $link }}" class="button">Verify your email</a>
                </div>

                <div class="support-section">
                    <h3>Need Help?</h3>
                    <p>Our team is here to assist you.</p>
                    <a href="mailto:support@backlsh.com" class="support-button">Contact Support</a>
                </div>

                <div class="description">
                    Backlsh helps you improve productivity with powerful tools.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
