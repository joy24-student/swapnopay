<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/CSRF_Protect.php';
$csrf=new CSRF_Protect();
header('Cache-Control: no-store');
if(empty($_SESSION['user']['id'])) { header('Location: ' . BASE_URL . 'admin/login.php');exit; }
$adminQuery=$pdo->prepare("SELECT * FROM tbl_user WHERE id=? AND status='Active'");
$adminQuery->execute([$_SESSION['user']['id']]);$adminUser=$adminQuery->fetch();
if(!$adminUser || !in_array($adminUser['role'],['Top Admin','Admin'],true)) { http_response_code(403);exit('Administrator access is required.'); }
if(!$adminUser || !in_array($adminUser['role'],['Top Admin','Admin','Super Admin'],true)) { http_response_code(403);exit('Administrator access is required.'); }
$passwordVersion=hash('sha256',$adminUser['password']);
if(isset($_SESSION['shop_admin_version']) && !hash_equals($_SESSION['shop_admin_version'],$passwordVersion)) {
    unset($_SESSION['user'],$_SESSION['shop_admin_version']);header('Location: ' . BASE_URL . 'admin/login.php');exit;
}
$_SESSION['shop_admin_version']=$passwordVersion;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$adminPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$ajaxReadOnly = in_array($adminPage, ['get-mid-category.php', 'get-end-category.php', 'fetch_sorted_products.php'], true);
if($method === 'POST' && !$ajaxReadOnly && !$csrf->checkToken() && !$csrf->isTokenValid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    exit('Your form session expired. Refresh the page and try again.');
}

// All rendered forms receive the same server-verified CSRF token, including
// legacy forms that did not previously include one. Scripts are left intact.
$formToken=$csrf->getToken();
ob_start(static function($html) use($formToken) {
    $parts=preg_split('~(<script\b.*?</script>)~is',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
    foreach($parts as $i=>$part) if($i%2===0) $parts[$i]=preg_replace('~(<form\b[^>]*>)~i','$1<input type="hidden" name="_csrf" value="' . $formToken . '">',$part);
    return implode('',$parts);
});
$adminPage=basename($_SERVER['SCRIPT_NAME']);
$mutatingGet=preg_match('/-(delete|remove|approve|change-status)\.php$/',$adminPage) || isset($_GET['delete']) || isset($_GET['delete_id']);
if($method==='GET' && $mutatingGet) {
    $action=htmlspecialchars($_SERVER['REQUEST_URI'],ENT_QUOTES,'UTF-8');
    echo '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm action</title><body style="font-family:system-ui;background:#f4f6fb;padding:24px"><main style="max-width:480px;margin:12vh auto;background:white;border-radius:20px;padding:32px"><h1>Confirm this change</h1><p>Review the selected record before continuing. This action may change or remove data.</p><form method="post" action="' . $action . '"><button style="padding:12px 20px" type="submit">Confirm change</button></form><p><a href="' . htmlspecialchars(BASE_URL,ENT_QUOTES,'UTF-8') . 'admin/index.php">Cancel</a></p></main></body></html>';exit;
}
if($method==='POST') {
    // Check file contents before any legacy upload handler can move the file.
    $validateUpload=static function($file) use (&$validateUpload) {
        if(is_array($file['tmp_name'] ?? null)) {
            foreach(array_keys($file['tmp_name']) as $key) $validateUpload(['tmp_name'=>$file['tmp_name'][$key],'error'=>$file['error'][$key],'size'=>$file['size'][$key],'name'=>$file['name'][$key]]);
            return;
        }
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return;
        $ext=strtolower(pathinfo($file['name'] ?? '',PATHINFO_EXTENSION));
        $info=is_uploaded_file($file['tmp_name'] ?? '') ? @getimagesize($file['tmp_name']) : false;
        $allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp'];
        if(($file['error'] ?? 1)!==UPLOAD_ERR_OK || ($file['size'] ?? 0)>8*1024*1024 || !$info || !isset($allowed[$ext]) || $info['mime']!==$allowed[$ext]) {http_response_code(400);exit('Upload a valid JPG, PNG, GIF or WebP image up to 8 MB.');}
    };
    foreach($_FILES as $file) $validateUpload($file);
    foreach($_POST as $field=>$value) if(str_starts_with($field,'current_') && str_contains($field,'photo') && (!is_string($value) || basename($value)!==$value)) {http_response_code(400);exit('Invalid image reference.');}
}
