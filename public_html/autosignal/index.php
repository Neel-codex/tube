<?php $active='autosignal'; $pageTitle='Auto Signal - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="autoSignal()">
  <h1 class="text-2xl font-bold mb-1">Auto Signal</h1>
  <p class="text-[#8A97AD] text-sm mb-6">Submit a chart (or just a symbol). The system reads it and instantly generates a rule-based signal from live market data — fully automatic, no AI.</p>

  <div class="grid lg:grid-cols-3 gap-6">
    <!-- Input -->
    <div class="card p-5 lg:col-span-1">
      <h3 class="font-semibold mb-4">1. Submit Chart / Symbol</h3>

      <label class="lbl">Chart screenshot (optional)</label>
      <div class="border border-dashed border-[#2a3b55] rounded-xl p-4 text-center mb-2 cursor-pointer hover:border-blue-500"
           @click="$refs.file.click()"
           @dragover.prevent @drop.prevent="onDrop($event)">
        <input type="file" accept="image/*" class="hidden" x-ref="file" @change="onFile($event.target.files[0])">
        <template x-if="!preview">
          <div class="text-[#8A97AD] text-sm py-4">
            <svg class="w-8 h-8 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 16V4m0 0L8 8m4-4l4 4M4 20h16"/></svg>
            Click or drag a chart image here
          </div>
        </template>
        <template x-if="preview"><img :src="preview" class="rounded-lg max-h-44 mx-auto"></template>
      </div>
      <p class="text-[10px] text-[#8A97AD] mb-4">Tip: TradingView screenshots whose filename contains the pair (e.g. <code>BINANCE_BTCUSDT_60.png</code>) auto-fill the symbol.</p>

      <label class="lbl">Symbol</label>
      <input class="input mb-3" x-model="symbol" placeholder="BTCUSDT">
      <div class="grid grid-cols-3 gap-2 mb-3">
        <div><label class="lbl">Market</label><select class="input" x-model="market"><option>crypto</option><option>stocks</option><option>forex</option><option>commodities</option></select></div>
        <div><label class="lbl">TF</label><select class="input" x-model="tf"><option>1m</option><option>5m</option><option>15m</option><option selected>1h</option><option>4h</option><option>1d</option></select></div>
        <div><label class="lbl">Style</label><select class="input" x-model="style"><option>scalping</option><option selected>intraday</option><option>swing</option></select></div>
      </div>
      <button class="btn btn-primary w-full" :disabled="loading" @click="run()" x-text="loading?'Reading chart & analyzing…':'Generate Auto Signal'"></button>
    </div>


    <!-- Result -->
    <div class="lg:col-span-2 space-y-4">
      <div x-show="!result && !loading" class="card p-10 text-center text-[#8A97AD]">Your auto-generated signal will appear here.</div>
      <div x-show="loading" class="card p-10 text-center text-[#8A97AD]"><div class="live-dot"></div> Reading chart pixels and crunching live data…</div>

      <template x-if="result && result.signal">
        <div class="space-y-4">
          <!-- Signal card -->
          <div class="card p-5">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3">
                <span class="font-bold text-xl" x-text="result.symbol"></span>
                <span class="text-xs text-[#8A97AD]" x-text="result.timeframe+' · '+result.market"></span>
                <span class="badge" :class="result.signal.direction==='long'?'rating-strong_buy':'rating-strong_sell'" x-text="result.signal.direction.toUpperCase()"></span>
              </div>
              <div class="text-right"><div class="text-2xl font-bold" x-text="TVP.fmtPrice(result.price)"></div><div class="text-xs text-[#8A97AD]">live price</div></div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-y-2 text-sm">
              <span class="text-[#8A97AD]">Entry</span><span class="text-right sm:col-span-2 font-medium" x-text="TVP.fmtPrice(result.signal.entry)"></span>
              <span class="text-[#8A97AD]">Stop Loss</span><span class="text-right sm:col-span-2 dir-short" x-text="TVP.fmtPrice(result.signal.stop_loss)"></span>
              <span class="text-[#8A97AD]">TP1 / TP2 / TP3</span><span class="text-right sm:col-span-2 dir-long" x-text="TVP.fmtPrice(result.signal.tp1)+' / '+TVP.fmtPrice(result.signal.tp2)+' / '+TVP.fmtPrice(result.signal.tp3)"></span>
              <span class="text-[#8A97AD]">Risk : Reward</span><span class="text-right sm:col-span-2" x-text="result.signal.risk_reward"></span>
            </div>
            <div class="mt-4 flex items-center gap-3">
              <div class="flex-1"><div class="flex justify-between text-xs mb-1"><span class="text-[#8A97AD]">Confidence (Grade <span x-text="result.signal.grade"></span>)</span><span x-text="result.signal.confidence+'%'"></span></div>
                <div class="h-2 bg-[#0B0F19] rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-blue-500 to-violet-500" :style="'width:'+result.signal.confidence+'%'"></div></div></div>
            </div>
          </div>

          <!-- Chart read + agreement -->
          <div class="card p-5" x-show="result.chart_read && result.chart_read.ok">
            <h3 class="font-semibold mb-2 text-sm">Chart Read (rule-based pixel analysis)</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
              <div><div class="text-xs text-[#8A97AD]">Detected Bias</div><div class="font-medium capitalize" x-text="result.chart_read.bias"></div></div>
              <div><div class="text-xs text-[#8A97AD]">Bullish / Bearish</div><div x-text="result.chart_read.green_pct+'% / '+result.chart_read.red_pct+'%'"></div></div>
              <div><div class="text-xs text-[#8A97AD]">On-screen Trend</div><div class="capitalize" x-text="result.chart_read.trend_direction"></div></div>
              <div><div class="text-xs text-[#8A97AD]">Read Confidence</div><div x-text="result.chart_read.read_confidence+'%'"></div></div>
            </div>
            <div class="mt-3 text-xs px-3 py-2 rounded-lg" :class="agreeClass()" x-text="agreeText()"></div>
          </div>

          <!-- Context + variants -->
          <div class="grid sm:grid-cols-2 gap-4">
            <div class="card p-4 text-sm">
              <h3 class="font-semibold mb-2">Market Context</h3>
              <div class="flex justify-between"><span class="text-[#8A97AD]">Trend</span><span class="capitalize" x-text="result.structure.trend.replace('_',' ')"></span></div>
              <div class="flex justify-between"><span class="text-[#8A97AD]">Trend Score</span><span x-text="Number(result.structure.trend_score).toFixed(1)"></span></div>
              <div class="flex justify-between"><span class="text-[#8A97AD]">Bias (TA)</span><span class="capitalize" x-text="(result.summary.bias||'').replace('_',' ')"></span></div>
              <div class="flex justify-between"><span class="text-[#8A97AD]">Zone</span><span class="capitalize" x-text="result.smc.premium_discount?.zone||'--'"></span></div>
            </div>
            <div class="card p-4 text-sm">
              <h3 class="font-semibold mb-2">Other Styles</h3>
              <template x-for="(v,st) in result.variants" :key="st">
                <div class="flex justify-between items-center py-0.5">
                  <span class="capitalize text-[#8A97AD]" x-text="st"></span>
                  <span><span class="badge" :class="v.direction==='long'?'rating-buy':'rating-sell'" x-text="v.direction"></span> <span class="text-xs" x-text="v.confidence+'% · RR '+v.risk_reward"></span></span>
                </div>
              </template>
            </div>
          </div>
          <p class="text-[10px] text-[#8A97AD]">Signal generated from live OHLCV via technical indicators, market structure, supply/demand and SMC. The chart read is a rule-based pixel cross-check. Not financial advice.</p>
        </div>
      </template>

      <div x-show="result && !result.signal" class="card p-6 text-amber-400 text-sm" x-text="result?.message"></div>
    </div>
  </div>
