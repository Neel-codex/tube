<?php $pageTitle='TradeVision Pro - Real-Time Market Scanner & Trading Signals';
require __DIR__.'/includes/partials/head.php'; ?>
<body class="bg-bg text-[#E5EAF2]" x-data="landing()" x-init="init()">
<!-- NAV -->
<header class="sticky top-0 z-40 border-b border-[#1E2A3D] bg-bg/80 backdrop-blur">
  <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="/" class="flex items-center gap-2">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-extrabold">T</div>
      <span class="font-bold text-lg">TradeVision <span class="text-blue-400">Pro</span></span>
    </a>
    <nav class="hidden md:flex items-center gap-7 text-sm text-[#8A97AD]">
      <a href="#features" class="hover:text-white">Features</a>
      <a href="#smc" class="hover:text-white">Smart Money</a>
      <a href="#signals" class="hover:text-white">Signals</a>
      <a href="#pricing" class="hover:text-white">Pricing</a>
      <a href="#faq" class="hover:text-white">FAQ</a>
    </nav>
    <div class="flex items-center gap-3">
      <a href="/login.php" class="btn btn-ghost text-sm">Sign In</a>
      <a href="/register.php" class="btn btn-primary text-sm">Start Free Trial</a>
    </div>
  </div>
</header>

<!-- HERO -->
<section class="relative bg-grid overflow-hidden">
  <div class="hero-glow"></div>
  <div class="max-w-7xl mx-auto px-4 pt-20 pb-16 text-center relative">
    <span class="badge rating-strong_buy mb-5 inline-block">100% Rule-Based · No AI · Live Market Data</span>
    <h1 class="text-4xl md:text-6xl font-extrabold leading-tight max-w-4xl mx-auto">
      Real-Time Market Scanner &amp; Professional Trading Signals
      <span class="text-gradient">Powered By Live Market Data</span>
    </h1>
    <p class="text-[#8A97AD] text-lg mt-6 max-w-2xl mx-auto">
      Scan every Binance Futures pair in real time. Detect breakouts, Smart Money Concepts,
      supply &amp; demand zones and high-confidence signals — all from pure technical analysis and mathematics.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4 mt-8">
      <a href="/register.php" class="btn btn-primary text-base px-7 py-3 glow-primary">Start Free Trial</a>
      <a href="#features" class="btn btn-ghost text-base px-7 py-3">Explore Features</a>
    </div>
  </div>


  <!-- Live market overview strip -->
  <div class="max-w-6xl mx-auto px-4 pb-16 relative">
    <div class="glass p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
      <div><div class="text-xs text-[#8A97AD]">Total Market Cap</div><div class="text-xl font-bold" x-text="ov.mcap"></div></div>
      <div><div class="text-xs text-[#8A97AD]">24h Volume</div><div class="text-xl font-bold" x-text="ov.vol"></div></div>
      <div><div class="text-xs text-[#8A97AD]">BTC Dominance</div><div class="text-xl font-bold" x-text="ov.btc"></div></div>
      <div><div class="text-xs text-[#8A97AD]">Active Signals</div><div class="text-xl font-bold text-success" x-text="ov.signals"></div></div>
    </div>
  </div>
</section>

<!-- SCANNER PREVIEW -->
<section id="scanner-preview" class="max-w-7xl mx-auto px-4 py-16">
  <div class="text-center mb-10">
    <h2 class="text-3xl font-bold">Live Market Scanner Preview</h2>
    <p class="text-[#8A97AD] mt-2">Top-rated setups detected this session, ranked Strong Buy → Strong Sell.</p>
  </div>
  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="tvp-table">
        <thead><tr><th>Symbol</th><th>Price</th><th>24h</th><th>Setup</th><th>Trend Score</th><th>Rating</th></tr></thead>
        <tbody>
          <template x-for="r in scanner" :key="r.symbol">
            <tr>
              <td class="font-semibold" x-text="r.symbol"></td>
              <td x-text="TVP.fmtPrice(r.price)"></td>
              <td :class="TVP.pctClass(r.change_pct)" x-text="TVP.pct(r.change_pct)"></td>
              <td class="text-[#8A97AD]" x-text="r.setup_type"></td>
              <td x-text="Number(r.trend_score).toFixed(1)"></td>
              <td><span class="badge" :class="'rating-'+r.rating" x-text="TVP.ratingLabel(r.rating)"></span></td>
            </tr>
          </template>
          <tr x-show="scanner.length===0"><td colspan="6" class="text-center text-[#8A97AD] py-8">Scanner warming up — sign up to see full live results.</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>


