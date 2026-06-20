<?php
/** Shared <head>. Set $pageTitle before including. */
$pageTitle = $pageTitle ?? 'TradeVision Pro';
?><!DOCTYPE html>
<html lang="en" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Real-time market scanner and professional trading signals powered by live market data and technical analysis.">
<title><?= htmlspecialchars($pageTitle) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { darkMode:'class', theme:{ extend:{ colors:{
  bg:'#0B0F19', card:'#121826', primary:'#3B82F6', success:'#10B981', danger:'#EF4444'
}, fontFamily:{ sans:['Inter','system-ui','sans-serif'] } } } };
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/app.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="/assets/js/app.js"></script>
</head>
