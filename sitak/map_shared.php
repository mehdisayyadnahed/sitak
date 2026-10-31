<script>
window.SiteMap=(function(){
  var RTL='https://cdn.map.ir/web-sdk/1.4.4/js/mapbox-gl-rtl-text-v0.2.0.js';
  var STYLE='https://map.ir/vector/styles/main/mapir-xyz-style.json';
  var pending=null;
  function loadSdk(cb){
    if(window.__mapSdkReady){cb();return}
    if(pending){pending.push(cb);return}
    pending=[cb];
    var css=document.createElement('link');css.rel='stylesheet';css.href='https://cdn.map.ir/web-sdk/1.4.4/css/mapp.min.css';document.head.appendChild(css);
    var files=['https://cdn.map.ir/web-sdk/1.4.4/js/jquery-3.2.1.min.js','https://cdn.map.ir/web-sdk/1.4.4/js/mapp.env.js','https://cdn.map.ir/web-sdk/1.4.4/js/mapp.min.js'];
    (function next(i){
      if(i>=files.length){window.__mapSdkReady=true;var q=pending;pending=null;q.forEach(function(f){f()});return}
      if(i===0&&window.jQuery){next(1);return}
      var sc=document.createElement('script');sc.src=files[i];sc.onload=function(){next(i+1)};sc.onerror=function(){pending=null};document.body.appendChild(sc);
    })(0);
  }
  function create(o){
    if(!window.mapboxgl)return null;
    if(!window.__rtlSet){window.__rtlSet=1;mapboxgl.setRTLTextPlugin(RTL,null,true);}
    var opts={container:o.container,style:STYLE,center:[o.lng,o.lat],zoom:o.zoom,accessToken:o.key,transformRequest:function(url){return {url:url,headers:{'x-api-key':o.key}}}};
    if(o.pixelRatio)opts.pixelRatio=o.pixelRatio;
    return new mapboxgl.Map(opts);
  }
  function pin(map,lat,lng){return new mapboxgl.Marker({color:'#e53935'}).setLngLat([lng,lat]).addTo(map)}
  function snapshot(o){return new Promise(function(res){
    if(!window.mapboxgl){res(null);return}
    loadSdk(function(){
      var box=document.createElement('div');box.style.cssText='position:fixed;left:-9999px;top:0;width:568px;height:200px';document.body.appendChild(box);
      var m=create({container:box,key:o.key,lat:o.lat,lng:o.lng,zoom:o.zoom||16,pixelRatio:2});
      var done=false;
      function cleanup(){try{m.remove()}catch(e){}box.remove()}
      function fin(){
        if(done)return;done=true;
        try{var url=m.getCanvas().toDataURL('image/png');var img=new Image();img.onload=function(){cleanup();res(img)};img.onerror=function(){cleanup();res(null)};img.src=url}
        catch(e){cleanup();res(null)}
      }
      m.once('idle',fin);
      setTimeout(fin,8000);
    });
  })}
  return {loadSdk:loadSdk,create:create,pin:pin,snapshot:snapshot};
})();
</script>