<!-- TECHNICAL FEATURES -->
<section id="features" class="max-w-7xl mx-auto px-4 py-16">
  <div class="text-center mb-12"><h2 class="text-3xl font-bold">Technical Analysis Engine</h2>
    <p class="text-[#8A97AD] mt-2">Every metric computed server-side from raw OHLCV data.</p></div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php
    $feats=[
      ['Indicator Suite','RSI, MACD, EMA 20/50/100/200, SMA, VWAP, Bollinger Bands, ATR, Stochastic RSI, OBV and Volume Profile — all live.'],
      ['Market Structure','Automatic HH / HL / LH / LL labelling with uptrend, downtrend and consolidation detection plus a numeric trend score.'],
      ['Supply &amp; Demand','Algorithmic zone detection with fresh / tested / mitigated states and historical zone storage.'],
      ['Multi-Timeframe Scan','1m, 5m, 15m and 1h scans across all Binance Futures pairs every minute via cron.'],
      ['Setup Detection','Breakouts, reversals, pullbacks, range breaks, volume surges, volatility expansion and liquidity sweeps.'],
      ['Weighted Signals','Confidence scored 0–100 from trend, volume, RSI, structure and S/D — never from AI.'],
    ];
    foreach($feats as $f): ?>
    <div class="card p-6 hover:border-blue-500/40 transition">
      <div class="w-10 h-10 rounded-lg bg-blue-500/15 text-blue-400 flex items-center justify-center mb-4">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 18l5-5 4 4 7-8"/></svg>
      </div>
      <h3 class="font-semibold text-lg mb-2"><?= $f[0] ?></h3>
      <p class="text-sm text-[#8A97AD]"><?= $f[1] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- SMC -->
<section id="smc" class="bg-[#0F1623] border-y border-[#1E2A3D]">
  <div class="max-w-7xl mx-auto px-4 py-16">
    <div class="text-center mb-12"><h2 class="text-3xl font-bold">Smart Money Concepts</h2>
      <p class="text-[#8A97AD] mt-2">Institutional price-action logic, detected mathematically.</p></div>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <?php foreach(['BOS','CHOCH','Liquidity Sweeps','Equal Highs/Lows','Fair Value Gaps','Order Blocks','Breaker Blocks','Premium Zones','Discount Zones','Mitigation'] as $s): ?>
      <div class="card-2 p-4 text-center"><div class="text-sm font-semibold"><?= $s ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<!-- SIGNALS PREVIEW -->
<section id="signals" class="max-w-7xl mx-auto px-4 py-16">
  <div class="text-center mb-10"><h2 class="text-3xl font-bold">High-Confidence Signals</h2>
    <p class="text-[#8A97AD] mt-2">Scalping, intraday and swing — with entry, stop loss, three targets and a transparent confidence score.</p></div>
  <div class="grid md:grid-cols-3 gap-5">
    <template x-for="s in signals" :key="s.id">
      <div class="card p-5">
        <div class="flex items-center justify-between mb-3">
          <span class="font-bold text-lg" x-text="s.symbol"></span>
          <span class="badge" :class="s.direction==='long'?'rating-strong_buy':'rating-strong_sell'" x-text="s.direction.toUpperCase()"></span>
        </div>
        <div class="grid grid-cols-2 gap-y-1 text-sm">
          <span class="text-[#8A97AD]">Entry</span><span class="text-right" x-text="TVP.fmtPrice(s.entry)"></span>
          <span class="text-[#8A97AD]">Stop</span><span class="text-right dir-short" x-text="TVP.fmtPrice(s.stop_loss)"></span>
          <span class="text-[#8A97AD]">TP1 / TP2 / TP3</span><span class="text-right dir-long" x-text="TVP.fmtPrice(s.tp1)+' / '+TVP.fmtPrice(s.tp2)+' / '+TVP.fmtPrice(s.tp3)"></span>
          <span class="text-[#8A97AD]">R:R</span><span class="text-right" x-text="s.risk_reward"></span>
        </div>
        <div class="mt-3"><div class="flex justify-between text-xs mb-1"><span class="text-[#8A97AD]">Confidence</span><span x-text="s.confidence+'%'"></span></div>
          <div class="h-2 bg-[#0B0F19] rounded-full overflow-hidden"><div class="h-full bg-gradient-to-r from-blue-500 to-violet-500" :style="'width:'+s.confidence+'%'"></div></div></div>
      </div>
    </template>
    <div x-show="signals.length===0" class="md:col-span-3 text-center text-[#8A97AD] py-10 card">Signals generate continuously from live data. <a href="/register.php" class="text-blue-400">Create an account</a> to view them.</div>
  </div>
