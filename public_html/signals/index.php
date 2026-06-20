<?php $active='signals'; $pageTitle='Signals - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="signalsPage()" x-init="load()">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div><h1 class="text-2xl font-bold">Trading Signals</h1><p class="text-[#8A97AD] text-sm">Multi-confirmation, weighted-confidence signals from live data.</p></div>
    <div class="flex gap-1">
      <template x-for="s in ['all','scalping','intraday','swing']" :key="s">
        <button class="btn btn-ghost px-3 py-1.5 text-xs capitalize" :class="style===s?'!border-blue-500 !text-white':''" @click="style=s;load()" x-text="s"></button>
      </template>
    </div>
  </div>

  <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    <template x-for="s in rows" :key="s.id">
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="font-bold text-lg" x-text="s.symbol"></span>
            <span class="text-xs text-[#8A97AD] capitalize" x-text="s.style+' · '+s.timeframe"></span>
          </div>
          <span class="badge" :class="s.direction==='long'?'rating-strong_buy':'rating-strong_sell'" x-text="s.direction.toUpperCase()"></span>
        </div>
        <div class="grid grid-cols-2 gap-y-1.5 text-sm">
          <span class="text-[#8A97AD]">Entry</span><span class="text-right font-medium" x-text="TVP.fmtPrice(s.entry)"></span>
          <span class="text-[#8A97AD]">Stop Loss</span><span class="text-right dir-short" x-text="TVP.fmtPrice(s.stop_loss)"></span>
          <span class="text-[#8A97AD]">TP1</span><span class="text-right dir-long" x-text="TVP.fmtPrice(s.tp1)"></span>
          <span class="text-[#8A97AD]">TP2</span><span class="text-right dir-long" x-text="TVP.fmtPrice(s.tp2)"></span>
          <span class="text-[#8A97AD]">TP3</span><span class="text-right dir-long" x-text="TVP.fmtPrice(s.tp3)"></span>
          <span class="text-[#8A97AD]">Risk : Reward</span><span class="text-right" x-text="s.risk_reward"></span>
        </div>
        <div class="mt-3"><div class="flex justify-between text-xs mb-1"><span class="text-[#8A97AD]">Confidence</span><span x-text="s.confidence+'%'"></span></div>
          <div class="h-2 bg-[#0B0F19] rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-blue-500 to-violet-500" :style="'width:'+s.confidence+'%'"></div></div></div>
        <div class="mt-3 flex items-center justify-between">
          <span class="badge" :class="statusClass(s.status)" x-text="s.status.replace('_',' ')"></span>
          <button class="text-xs text-blue-400" @click="expand=expand===s.id?null:s.id">Confluences</button>
        </div>
        <div x-show="expand===s.id" class="mt-2 text-xs text-[#8A97AD] space-y-1">
          <template x-for="(c,k) in (s.confluences||{})" :key="k">
            <div class="flex justify-between"><span class="capitalize" x-text="k.replace('_',' ')"></span><span x-text="'w '+c.weight+' · '+(c.signed>=0?'+':'')+c.signed"></span></div>
          </template>
        </div>
      </div>
    </template>
  </div>
  <div x-show="rows.length===0" class="card text-center text-[#8A97AD] py-12 mt-2">No signals match this filter yet.</div>
  <div x-show="locked" class="text-center text-amber-400 text-sm mt-4">Free plan shows limited signals. <a href="/pricing/" class="underline">Upgrade to Pro</a> for unlimited + premium signals.</div>
</div>
<script>
function signalsPage(){
  return { rows:[], style:'all', expand:null, locked:false,
    statusClass(s){ if(s==='sl_hit')return 'rating-strong_sell'; if(s.startsWith('tp'))return 'rating-strong_buy'; if(s==='active')return 'rating-neutral'; return 'rating-neutral'; },
    async load(){
      try{ const q=this.style==='all'?'':'?style='+this.style; this.rows=await TVP.get('signals'+q); }
      catch(e){ TVP.toast(e.message,'error'); }
    }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
