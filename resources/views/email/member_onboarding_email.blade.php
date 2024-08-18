<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{$adminName}} Team!</title>
    <style>
        /* General email styles */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table {
            border-spacing: 0;
            border-collapse: collapse;
            width: 100%;
        }
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            display: block;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 0;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        .content {
            padding: 20px;
            color: #333333;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            width: 50%; /* Set to 48% to allow some space between the buttons */
            padding: 10px 0;
            background-color: #5A9BD5;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            text-align: center;
            box-sizing: border-box;
            margin:10px;
        }
        .button.download {
            background-color: #69B0AC; /* Different color for download button */
        }
        .button:hover {
            opacity: 0.8;
        }
        .email-footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999999;
            border-top: 1px solid #e0e0e0;
        }
        .description {
            font-size: 12px;
            color: #aaaaaa;
            text-align: center;
            margin-top: 20px;
            padding-right: 40px;
            padding-left: 40px;
        }
        .social-icons {
            text-align: center;
            margin-top: 10px;
        }
        .social-icons svg {
            width: 24px;
            height: 24px;
            margin: 0 10px;
            fill: #aaaaaa;
        }
        .social-icons svg:hover {
            fill: #007bff; /* Change color on hover */
        }
        .credentials-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .credentials-table td {
            padding: 8px;
            border: 1px solid #dddddd;
        }
        .credentials-table th {
            padding: 8px;
            border: 1px solid #dddddd;
            background-color: #f4f4f4;
            text-align: left;
        }
    </style>
</head>
<body>
    <table role="presentation" class="container">
        <tr>
            <td>
                <div class="content">
                    <h3>{{ $name }}, you have been invited to improve & track your productivity in Backlsh</h3>
                    <p>Use the credentials below to log in to your account:</p>
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
                    <p>You can log in or download our app using the buttons below:</p>
                    <div class="button-container">
                        <a href="{{ config('app.website_url').'/login' }}" class="button">Login</a>
                        <a href="{{ config('app.website_url').'/download' }}" class="button download">Download App</a>
                    </div>
                    <p>If you have any questions or need assistance, feel free to contact us at <a href="mailto:support@backlsh.com">support@backlsh.com</a></p>
                    <p>Best regards,<br>{{ $adminName }}</p>
                </div>
                <div class="description">
                    Backlsh helps you improve and track your productivity with powerful tools and insights. Backlsh has been rated among the top productivity tools by various organizations.
                </div>
                <div class="social-icons">
                    <!-- Facebook SVG Icon -->
                    <a href="https://www.facebook.com/Backlsh" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M22.675 0h-21.35C.597 0 0 .597 0 1.326v21.348C0 23.403.597 24 1.326 24H12.82V14.706h-3.281v-3.62h3.281V8.406c0-3.253 1.981-5.025 4.875-5.025 1.387 0 2.58.103 2.927.149v3.395l-2.008.001c-1.574 0-1.879.748-1.879 1.847v2.421h3.759l-.49 3.62h-3.27V24h6.412c.729 0 1.326-.597 1.326-1.326V1.326C24 .597 23.403 0 22.675 0z"/>
                        </svg>
                    </a>
                    <!-- LinkedIn SVG Icon -->
                    <a href="https://www.linkedin.com/company/Backlsh" target="_blank">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M22.23 0H1.77C.79 0 0 .77 0 1.77v20.46C0 23.23.79 24 1.77 24h20.46c.98 0 1.77-.77 1.77-1.77V1.77C24 .77 23.23 0 22.23 0zM7.09 20.45H3.55V9.03h3.54v11.42zM5.32 7.51c-1.13 0-2.05-.92-2.05-2.05s.92-2.05 2.05-2.05 2.05.92 2.05 2.05-.92 2.05-2.05 2.05zM20.45 20.45h-3.54v-5.6c0-1.34-.03-3.07-1.87-3.07-1.87 0-2.16 1.46-2.16 2.98v5.69h-3.54V9.03h3.4v1.56h.05c.47-.89 1.61-1.83 3.31-1.83 3.54 0 4.19 2.33 4.19 5.35v6.34z"/>
                        </svg>
                    </a>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
