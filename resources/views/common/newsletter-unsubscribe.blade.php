<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unsubscribed</title>
</head>
<body>

@if (isset($success) && $success)
    <p>
        You have been unsubscribed successfully.
    </p>
    <p>
        If you want to subscribe again, log in to your account and change the newsletter setting.
    </p>
@else
    <p>
        We were not able to unsubscribe you. Please try again later or log in to your account and unsubscribe using
        newsletter settings.
    </p>
@endif
</body>
</html>
