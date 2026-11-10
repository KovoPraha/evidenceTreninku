(function(){
    const obsah=document.getElementById('obsah');
    function hodiny(){document.getElementById('hodiny').textContent=new Intl.DateTimeFormat('cs-CZ',{hour:'2-digit',minute:'2-digit'}).format(new Date())}
    function prizpusob(){
        const zaklad=parseFloat(getComputedStyle(document.body).getPropertyValue('--zakladni-meritko'))||1;
        document.body.classList.remove('automaticky-bez-popisu');
        document.documentElement.style.setProperty('--meritko',String(zaklad));
        const sloupce=[...document.querySelectorAll('.sloupec')];
        if(!sloupce.length)return;
        const misto=Math.max(1,obsah.clientHeight-48);
        let nejvyssi=Math.max(...sloupce.map(s=>s.scrollHeight));
        if(zaklad*misto/nejvyssi<.68){
            document.body.classList.add('automaticky-bez-popisu');
            nejvyssi=Math.max(...sloupce.map(s=>s.scrollHeight));
        }
        if(nejvyssi>misto){document.documentElement.style.setProperty('--meritko',String(Math.max(.55,zaklad*misto/nejvyssi)))}
        if(window.parent!==window){window.parent.postMessage({typ:'bar-obrazovka',preteka:sloupce.some(s=>s.scrollHeight>obsah.clientHeight+2),meritko:parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--meritko'))},location.origin)}
    }
    hodiny();prizpusob();requestAnimationFrame(prizpusob);setTimeout(prizpusob,250);
    setInterval(hodiny,10000);
    setInterval(()=>{if(!document.hidden)location.reload()},Number(document.body.dataset.refreshSeconds)*1000);
    addEventListener('resize',prizpusob);
})();
