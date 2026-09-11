(function(){
'use strict';
const imgs=[...document.querySelectorAll('img[data-pr-smart-crop]')];
if(!imgs.length)return;
const clamp=(v,min,max)=>Math.max(min,Math.min(max,v));
function fallbackFocus(img){
  const w=img.naturalWidth||1,h=img.naturalHeight||1,ratio=w/h;
  let x=50,y=18;
  if(ratio>=1.35){y=28;}
  else if(ratio>=1.0){y=24;}
  else if(ratio>=0.78){y=20;}
  else if(ratio>=0.58){y=17;}
  else{y=14;}
  img.style.objectPosition=x+'% '+y+'%';
  img.dataset.cropMode='ratio';
}
async function faceFocus(img){
  if(typeof window.FaceDetector!=='function')return false;
  try{
    const detector=new FaceDetector({fastMode:true,maxDetectedFaces:3});
    const faces=await detector.detect(img);
    if(!faces||!faces.length)return false;
    let best=faces[0],bestArea=0;
    faces.forEach(f=>{const b=f.boundingBox||{};const area=(b.width||0)*(b.height||0);if(area>bestArea){best=f;bestArea=area;}});
    const b=best.boundingBox;if(!b)return false;
    const w=img.naturalWidth||1,h=img.naturalHeight||1;
    const cx=((b.x+b.width/2)/w)*100;
    const cy=((b.y+b.height/2)/h)*100;
    const x=clamp(cx,24,76);
    // Keep the face in the upper third so the 2:3 portrait preserves more body.
    const y=clamp(cy-8,14,38);
    img.style.objectPosition=x.toFixed(1)+'% '+y.toFixed(1)+'%';
    img.dataset.cropMode='face';
    return true;
  }catch(e){return false;}
}
async function smartCrop(img){
  fallbackFocus(img);
  await faceFocus(img);
}
imgs.forEach(img=>{
  if(img.complete&&img.naturalWidth){smartCrop(img);}else{
    img.addEventListener('load',()=>smartCrop(img),{once:true});
  }
});
})();
