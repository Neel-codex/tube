<?php $pageTitle='Sign In - TradeVision Pro'; require __DIR__.'/includes/partials/head.php'; ?>
<body class="bg-bg text-[#E5EAF2] bg-grid min-h-screen flex items-center justify-center p-4" x-data="loginPage()">
<div class="hero-glow"></div>
<div class="w-full max-w-md relative">
  <a href="/" class="flex items-center justify-center gap-2 mb-6">
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-extrabold">T</div>
    <span class="font-bold text-xl">TradeVision <span class="text-blue-400">Pro</span></span>
  </a>
  <div class="glass p-7">
    <h1 class="text-2xl font-bold mb-1">Welcome back</h1>
    <p class="text-sm text-[#8A97AD] mb-6">Sign in to your trading terminal.</p>
    <form @submit.prevent="submit()">
      <label class="lbl">Email</label>
      <input type="email" class="input mb-4" x-model="email" required autocomplete="email">
      <label class="lbl">Password</label>
      <input type="password" class="input mb-5" x-model="password" required autocomplete="current-password">
      <button class="btn btn-primary w-full" :disabled="loading" x-text="loading?'Signing in...':'Sign In'"></button>
    </form>
    <p class="text-sm text-[#8A97AD] mt-5 text-center">No account? <a href="/register.php" class="text-blue-400">Create one free</a></p>
  </div>
</div>
<script>
function loginPage(){
  return { email:'', password:'', loading:false,
    init(){ if(TVP.isAuthed()) location.href='/dashboard/'; },
    async submit(){
      this.loading=true;
      try{
        const d = await TVP.post('auth/login',{email:this.email,password:this.password});
        TVP.setToken(d.token); TVP.toast('Welcome back!','success');
        location.href = d.user.role==='admin' ? '/admin/' : '/dashboard/';
      }catch(e){ TVP.toast(e.message,'error'); }
      this.loading=false;
    }
  };
}
</script>
</body></html>
