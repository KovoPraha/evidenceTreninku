(function(){
    'use strict';
    const vsechny=JSON.parse(document.getElementById('bar-screen-products').textContent);
    const kontejner=document.getElementById('produkty');
    const pocet=Math.min(3,vsechny.length);
    let predchozi=[];
    let casovac=null;

    function zamichej(pole){
        const vysledek=pole.slice();
        for(let i=vysledek.length-1;i>0;i--){
            const j=Math.floor(Math.random()*(i+1));
            [vysledek[i],vysledek[j]]=[vysledek[j],vysledek[i]];
        }
        return vysledek;
    }

    function dalsi(){
        const minule=new Set(predchozi.map(produkt=>produkt.id));
        const bezOpakovani=vsechny.filter(produkt=>!minule.has(produkt.id));
        const zdroj=bezOpakovani.length>=pocet?bezOpakovani:vsechny;
        return zamichej(zdroj).slice(0,pocet);
    }

    function prvek(nazev,trida,text){
        const element=document.createElement(nazev);
        if(trida)element.className=trida;
        if(text!==undefined)element.textContent=text;
        return element;
    }

    function karta(produkt){
        const clanek=prvek('article','produkt k'+produkt.barva);
        clanek.dataset.produktId=String(produkt.id);
        const foto=prvek('div','foto'+(produkt.obrazek?'':' bez-fotky'));
        if(produkt.obrazek){
            const img=document.createElement('img');
            img.src=produkt.obrazek;
            img.alt=produkt.nazev;
            img.decoding='async';
            foto.appendChild(img);
        }else{
            foto.textContent='KOVO BAR';
        }
        clanek.appendChild(foto);
        if(produkt.vyprodano)clanek.appendChild(prvek('span','stav','Vyprodáno'));

        const telo=prvek('div','telo');
        telo.appendChild(prvek('div','kategorie',produkt.kategorie));
        const hlavni=prvek('div','hlavni');
        hlavni.appendChild(prvek('div','nazev',produkt.nazev));
        hlavni.appendChild(prvek('div','cena',produkt.cena));
        telo.appendChild(hlavni);
        if(produkt.popis)telo.appendChild(prvek('p','popis',produkt.popis));
        clanek.appendChild(telo);
        return clanek;
    }

    function vykresli(skupina){
        kontejner.replaceChildren(...skupina.map(karta));
        predchozi=skupina;
        requestAnimationFrame(()=>requestAnimationFrame(()=>kontejner.classList.add('viditelne')));
        casovac=setTimeout(vymen,10000);
    }

    function vymen(){
        clearTimeout(casovac);
        kontejner.classList.remove('viditelne');
        setTimeout(()=>vykresli(dalsi()),720);
    }

    vykresli(dalsi());
    setTimeout(()=>{if(!document.hidden)location.reload()},Number(document.body.dataset.refreshSeconds)*1000);
})();
