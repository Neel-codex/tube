<?php $active='terminal'; $pageTitle='Trading Terminal - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="terminal()" x-init="init()">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2">
      <input class="input w-40" x-model="symbolInput" @keydown.enter="setSymbol(symbolInput)" placeholder="Symbol e.g. BTCUSDT">
      <button class="btn btn-primary" @click="setSymbol(symbolInput)">Load</button>
    </div>
    <div class="flex items-center gap-1">
      <template x-for="t in ['1m','5m','15m','1h','4h','1d']" :key="t">
        <button class="btn btn-ghost px-3 py-1.5 text-xs" :class="tf===t?'!border-blue-500 !text-white':''" @click="tf=t;loadAnalysis()" x-text="t"></button>
      </template>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-4">
    <!-- Chart -->
    <div class="lg:col-span-2 card p-2" style="min-height:520px">
      <div id="tv_chart" style="height:520px"></div>
    </div>

    <!-- Analysis side panel -->
    <div class="space-y-4">
      <div class="card p-4">
        <div class="flex items-center justify-between">
          <div><div class="text-xs text-[#8A97AD]" x-text="sym"></div><div class="text-2xl font-bold" x-text="TVP.fmtPrice(a.price)"></div></div>
          <span class="badge" :class="'rating-'+(a.summaryBias||'neutral')" x-text="(a.summaryBias||'--').replace('_',' ')"></span>
        </div>
      </div>
      <div class="card p-4">
        <h3 class="font-semibold mb-3 text-sm">Indicators</h3>
        <div class="grid grid-cols-2 gap-y-2 text-sm">
          <template x-for="ind in indList" :key="ind.k">
            <template x-if="a.ind && a.ind[ind.k]!==undefined && a.ind[ind.k]!==null">
              <div class="contents"><span class="text-[#8A97AD]" x-text="ind.l"></span><span class="text-right" x-text="ind.f(a.ind[ind.k])"></span></div>
            </template>
          </template>
        </div>
      </div>
    </div>
  </div>


  <!-- Lower panels: structure, SMC, zones, signals -->
  <div class="grid lg:grid-cols-4 gap-4 mt-4">
    <div class="card p-4">
      <h3 class="font-semibold mb-3 text-sm">Market Structure</h3>
      <div class="text-sm space-y-2">
        <div class="flex justify-between"><span class="text-[#8A97AD]">Trend</span><span class="font-medium capitalize" x-text="(a.structure?.trend||'--').replace('_',' ')"></span></div>
        <div class="flex justify-between"><span class="text-[#8A97AD]">Trend Score</span><span x-text="a.structure?Number(a.structure.trend_score).toFixed(1):'--'"></span></div>
        <div class="flex justify-between"><span class="text-[#8A97AD]">HH/HL/LH/LL</span><span x-text="a.structure?`${a.structure.counts.HH}/${a.structure.counts.HL}/${a.structure.counts.LH}/${a.structure.counts.LL}`:'--'"></span></div>
      </div>
    </div>
    <div class="card p-4">
      <h3 class="font-semibold mb-3 text-sm">Smart Money</h3>
      <div class="text-xs space-y-1.5">
        <template x-for="(e,i) in (a.smc?.bos_choch||[])" :key="i"><div><span class="badge" :class="e.direction==='bullish'?'rating-buy':'rating-sell'" x-text="e.type"></span> <span class="text-[#8A97AD]" x-text="'@ '+TVP.fmtPrice(e.level)"></span></div></template>
        <div x-show="(a.smc?.bos_choch||[]).length===0" class="text-[#8A97AD]">No BOS/CHOCH</div>
        <div class="pt-1 text-[#8A97AD]" x-text="'Zone bias: '+(a.smc?.premium_discount?.zone||'--')"></div>
        <div class="text-[#8A97AD]" x-text="'FVGs: '+(a.smc?.fvg?.length||0)+' · OBs: '+(a.smc?.order_blocks?.length||0)"></div>
      </div>
    </div>
    <div class="card p-4">
      <h3 class="font-semibold mb-3 text-sm">Supply / Demand</h3>
      <div class="text-xs space-y-1.5 max-h-40 overflow-auto">
        <template x-for="(z,i) in (a.zones||[])" :key="i">
          <div class="flex justify-between"><span :class="z.type==='demand'?'dir-long':'dir-short'" x-text="z.type"></span><span class="text-[#8A97AD]" x-text="TVP.fmtPrice(z.low)+'–'+TVP.fmtPrice(z.high)"></span><span x-text="z.status"></span></div>
        </template>
        <div x-show="(a.zones||[]).length===0" class="text-[#8A97AD]">No zones detected</div>
      </div>
    </div>
    <div class="card p-4">
      <h3 class="font-semibold mb-3 text-sm">Signals</h3>
      <div class="text-xs space-y-2">
        <template x-for="s in signals" :key="s.id">
          <div class="card-2 p-2"><span class="badge" :class="s.direction==='long'?'rating-strong_buy':'rating-strong_sell'" x-text="s.direction.toUpperCase()"></span> <span x-text="s.style"></span> · <span x-text="s.confidence+'%'"></span></div>
        </template>
        <div x-show="signals.length===0" class="text-[#8A97AD]">No signals for this symbol</div>
      </div>
    </div>
  </div>
