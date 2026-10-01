(() => {
  'use strict';
  const config = window.MRBAR_SUPERVISION;
  const modal = document.getElementById('employeeSupervisorModal');
  if (!config || !modal) return;
  const select = modal.querySelector('[data-supervisor-select]');
  const employeeId = modal.querySelector('[data-supervisor-employee-id]');
  const employeeLabel = modal.querySelector('[data-supervisor-employee]');
  let returnFocus = null;

  const close = () => { modal.hidden = true; returnFocus?.focus(); };
  const addApprovalAccess = (card, id) => {
    const panel = card.querySelector('[data-people-panel="account"]');
    const state = config.approval?.[id];
    if (!panel || !state || panel.querySelector('.emp-approval-access')) return;

    const form = document.createElement('form');
    form.method = 'post';
    form.className = 'emp-approval-access';
    form.dataset.loadingLabel = 'กำลังบันทึกสิทธิ์อนุมัติ';
    for (const [name, value] of [['csrf', config.csrf], ['action', 'set_employee_approval'], ['employee_id', id]]) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = name;
      hidden.value = String(value || '');
      form.append(hidden);
    }

    const head = document.createElement('div');
    head.className = 'emp-approval-access-copy';
    const title = document.createElement('b');
    title.textContent = 'สิทธิ์อนุมัติคำขอของทีม';
    const help = document.createElement('small');
    help.textContent = 'เปิดเพื่อให้พนักงานคนนี้รับคำขอลาและรายการเวลาจากลูกทีมที่กำหนดได้';
    head.append(title, help);

    const toggleLabel = document.createElement('label');
    toggleLabel.className = 'emp-approval-toggle';
    const toggle = document.createElement('input');
    toggle.type = 'checkbox';
    toggle.name = 'approval_enabled';
    toggle.value = '1';
    toggle.checked = Boolean(state.enabled);
    toggle.disabled = !state.has_login && !state.enabled;
    const status = document.createElement('span');
    status.textContent = toggle.checked ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
    toggle.addEventListener('change', () => { status.textContent = toggle.checked ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; });
    toggleLabel.append(toggle, status);

    const note = document.createElement('p');
    note.className = 'emp-approval-access-note';
    if (!state.has_login) note.textContent = state.enabled ? 'ไม่พบบัญชี Login ที่เปิดใช้งาน กรุณาตรวจการเชื่อมบัญชีก่อน' : 'ต้องเชื่อมบัญชี Login ที่เปิดใช้งานก่อน จึงจะเปิดสิทธิ์นี้ได้';
    else if (state.report_count > 0) note.textContent = `มีลูกทีมตรง ${state.report_count} คน · ต้องย้ายสายอนุมัติของลูกทีมก่อนปิดสิทธิ์`;
    else note.textContent = 'การตั้งค่านี้ใช้กับการอนุมัติผ่านสายทีม ไม่เปลี่ยนสิทธิ์ส่วนกลางในบัญชี เช่น HR/Admin';

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.textContent = 'บันทึกสิทธิ์อนุมัติ';
    submit.disabled = !state.has_login && !state.enabled;
    form.append(head, toggleLabel, note, submit);
    const panelHead = panel.querySelector('.account-panel-head');
    if (panelHead) panelHead.after(form);
    else panel.prepend(form);
  };
  const open = (card, button) => {
    const id = card.dataset.employeeId;
    const data = config.employees[id];
    if (!data) return;
    employeeId.value = id;
    employeeLabel.textContent = `${card.querySelector('.emp-title h2')?.textContent?.trim() || id} · ${card.querySelector('.emp-title>small')?.textContent?.trim() || ''}`;
    select.querySelectorAll('option[data-legacy]').forEach((option) => option.remove());
    if (data.supervisor_id && ![...select.options].some((option) => option.value === String(data.supervisor_id))) {
      const legacy = new Option(`${data.supervisor_name} · ตรวจสอบสถานะ`, String(data.supervisor_id));
      legacy.dataset.legacy = '1';
      select.add(legacy);
    }
    select.value = String(data.supervisor_id || 0);
    returnFocus = button;
    modal.hidden = false;
    select.focus();
  };

  document.querySelectorAll('.employee-card').forEach((card) => {
    const id = card.dataset.employeeId;
    const data = config.employees[id];
    if (!data) return;
    const strip = card.querySelector('.people-health-strip');
    if (strip) {
      const supervisor = document.createElement('span');
      supervisor.className = data.supervisor_id ? 'health-position' : 'health-warn';
      supervisor.textContent = `หัวหน้างาน: ${data.supervisor_name}`;
      const reports = document.createElement('span');
      reports.className = 'health-muted';
      reports.textContent = `ลูกทีมตรง ${data.report_count} คน`;
      strip.append(supervisor, reports);
    }
    if (config.canManage) addApprovalAccess(card, id);
    if (config.canManage) {
      const actions = card.querySelector('.emp-actions');
      if (!actions) return;
      actions.classList.add('has-supervisor-action');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'emp-supervisor-button';
      button.textContent = 'หัวหน้า / ทีม';
      button.addEventListener('click', () => open(card, button));
      actions.append(button);
    }
  });

  modal.querySelectorAll('[data-supervisor-close]').forEach((button) => button.addEventListener('click', close));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.hidden) close(); });
})();
