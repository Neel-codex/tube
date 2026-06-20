<?php $active='journal'; $pageTitle='Trade Journal - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="journalPage()" x-init="load()">
  <h1 class="text-2xl font-bold mb-1">Trade Journal</h1>
  <p class="text-[#8A97AD] text-sm mb-5">Log trades, attach screenshots and track performance.</p>

  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Total Trades</div><div class="text-xl font-bold" x-text="stats.total_trades"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Win Rate</div><div class="text-xl font-bold text-success" x-text="stats.win_rate+'%'"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Avg R:R</div><div class="text-xl font-bold" x-text="stats.avg_rr"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Profit Factor</div><div class="text-xl font-bold" x-text="stats.profit_factor"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Total PnL</div><div class="text-xl font-bold" :class="stats.total_pnl>=0?'text-success':'text-danger'" x-text="TVP.fmt(stats.total_pnl)"></div></div>
  </div>

  <div class="card p-4 mb-6">
    <h3 class="font-semibold mb-3">Log a Trade</h3>
    <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
      <div><label class="lbl">Symbol</label><input class="input" x-model="f.symbol" placeholder="BTCUSDT"></div>
      <div><label class="lbl">Direction</label><select class="input" x-model="f.direction"><option value="long">Long</option><option value="short">Short</option></select></div>
      <div><label class="lbl">Entry</label><input class="input" type="number" step="any" x-model="f.entry_price"></div>
      <div><label class="lbl">Exit</label><input class="input" type="number" step="any" x-model="f.exit_price"></div>
      <div><label class="lbl">Stop</label><input class="input" type="number" step="any" x-model="f.stop_loss"></div>
      <div><label class="lbl">Size</label><input class="input" type="number" step="any" x-model="f.position_size"></div>
      <div class="lg:col-span-3"><label class="lbl">Notes</label><input class="input" x-model="f.notes"></div>
      <div class="lg:col-span-2"><label class="lbl">Screenshot</label><input class="input" type="file" accept="image/*" @change="file=$event.target.files[0]"></div>
      <button class="btn btn-primary" @click="save()">Add Trade</button>
    </div>
  </div>

  <div class="card overflow-hidden">
    <table class="tvp-table">
      <thead><tr><th>Symbol</th><th>Dir</th><th>Entry</th><th>Exit</th><th>PnL</th><th>R:R</th><th>Outcome</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <template x-for="t in trades" :key="t.id">
          <tr>
            <td class="font-semibold" x-text="t.symbol"></td>
            <td :class="t.direction==='long'?'dir-long':'dir-short'" x-text="t.direction"></td>
            <td x-text="TVP.fmtPrice(t.entry_price)"></td>
            <td x-text="t.exit_price?TVP.fmtPrice(t.exit_price):'—'"></td>
            <td :class="t.pnl>=0?'dir-long':'dir-short'" x-text="t.pnl!==null?TVP.fmt(t.pnl):'—'"></td>
            <td x-text="t.rr??'—'"></td>
            <td><span class="badge" :class="{win:'rating-strong_buy',loss:'rating-strong_sell',open:'rating-neutral',breakeven:'rating-neutral'}[t.outcome]" x-text="t.outcome"></span></td>
            <td class="text-xs text-[#8A97AD]" x-text="(t.created_at||'').slice(0,10)"></td>
            <td><button class="text-xs text-red-400" @click="remove(t.id)">Delete</button></td>
          </tr>
        </template>
        <tr x-show="trades.length===0"><td colspan="9" class="text-center text-[#8A97AD] py-10">No trades logged yet.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<script>
function journalPage(){
  return { trades:[], stats:{total_trades:0,win_rate:0,avg_rr:0,profit_factor:0,total_pnl:0}, f:{direction:'long'}, file:null,
    async load(){ try{ const d=await TVP.get('journal'); this.trades=d.trades; this.stats=d.stats; }catch(e){ TVP.toast(e.message,'error'); } },
    async save(){
      if(!this.f.symbol||!this.f.entry_price){ TVP.toast('Symbol and entry required','error'); return; }
      let shot=null;
      if(this.file){ const fd=new FormData(); fd.append('screenshot',this.file); try{ const r=await TVP.api('journal/upload',{method:'POST',body:fd}); shot=r.path; }catch(e){ TVP.toast('Upload failed: '+e.message,'error'); } }
      try{ await TVP.post('journal',Object.assign({},this.f,{screenshot:shot})); TVP.toast('Trade logged','success'); this.f={direction:'long'}; this.file=null; this.load(); }
      catch(e){ TVP.toast(e.message,'error'); }
    },
    async remove(id){ if(!confirm('Delete trade?'))return; try{ await TVP.del('journal/'+id); this.load(); }catch(e){ TVP.toast(e.message,'error'); } }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
