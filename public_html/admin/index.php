<?php $pageTitle='Admin - TradeVision Pro';
require __DIR__.'/../includes/partials/head.php'; ?>
<body class="bg-bg text-[#E5EAF2] min-h-screen" x-data="adminApp()" x-init="init()">
<div class="flex min-h-screen">
  <aside class="hidden lg:flex flex-col w-60 shrink-0 border-r border-[#1E2A3D] bg-[#0F1623] p-4">
    <div class="flex items-center gap-2 mb-6 px-2">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-blue-600 flex items-center justify-center font-extrabold">A</div>
      <div><div class="font-bold leading-none">Admin Panel</div><div class="text-[10px] text-violet-400 tracking-widest">TRADEVISION</div></div>
    </div>
    <nav class="flex-1 space-y-1">
      <template x-for="t in tabs" :key="t.k">
        <button class="sidebar-link w-full text-left" :class="tab===t.k?'active':''" @click="go(t.k)" x-text="t.l"></button>
      </template>
    </nav>
    <a href="/dashboard/" class="sidebar-link">← User App</a>
    <button @click="TVP.logout()" class="sidebar-link text-left w-full">Logout</button>
  </aside>

  <div class="flex-1 min-w-0">
    <header class="h-14 border-b border-[#1E2A3D] bg-[#0F1623]/80 backdrop-blur flex items-center justify-between px-4 sticky top-0 z-30">
      <select class="input lg:hidden w-40" x-model="tab" @change="go(tab)"><template x-for="t in tabs" :key="t.k"><option :value="t.k" x-text="t.l"></option></template></select>
      <div class="font-semibold capitalize hidden lg:block" x-text="tab"></div>
      <div class="text-sm text-[#8A97AD]" x-text="admin.email"></div>
    </header>
    <main class="p-4 sm:p-6 max-w-[1500px] mx-auto fade-in">


    <!-- DASHBOARD -->
    <div x-show="tab==='dashboard'">
      <h1 class="text-2xl font-bold mb-5">Overview</h1>
      <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Total Users</div><div class="text-2xl font-bold" x-text="stats.users"></div></div>
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Active Subscriptions</div><div class="text-2xl font-bold text-success" x-text="stats.active_subs"></div></div>
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Pending Payments</div><div class="text-2xl font-bold text-amber-400" x-text="stats.pending_payments"></div></div>
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Signals Today</div><div class="text-2xl font-bold" x-text="stats.signals_today"></div></div>
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Scanned Symbols</div><div class="text-2xl font-bold" x-text="stats.scanned_symbols"></div></div>
        <div class="card p-5"><div class="text-xs text-[#8A97AD]">Revenue (USDT)</div><div class="text-2xl font-bold text-success" x-text="Number(stats.revenue_usdt||0).toFixed(2)"></div></div>
      </div>
    </div>

    <!-- USERS -->
    <div x-show="tab==='users'">
      <div class="flex justify-between items-center mb-4"><h1 class="text-2xl font-bold">Users</h1>
        <input class="input w-64" placeholder="Search email/name" x-model="userSearch" @keydown.enter="loadUsers()"></div>
      <div class="card overflow-hidden"><table class="tvp-table">
        <thead><tr><th>Name</th><th>Email</th><th>Plan</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody><template x-for="u in users" :key="u.id"><tr>
          <td x-text="u.full_name"></td><td class="text-xs" x-text="u.email"></td>
          <td><span class="badge rating-neutral" x-text="u.plan_code"></span></td>
          <td><span class="badge" :class="u.status==='active'?'rating-strong_buy':'rating-strong_sell'" x-text="u.status"></span></td>
          <td class="text-xs text-[#8A97AD]" x-text="(u.created_at||'').slice(0,10)"></td>
          <td class="space-x-1 whitespace-nowrap">
            <button class="btn btn-ghost text-xs px-2 py-1" @click="setStatus(u, u.status==='active'?'suspended':'active')" x-text="u.status==='active'?'Suspend':'Activate'"></button>
            <select class="input text-xs inline-block w-auto py-1" @change="assignPlan(u,$event.target.value)">
              <option value="">Set plan…</option><template x-for="p in plans" :key="p.id"><option :value="p.id" x-text="p.name"></option></template>
            </select>
          </td>
        </tr></template></tbody>
      </table></div>
    </div>


    <!-- PAYMENTS -->
    <div x-show="tab==='payments'">
      <div class="flex justify-between items-center mb-4"><h1 class="text-2xl font-bold">Payment Requests</h1>
        <select class="input w-40" x-model="payStatus" @change="loadPayments()"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
      <div class="card overflow-hidden"><table class="tvp-table">
        <thead><tr><th>User</th><th>Plan</th><th>Amount</th><th>TXID</th><th>Proof</th><th>Submitted</th><th>Actions</th></tr></thead>
        <tbody><template x-for="p in payments" :key="p.id"><tr>
          <td class="text-xs"><div x-text="p.full_name"></div><div class="text-[#8A97AD]" x-text="p.email"></div></td>
          <td x-text="p.plan_name"></td>
          <td x-text="Number(p.amount_usdt).toFixed(2)"></td>
          <td class="text-xs break-all max-w-[160px]"><span x-text="(p.txid||'').slice(0,22)+'…'"></span></td>
          <td><a x-show="p.screenshot" :href="'/'+p.screenshot" target="_blank" class="text-blue-400 text-xs">View</a><span x-show="!p.screenshot" class="text-[#8A97AD] text-xs">—</span></td>
          <td class="text-xs text-[#8A97AD]" x-text="(p.created_at||'').slice(0,16)"></td>
          <td class="space-x-1 whitespace-nowrap" x-show="p.status==='pending'">
            <button class="btn btn-success text-xs px-2 py-1" @click="approve(p)">Approve</button>
            <button class="btn btn-danger text-xs px-2 py-1" @click="reject(p)">Reject</button>
          </td>
          <td x-show="p.status!=='pending'"><span class="badge" :class="p.status==='approved'?'rating-strong_buy':'rating-strong_sell'" x-text="p.status"></span></td>
        </tr></template>
        <tr x-show="payments.length===0"><td colspan="7" class="text-center text-[#8A97AD] py-8">No payment requests.</td></tr>
        </tbody>
      </table></div>
    </div>

    <!-- WALLETS -->
    <div x-show="tab==='wallets'">
      <h1 class="text-2xl font-bold mb-4">USDT Wallets</h1>
      <div class="card p-4 mb-4 grid sm:grid-cols-4 gap-3 items-end">
        <div><label class="lbl">Network</label><input class="input" x-model="newWallet.network" placeholder="BEP20"></div>
        <div><label class="lbl">Address</label><input class="input" x-model="newWallet.address" placeholder="0x..."></div>
        <div><label class="lbl">Label</label><input class="input" x-model="newWallet.label"></div>
        <button class="btn btn-primary" @click="addWallet()">Add Wallet</button>
      </div>
      <div class="card overflow-hidden"><table class="tvp-table">
        <thead><tr><th>Network</th><th>Address</th><th>Label</th><th>Active</th><th></th></tr></thead>
        <tbody><template x-for="w in wallets" :key="w.id"><tr>
          <td x-text="w.network"></td><td class="text-xs break-all" x-text="w.address"></td><td x-text="w.label"></td>
          <td><button class="badge" :class="w.is_active==1?'rating-strong_buy':'rating-neutral'" @click="toggleWallet(w)" x-text="w.is_active==1?'Active':'Off'"></button></td>
          <td><button class="text-xs text-red-400" @click="delWallet(w.id)">Delete</button></td>
        </tr></template></tbody>
      </table></div>
    </div>


    <!-- SETTINGS (incl scanner rules + indicator weights) -->
    <div x-show="tab==='settings'">
      <h1 class="text-2xl font-bold mb-4">Site & Engine Settings</h1>
      <div class="card p-5 mb-4">
        <h3 class="font-semibold mb-3">Signal Confidence Weights (must total 100)</h3>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
          <template x-for="w in ['weight_trend','weight_volume','weight_rsi','weight_structure','weight_supply_demand']" :key="w">
            <div><label class="lbl" x-text="w.replace('weight_','').replace('_',' ')"></label><input class="input" type="number" x-model="settingsMap[w]"></div>
          </template>
        </div>
        <div class="text-xs mt-2" :class="weightSum===100?'text-success':'text-amber-400'" x-text="'Total: '+weightSum"></div>
      </div>
      <div class="card p-5 mb-4 grid sm:grid-cols-2 gap-3">
        <div><label class="lbl">Min Signal Confidence</label><input class="input" type="number" x-model="settingsMap.signal_min_confidence"></div>
        <div><label class="lbl">Scanner Symbol Limit</label><input class="input" type="number" x-model="settingsMap.scanner_symbols_limit"></div>
        <div><label class="lbl">Scanner Min Volume (USDT)</label><input class="input" type="number" x-model="settingsMap.scanner_min_volume_usdt"></div>
        <div><label class="lbl">Cron Key (HTTP trigger)</label><input class="input" x-model="settingsMap.cron_key"></div>
        <div><label class="lbl">Telegram Bot Token</label><input class="input" x-model="settingsMap.telegram_bot_token"></div>
        <div><label class="lbl">Support Email</label><input class="input" x-model="settingsMap.support_email"></div>
        <div><label class="lbl">Site Name</label><input class="input" x-model="settingsMap.site_name"></div>
      </div>
      <button class="btn btn-primary" @click="saveSettings()">Save Settings</button>
    </div>

    <!-- ANNOUNCEMENTS -->
    <div x-show="tab==='announcements'">
      <h1 class="text-2xl font-bold mb-4">Announcements</h1>
      <div class="card p-4 mb-4 grid sm:grid-cols-4 gap-3 items-end">
        <div class="sm:col-span-2"><label class="lbl">Title</label><input class="input" x-model="newAnn.title"></div>
        <div><label class="lbl">Level</label><select class="input" x-model="newAnn.level"><option>info</option><option>success</option><option>warning</option><option>danger</option></select></div>
        <button class="btn btn-primary" @click="addAnn()">Post</button>
        <div class="sm:col-span-4"><label class="lbl">Body</label><input class="input" x-model="newAnn.body"></div>
      </div>
      <div class="space-y-2">
        <template x-for="a in announcements" :key="a.id">
          <div class="card p-4 flex justify-between items-center"><div><span class="badge" :class="'rating-'+(a.level==='danger'?'strong_sell':a.level==='success'?'strong_buy':'neutral')" x-text="a.level"></span> <b x-text="a.title"></b><div class="text-xs text-[#8A97AD]" x-text="a.body"></div></div><button class="text-xs text-red-400" @click="delAnn(a.id)">Delete</button></div>
        </template>
      </div>
    </div>

    <!-- LOGS -->
    <div x-show="tab==='logs'">
      <h1 class="text-2xl font-bold mb-4">Activity Logs</h1>
      <div class="card overflow-hidden"><table class="tvp-table">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
        <tbody><template x-for="l in logs" :key="l.id"><tr>
          <td class="text-xs text-[#8A97AD]" x-text="(l.created_at||'').slice(0,19)"></td>
          <td class="text-xs" x-text="l.email||'system'"></td><td x-text="l.action"></td>
          <td class="text-[#8A97AD]" x-text="l.entity||'—'"></td><td class="text-xs" x-text="l.ip_address"></td>
        </tr></template></tbody>
      </table></div>
    </div>


    </main>
  </div>
