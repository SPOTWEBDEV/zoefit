<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireUser();
$phone = normalizePhone(trim($_GET['phone']??''));
if (strlen($phone)<12) jsonResponse(['error'=>'Invalid phone number'],400);
$db=getDB();
$stmt=$db->prepare("SELECT id,full_name,phone,status FROM users WHERE phone=?");
$stmt->execute([$phone]);$user=$stmt->fetch();
if (!$user) jsonResponse(['error'=>'No user found with that phone number'],404);
if ($user['status']!=='active') jsonResponse(['error'=>'User account is not active'],400);
jsonResponse(['id'=>(int)$user['id'],'name'=>$user['full_name'],'phone'=>formatPhone($user['phone'])]);
