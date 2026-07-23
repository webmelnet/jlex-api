<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div
        style="width: 100%; max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; box-sizing: border-box;">
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="{{ config('app.frontend_url') }}/images/logo/logo.png" alt="{{ config('app.name') }}"
                style="max-width: 200px; height: auto;">
        </div>

        <h1 style="text-align: center; color: #333; font-size: 14px; margin-bottom: 20px;">Hi {{ $user->name }}</h1>

        <p style="text-align: center; font-size: 18px; color: #666; line-height: 1.6; margin-bottom: 20px;">
            Your password has been reset successfully. Please contact your administrator to obtain your new password.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $loginUrl }}"
                style="background-color: #01AEF0; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                You can login here
            </a>
        </div>

        <p style="text-align: center; font-size: 12px; color: #666; line-height: 1.6; margin-bottom: 20px;">
            If you did not request this password reset, please contact our support team immediately.
        </p>
    </div>
    <div style="display: table; width: 100%; max-width: 600px; margin: 10px auto; padding: 10px;">
        <div style="display: table-cell; text-align: left; vertical-align: middle; font-size: 12px; color: #c6c6c6;">
            &#169; 2026 MPOS | www.mpos.com
        </div>
        <div style="display: table-cell; text-align: right; vertical-align: middle;">
            <a href="{{ config('app.frontend_url') }}" style="color: #c6c6c6; text-decoration: none; font-size: 12px;">
                Log in to MPOS
            </a>
        </div>
    </div>

</body>

</html>