<?php $active='alerts'; $pageTitle='Alerts - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="alertsPage()" x-init="load()">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div><h1 class="text-2xl font-bold">Alert Center</h1><p class="text-[#8A97AD] text-sm">Browser, email and Telegram alerts.</p></div>
    <button class="btn btn-ghost text-sm" @click="enablePush()">Enable Browser Notifications</button>
  </div>

  <div class="card p-4 mb-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
    <div><label class="lbl">Type</label>
      <select class="input" x-model="f.type">
        <option value="new_signal">New Signal</option><option value="breakout">Breakout</option>
        <option value="trend_change">Trend Change</option><option value="zone_touch">Zone Touch</option>
        <option value="volume_spike">Volume Spike</option><option value="price_above">Price Above</option>
        <option value="price_below">Price Below</option>
      </select></div>
    <div><label class="lbl">Symbol (optional)</label><input class="input" x-model="f.symbol" placeholder="BTCUSDT"></div>
    <div><label class="lbl">Price value</label><input class="input" type="number" step="any" x-model="f.value" placeholder="e.g. 65000"></div>
    <div><label class="lbl">Channels</label>
      <div class="flex gap-2 text-xs mt-2">
        <label class="flex items-center gap-1"><input type="checkbox" value="browser" x-model="f.channels"> Browser</label>
        <label class="flex items-center gap-1"><input type="checkbox" value="email" x-model="f.channels"> Email</label>
        <label class="flex items-center gap-1"><input type="checkbox" value="telegram" x-model="f.channels"> Telegram</label>
      </div></div>
    <button class="btn btn-primary" @click="create()">Create Alert</button>
  </div>

  <div class="card overflow-hidden">
    <table class="tvp-table">
      <thead><tr><th>Type</th><th>Symbol</th><th>Condition</th><th>Channels</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <template x-for="a in rows" :key="a.id">
          <tr>
            <td class="capitalize" x-text="a.alert_type.replace('_',' ')"></td>
            <td x-text="a.symbol||'Any'"></td>
            <td x-text="a.condition_value?TVP.fmtPrice(a.condition_value):'—'"></td>
            <td class="text-xs text-[#8A97AD]" x-text="(a.channels||[]).join(', ')"></td>
            <td><button class="badge" :class="a.is_active==1?'rating-strong_buy':'rating-neutral'" @click="toggle(a)" x-text="a.is_active==1?'Active':'Paused'"></button></td>
            <td><button class="text-xs text-red-400" @click="remove(a.id)">Delete</button></td>
          </tr>
        </template>
        <tr x-show="rows.length===0"><td colspan="6" class="text-center text-[#8A97AD] py-10">No alerts configured.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<script>
function alertsPage(){
  return { rows:[], f:{type:'new_signal',symbol:'',value:'',channels:['browser']},
    async load(){ try{ this.rows=await TVP.get('alerts'); }catch(e){ TVP.toast(e.message,'error'); } },
    async enablePush(){ const ok=await TVP.enableBrowserNotifications(); TVP.toast(ok?'Browser notifications enabled':'Permission denied',ok?'success':'error'); },
    async create(){
      try{ await TVP.post('alerts',{alert_type:this.f.type,symbol:this.f.symbol||null,condition_value:this.f.value||null,channels:this.f.channels.length?this.f.channels:['browser']});
        TVP.toast('Alert created','success'); this.f.symbol='';this.f.value=''; this.load();
      }catch(e){ TVP.toast(e.message,'error'); }
    },
    async toggle(a){ try{ await TVP.patch('alerts/'+a.id,{is_active:a.is_active==1?0:1}); this.load(); }catch(e){ TVP.toast(e.message,'error'); } },
    async remove(id){ try{ await TVP.del('alerts/'+id); this.load(); }catch(e){ TVP.toast(e.message,'error'); } }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
