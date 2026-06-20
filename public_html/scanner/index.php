<?php $active='scanner'; $pageTitle='Market Scanner - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="scannerPage()" x-init="load()">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div><h1 class="text-2xl font-bold">Market Scanner</h1><p class="text-[#8A97AD] text-sm">Live setups across Binance Futures pairs.</p></div>
    <div class="flex items-center gap-2 text-sm">
      <span class="text-[#8A97AD]"><span class="live-dot"></span> Auto-refresh 30s</span>
    </div>
  </div>

  <!-- Filters -->
  <div class="card p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div><label class="lbl">Timeframe</label>
      <select class="input" x-model="tf" @change="load()"><option>1m</option><option>5m</option><option selected>15m</option><option>1h</option></select></div>
    <div><label class="lbl">Rating</label>
      <select class="input" x-model="rating" @change="load()"><option value="">All</option><option value="strong_buy">Strong Buy</option><option value="buy">Buy</option><option value="neutral">Neutral</option><option value="sell">Sell</option><option value="strong_sell">Strong Sell</option></select></div>
    <div class="flex-1 min-w-[160px]"><label class="lbl">Search symbol</label>
      <input class="input" x-model="search" placeholder="e.g. BTC"></div>
    <div x-show="locked" class="text-xs text-amber-400">Free plan: limited results. <a href="/pricing/" class="underline">Upgrade</a></div>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="tvp-table">
        <thead><tr>
          <th @click="sortBy('symbol')">Symbol</th>
          <th @click="sortBy('price')">Price</th>
          <th @click="sortBy('change_pct')">24h %</th>
          <th>Setup</th>
          <th @click="sortBy('rsi')">RSI</th>
          <th @click="sortBy('trend_score')">Trend Score</th>
          <th>Rating</th>
          <th>Scanned</th>
        </tr></thead>
        <tbody>
          <template x-for="r in filtered()" :key="r.symbol">
            <tr>
              <td class="font-semibold"><a :href="'/terminal/?symbol='+r.symbol" class="hover:text-blue-400" x-text="r.symbol"></a></td>
              <td x-text="TVP.fmtPrice(r.price)"></td>
              <td :class="TVP.pctClass(r.change_pct)" x-text="TVP.pct(r.change_pct)"></td>
              <td class="text-[#8A97AD]" x-text="r.setup_type"></td>
              <td x-text="r.rsi?Number(r.rsi).toFixed(1):'--'"></td>
              <td x-text="Number(r.trend_score).toFixed(1)"></td>
              <td><span class="badge" :class="'rating-'+r.rating" x-text="TVP.ratingLabel(r.rating)"></span></td>
              <td class="text-xs text-[#8A97AD]" x-text="TVP.timeAgo(r.scanned_at)"></td>
            </tr>
          </template>
          <tr x-show="!loading && filtered().length===0"><td colspan="8" class="text-center text-[#8A97AD] py-10">No results. The scanner cron may not have run yet.</td></tr>
          <tr x-show="loading"><td colspan="8" class="py-10 text-center text-[#8A97AD]">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function scannerPage(){
  return {
    rows:[], tf:'15m', rating:'', search:'', sort:'trend_score', dir:'desc', loading:true, locked:false, timer:null,
    async load(){
      this.loading=true;
      try{
        const res = await TVP.api('scanner?timeframe='+this.tf+(this.rating?'&rating='+this.rating:'')+'&sort='+this.sort+'&dir='+this.dir,{method:'GET'});
        this.rows = res;
      }catch(e){ TVP.toast(e.message,'error'); }
      this.loading=false;
      clearTimeout(this.timer); this.timer=setTimeout(()=>this.load(),30000);
    },
    sortBy(c){ if(this.sort===c){this.dir=this.dir==='desc'?'asc':'desc';}else{this.sort=c;this.dir='desc';} this.load(); },
    filtered(){ const s=this.search.toUpperCase(); return this.rows.filter(r=>!s||r.symbol.includes(s)); }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
