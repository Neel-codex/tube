    </main>
  </div>
</div>
<script>
function appShell(){
  return {
    user:{}, notifications:[], unread:0, mobileNav:false,
    get planClass(){ return {free:'text-[#8A97AD]',pro:'text-blue-400',elite:'text-violet-400'}[this.user.plan_code]||'text-[#8A97AD]'; },
    async init(){
      this.user = await TVP.requireAuth() || {};
      TVP.enableBrowserNotifications();
      TVP.startNotificationPolling((d)=>{ this.notifications=d.notifications||[]; this.unread=d.unread||0; });
    },
    async markAllRead(){ try{ await TVP.post('notifications/read-all'); this.unread=0; this.notifications.forEach(n=>n.is_read=1);}catch(e){} }
  };
}
</script>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
