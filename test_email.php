<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/classes/Mailer.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'] ?? '';
    $name = $_POST['name'] ?? 'Test User';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<div class='error'>Invalid email address</div>";
    } else {

        try {

            $mailer = new Mailer(true);

            $contactData = [
                'full_name' => $name,
                'email' => $email,
                'phone' => '08000000000',
                'subject' => 'Mailer System Test',
                'message' => 'This is a test message sent from the mailer testing page.',
                'contact_type' => 'general',
                'user_id' => null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Browser'
            ];

            $sent = false;

            if ($_POST['type'] === 'admin') {
                $sent = $mailer->sendContactToAdmin($contactData);
            }

            if ($_POST['type'] === 'user') {
                $sent = $mailer->sendContactToUser($contactData);
            }

            if ($sent) {
                $message = "<div class='success'>Email sent successfully!</div>";
            } else {
                $message = "<div class='error'>Failed to send email.</div>";
            }

        } catch (Exception $e) {
            $message = "<div class='error'>Mailer Error: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Mailer Test</title>

<style>

body{
font-family:Arial;
background:#f4f6f8;
padding:40px;
}

.container{
max-width:600px;
margin:auto;
background:white;
padding:30px;
border-radius:10px;
box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

h2{
text-align:center;
margin-bottom:25px;
}

input,select{
width:100%;
padding:12px;
margin-top:10px;
margin-bottom:20px;
border:1px solid #ddd;
border-radius:6px;
}

button{
width:100%;
padding:12px;
background:#28a745;
border:none;
color:white;
font-size:16px;
border-radius:6px;
cursor:pointer;
}

button:hover{
background:#218838;
}

.success{
background:#d4edda;
color:#155724;
padding:12px;
border-radius:5px;
margin-bottom:20px;
}

.error{
background:#f8d7da;
color:#721c24;
padding:12px;
border-radius:5px;
margin-bottom:20px;
}

</style>

</head>

<body>

<div class="container">

<h2>Mailer Testing Tool</h2>

<?php echo $message; ?>

<form method="POST">

<label>Recipient Name</label>
<input type="text" name="name" placeholder="John Doe" required>

<label>Email Address</label>
<input type="email" name="email" placeholder="example@gmail.com" required>

<label>Email Type</label>
<select name="type">

<option value="user">User Confirmation Email</option>
<option value="admin">Admin Contact Email</option>

</select>

<button type="submit">Send Test Email</button>

</form>

</div>

</body>
</html>