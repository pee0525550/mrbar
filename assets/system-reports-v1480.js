(function(){
  var form=document.querySelector('[data-clear-report-form]');
  if(!form)return;
  form.addEventListener('submit',function(ev){
    var scope=form.querySelector('[name="scope"]');
    var selected=scope?scope.value:'';
    var steps=[
      'ยืนยันชั้นที่ 1: คุณกำลังใช้ Hidden Clear Report Tool สำหรับ Super Admin',
      'ยืนยันชั้นที่ 2: ระบบจะทำ Soft Clear ตาม scope "'+selected+'" และบันทึก Audit Log',
      'ยืนยันชั้นที่ 3: ตรวจสอบแล้วว่าอยู่ branch และ domain ทดสอบที่ต้องการ'
    ];
    for(var i=0;i<steps.length;i++){
      if(!window.confirm(steps[i])){
        ev.preventDefault();
        return false;
      }
    }
    return true;
  });
})();
