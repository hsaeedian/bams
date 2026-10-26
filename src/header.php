<?php
require_once __DIR__ . '/lib/bootstrap.php';
login_require();
?>

<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta name="csrf-token" content="<?php echo h(csrf_token()); ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bigbluebutton & Adobe Connect Monitoring System">
    <meta name="author" content="Geraked, Rabist">
    <title>BAMS</title>
    <link rel="icon" href="<?php echo h(URL); ?>images/icon.png" type="image/png">

    <link id="boot-ltr" href="<?php echo h(URL); ?>plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link id="boot-rtl" href="<?php echo h(URL); ?>plugins/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo h(URL); ?>plugins/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?php echo h(URL); ?>css/style.css?v=<?php echo VERSION; ?>">
</head>

<body>