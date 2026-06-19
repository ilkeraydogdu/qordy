<?php $base = defined('BASE_URL') ? BASE_URL : ''; ?>
</main>

<!-- ========= Newsletter CTA ========= -->
<section class="border-t border-white/10 mt-16 sm:mt-24">
  <div class="container py-14 sm:py-20 text-center">
    <h2 class="font-display text-white text-3xl sm:text-4xl font-semibold">Restoranınızı bir adım öne taşıyın.</h2>
    <p class="mt-4 text-slate-300 max-w-xl mx-auto">
      Qordy ile QR menü, POS, mutfak ekranı ve ödeme entegrasyonlarını tek panelden yönetin.
      14 gün ücretsiz — kart bilgisi gerekmez.
    </p>
    <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
      <a href="<?= $base ?>/register" class="btn-cta">
        Ücretsiz Başla
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
      <a href="<?= $base ?>/#pricing" class="inline-flex items-center gap-2 text-sm font-medium text-slate-200 hover:text-white border border-white/15 hover:border-white/30 rounded-full px-5 py-3 transition-colors">
        Fiyatları İncele
      </a>
    </div>
  </div>
</section>

<!-- ========= Footer ========= -->
<footer class="border-t border-white/10 bg-ink-900/60 mt-0">
  <div class="container py-10 sm:py-14 grid gap-10 md:grid-cols-4">
    <div class="md:col-span-2">
      <div class="flex items-center gap-2 text-white font-display text-xl font-semibold">
        <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
          <circle cx="16" cy="16" r="14" stroke="url(#qg2)" stroke-width="2.25"/>
          <path d="M22 22 L27 27" stroke="url(#qg2)" stroke-width="2.5" stroke-linecap="round"/>
          <defs><linearGradient id="qg2" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#2B7AC9"/><stop offset="1" stop-color="#3B82F6"/></linearGradient></defs>
        </svg>
        Qordy
      </div>
      <p class="mt-4 text-slate-400 max-w-sm">
        Büyüyen restoranlar için kapsamlı yönetim çözümü. QR menü, POS, mutfak ekranı ve
        ödeme entegrasyonlarını tek bir platformda birleştiriyoruz.
      </p>
    </div>
    <div>
      <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Ürün</h3>
      <ul class="space-y-2 text-slate-400 text-sm">
        <li><a href="<?= $base ?>/#features" class="hover:text-white transition-colors">Özellikler</a></li>
        <li><a href="<?= $base ?>/#pricing" class="hover:text-white transition-colors">Fiyatlar</a></li>
        <li><a href="<?= $base ?>/blog" class="hover:text-white transition-colors">Blog</a></li>
        <li><a href="<?= $base ?>/register" class="hover:text-white transition-colors">Ücretsiz Dene</a></li>
      </ul>
    </div>
    <div>
      <h3 class="text-white font-semibold mb-4 text-sm uppercase tracking-wider">Destek</h3>
      <ul class="space-y-2 text-slate-400 text-sm">
        <li><a href="<?= $base ?>/login" class="hover:text-white transition-colors">Giriş</a></li>
        <li><a href="<?= $base ?>/#contact" class="hover:text-white transition-colors">İletişim</a></li>
        <li><a href="<?= $base ?>/sitemap.xml" class="hover:text-white transition-colors">Sitemap</a></li>
        <li><a href="<?= $base ?>/robots.txt" class="hover:text-white transition-colors">Robots</a></li>
      </ul>
    </div>
  </div>
  <div class="border-t border-white/10">
    <div class="container py-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
      <p>&copy; <?= date('Y') ?> Qordy. Tüm hakları saklıdır.</p>
      <p class="flex items-center gap-4">
        <span>Hazırlayan: AI-powered content engine</span>
      </p>
    </div>
  </div>
</footer>

<!-- ========= JS: sadece gerekenler, defer ========= -->
<script defer>
// Server-rendered fallback'i Soro widget hydrate olduğunda gizle.
(function(){
  var mount=document.getElementById('soro-blog');if(!mount)return;
  var tries=0,t=setInterval(function(){
    if(mount.childElementCount>0){
      document.querySelectorAll('.soro-fallback').forEach(function(e){e.style.display='none'});
      clearInterval(t);
    } else if(++tries>60){clearInterval(t)}
  },500);
})();
// Paylaşım butonları — popup aç, analytics'e event gönder, pano kopyala.
(function(){
  document.querySelectorAll('[data-share]').forEach(function(el){
    el.addEventListener('click',function(e){
      var kind=el.getAttribute('data-share');
      var url=el.getAttribute('data-url')||location.href;
      var title=el.getAttribute('data-title')||document.title;
      if(kind==='copy'){
        e.preventDefault();
        if(navigator.clipboard){navigator.clipboard.writeText(url)}
        var old=el.textContent; el.textContent='Kopyalandı ✓';
        setTimeout(function(){el.textContent=old},1400);
        try{gtag('event','share',{method:'copy_link',content_id:url})}catch(_){}
        return;
      }
      var share={
        twitter:'https://twitter.com/intent/tweet?text='+encodeURIComponent(title)+'&url='+encodeURIComponent(url),
        facebook:'https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(url),
        linkedin:'https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(url),
        whatsapp:'https://api.whatsapp.com/send?text='+encodeURIComponent(title+' — '+url),
        telegram:'https://t.me/share/url?url='+encodeURIComponent(url)+'&text='+encodeURIComponent(title)
      };
      if(share[kind]){
        e.preventDefault();
        window.open(share[kind],'share_'+kind,'width=640,height=560,menubar=no,toolbar=no');
        try{gtag('event','share',{method:kind,content_id:url})}catch(_){}
      }
    });
  });
})();
// Reading progress bar
(function(){
  var bar=document.getElementById('reading-progress');if(!bar)return;
  var doc=document.documentElement;
  function upd(){var h=doc.scrollHeight-doc.clientHeight;var s=h>0?(doc.scrollTop/h)*100:0;bar.style.width=s+'%'}
  addEventListener('scroll',upd,{passive:true});upd();
})();
</script>

</body>
</html>
