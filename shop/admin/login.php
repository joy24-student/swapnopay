<?php
ob_start();
session_start();
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/CSRF_Protect.php');
$csrf = new CSRF_Protect();
$error_message='';

if (isset($_POST['form1'])) {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!$csrf->checkToken()) {
        $error_message = 'Your session expired. Refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error_message = 'Enter your email and password.';
    } else {
        $key = hash('sha256', $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $blocked = false;
        if ($runtimeRoot) {
            $attempt = $pdo->prepare("SELECT failures FROM shop_login_attempts WHERE attempt_key=? AND last_attempt > NOW() - INTERVAL '15 minutes'");
            $attempt->execute([$key]);
            $blocked = (int)$attempt->fetchColumn() >= 10;
        }
        $statement = $pdo->prepare("SELECT * FROM tbl_user WHERE lower(email)=? AND status='Active' LIMIT 1");
        $statement->execute([$email]);
        $row = $statement->fetch();
        $legacy = $row && preg_match('/\A[a-f0-9]{32}\z/i', $row['password']);
        $valid = !$blocked && $row && (password_verify($password, $row['password']) || ($legacy && hash_equals(strtolower($row['password']), md5($password))));
        if ($valid) {
            if ($legacy || password_needs_rehash($row['password'], PASSWORD_BCRYPT, ['cost'=>12])) {
                $row['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
                $pdo->prepare('UPDATE tbl_user SET password=? WHERE id=?')->execute([$row['password'],$row['id']]);
            }
            if ($runtimeRoot) $pdo->prepare('DELETE FROM shop_login_attempts WHERE attempt_key=?')->execute([$key]);
            session_regenerate_id(true);
            $_SESSION['shop_admin_version'] = hash('sha256',$row['password']);
            unset($row['password']);
            $_SESSION['user'] = $row;
            $_SESSION['shop_merchant_id'] = MERCHANT_ID;
            header('Location: index.php'); exit;
        }
        if ($runtimeRoot && !$blocked) $pdo->prepare("INSERT INTO shop_login_attempts(attempt_key,failures) VALUES(?,1) ON CONFLICT(attempt_key) DO UPDATE SET failures=CASE WHEN shop_login_attempts.last_attempt < NOW() - INTERVAL '15 minutes' THEN 1 ELSE shop_login_attempts.failures+1 END,last_attempt=NOW()")->execute([$key]);
        $error_message = $blocked ? 'Too many attempts. Try again in 15 minutes.' : 'Email or password is incorrect.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
	<base href="<?php echo htmlspecialchars(BASE_URL . 'admin/', ENT_QUOTES, 'UTF-8'); ?>">
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>Login</title>

	<meta content="width=device-width, initial-scale=1" name="viewport">

	<link rel="stylesheet" href="css/bootstrap.min.css">
	<link rel="stylesheet" href="css/font-awesome.min.css">
	<link rel="stylesheet" href="css/ionicons.min.css">
	<link rel="stylesheet" href="css/datepicker3.css">
	<link rel="stylesheet" href="css/all.css">
	<link rel="stylesheet" href="css/select2.min.css">
	<link rel="stylesheet" href="css/dataTables.bootstrap.css">
	<link rel="stylesheet" href="css/AdminLTE.min.css">
	<link rel="stylesheet" href="css/_all-skins.min.css">

	<link rel="stylesheet" href="style.css">
</head>

<body class="hold-transition login-page sidebar-mini">

<div class="login-box">
	<div class="login-logo">
		<b>Admin Panel</b>
	</div>
  	<div class="login-box-body">
    	<p class="login-box-msg">Log in to start your session</p>
    
	    <?php 
	    if( (isset($error_message)) && ($error_message!='') ):
	        echo '<div class="error">'.$error_message.'</div>';
	    endif;
	    ?>

		<form action="" method="post">
			<?php $csrf->echoInputField(); ?>
			<div class="form-group has-feedback">
				<input class="form-control" placeholder="Email address" name="email" type="email" autocomplete="username" required autofocus>
			</div>
			<div class="form-group has-feedback">
				<input class="form-control" placeholder="Password" name="password" type="password" autocomplete="current-password" required value="">
			</div>
			<div class="row">
				<div class="col-xs-8"></div>
				<div class="col-xs-4">
					<input type="submit" class="btn btn-success btn-block btn-flat login-button" name="form1" value="Log In">
				</div>
			</div>
		</form>
	</div>
</div>


<script src="js/jquery-2.2.3.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.bootstrap.min.js"></script>
<script src="js/select2.full.min.js"></script>
<script src="js/jquery.inputmask.js"></script>
<script src="js/jquery.inputmask.date.extensions.js"></script>
<script src="js/jquery.inputmask.extensions.js"></script>
<script src="js/moment.min.js"></script>
<script src="js/bootstrap-datepicker.js"></script>
<script src="js/icheck.min.js"></script>
<script src="js/fastclick.js"></script>
<script src="js/jquery.sparkline.min.js"></script>
<script src="js/jquery.slimscroll.min.js"></script>
<script src="js/app.min.js"></script>
<script src="js/demo.js"></script>

</body>
</html>