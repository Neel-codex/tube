<?php $active='dashboard'; $pageTitle='Dashboard - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="dashboard()" x-init="load()">
  <h1 class="text-2xl font-bold mb-1">Dashboard</h1>
  <p class="text-[#8A97AD] mb-6">Live market snapshot and your latest opportunities.</p>

  <!-- Stat cards -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="card p-5"><div class="text-xs text-[#8A97AD]">Market Cap</div><div class="text-2xl font-bold mt-1" x-text="ov.mcap"></div></div>
    <div class="card p-5"><div class="text-xs text-[#8A97AD]">BTC Dominance</div><div class="text-2xl font-bold mt-1" x-text="ov.btc"></div></div>
    <div class="card p-5"><div class="text-xs text-[#8A97AD]">Active Signals</div><div class="text-2xl font-bold mt-1 text-success" x-text="ov.signals"></div></div>
    <div class="card p-5"><div class="text-xs text-[#8A97AD]">Last Scan</div><div class="text-lg font-bold mt-1" x-text="ov.lastScan"></div></div>
  </div>

  <!-- Scanner rating distribution -->
  <div class="grid lg:grid-cols-3 gap-6">
    <div class="card p-5 lg:col-span-1">
      <h3 class="font-semibold mb-4">Scanner Sentiment</h3>
      <template x-for="(c,k) in ratings" :key="k">
        <div class="mb-3">
          <div class="flex justify-between text-sm mb-1"><span class="badge" :class="'rating-'+k" x-text="TVP.ratingLabel(k)"></span><span x-text="c"></span></div>
          <div class="h-2 bg-[#0B0F19] rounded-full overflow-hidden"><div class="h-full" :class="barColor(k)" :style="'width:'+pct(c)+'%'"></div></div>
        </div>
      </template>
    </div>

    <!-- Top signals -->
    <div class="card p-5 lg:col-span-2">
      <div class="flex justify-between items-center mb-4"><h3 class="font-semibold">Latest Signals</h3><a href="/signals/" class="text-sm text-blue-400">View all</a></div>
      <div class="space-y-2">
        <template x-for="s in signals" :key="s.id">
          <a href="/signals/" class="flex items-center justify-between card-2 p-3 hover:border-blue-500/40">
            <div class="flex items-center gap-3">
              <span class="badge" :class="s.direction==='long'?'rating-strong_buy':'rating-strong_sell'" x-text="s.direction.toUpperCase()"></span>
              <span class="font-semibold" x-text="s.symbol"></span>
              <span class="text-xs text-[#8A97AD]" x-text="s.style"></span>
            </div>
            <div class="text-right"><div class="text-sm" x-text="'Entry '+TVP.fmtPrice(s.entry)"></div><div class="text-xs text-[#8A97AD]" x-text="s.confidence+'% conf'"></div></div>
          </a>
        </template>
        <div x-show="signals.length===0" class="text-center text-[#8A97AD] py-8 text-sm">No active signals yet.</div>
      </div>
    </div>
  </div>


  <!-- Top scanner movers -->
  <div class="card mt-6 overflow-hidden">
    <div class="flex justify-between items-center p-4 border-b border-[#1E2A3D]"><h3 class="font-semibold">Top Scanner Setups</h3><a href="/scanner/" class="text-sm text-blue-400">Open scanner</a></div>
    <div class="overflow-x-auto">
      <table class="tvp-table">
        <thead><tr><th>Symbol</th><th>Price</th><th>24h</th><th>Setup</th><th>Trend</th><th>Rating</th></tr></thead>
        <tbody>
          <template x-for="r in scanner" :key="r.symbol">
            <tr>
              <td class="font-semibold"><a :href="'/terminal/?symbol='+r.symbol" class="hover:text-blue-400" x-text="r.symbol"></a></td>
              <td x-text="TVP.fmtPrice(r.price)"></td>
              <td :class="TVP.pctClass(r.change_pct)" x-text="TVP.pct(r.change_pct)"></td>
              <td class="text-[#8A97AD]" x-text="r.setup_type"></td>
              <td x-text="Number(r.trend_score).toFixed(1)"></td>
              <td><span class="badge" :class="'rating-'+r.rating" x-text="TVP.ratingLabel(r.rating)"></span></td>
            </tr>
          </template>
          <tr x-show="scanner.length===0"><td colspan="6" class="text-center text-[#8A97AD] py-8">Scanner results will appear once the cron job runs.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function dashboard(){
  return {
    ov:{mcap:'--',btc:'--',signals:0,lastScan:'--'}, ratings:{strong_buy:0,buy:0,neutral:0,sell:0,strong_sell:0}, signals:[], scanner:[],
    pct(c){ const t=Object.values(this.ratings).reduce((a,b)=>a+Number(b),0)||1; return (Number(c)/t*100).toFixed(0); },
    barColor(k){ return {strong_buy:'bg-success',buy:'bg-emerald-400',neutral:'bg-slate-500',sell:'bg-red-400',strong_sell:'bg-danger'}[k]; },
    async load(){
      try{ const o=await TVP.get('market/overview');
        this.ov.mcap=o.global.total_market_cap_usd?'$'+(o.global.total_market_cap_usd/1e12).toFixed(2)+'T':'--';
        this.ov.btc=o.global.btc_dominance?o.global.btc_dominance.toFixed(1)+'%':'--';
        this.ov.signals=o.active_signals??0; this.ov.lastScan=o.last_scan?TVP.timeAgo(o.last_scan):'pending';
        if(o.scanner_ratings) this.ratings=o.scanner_ratings;
      }catch(e){}
      try{ this.signals=(await TVP.get('signals')).slice(0,6); }catch(e){}
      try{ this.scanner=(await TVP.get('scanner?timeframe=15m')).slice(0,10); }catch(e){}
    }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