</section>

<!-- PRICING -->
<section id="pricing" class="bg-[#0F1623] border-y border-[#1E2A3D]">
  <div class="max-w-6xl mx-auto px-4 py-16">
    <div class="text-center mb-12"><h2 class="text-3xl font-bold">Simple, Transparent Pricing</h2>
      <p class="text-[#8A97AD] mt-2">Pay with USDT (BEP20). No card required.</p></div>
    <div class="grid md:grid-cols-3 gap-6">
      <template x-for="p in plans" :key="p.id">
        <div class="card p-6 flex flex-col" :class="p.code==='pro'?'border-blue-500/60 glow-primary':''">
          <div class="text-sm uppercase tracking-widest" :class="{free:'text-[#8A97AD]',pro:'text-blue-400',elite:'text-violet-400'}[p.code]" x-text="p.name"></div>
          <div class="mt-3 text-4xl font-extrabold"><span x-text="p.price_usdt==0?'Free':('$'+Number(p.price_usdt).toFixed(0))"></span><span class="text-base text-[#8A97AD] font-normal" x-show="p.price_usdt>0">/mo</span></div>
          <ul class="mt-5 space-y-2 text-sm flex-1">
            <template x-for="f in p.features"><li class="flex gap-2"><span class="text-success">✓</span><span x-text="f"></span></li></template>
          </ul>
          <a :href="p.price_usdt==0?'/register.php':'/pricing/?plan='+p.code" class="btn btn-primary mt-6" x-text="p.price_usdt==0?'Get Started':'Choose '+p.name"></a>
        </div>
      </template>
    </div>
  </div>
</section>


<!-- TESTIMONIALS -->
<section class="max-w-7xl mx-auto px-4 py-16">
  <div class="text-center mb-10"><h2 class="text-3xl font-bold">Trusted by Active Traders</h2></div>
  <div class="grid md:grid-cols-3 gap-5">
    <?php
    $tnames=[['Daniel R.','Futures Trader'],['Aisha K.','Swing Trader'],['Marcus L.','Day Trader']];
    $tquotes=[
      'The scanner surfaces clean setups I would have missed. Everything is rule-based, which I trust far more than black-box AI calls.',
      'Supply &amp; demand zones plus the SMC dashboard replaced three separate tools for me. The confidence score keeps me disciplined.',
      'Multi-timeframe scanning on every Binance pair, with a proper trade journal and portfolio tracker. Excellent value in USDT.'];
    foreach($tnames as $i=>$t): ?>
    <div class="card p-6">
      <div class="text-yellow-400 mb-3">★★★★★</div>
      <p class="text-sm text-[#C7D0DE]"><?= $tquotes[$i] ?></p>
      <div class="mt-4 flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-bold"><?= $t[0][0] ?></div>
        <div><div class="text-sm font-semibold"><?= $t[0] ?></div><div class="text-xs text-[#8A97AD]"><?= $t[1] ?></div></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- FAQ -->
