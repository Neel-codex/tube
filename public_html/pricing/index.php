<?php $active='pricing'; $pageTitle='Upgrade - TradeVision Pro';
require __DIR__.'/../includes/partials/app_top.php'; ?>
<div x-data="pricingPage()" x-init="load()">
  <h1 class="text-2xl font-bold mb-1">Upgrade your plan</h1>
  <p class="text-[#8A97AD] text-sm mb-6">Pay manually with USDT (BEP20). Your subscription activates automatically after admin approval.</p>

  <!-- Plans -->
  <div class="grid md:grid-cols-3 gap-5 mb-8">
    <template x-for="p in plans" :key="p.id">
      <div class="card p-6 flex flex-col" :class="p.code==='pro'?'border-blue-500/60 glow-primary':''">
        <div class="text-sm uppercase tracking-widest" :class="{free:'text-[#8A97AD]',pro:'text-blue-400',elite:'text-violet-400'}[p.code]" x-text="p.name"></div>
        <div class="mt-3 text-4xl font-extrabold"><span x-text="p.price_usdt==0?'Free':('$'+Number(p.price_usdt).toFixed(0))"></span><span class="text-base text-[#8A97AD] font-normal" x-show="p.price_usdt>0">/mo</span></div>
        <ul class="mt-5 space-y-2 text-sm flex-1">
          <template x-for="f in p.features"><li class="flex gap-2"><span class="text-success">✓</span><span x-text="f"></span></li></template>
        </ul>
        <button class="btn mt-6" :class="p.code===current?'btn-ghost':'btn-primary'" :disabled="p.price_usdt==0||p.code===current"
          @click="choose(p)" x-text="p.code===current?'Current Plan':(p.price_usdt==0?'Free':'Pay with USDT')"></button>
      </div>
    </template>
  </div>


  <!-- Payment modal -->
  <div x-show="selected" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @click.self="selected=null">
    <div class="glass p-6 w-full max-w-lg">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">Pay for <span x-text="selected?.name"></span> — <span x-text="'$'+Number(selected?.price_usdt||0).toFixed(0)"></span> USDT</h3>
        <button @click="selected=null" class="text-[#8A97AD]">✕</button>
      </div>

      <ol class="text-sm text-[#8A97AD] space-y-1 mb-4 list-decimal list-inside">
        <li>Send the exact USDT amount on the <b class="text-white" x-text="wallet.network||'BEP20'"></b> network.</li>
        <li>Paste the transaction hash (TXID) and amount below.</li>
        <li>Optionally upload a screenshot, then submit for review.</li>
      </ol>

      <div class="card-2 p-4 mb-4">
        <div class="text-xs text-[#8A97AD] mb-1">Send USDT to (<span x-text="wallet.network"></span>):</div>
        <div class="flex items-center gap-2">
          <code class="text-xs break-all flex-1" x-text="wallet.address||'Not configured'"></code>
          <button class="btn btn-ghost text-xs px-2 py-1" @click="copy(wallet.address)">Copy</button>
        </div>
        <div class="mt-3 flex justify-center" x-show="wallet.address">
          <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='+encodeURIComponent(wallet.address)" alt="QR" class="rounded-lg bg-white p-1">
        </div>
      </div>

      <label class="lbl">Amount sent (USDT)</label>
      <input class="input mb-3" type="number" step="any" x-model="form.amount" :placeholder="selected?.price_usdt">
      <label class="lbl">Transaction Hash (TXID)</label>
      <input class="input mb-3" x-model="form.txid" placeholder="0x...">
      <label class="lbl">Screenshot (optional)</label>
      <input class="input mb-4" type="file" accept="image/*" @change="form.file=$event.target.files[0]">
      <button class="btn btn-primary w-full" :disabled="submitting" @click="submit()" x-text="submitting?'Submitting...':'Submit Payment for Review'"></button>
    </div>
  </div>

  <!-- My payment requests -->
  <h3 class="font-semibold mb-3">My Payment Requests</h3>
  <div class="card overflow-hidden">
    <table class="tvp-table">
      <thead><tr><th>Plan</th><th>Amount</th><th>TXID</th><th>Status</th><th>Submitted</th></tr></thead>
      <tbody>
        <template x-for="r in requests" :key="r.id">
          <tr>
            <td x-text="r.plan_name"></td>
            <td x-text="Number(r.amount_usdt).toFixed(2)+' USDT'"></td>
            <td class="text-xs"><span x-text="(r.txid||'').slice(0,14)+'…'"></span></td>
            <td><span class="badge" :class="{pending:'rating-neutral',approved:'rating-strong_buy',rejected:'rating-strong_sell'}[r.status]" x-text="r.status"></span></td>
            <td class="text-xs text-[#8A97AD]" x-text="(r.created_at||'').slice(0,16)"></td>
          </tr>
        </template>
        <tr x-show="requests.length===0"><td colspan="5" class="text-center text-[#8A97AD] py-8">No payment requests yet.</td></tr>
      </tbody>
    </table>
  </div>
</div>


<script>
function pricingPage(){
  return {
    plans:[], current:'free', selected:null, wallet:{}, requests:[], submitting:false,
    form:{amount:'',txid:'',file:null},
    async load(){
      try{ this.plans=await TVP.get('plans'); }catch(e){}
      try{ const u=await TVP.get('auth/me'); this.current=u.user.plan_code; }catch(e){}
      try{ this.requests=await TVP.get('payments/mine'); }catch(e){}
      const pre=new URLSearchParams(location.search).get('plan');
      if(pre){ const p=this.plans.find(x=>x.code===pre); if(p&&p.price_usdt>0) this.choose(p); }
    },
    async choose(p){
      this.selected=p; this.form={amount:p.price_usdt,txid:'',file:null};
      try{ const w=await TVP.get('payments/wallet'); this.wallet=w.wallet; }catch(e){ TVP.toast(e.message,'error'); }
    },
    copy(t){ if(t) navigator.clipboard.writeText(t).then(()=>TVP.toast('Address copied','success')); },
    async submit(){
      if(!this.form.txid||!this.form.amount){ TVP.toast('TXID and amount required','error'); return; }
      this.submitting=true;
      try{
        const fd=new FormData();
        fd.append('plan_id',this.selected.id); fd.append('txid',this.form.txid); fd.append('amount_usdt',this.form.amount);
        if(this.form.file) fd.append('screenshot',this.form.file);
        await TVP.api('payments/submit',{method:'POST',body:fd});
        TVP.toast('Payment submitted for review','success'); this.selected=null;
        this.requests=await TVP.get('payments/mine');
      }catch(e){ TVP.toast(e.message,'error'); }
      this.submitting=false;
    }
  };
}
</script>
<?php require __DIR__.'/../includes/partials/app_bottom.php'; ?>