</div>
<script>
function adminApp(){
  return {
    tab:'dashboard', admin:{},
    tabs:[{k:'dashboard',l:'Dashboard'},{k:'users',l:'Users'},{k:'payments',l:'Payments'},{k:'wallets',l:'Wallets'},{k:'settings',l:'Settings'},{k:'announcements',l:'Announcements'},{k:'logs',l:'Logs'}],
    stats:{}, users:[], userSearch:'', plans:[], payments:[], payStatus:'pending',
    wallets:[], newWallet:{network:'BEP20',address:'',label:''},
    settingsMap:{}, announcements:[], newAnn:{level:'info'}, logs:[],
    get weightSum(){ return ['weight_trend','weight_volume','weight_rsi','weight_structure','weight_supply_demand'].reduce((a,k)=>a+Number(this.settingsMap[k]||0),0); },
    async init(){
      const u=await TVP.requireAuth(); if(!u){return;}
      if(u.role!=='admin'){ location.href='/dashboard/'; return; }
      this.admin=u;
      try{ this.plans=await TVP.get('plans'); }catch(e){}
      this.go('dashboard');
    },
    go(t){ this.tab=t;
      if(t==='dashboard') this.loadStats();
      if(t==='users') this.loadUsers();
      if(t==='payments') this.loadPayments();
      if(t==='wallets') this.loadWallets();
      if(t==='settings') this.loadSettings();
      if(t==='announcements') this.loadAnn();
      if(t==='logs') this.loadLogs();
    },
    async loadStats(){ try{ this.stats=await TVP.get('admin/stats'); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadUsers(){ try{ this.users=await TVP.get('admin/users?q='+encodeURIComponent(this.userSearch)); }catch(e){ TVP.toast(e.message,'error'); } },
    async setStatus(u,s){ try{ await TVP.patch('admin/users/'+u.id,{status:s}); u.status=s; TVP.toast('Updated','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async assignPlan(u,pid){ if(!pid)return; try{ await TVP.patch('admin/users/'+u.id,{plan_id:Number(pid)}); TVP.toast('Plan assigned','success'); this.loadUsers(); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadPayments(){ try{ this.payments=await TVP.get('admin/payments?status='+this.payStatus); }catch(e){ TVP.toast(e.message,'error'); } },
    async approve(p){ if(!confirm('Approve & activate subscription?'))return; try{ await TVP.post('admin/payments/approve',{id:p.id}); TVP.toast('Approved','success'); this.loadPayments(); }catch(e){ TVP.toast(e.message,'error'); } },
    async reject(p){ const note=prompt('Reason (optional):',''); try{ await TVP.post('admin/payments/reject',{id:p.id,note:note||''}); TVP.toast('Rejected','info'); this.loadPayments(); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadWallets(){ try{ this.wallets=await TVP.get('admin/wallets'); }catch(e){ TVP.toast(e.message,'error'); } },
    async addWallet(){ if(!this.newWallet.address){TVP.toast('Address required','error');return;} try{ await TVP.post('admin/wallets',Object.assign({is_active:1},this.newWallet)); this.newWallet={network:'BEP20',address:'',label:''}; this.loadWallets(); TVP.toast('Wallet added','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async toggleWallet(w){ try{ await TVP.post('admin/wallets',{id:w.id,network:w.network,address:w.address,label:w.label,is_active:w.is_active==1?0:1}); this.loadWallets(); }catch(e){ TVP.toast(e.message,'error'); } },
    async delWallet(id){ if(!confirm('Delete wallet?'))return; try{ await TVP.del('admin/wallets/'+id); this.loadWallets(); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadSettings(){ try{ const rows=await TVP.get('admin/settings'); this.settingsMap={}; rows.forEach(r=>this.settingsMap[r.skey]=r.svalue); }catch(e){ TVP.toast(e.message,'error'); } },
    async saveSettings(){ try{ await TVP.post('admin/settings',{settings:this.settingsMap}); TVP.toast('Settings saved','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadAnn(){ try{ this.announcements=await TVP.get('admin/announcements'); }catch(e){ TVP.toast(e.message,'error'); } },
    async addAnn(){ if(!this.newAnn.title){TVP.toast('Title required','error');return;} try{ await TVP.post('admin/announcements',Object.assign({is_active:1},this.newAnn)); this.newAnn={level:'info'}; this.loadAnn(); TVP.toast('Posted','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async delAnn(id){ try{ await TVP.del('admin/announcements/'+id); this.loadAnn(); }catch(e){ TVP.toast(e.message,'error'); } },
    async loadLogs(){ try{ this.logs=await TVP.get('admin/logs'); }catch(e){ TVP.toast(e.message,'error'); } }
  };
}
</script>
<style>[x-cloak]{display:none!important}</style>
</body></html>