</div>


<script>
function autoSignal(){
  return {
    file:null, preview:null, symbol:'', market:'crypto', tf:'1h', style:'intraday',
    loading:false, result:null,
    onFile(f){
      if(!f) return;
      this.file=f; this.preview=URL.createObjectURL(f);
      // try to auto-fill symbol from filename
      const m=(f.name||'').toUpperCase().match(/([A-Z]{2,15}(?:USDT|USD|BTC|ETH|PERP))/);
      if(m && !this.symbol) this.symbol=m[1];
    },
    onDrop(e){ const f=e.dataTransfer.files[0]; if(f) this.onFile(f); },
    async run(){
      if(!this.file && !this.symbol){ TVP.toast('Upload a chart or enter a symbol','error'); return; }
      this.loading=true; this.result=null;
      try{
        const fd=new FormData();
        if(this.file) fd.append('chart',this.file);
        if(this.symbol) fd.append('symbol',this.symbol);
        fd.append('market',this.market); fd.append('timeframe',this.tf); fd.append('style',this.style);
        this.result=await TVP.api('autosignal',{method:'POST',body:fd});
        if(this.result.signal) TVP.toast('Signal generated','success');
        else TVP.toast(this.result.message||'No signal','info');
      }catch(e){ TVP.toast(e.message,'error'); }
      this.loading=false;
    },
    agreeClass(){ const a=this.result?.chart_agreement; return a==='agrees'?'bg-emerald-500/15 text-emerald-300':a==='diverges'?'bg-red-500/15 text-red-300':'bg-slate-500/15 text-slate-300'; },
    agreeText(){ const a=this.result?.chart_agreement;
      if(a==='agrees') return '✓ The chart read agrees with the data-driven signal direction — higher conviction.';
      if(a==='diverges') return '⚠ The chart read diverges from the live-data signal. Trade with caution; the data-driven read takes priority.';
      return 'Chart read was inconclusive; signal is based on live market data.'; }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