<section id="faq" class="max-w-3xl mx-auto px-4 py-16" x-data="{open:0}">
  <div class="text-center mb-10"><h2 class="text-3xl font-bold">Frequently Asked Questions</h2></div>
  <?php
  $faqs=[
    ['Do you use AI to generate signals?','No. Every scan, zone, structure read and signal is produced from live market data using technical indicators and mathematical rules. There is no AI, LLM or generated analysis anywhere in the platform.'],
    ['Where does the market data come from?','Free public sources: Binance Spot &amp; Futures APIs for crypto, plus Yahoo Finance and CoinGecko for broader markets and metrics.'],
    ['How do payments work?','You pay manually in USDT (BEP20). Submit your TXID and screenshot; an admin verifies it and your subscription activates automatically.'],
    ['Can I run this on shared cPanel hosting?','Yes. The entire stack is PHP 8.2+ and MySQL 8 with no build step, Node, or Docker required. Upload, import the SQL, edit config.php and launch.'],
    ['How is the confidence score calculated?','A weighted sum of trend (25%), volume (20%), RSI (15%), structure (20%) and supply/demand (20%), normalised to 0–100.'],
  ];
  foreach($faqs as $i=>$f): ?>
  <div class="card mb-3 overflow-hidden">
    <button @click="open===<?= $i ?>?open=-1:open=<?= $i ?>" class="w-full flex justify-between items-center p-4 text-left">
      <span class="font-medium"><?= $f[0] ?></span><span x-text="open===<?= $i ?>?'−':'+'" class="text-blue-400 text-xl"></span>
    </button>
    <div x-show="open===<?= $i ?>" x-collapse class="px-4 pb-4 text-sm text-[#8A97AD]"><?= $f[1] ?></div>
  </div>
  <?php endforeach; ?>
</section>


<!-- CONTACT / CTA -->
<section id="contact" class="max-w-7xl mx-auto px-4 py-16">
  <div class="glass p-10 text-center relative overflow-hidden">
    <div class="hero-glow"></div>
    <h2 class="text-3xl font-bold relative">Start scanning the market like an institution</h2>
    <p class="text-[#8A97AD] mt-3 relative">Create a free account in seconds. Upgrade anytime with USDT.</p>
    <div class="mt-7 relative"><a href="/register.php" class="btn btn-primary px-8 py-3 text-base glow-primary">Start Free Trial</a></div>
    <p class="text-xs text-[#8A97AD] mt-6 relative">Questions? Email <a href="mailto:support@tradevision.pro" class="text-blue-400">support@tradevision.pro</a></p>
  </div>
</section>

<!-- FOOTER -->
<footer class="border-t border-[#1E2A3D] bg-[#0F1623]">
  <div class="max-w-7xl mx-auto px-4 py-10 grid md:grid-cols-4 gap-8 text-sm">
    <div>
      <div class="flex items-center gap-2 mb-3"><div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-extrabold">T</div><span class="font-bold">TradeVision Pro</span></div>
      <p class="text-[#8A97AD]">Real-time scanning and rule-based trading signals from live market data.</p>
    </div>
    <div><div class="font-semibold mb-3">Product</div><ul class="space-y-2 text-[#8A97AD]"><li><a href="#features" class="hover:text-white">Features</a></li><li><a href="#pricing" class="hover:text-white">Pricing</a></li><li><a href="#smc" class="hover:text-white">Smart Money</a></li></ul></div>
    <div><div class="font-semibold mb-3">Account</div><ul class="space-y-2 text-[#8A97AD]"><li><a href="/login.php" class="hover:text-white">Sign In</a></li><li><a href="/register.php" class="hover:text-white">Register</a></li></ul></div>
    <div><div class="font-semibold mb-3">Legal</div><p class="text-[#8A97AD] text-xs">Trading involves substantial risk. TradeVision Pro provides analytical tools only and not financial advice. Past performance does not guarantee future results.</p></div>
  </div>
  <div class="border-t border-[#1E2A3D] py-4 text-center text-xs text-[#8A97AD]">© <?= date('Y') ?> TradeVision Pro. All rights reserved.</div>
</footer>

<script>
function landing(){
  return {
    ov:{mcap:'--',vol:'--',btc:'--',signals:'--'}, scanner:[], signals:[], plans:[],
    async init(){
      try{ this.plans = await TVP.get('plans'); }catch(e){}
      try{ const o=await TVP.get('market/overview');
        this.ov.mcap = o.global.total_market_cap_usd? '$'+(o.global.total_market_cap_usd/1e12).toFixed(2)+'T':'--';
        this.ov.vol  = o.global.total_volume_usd? '$'+(o.global.total_volume_usd/1e9).toFixed(1)+'B':'--';
        this.ov.btc  = o.global.btc_dominance? o.global.btc_dominance.toFixed(1)+'%':'--';
        this.ov.signals = o.active_signals ?? 0;
      }catch(e){}
      try{ this.scanner = (await TVP.get('scanner?timeframe=15m')).slice(0,8); }catch(e){}
      try{ this.signals = (await TVP.get('signals')).slice(0,3); }catch(e){}
    }
  };
}
</script>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
