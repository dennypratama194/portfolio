/* Page view + session duration tracker */
(function(page, slug){
  var startTime = Date.now();
  var viewId    = null;
  var sent      = false;

  /* ── Fire start event ── */
  fetch('/api/track.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({page: page, slug: slug || null})
  })
  .then(function(r){ return r.json(); })
  .then(function(d){ if (d && d.view_id) viewId = d.view_id; })
  .catch(function(){});

  /* ── Send duration on leave ── */
  function sendEnd() {
    if (sent || !viewId) return;
    sent = true;
    var duration = Math.round((Date.now() - startTime) / 1000);
    if (duration < 1) return;
    var payload = JSON.stringify({action:'end', view_id: viewId, duration: duration});
    if (navigator.sendBeacon) {
      navigator.sendBeacon('/api/track.php', new Blob([payload], {type:'application/json'}));
    } else {
      fetch('/api/track.php', {method:'POST', keepalive:true,
        headers:{'Content-Type':'application/json'}, body: payload}).catch(function(){});
    }
  }

  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'hidden') sendEnd();
  });
  window.addEventListener('pagehide', sendEnd);
})(PAGE, SLUG);
