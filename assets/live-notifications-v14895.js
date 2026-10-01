(function(){
  'use strict';
  var root=document.getElementById('mrLiveNotifications');
  if(!root||!root.dataset.endpoint)return;
  var afterId=Math.max(0,parseInt(root.dataset.afterId||'0',10)||0);
  var inFlight=false;
  var pollingEnabled=root.dataset.enabled==='1';
  var initialPoll=true;
  var pollTimer=null;

  function stopPolling(){
    pollingEnabled=false;
    if(pollTimer!==null)window.clearInterval(pollTimer);
    root.remove();
  }

  function playSound(){
    var AudioContext=window.AudioContext||window.webkitAudioContext;
    if(!AudioContext)return;
    try{
      var context=new AudioContext();
      var oscillator=context.createOscillator();
      var gain=context.createGain();
      oscillator.type='sine';
      oscillator.frequency.value=740;
      gain.gain.setValueAtTime(0.0001,context.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.07,context.currentTime+0.015);
      gain.gain.exponentialRampToValueAtTime(0.0001,context.currentTime+0.16);
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start();
      oscillator.stop(context.currentTime+0.17);
      oscillator.onended=function(){context.close();};
    }catch(e){}
  }

  function closeToast(toast){
    if(!toast||!toast.isConnected)return;
    toast.classList.add('is-leaving');
    window.setTimeout(function(){toast.remove();},180);
  }

  function showToast(item,desktopEnabled,notifyDevice){
    var toast=document.createElement('section');
    toast.className='mr-live-notification';
    toast.setAttribute('role','status');
    var head=document.createElement('div');
    head.className='mr-live-notification-head';
    var mark=document.createElement('span');
    mark.className='mr-live-notification-mark';
    mark.setAttribute('aria-hidden','true');
    mark.textContent='!';
    var title=document.createElement('b');
    title.textContent=item.type==='reservation_new'?'มีคำขอจองโต๊ะใหม่':'แจ้งเตือนใหม่';
    head.append(mark,title);
    var message=document.createElement('p');
    message.textContent=item.message||'มีรายการใหม่';
    toast.append(head,message);
    if(item.href){
      var link=document.createElement('a');
      link.href=item.href;
      link.className='mr-live-notification-link';
      link.textContent='เปิดรายการจอง';
      toast.append(link);
    }else if(!item.test){
      var note=document.createElement('small');
      note.textContent='คุณได้รับแจ้งในฐานะผู้เกี่ยวข้อง';
      toast.append(note);
    }
    var acknowledge=document.createElement('button');
    acknowledge.type='button';
    acknowledge.className='mr-live-notification-ack';
    acknowledge.textContent=item.test?'ปิดตัวอย่าง':'รับทราบ';
    acknowledge.addEventListener('click',async function(){
      if(item.test){closeToast(toast);return;}
      acknowledge.disabled=true;
      try{
        var form=new URLSearchParams();
        form.set('csrf',root.dataset.csrf||'');
        form.set('notification_id',String(item.id));
        var response=await fetch(root.dataset.ackEndpoint,{
          method:'POST',
          credentials:'same-origin',
          cache:'no-store',
          headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
          body:form.toString()
        });
        var result=await response.json();
        if(!response.ok||!result.ok)throw new Error('ack_failed');
        closeToast(toast);
      }catch(e){
        acknowledge.disabled=false;
        var error=toast.querySelector('.mr-live-notification-error');
        if(!error){error=document.createElement('small');error.className='mr-live-notification-error';toast.append(error);}
        error.textContent='รับทราบไม่สำเร็จ กรุณาตรวจการเชื่อมต่อแล้วลองอีกครั้ง';
      }
    });
    toast.append(acknowledge);
    root.append(toast);
    if(notifyDevice&&root.dataset.sound==='1')playSound();
    if(item.test)window.setTimeout(function(){closeToast(toast);},12000);

    if(notifyDevice&&desktopEnabled&&'Notification'in window&&Notification.permission==='granted'&&document.visibilityState!=='visible'){
      try{
        var notice=new Notification(title.textContent,{body:message.textContent,tag:'mrbar-'+item.id,renotify:false});
        notice.onclick=function(){window.focus();if(item.href)window.location.href=item.href;notice.close();};
      }catch(e){}
    }
  }

  async function poll(){
    if(!pollingEnabled||inFlight)return;
    if(document.visibilityState==='hidden'&&!(root.dataset.desktop==='1'&&'Notification'in window&&Notification.permission==='granted'))return;
    inFlight=true;
    try{
      var response=await fetch(root.dataset.endpoint+'?after_id='+encodeURIComponent(afterId),{
        credentials:'same-origin',
        cache:'no-store',
        headers:{'Accept':'application/json'}
      });
      if(response.status===401){stopPolling();return;}
      if(!response.ok)return;
      var data=await response.json();
      if(!data.enabled){stopPolling();return;}
      root.dataset.desktop=data.desktop?'1':'0';
      root.dataset.sound=data.sound?'1':'0';
      (Array.isArray(data.items)?data.items:[]).forEach(function(item){
        var id=parseInt(item.id||'0',10)||0;
        if(id<=afterId)return;
        afterId=id;
        showToast(item,!!data.desktop,!initialPoll);
      });
      initialPoll=false;
    }catch(e){
      // A temporary network error should not disrupt the page; the next poll retries.
    }finally{
      inFlight=false;
    }
  }

  if(pollingEnabled){
    pollTimer=window.setInterval(poll,12000);
    window.addEventListener('focus',poll);
    document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible')poll();});
    poll();
  }

  document.querySelectorAll('[data-enable-browser-notifications]').forEach(function(button){
    button.addEventListener('click',async function(){
      if(!('Notification'in window)){
        button.textContent='อุปกรณ์นี้ไม่รองรับ';
        return;
      }
      if(Notification.permission==='granted'){
        button.textContent='อนุญาตแล้วบนอุปกรณ์นี้';
        return;
      }
      if(Notification.permission==='denied'){
        button.textContent='ถูกปิดใน Browser Settings';
        return;
      }
      try{
        var permission=await Notification.requestPermission();
        button.textContent=permission==='granted'?'อนุญาตแล้วบนอุปกรณ์นี้':'ยังไม่ได้อนุญาต';
      }catch(e){
        button.textContent='Browser ไม่อนุญาตให้ขอสิทธิ์';
      }
    });
  });

  document.querySelectorAll('[data-test-live-notification]').forEach(function(button){
    button.addEventListener('click',function(){
      showToast({id:'test',type:'reservation_new',message:'ตัวอย่าง Popup แจ้งเตือนคำขอจองใหม่',test:true},false,false);
    });
  });
})();
