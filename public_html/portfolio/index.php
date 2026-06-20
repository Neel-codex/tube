<?php $active='portfolio'; $pageTitle='Portfolio - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="pfPage()" x-init="load()">
  <h1 class="text-2xl font-bold mb-1">Portfolio Tracker</h1>
  <p class="text-[#8A97AD] text-sm mb-5">Open/closed positions, PnL and drawdown.</p>

  <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Open</div><div class="text-xl font-bold" x-text="an.open_trades"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Closed</div><div class="text-xl font-bold" x-text="an.closed_trades"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Net PnL</div><div class="text-xl font-bold" :class="an.net_pnl>=0?'text-success':'text-danger'" x-text="TVP.fmt(an.net_pnl)"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Win Rate</div><div class="text-xl font-bold text-success" x-text="an.win_rate+'%'"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Max DD</div><div class="text-xl font-bold text-danger" x-text="TVP.fmt(an.max_drawdown)"></div></div>
    <div class="card p-4"><div class="text-xs text-[#8A97AD]">Total Loss</div><div class="text-xl font-bold text-danger" x-text="TVP.fmt(an.total_loss)"></div></div>
  </div>

  <div class="grid lg:grid-cols-3 gap-6 mb-6">
    <div class="card p-4 lg:col-span-2"><h3 class="font-semibold mb-3">Equity Curve</h3><canvas id="equityChart" height="110"></canvas></div>
    <div class="card p-4">
      <h3 class="font-semibold mb-3">Add Position</h3>
      <label class="lbl">Symbol</label><input class="input mb-2" x-model="f.symbol" placeholder="BTCUSDT">
      <div class="grid grid-cols-2 gap-2 mb-2">
        <div><label class="lbl">Direction</label><select class="input" x-model="f.direction"><option value="long">Long</option><option value="short">Short</option></select></div>
        <div><label class="lbl">Quantity</label><input class="input" type="number" step="any" x-model="f.quantity"></div>
      </div>
      <label class="lbl">Avg Entry</label><input class="input mb-3" type="number" step="any" x-model="f.avg_entry">
      <button class="btn btn-primary w-full" @click="add()">Add Position</button>
    </div>
  </div>

  <div class="card overflow-hidden">
    <table class="tvp-table">
      <thead><tr><th>Symbol</th><th>Dir</th><th>Qty</th><th>Avg Entry</th><th>Current</th><th>Unrealized</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <template x-for="p in positions" :key="p.id">
          <tr>
            <td class="font-semibold" x-text="p.symbol"></td>
            <td :class="p.direction==='long'?'dir-long':'dir-short'" x-text="p.direction"></td>
            <td x-text="TVP.fmt(p.quantity)"></td>
            <td x-text="TVP.fmtPrice(p.avg_entry)"></td>
            <td x-text="p.current_price?TVP.fmtPrice(p.current_price):'—'"></td>
            <td :class="(p.status==='open'?p.unrealized_pnl:p.realized_pnl)>=0?'dir-long':'dir-short'" x-text="TVP.fmt(p.status==='open'?p.unrealized_pnl:p.realized_pnl)"></td>
            <td><span class="badge" :class="p.status==='open'?'rating-neutral':'rating-strong_buy'" x-text="p.status"></span></td>
            <td>
              <button x-show="p.status==='open'" class="text-xs text-blue-400 mr-2" @click="close(p)">Close</button>
              <button class="text-xs text-red-400" @click="remove(p.id)">Delete</button>
            </td>
          </tr>
        </template>
        <tr x-show="positions.length===0"><td colspan="8" class="text-center text-[#8A97AD] py-10">No positions yet.</td></tr>
      </tbody>
    </table>
  </div>
</div>
<script>
function pfPage(){
  return { positions:[], an:{open_trades:0,closed_trades:0,net_pnl:0,win_rate:0,max_drawdown:0,total_loss:0,equity_curve:[]}, f:{direction:'long'}, chart:null,
    async load(){
      try{ const d=await TVP.get('portfolio'); this.positions=d.positions; this.an=d.analytics; this.draw(); }
      catch(e){ TVP.toast(e.message,'error'); }
    },
    draw(){
      const ctx=document.getElementById('equityChart'); if(!ctx)return;
      const data=this.an.equity_curve&&this.an.equity_curve.length?this.an.equity_curve:[0];
      if(this.chart)this.chart.destroy();
      this.chart=new Chart(ctx,{type:'line',data:{labels:data.map((_,i)=>i+1),datasets:[{data,borderColor:'#3B82F6',backgroundColor:'rgba(59,130,246,.12)',fill:true,tension:.3,pointRadius:0,borderWidth:2}]},options:{plugins:{legend:{display:false}},scales:{x:{display:false},y:{grid:{color:'#1E2A3D'},ticks:{color:'#8A97AD'}}}}});
    },
    async add(){ if(!this.f.symbol){TVP.toast('Symbol required','error');return;} try{ await TVP.post('portfolio',this.f); this.f={direction:'long'}; this.load(); TVP.toast('Position added','success'); }catch(e){ TVP.toast(e.message,'error'); } },
    async close(p){ const px=prompt('Exit price?',p.current_price||p.avg_entry); if(px===null)return; try{ await TVP.put('portfolio/'+p.id,{close:true,exit_price:parseFloat(px)}); this.load(); }catch(e){ TVP.toast(e.message,'error'); } },
    async remove(id){ if(!confirm('Delete position?'))return; try{ await TVP.del('portfolio/'+id); this.load(); }catch(e){ TVP.toast(e.message,'error'); } }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
