<?php
// components/header.php
$pageTitle = $pageTitle ?? APP_NAME;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="ZoeFeeds — Loyalty Reward & Raffle Eligibility Platform">
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { 50:'#fff7ed',100:'#ffedd5',200:'#fed7aa',300:'#fdba74',400:'#fb923c',500:'#f97316',600:'#ea580c',700:'#c2410c',800:'#9a3412',900:'#7c2d12' },
            brand: { DEFAULT:'#0a2540', light:'#1a3a5c', accent:'#00d4ff' },
          },
          fontFamily: {
            sans: ['Plus Jakarta Sans', 'sans-serif'],
            display: ['Space Grotesk', 'sans-serif'],
          },
        }
      }
    }
  </script>
</head>
<body class="bg-[#0a0f1a] text-white font-sans">
