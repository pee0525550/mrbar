/* MR BAR Zone Studio — v1.29.10 Hotspot mode controls */
(function(){'use strict';
var stage=document.querySelector('[data-zs-stage]'),guide=document.querySelector('[data-zs-hotspot-guide]'),mode=document.querySelector('[data-zs-layout-mode]'),quick=document.querySelector('[data-zs-quick-hotspot]');
function applyMode(){var on=mode&&mode.value==='image_hotspot';if(stage)stage.classList.toggle('is-hotspot-mode',on);if(guide)guide.classList.toggle('is-active',on);var opacity=document.querySelector('[data-zs-bg-opacity]'),label=document.querySelector('[data-bg-opacity-label]');if(on&&opacity){opacity.value='100';opacity.dispatchEvent(new Event('input',{bubbles:true}));if(label)label.textContent='100%';}}
if(mode){mode.addEventListener('change',applyMode);applyMode();}
if(quick)quick.addEventListener('click',function(){if(mode&&mode.value!=='image_hotspot'){mode.value='image_hotspot';applyMode();}var preset=document.querySelector('[data-zs-preset="table_rect_4"]');if(!preset)return;preset.click();setTimeout(function(){var selected=document.querySelector('[data-zs-items] .zs-item.selected');if(selected)selected.dispatchEvent(new MouseEvent('dblclick',{bubbles:true}));},40);});
})();