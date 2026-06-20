<?php
/** Authenticated app shell - opening. Set $active and $pageTitle before include. */
$active = $active ?? '';
require __DIR__ . '/head.php';
$nav = [
  ['dashboard','Dashboard','/dashboard/','M3 12l9-9 9 9M5 10v10h14V10'],
  ['terminal','Terminal','/terminal/','M4 5h16v14H4zM8 9l3 3-3 3'],
  ['scanner','Scanner','/scanner/','M4 6h16M4 12h10M4 18h7'],
  ['signals','Signals','/signals/','M13 2L3 14h7l-1 8 10-12h-7z'],
  ['watchlists','Watchlists','/watchlists/','M5 4h14v16l-7-4-7 4z'],
  ['journal','Journal','/journal/','M4 4h16v16H4zM8 8h8M8 12h8M8 16h5'],
  ['portfolio','Portfolio','/portfolio/','M3 13h4v8H3zM10 7h4v14h-4zM17 10h4v11h-4z'],
  ['alerts','Alerts','/alerts/','M12 3a6 6 0 00-6 6v4l-2 3h16l-2-3V9a6 6 0 00-6-6z'],
  ['pricing','Upgrade','/pricing/','M12 2l2.4 7.4H22l-6 4.3 2.3 7.3L12 16.8 5.7 21l2.3-7.3-6-4.3h7.6z'],
];
?>
<body class="bg-bg text-[#E5EAF2] min-h-screen" x-data="appShell()" x-init="init()">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="hidden lg:flex flex-col w-60 shrink-0 border-r border-[#1E2A3D] bg-[#0F1623] p-4">
    <a href="/" class="flex items-center gap-2 mb-6 px-2">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-extrabold">T</div>
      <div><div class="font-bold leading-none">TradeVision</div><div class="text-[10px] text-blue-400 tracking-widest">PRO</div></div>
    </a>
    <nav class="flex-1 space-y-1">
      <?php foreach ($nav as [$key,$label,$href,$path]): ?>
      <a href="<?= $href ?>" class="sidebar-link <?= $active===$key?'active':'' ?>">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $path ?>"/></svg>
        <span><?= $label ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <button @click="TVP.logout()" class="sidebar-link mt-2 text-left w-full">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" d="M16 17l5-5-5-5M21 12H9M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/></svg>
      <span>Logout</span>
    </button>
  </aside>


  <!-- Main column -->
  <div class="flex-1 flex flex-col min-w-0">
    <!-- Topbar -->
    <header class="h-14 border-b border-[#1E2A3D] bg-[#0F1623]/80 backdrop-blur flex items-center justify-between px-4 sticky top-0 z-30">
      <div class="flex items-center gap-3">
        <button class="lg:hidden" @click="mobileNav=!mobileNav">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <span class="text-sm text-[#8A97AD]"><span class="live-dot"></span> Live market data</span>
      </div>
      <div class="flex items-center gap-3">
        <!-- notifications -->
        <div class="relative" x-data="{open:false}">
          <button @click="open=!open" class="relative p-2 rounded-lg hover:bg-white/5">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M12 3a6 6 0 00-6 6v4l-2 3h16l-2-3V9a6 6 0 00-6-6zM9 19a3 3 0 006 0"/></svg>
            <span x-show="unread>0" x-text="unread" class="absolute -top-0.5 -right-0.5 bg-danger text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center"></span>
          </button>
          <div x-show="open" @click.outside="open=false" x-cloak class="absolute right-0 mt-2 w-80 glass p-2 max-h-96 overflow-auto z-50">
            <div class="flex items-center justify-between px-2 py-1">
              <span class="font-semibold text-sm">Notifications</span>
              <button class="text-xs text-blue-400" @click="markAllRead()">Mark all read</button>
            </div>
            <template x-for="n in notifications" :key="n.id">
              <div class="px-2 py-2 rounded-lg hover:bg-white/5" :class="n.is_read?'opacity-60':''">
                <div class="text-sm font-medium" x-text="n.title"></div>
                <div class="text-xs text-[#8A97AD]" x-text="n.body"></div>
              </div>
            </template>
            <div x-show="notifications.length===0" class="px-2 py-6 text-center text-sm text-[#8A97AD]">No notifications</div>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <div class="text-right hidden sm:block">
            <div class="text-sm font-medium" x-text="user.full_name||'Trader'"></div>
            <div class="text-[11px] uppercase tracking-wider" :class="planClass" x-text="(user.plan_code||'free')+' plan'"></div>
          </div>
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-bold" x-text="(user.full_name||'T').charAt(0)"></div>
        </div>
      </div>
    </header>

    <!-- Mobile nav drawer -->
    <div x-show="mobileNav" x-cloak class="lg:hidden border-b border-[#1E2A3D] bg-[#0F1623] p-3 grid grid-cols-2 gap-2 z-40">
      <?php foreach ($nav as [$key,$label,$href]): ?>
      <a href="<?= $href ?>" class="sidebar-link <?= $active===$key?'active':'' ?> text-sm"><?= $label ?></a>
      <?php endforeach; ?>
      <button @click="TVP.logout()" class="sidebar-link text-sm text-left">Logout</button>
    </div>

    <main class="flex-1 p-4 sm:p-6 max-w-[1600px] w-full mx-auto fade-in">
