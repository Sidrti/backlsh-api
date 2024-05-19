<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Team!</title>
    <style>
        /* Add your email styles here */
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header {
            background-color: #007bff;
            color: #fff;
            text-align: center;
            padding: 20px;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .button:hover {
            background-color: #0056b3;
        }
 
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
<body>
    
    <div class="container">
        <div class="email-header">
        <img src="https://backlsh.com/wp-content/uploads/2023/09/logo.jpg" alt="Backlsh Logo" >
        <h1>Welcome to Backlsh !</h1>
    </div>
        <div class="content">
            <p>Hello {{ $name }},</p>
            <p>Welcome to our team! Your account has been created successfully.</p>
            <p>Your login credentials are:</p>
            <ul>
                <li><strong>Email:</strong> {{ $email }}</li>
                <li><strong>Password:</strong> {{ $password }}</li>
            </ul>
            
            <p>You can log in using the button below:</p>
            <a href="{{config('app.website_url').'/login'}}" class="button">Login</a>
            <p>If you have any questions or need assistance, feel free to contact us at <a href="mailto:support@backlsh.com">support@backlsh.com</a></p>
            <p>Best regards,<br>{{$adminName}}</p>
        </div>
    </div>
</body>
</html>