</div>

<script src="https://s3.tradingview.com/tv.js"></script>
<script>
function terminal(){
  return {
    sym:'BTCUSDT', symbolInput:'BTCUSDT', tf:'1h', a:{}, signals:[], widget:null,
    indList:[
      {k:'rsi',l:'RSI',f:v=>Number(v).toFixed(1)},{k:'macd_hist',l:'MACD Hist',f:v=>Number(v).toFixed(4)},
      {k:'ema20',l:'EMA 20',f:v=>TVP.fmtPrice(v)},{k:'ema50',l:'EMA 50',f:v=>TVP.fmtPrice(v)},
      {k:'ema200',l:'EMA 200',f:v=>TVP.fmtPrice(v)},{k:'vwap',l:'VWAP',f:v=>TVP.fmtPrice(v)},
      {k:'bb_upper',l:'BB Upper',f:v=>TVP.fmtPrice(v)},{k:'bb_lower',l:'BB Lower',f:v=>TVP.fmtPrice(v)},
      {k:'atr',l:'ATR',f:v=>TVP.fmtPrice(v)},{k:'stoch_k',l:'Stoch %K',f:v=>Number(v).toFixed(1)},
    ],
    init(){
      const u=new URLSearchParams(location.search); if(u.get('symbol')) this.sym=this.symbolInput=u.get('symbol').toUpperCase();
      this.renderChart(); this.loadAnalysis();
    },
    tvInterval(){ return ({'1m':'1','5m':'5','15m':'15','1h':'60','4h':'240','1d':'D'})[this.tf]||'60'; },
    renderChart(){
      document.getElementById('tv_chart').innerHTML='';
      this.widget=new TradingView.widget({autosize:true,symbol:'BINANCE:'+this.sym,interval:this.tvInterval(),timezone:'Etc/UTC',theme:'dark',style:'1',locale:'en',toolbar_bg:'#0B0F19',enable_publishing:false,hide_side_toolbar:false,allow_symbol_change:true,container_id:'tv_chart',studies:['RSI@tv-basicstudies','MAExp@tv-basicstudies']});
    },
    setSymbol(s){ s=(s||'').toUpperCase().trim(); if(!s)return; this.sym=s; this.symbolInput=s; this.renderChart(); this.loadAnalysis(); },
    async loadAnalysis(){
      try{
        const d=await TVP.get('analysis/'+encodeURIComponent(this.sym)+'?timeframe='+this.tf+'&market=crypto');
        this.a={price:d.price,ind:d.indicators,structure:d.structure,smc:d.smc,zones:d.zones,summaryBias:d.summary?.bias};
      }catch(e){ this.a={}; TVP.toast(e.message,'error'); }
      try{ this.signals=await TVP.get('signals?symbol='+encodeURIComponent(this.sym)); }catch(e){ this.signals=[]; }
    }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
