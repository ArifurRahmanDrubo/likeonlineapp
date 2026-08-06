<!DOCTYPE html>
<html>

<head>
    <title>Payment Successful</title>
</head>

<body>
    <h1>Payment Successful!</h1>
    <p>Dear {{ $customerName }},</p>
    <p>Thank you for your payment of <strong>Tk{{ $amount }}</strong>.</p>
    <p>Your transaction ID is: <strong>{{ $transactionId }}</strong>.</p>
    <p>If you have any questions, feel free to contact us.</p>
</body>

</html>
