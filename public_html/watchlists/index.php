<?php $active='watchlists'; $pageTitle='Watchlists - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="wlPage()" x-init="load()">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div><h1 class="text-2xl font-bold">Watchlists</h1><p class="text-[#8A97AD] text-sm">Track crypto, forex, stocks and commodities.</p></div>
    <button class="btn btn-primary" @click="creating=true">+ New Watchlist</button>
  </div>

  <div x-show="creating" class="card p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div><label class="lbl">Name</label><input class="input" x-model="newName" placeholder="My Watchlist"></div>
    <div><label class="lbl">Market</label><select class="input" x-model="newMarket"><option>crypto</option><option>forex</option><option>stocks</option><option>commodities</option></select></div>
    <button class="btn btn-primary" @click="create()">Create</button>
    <button class="btn btn-ghost" @click="creating=false">Cancel</button>
  </div>

  <div class="grid lg:grid-cols-2 gap-5">
    <template x-for="wl in lists" :key="wl.id">
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <div><span class="font-semibold" x-text="wl.name"></span> <span class="badge rating-neutral capitalize" x-text="wl.market"></span></div>
          <button class="text-xs text-red-400" @click="removeList(wl.id)">Delete</button>
        </div>
        <div class="flex gap-2 mb-3">
          <input class="input text-sm" :id="'sym'+wl.id" placeholder="Add symbol e.g. BTCUSDT" @keydown.enter="addItem(wl.id)">
          <button class="btn btn-ghost text-sm" @click="addItem(wl.id)">Add</button>
        </div>
        <div class="space-y-1">
          <template x-for="it in wl.items" :key="it.id">
            <div class="flex items-center justify-between card-2 p-2 text-sm">
              <a :href="'/terminal/?symbol='+it.symbol" class="font-medium hover:text-blue-400" x-text="it.symbol"></a>
              <span class="text-[#8A97AD] text-xs" x-text="it.added_price?('@ '+TVP.fmtPrice(it.added_price)):''"></span>
              <button class="text-xs text-red-400" @click="removeItem(it.id,wl.id)">✕</button>
            </div>
          </template>
          <div x-show="wl.items.length===0" class="text-[#8A97AD] text-sm py-3 text-center">No symbols yet.</div>
        </div>
      </div>
    </template>
  </div>
  <div x-show="lists.length===0" class="card text-center text-[#8A97AD] py-12">No watchlists yet — create your first one.</div>
</div>
<script>
function wlPage(){
  return { lists:[], creating:false, newName:'', newMarket:'crypto',
    async load(){ try{ this.lists=await TVP.get('watchlists'); }catch(e){ TVP.toast(e.message,'error'); } },
    async create(){ try{ await TVP.post('watchlists',{name:this.newName||'My Watchlist',market:this.newMarket}); this.creating=false;this.newName=''; this.load(); TVP.toast('Watchlist created','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async removeList(id){ if(!confirm('Delete this watchlist?'))return; try{ await TVP.del('watchlists/'+id); this.load(); }catch(e){ TVP.toast(e.message,'error'); } },
    async addItem(wlId){ const el=document.getElementById('sym'+wlId); const sym=(el.value||'').toUpperCase().trim(); if(!sym)return; try{ await TVP.post('watchlists/items',{watchlist_id:wlId,symbol:sym}); el.value=''; this.load(); }catch(e){ TVP.toast(e.message,'error'); } },
    async removeItem(id){ try{ await TVP.del('watchlists/items/'+id); this.load(); }catch(e){ TVP.toast(e.message,'error'); } }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
