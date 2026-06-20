<?php $pageTitle='Create Account - TradeVision Pro'; require __DIR__.'/includes/partials/head.php'; ?>
<body class="bg-bg text-[#E5EAF2] bg-grid min-h-screen flex items-center justify-center p-4" x-data="regPage()">
<div class="hero-glow"></div>
<div class="w-full max-w-md relative">
  <a href="/" class="flex items-center justify-center gap-2 mb-6">
    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center font-extrabold">T</div>
    <span class="font-bold text-xl">TradeVision <span class="text-blue-400">Pro</span></span>
  </a>
  <div class="glass p-7">
    <h1 class="text-2xl font-bold mb-1">Start your free trial</h1>
    <p class="text-sm text-[#8A97AD] mb-6">Create an account — no card required.</p>
    <form @submit.prevent="submit()">
      <label class="lbl">Full name</label>
      <input type="text" class="input mb-4" x-model="name" required autocomplete="name">
      <label class="lbl">Email</label>
      <input type="email" class="input mb-4" x-model="email" required autocomplete="email">
      <label class="lbl">Password (min 8 chars)</label>
      <input type="password" class="input mb-5" x-model="password" required minlength="8" autocomplete="new-password">
      <button class="btn btn-primary w-full" :disabled="loading" x-text="loading?'Creating...':'Create Free Account'"></button>
    </form>
    <p class="text-sm text-[#8A97AD] mt-5 text-center">Already have an account? <a href="/login.php" class="text-blue-400">Sign in</a></p>
  </div>
</div>
<script>
function regPage(){
  return { name:'', email:'', password:'', loading:false,
    init(){ if(TVP.isAuthed()) location.href='/dashboard/'; },
    async submit(){
      this.loading=true;
      try{
        const d = await TVP.post('auth/register',{name:this.name,email:this.email,password:this.password});
        TVP.setToken(d.token); TVP.toast('Account created!','success'); location.href='/dashboard/';
      }catch(e){ TVP.toast(e.message,'error'); }
      this.loading=false;
    }
  };
}
</script>
</body></html>
