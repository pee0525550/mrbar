# v1.49.17 - Auth, Booking & Notification Reliability
- Persist failed PIN attempts and the 15-minute lock inside a single database transaction; validate expiry and revocation before authenticating a trusted device.
- Refresh session permissions from the live account, revoke sessions after credential changes, and reject missing CSRF tokens in LINE endpoints.
- Keep real booking alerts visible until acknowledged, retain all unread alerts in a bounded scroll area, and stop polling after logout or disablement.
- Validate calendar dates, times, party limits and advance-booking policy; redirect successful customer submissions to private receipts and deduplicate replayed requests without resending LINE.
- Recheck booking/payment/table policies inside the write transaction, preserve committed slips on later errors, and require verified deposits before seating through Operations.
- Repair reservation action placement and deposit review layouts on mobile and intermediate desktop widths; release session locks early in read-only notification feeds.
- No database migration. Update pack base: v1.49.16.

# v1.49.16 - P1 Atomic Storage & Permission Fix
- Serialize shared database reads/writes with a stable lock and atomically replace the data file to prevent partial reads and lost concurrent updates.
- Remove world-writable `0777` permission fallbacks for customer media directories; retain least-privilege directory permissions.
- No database migration; existing data and booking flows remain unchanged.

# v1.49.15 - LINE Customer Booking Guard
- เปิดขั้นตอนจองผ่าน LIFF URL จริง และอ่าน flow / return_to จาก liff.state รองรับการคืนค่าของ LIFF และ PHP-FPM
- บังคับตรวจสถานะเพื่อน LINE OA ก่อนแสดงแบบฟอร์มและก่อนรับ POST; ต้องตรวจใหม่เมื่อสถานะเก่ากว่า 30 นาที
- ถ้าไม่ตั้งค่า OA, ปฏิเสธเพิ่มเพื่อน หรือ LIFF ตรวจสถานะไม่ได้ จะไม่สามารถส่งคำขอจองผ่านได้
- แสดงผลการส่งข้อความ LINE หลังรับคำจองจากผล API ที่บันทึกไว้; ไม่มี Database Migration

# v1.49.14 - Reservation Deposit Controls
- เพิ่มหน้าหลังบ้าน เงื่อนไขจอง & มัดจำ แยกตามสาขา พร้อมสวิตช์ปิดมัดจำฉุกเฉินโดยไม่ปิดรับจอง
- เพิ่มช่องทางไม่ชำระ / สแกนจ่ายแนบสลิป / แนบสลิปพร้อมเตรียมตรวจ API, คำนวณมัดจำต่อรายการหรือต่อคน, QR, รายละเอียดบัญชี และเงื่อนไขจอง
- เพิ่มอัปโหลดสลิปส่วนตัว, หน้าเปิดสลิปตามสิทธิ์สาขา และคิวให้พนักงานรับรอง/ปฏิเสธ โดยล็อกการยืนยันโต๊ะและรับลูกค้าจนกว่ามัดจำผ่าน
- ผล API ที่ไม่ครบยอด/ผู้รับ/ความไม่ซ้ำจะไม่ยืนยันอัตโนมัติ; ต้องติดตั้ง Provider adapter และ Environment Variables ก่อนใช้ API จริง
- ไม่มี Database Migration; ค่าเริ่มต้นคง Flow จองแบบไม่ชำระเงินเดิม

# v1.49.13 - LINE OA Broadcast
- เพิ่มเครื่องมือ Broadcast ข้อความถึงผู้ติดตาม LINE OA แยกจากปุ่มทดสอบส่งหา LINE พนักงาน
- เพิ่ม Preview, ตัวนับ 5,000 ตัวอักษร, คำเตือนโควตา และยืนยันสองชั้นก่อนส่ง
- จำกัดสิทธิ์ `settings.manage`, ป้องกันคำขอซ้ำด้วย LINE Retry Key และเว้นช่วงการส่งส่วนกลาง 60 วินาที
- บันทึก Audit เฉพาะผู้ส่ง ความยาวข้อความ และผลตอบกลับ โดยไม่เก็บเนื้อหาหรือ Channel Access Token
- ไม่มี Database Migration

# v1.49.12 - LINE Customer Auth Error Handling
- ดักข้อผิดพลาดระหว่างตรวจ Token ฝั่ง Server และตอบ JSON error แทนปล่อย response ว่าง
- หน้า LINE อ่าน response แบบป้องกันกรณี body ว่าง/ไม่ใช่ JSON และแสดง HTTP status ที่เป็นมิตร
- ไม่มี Database Migration

# v1.49.11 - LINE Customer Login Verification Fix
- ขอ ID Token หลังขั้นตรวจ/เพิ่มเพื่อน OA เพื่อใช้ Token ล่าสุดก่อนยืนยันกับ LINE
- ตรวจ Channel ID ของ LIFF เทียบกับ LINE Login Channel ID ก่อนส่ง Token
- แสดงสาเหตุยืนยันไม่ผ่านที่จำแนกได้ เช่น Channel ID ไม่ตรงหรือ Token หมดอายุ โดยไม่เปิดเผย Token
- ไม่มี Database Migration

# v1.49.10 - LINE Customer OA Friend Flow
- LINE Login ฝั่งลูกค้าจะตรวจสถานะเพื่อน OA และเรียกหน้าต่างเพิ่มเพื่อนผ่าน LIFF ก่อนกลับไปหน้าจอง
- เพิ่มช่องตั้งค่า LINE OA Basic ID หรือลิงก์เพิ่มเพื่อนในหลังบ้าน พร้อมตรวจโดเมนปลายทาง
- แสดงสถานะเพื่อนและลิงก์สำรองในหน้าจอง; ลูกค้ายังจองต่อได้หากข้ามการเพิ่มเพื่อน
- ไม่มี Database Migration

# v1.48.96 - Notification Acknowledgement Persistence Fix
- แก้ API “รับทราบ” ให้บันทึก read_by ลงรายการ Notification จริง ป้องกัน Popup กลับมาหลังรีเฟรช
- ยืนยันสิทธิ์ผู้รับก่อนบันทึก และเก็บสถานะรับทราบแยกตามบัญชี
- เพิ่ม regression test สำหรับการรับทราบ Notification รายบุคคลและแบบ Role
- ไม่มี Database Migration

# v1.48.95 - Acknowledged Reservation & Time Staff Notifications
- Popup คำขอจองค้างจนกด “รับทราบ” และโหลดรายการที่ยังค้างกลับมาเมื่อเปิดหน้าใหม่
- บันทึกการรับทราบแยกตามผู้ใช้ เพื่อไม่ซ่อน Popup ของบัญชีอื่น
- เพิ่มสวิตช์ Time Staff แยกจาก Reservation สำหรับรายการคำขอลา/แก้เวลา/อนุมัติ และเปลี่ยนปุ่มปิดเป็น “รับทราบ”
- รองรับ Push ตอนแอปพับผ่าน PWA ได้เมื่อเชื่อม Push Provider และให้ผู้ใช้สมัครรับบนอุปกรณ์แล้ว; แพ็กนี้ยังไม่รวมการส่ง Push จริง
- ไม่มี Database Migration

# v1.48.94 - Live Reservation Notifications
- แสดง Popup คำขอจองใหม่ในหน้าที่เจ้าหน้าที่ล็อกอินอยู่ โดยดึงเฉพาะ Notification ของบัญชีและสาขาปัจจุบัน
- เพิ่ม Settings Center > การแจ้งเตือน สำหรับเปิด/ปิด Popup และขอ Browser Notification บนอุปกรณ์นั้น
- Browser Notification ทำงานเมื่อหน้าเว็บยังเปิดอยู่; Mobile Push ตอนปิดแอปและ LINE ยังต้องเชื่อม Provider เพิ่ม
- ไม่มี Database Migration

# v1.48.93 - Reservation Control Center Navigation
- ย้ายเมนู “การจองโต๊ะ” ไปไว้ในกลุ่มศูนย์ควบคุม โดยคง Permission เดิม
- ไม่มี Database Migration

# v1.48.92 - Reservation Management Separation
- แยกเมนูและหน้าจัดการ Reservation / Waitlist ออกจาก Night Operations ให้ทีมค้นหาและดูแลคำจองได้ตรงจุด
- จัดหน้าใหม่ด้วยสรุปสถานะ ตัวกรองค้นหา และการ์ดรายการที่ปรับตามมือถือ พร้อมเพิ่ม/เปลี่ยนสถานะ/รับลูกค้าเข้าร้านตามสิทธิ์
- แยก Permission `reservations.view`, `reservations.manage` และ `reservations.seat`; หน้า Operations เหลือการจัดการโต๊ะและ Service และยังรองรับรับลูกค้าจากผังโต๊ะ
- ไม่มี Database Migration และไม่เปลี่ยน Schema

# v1.48.91 — Customer Booking Integrations Foundation

- เพิ่มจุดต่อ Slip Verifier แบบ Provider Adapter โดยอ่าน API Key จาก Server Environment และคง Manual Review เมื่อยังไม่มี Provider
- แจ้งคำขอจองในสาขาให้ผู้มีสิทธิ์ดู/จัดการการจองและ Sales ที่เกี่ยวข้อง ไม่กระจายไปทุกบัญชี
- เพิ่ม LIFF account-link flow ที่ตรวจ ID Token กับ LINE Platform ก่อนผูกบัญชี และเพิ่ม LINE Messaging API sender foundation
- เพิ่ม Push/Notification click handlers ใน Service Worker เพื่อเตรียมต่อ Mobile Push
- ยังไม่เปิดเก็บมัดจำหรือส่งข้อความออกจริงจนกว่าจะตั้ง Provider, กติกามัดจำ และ Credentials
- ไม่มี DB migration

# v1.48.90 — HR Approval Center Mobile Layout

- แก้หน้าศูนย์อนุมัติบนมือถือที่ยังคงแบ่งเป็นคอลัมน์ desktop จนเนื้อหาถูกบีบและล้นจอ
- จัดเมนูอนุมัติเป็น 2 คอลัมน์ด้านบน และขยายรายละเอียด/iframe ให้เต็มความกว้างหน้าจอ
- จำกัดความสูงป๊อปอัปแจ้งเตือนบนมือถือ พร้อมเลื่อนดูรายการภายในโดยไม่บังทั้งหน้า
- ไม่มี DB migration

# v1.48.89 — Operations Tools QR Cleanup

- เอาส่วนจัดกะ PR, Notification Center และรายงานวันนี้ที่ซ้ำซ้อนออกจากหน้า Operations Tools
- คง KPI สรุปและเครื่องมือ QR โต๊ะ Sales ไว้
- ปรับชื่อ/สิทธิ์เมนูให้ตรงกับ QR Tools และส่งลิงก์แจ้งเตือนไปยังหน้า Operations ที่ยังใช้งานอยู่
- ไม่มี DB migration

# v1.48.88 — GPS Integrity Guard & Fake GPS Test Toggle

- เพิ่มตัวเลือกเปิด/ปิดการตรวจจับพิกัด GPS กระโดดผิดปกติใน Settings Center
- เมื่อเปิด จะปฏิเสธพิกัดที่ห่างจากจุดลงเวลาล่าสุดอย่างน้อย 1 กม. และประเมินความเร็วเกิน 250 กม./ชม. ภายใน 30 นาที
- ปิดตัวตรวจนี้ชั่วคราวได้สำหรับการทดสอบ Fake GPS; Geofence และนโยบาย GPS อื่นยังทำงานตามค่าที่ตั้งไว้
- เพิ่มค่าเริ่มต้นของนโยบายในระบบ โดยไม่ต้องทำ DB migration

# v1.48.87 — Workforce Calendar Attendance Markers

- แสดงวงเขียวรอบรูปพนักงานเมื่อมีตารางและ Check-in แล้ว รวมสถานะมาสาย/ยังไม่ Check-out
- แสดงวงแดงเมื่อมีตารางแต่พ้นเวลาทำงานแล้วโดยไม่มี Check-in; วันลาหรือกะที่ยังไม่จบไม่แสดงเป็นขาดงาน
- จับคู่ Attendance ด้วย employee_id หรือ PR Profile ID ให้ตรงกับรายการพนักงานบนปฏิทิน
- เพิ่ม Legend สถานะและไม่ต้องเปลี่ยน Schema/DB

# v1.48.86 — Per-Employee Approval Access

- เพิ่มสวิตช์เปิด/ปิดสิทธิ์อนุมัติคำขอของทีมในแท็บบัญชี & สิทธิ์ของ Employee Card
- สิทธิ์รายบุคคลที่เปิดไว้เลือกเป็นผู้อนุมัติได้ แม้นโยบาย Peer Approval ของสาขาปิดอยู่
- การปิดสิทธิ์รายบุคคลมีผลกับการเลือกผู้อนุมัติและตรวจสิทธิ์ฝั่ง Server; ปิดไม่ได้หากยังมีลูกทีมผูกอยู่จนกว่าจะย้ายสายอนุมัติ
- แยกสิทธิ์อนุมัติผ่านสายทีมออกจากสิทธิ์ HR/Admin ระดับระบบ และคงค่าเดิมของพนักงานเดิมจนกว่าจะตั้งรายบุคคล
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.85 — Unified Employee and Login Linking

- ตรวจบัญชี Login ผ่าน Employee.user_id และ PR Profile.user_id ร่วมกัน ลดสถานะ “ยังไม่มีบัญชี Login” ที่ผิดพลาด
- ใช้การเชื่อม PR Profile เดียวกันในสายอนุมัติ, Account & Permissions, Employee lookup, Health check และการ Reset Password
- ป้องกันสร้างบัญชี/Invite ซ้ำ เมื่อบัญชี Login ผูกอยู่กับ PR Profile แล้ว
- บันทึก Employee โดยไม่ล้างบัญชี Login ของ PR Profile ที่ยังเชื่อมอยู่
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.84 — Complete Approver Employee List

- แสดงรายชื่อพนักงานที่ยังทำงานในสาขาปัจจุบันครบในหน้าต่างกำหนดหัวหน้า/ผู้อนุมัติ
- แสดงเหตุผลข้างชื่อที่ยังเลือกไม่ได้ เช่น ไม่มีบัญชี Login, Login ปิด หรือ Peer Approval ยังปิด
- เมื่อเปิด Peer Approval ผู้มีบัญชีใช้งานในสาขาเดียวกันเลือกเป็นผู้อนุมัติได้ตามเดิม
- ยังคงป้องกันการเลือก/อนุมัติตัวเองและข้ามสาขา และตรวจเงื่อนไขซ้ำฝั่ง Server
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.83 — Flexible Peer Approval Routing

- เพิ่มนโยบาย Peer Approval แยกตามสาขา ค่าเริ่มต้นปิด และผู้ดูแลเปิดได้จาก Workforce Exceptions
- เมื่อเปิดนโยบาย เลือกพนักงานที่มีบัญชีใช้งานในสาขาเดียวกันเป็นผู้อนุมัติได้ รวมถึงเพื่อนร่วมงานเพื่อทดสอบ
- ผู้อนุมัติจัดการได้เฉพาะคำขอลา/แก้เวลา/Attendance Exception ของพนักงานที่มอบหมาย ไม่เห็นรายการของทีมอื่น
- ป้องกันอนุมัติตัวเองและข้ามสาขา และปิดสิทธิ์ Peer Approval ทันทีเมื่อนโยบายสาขาถูกปิด
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.82 — Admin PIN Protected User Card Archive

- เพิ่มคำสั่งนำบัญชีออกจาก Account Directory โดยเก็บบัญชีและประวัติเดิมไว้ พร้อมปิด Login และ Trusted Devices
- ยืนยันด้วย PIN 6 หลักของ Admin ที่กำลังใช้งาน จำกัดผิด 5 ครั้งและล็อก 15 นาที
- ปลดการเชื่อม Employee/PR ของบัญชีที่เก็บถาวรทุกสาขา ป้องกันลบตัวเอง, Admin โดยไม่มี Super Admin และ Super Admin คนสุดท้าย
- บังคับออกจากระบบเมื่อบัญชีถูกปิดใช้งาน/เก็บถาวร และซ่อนบัญชีที่เก็บถาวรจาก Account Directory
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.81 — Save Confirmation & Supervisor Approval Routing

- เพิ่ม Popup ยืนยันผลหลัง Loading สำหรับฟอร์ม POST และ API POST แบบ JSON แสดงข้อความสำเร็จ/ข้อผิดพลาดจาก Server โดยไม่อ่านกลืน Response ของหน้าเดิม
- เพิ่มข้อมูลหัวหน้าและจำนวนลูกทีมบน Employee Card พร้อมหน้าต่างกำหนดหัวหน้างานในสาขาเดียวกัน
- จำกัดหัวหน้าที่กำหนดให้ตรวจและอนุมัติคำขอลาของลูกทีมตรง โดยตรวจสิทธิ์ซ้ำฝั่ง Server และเพิ่มรายการแจ้งเตือนให้หัวหน้า
- รองรับพนักงานแบบ AUTO/ใกล้ที่สุดในบริบทสาขาปัจจุบัน และป้องกันการตั้งหัวหน้าวนลูป
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.80 — HR Approval UX & Duplicate Notice Fix

- แสดง Approval Notification เฉพาะหน้าหลัก ป้องกัน popup ซ้ำในหน้า iframe/embed
- ปรับสีหน้า Leave, Workforce Exception และ Payroll ที่ฝังใน Approval Center ให้ตามธีมมืด/สว่าง
- ปรับกรอบรายละเอียดให้ขยายตามเนื้อหา ลด scrollbar ซ้อนและพื้นที่ใช้งานอึดอัด
- คง flow และ handler อนุมัติเดิม ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.79 — Time Staff Approval Notifications & Access Guard

- เพิ่มการตรวจสอบ payload ภาพและช่วงพิกัด/ความแม่นยำ GPS ฝั่ง Server สำหรับ Check-in/Check-out ของ Employee และ PR
- ปิดการเข้าถึงปฏิทินและรายได้ของ Employee ที่ไม่ Active โดยยังคง Admin Preview
- เพิ่ม Popup คิวอนุมัติแบบ permission-aware แยกตามสาขา พร้อมลิงก์ HR Approval Center และอัปเดตคิวอัตโนมัติ
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.78 — Workforce Dark Theme Contrast

- แก้พื้นและสีข้อความแถวตารางงานทั้งหมดของวันที่เลือกใน Workforce Schedule
- แก้แผงกฎ PR No-show รวมช่องกรอก แถบแจ้งเตือน และรายการรอตรวจใน Payroll/Attendance
- คง logic การจัดตารางและการคำนวณค่าปรับเดิม ไม่มี DB migration

# v1.48.77 — Admin Sidebar Navigation Groups

- จัดกลุ่มเมนูใหม่เป็นศูนย์ควบคุม, ทีมงาน/เวลา, ลูกค้า/ช่องทาง, รายงาน/ค่าตอบแทน และตั้งค่าร้าน/ระบบ
- ย้าย POS Incentive ไปกลุ่มรายงาน, แยก CRM/Customer Web ออกจาก Zone Studio และย้าย Staff Preview ไปกลุ่มทีมงาน
- คง URL และ permission เดิม พร้อมปรับสีไอคอนให้แยกกลุ่มง่ายขึ้น
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.76 — POS Commission Batch Contrast Fix

- แก้การ์ด Batch สรุปรายละเอียดบิลในหน้าค่าคอม Sales ที่ยังเป็นพื้นสว่างและข้อความจางใน Dark Mode
- ปรับสีชื่อไฟล์ ช่วงวันที่ ยอดรวม และตัวเลขสรุป พร้อมเพิ่ม cache-busting stylesheet รุ่นใหม่
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.75 — POS Incentive Dark Theme Fix

- แก้คอนทราสต์เมนูขั้นตอน การ์ดค่าดื่ม/ค่าคอม ตาราง และช่องกรอกในธีมมืด
- ปรับหน้า POS Reports ให้การ์ด ตัวกรอง KPI และตารางอ่านได้ครบในธีมมืด
- คงสูตรคำนวณและการบันทึกเดิม ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.74 — POS Import Contrast & Validation

- แก้ Dark Mode หน้า Import POS ทั้งแถบขั้นตอน การ์ดสรุป การ์ดอัปโหลด ช่องกรอก และตัวเลือกไฟล์
- ใช้ Theme stylesheet ชุดเดียวกันในหน้า Process และ Import เพื่อให้สีสอดคล้องกัน
- ตรวจสอบวันที่ปฏิทินจริงฝั่ง Server ก่อนรับไฟล์ ปฏิเสธวันที่รูปแบบถูกแต่ไม่มีจริง
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.73 — POS Incentive Dark Theme Fix

- แก้ความต่างสีของแถบขั้นตอน POS Incentive ใน Dark Mode ทั้งขั้นปัจจุบันและขั้นอื่นให้อ่านชัด
- ปรับข้อความกรณี Inbox ว่างให้ระบุว่าแยกไฟล์ตามสาขาที่เลือก พร้อมลิงก์ไปหน้า Import POS
- คงการแยก Inbox ตามสาขาเพื่อป้องกันการนำไฟล์ของสาขาอื่นมา Process ผิดร้าน
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.72 — System Reports Dashboard

- ปรับหน้า System Reports เป็น Dashboard รายงานกิจกรรม แยกตาม Module และชนิดข้อมูล พร้อมรายการ Audit Log ล่าสุด
- แยกยอด POS เมนู, Report บิล, ยอดปิดรอบ, ค่าดื่ม และค่าคอมมิชชัน ไม่บวกข้ามประเภทเพื่อเลี่ยงยอดซ้ำ/ความหมายคลาดเคลื่อน
- แยกมุมมอง ภาพรวม / รายการข้อมูล / Audit Log และคงการค้นหา กรอง เรียง และ Export CSV
- แสดงรายการ 20 แถวแรก และโหลดชุดถัดไปจาก Server ทีละ 20 แถว โดยไม่ใส่แถวที่เหลือไว้ใน DOM
- คง Soft Clear สำหรับ Super Admin, Loading กลางระบบ และการแก้ Contrast จากเวอร์ชันก่อนหน้า
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.71 — Global Loading Experience

- เพิ่ม loading overlay สำหรับการเปิด/เปลี่ยนหน้าและการส่งฟอร์ม พร้อมข้อความบอกสถานะ
- แสดง loading badge แบบไม่บังหน้าจอสำหรับ fetch ที่ใช้เวลานาน และเปิด API MRBarLoading ให้ action อื่นเรียกใช้ได้
- หน่วงการแสดงสถานะใน request สั้นเพื่อลดการกระพริบ และรองรับ prefers-reduced-motion
- รวมการแก้ contrast ของ HR Approval Center และ System Reports จาก v1.48.70
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.70 — HR & System Reports Theme Contrast Fix

- แก้สีตัวอักษรจางบนพื้นสว่างใน HR Approval Center และ System Reports ให้สอดคล้องกับธีม Light/Dark
- ปรับพื้นหลังหัวเรื่อง, KPI, เมนู/ตัวกรอง, ตาราง และแผงอนุมัติ/รายงานตามธีม
- คงสีสถานะเข้างาน Normal/Late/Wait ให้แยกแยะได้ในธีมมืด
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.68 — Admin Performance Optimization

- ลดการอ่านและ migrate ฐานข้อมูลซ้ำภายใน request โดยใช้ snapshot ที่โหลดแล้ว และไม่ migrate ซ้ำเมื่อต้องสร้าง Branch View ภายใน
- ไม่ invalidate OPcache ทุกครั้งที่อ่านไฟล์ฐานข้อมูล โดยยัง invalidate หลังเขียนข้อมูลตามเดิม
- จำกัดการตรวจ Check-out ที่ค้างไว้เฉพาะหน้าที่เกี่ยวข้องและไม่เกินหนึ่งครั้งต่อนาทีต่อ session
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.67 — Admin Integrity & Attendance Guard

- ตรวจจับ Attendance ที่เวลาเข้า/ออกติดลบหรือเกิน 24 ชั่วโมง และพักรายการจาก Payroll/OT จนกว่าจะตรวจสอบ
- ป้องกันการ Check-out และส่งคำขอแก้เวลาข้าม PR/พนักงาน พร้อมรองรับการส่งรายการเวลาผิดปกติเข้าตรวจสอบ
- ป้องกันการผูก Table Hotspot ซ้ำใน Zone Studio ทั้งตอนบันทึกและ Publish พร้อมแจ้งรหัสโต๊ะที่ซ้ำ และซ่อน Hotspot ซ้ำจากหน้าลูกค้าจนกว่าจะแก้ผัง
- สืบทอด Responsive Settings Navigation จาก v1.48.66
- ไม่มี DB migration และไม่รวมข้อมูล Production

# v1.48.66 — Responsive Settings Navigation

- เปลี่ยนเมนู 9 หมวดใน Settings เป็นกริด 3 คอลัมน์บนแท็บเล็ต และ 2 คอลัมน์บนมือถือ ไม่ต้องเลื่อนแนวนอนเพื่อหาเมนู
- รักษาขนาดตัวอักษรและการตัดบรรทัดให้ชื่อหมวดอ่านง่ายบนจอเล็ก
- สืบทอดการแก้ตัวเลือกสาขาไม่ให้ลอยทับเนื้อหาจาก v1.48.65
- ไม่มี DB migration

# v1.48.65 — Responsive Admin Branch Switcher

- ย้ายตัวเลือกสาขาออกจากตำแหน่ง fixed บนหน้าจอไม่เกิน 900px เพื่อไม่ให้บังฟอร์มและปุ่มหลังบ้าน
- คงเมนูสาขาให้เปิดขึ้นด้านบนและจำกัดความสูงให้เหมาะกับหน้าจอขนาดเล็ก
- ปรับ cache version ของ CSS เพื่อให้เบราว์เซอร์โหลดกฎใหม่
- ไม่มี DB migration

# v1.48.64 — Admin Permission Navigation & Sales QR Setup

- กรองเมนูหลังบ้านตาม permission ที่หน้าเป้าหมายรองรับ ลดเมนูที่ผู้ใช้กดเข้าแล้วถูกปฏิเสธ
- แก้เมนู active ของหน้าคำขอลา, attendance exceptions และ roster ให้ชี้ไปยังหมวดแม่ที่ถูกต้อง
- หยุดสร้าง Sales Table QR secret อัตโนมัติเมื่อเปิดหน้า Tools; ผู้มีสิทธิ์ต้องกดตั้งค่าผ่าน POST ที่ตรวจ CSRF
- คง QR secret เดิมไว้ ไม่หมุนค่าใหม่เมื่อกด setup ซ้ำ
- ไม่มี DB migration

# v1.48.63 — Portal Customer Hero Gallery Sync

- ซิงก์แถบภาพ Portal กับรายการ Hero Gallery ที่เปิดใช้งานของหน้าร้าน รองรับภาพและวิดีโอ พร้อม fallback แกลเลอรีเดิม
- คงภาพ Cover ของสาขาและโลโก้ พร้อมแสดง Mini Map และ Facebook Timeline เมื่อกำหนดข้อมูลไว้
- ไม่มี DB migration

# v1.48.62 — Portal Branch Gallery, Map & Facebook Previews

- ขยายภาพบรรยากาศสาขาเป็นแถบ Gallery ที่แตะเปิดภาพเต็มจอ เลื่อนภาพด้วยปุ่มหรือปุ่มลูกศร และปิดได้
- เพิ่ม Mini Map ในการ์ดสาขา ใช้พิกัดที่ตั้งไว้ก่อน หรือใช้ที่อยู่เป็นทางเลือก พร้อมลิงก์เปิด Google Maps
- เพิ่ม Facebook Page Timeline แบบฝังในตัวการ์ด เมื่อสาขามี URL Facebook ที่ถูกต้อง
- ขยายการ์ดให้รองรับเนื้อหาเสริม พร้อมจัดรูปแบบใหม่บนมือถือ
- ไม่มี DB migration

# v1.48.61 — Portal Nearby Badge Position Fix

- ย้ายป้ายระยะทางสาขาไปมุมล่างซ้ายของภาพร้านและกำหนดความกว้างให้พอดีกับข้อความ ไม่ยืดเป็นแถบเต็มรูป
- ปรับตำแหน่งและระยะขอบให้เหมาะกับหน้าจอมือถือ
- ไม่มี DB migration

# v1.48.60 — Customer Portal Welcome, Background & Daylight Readability

- ซ่อนลิงก์ Config Portal จากหน้าสำหรับลูกค้าทุกคน โดยหน้า Config ยังเข้าได้จากเส้นทางผู้ดูแลโดยตรง
- เปลี่ยนส่วนตำแหน่งใกล้ฉันเป็นข้อความต้อนรับและแจ้งการใช้งาน GPS ก่อนขออนุญาตอัตโนมัติจากเบราว์เซอร์
- เมื่ออนุญาตตำแหน่ง จะเรียงสาขาตามระยะทางและแสดงระยะโดยไม่จัดเก็บพิกัด; หากไม่อนุญาตยังดูร้านได้ตามปกติ
- เพิ่มช่องอัปโหลด/ลบภาพพื้นหลัง Portal แยกจาก Banner และ Hero พร้อมแสดงภาพแบบเบลอหลังเนื้อหา
- ปรับสีตัวอักษรและคอนทราสต์ของ Light Theme ให้ชัดขึ้น โดยคงธีมกลางคืนเดิม
- รวมการแก้ภาพ Cover และกรอบ Hero ที่เตรียมไว้ใน v1.48.59
- ไม่มี DB migration

# v1.48.59 — Portal Branch Cover & Eyebrow Fit Fix

- แก้รูป Cover ของ Branch Card ในหน้า Portal ไม่แสดง เพราะธีม Black/Gold ใช้ `background-image: ... !important` ไปทับ URL รูปของสาขา
- เปลี่ยนการส่งรูป Cover เป็น CSS custom property แล้ว render เป็น layer ของ card โดยยังคง overlay และโลโก้ร้านไว้
- แก้กรอบ `CHOOSE YOUR EXPERIENCE` ที่ถูก Grid stretch จนยาวเกินข้อความ ให้กรอบพอดีข้อความทั้ง Desktop/Mobile
- เพิ่ม responsive guard สำหรับชื่อ/กรอบยาวเพื่อไม่ให้ล้นบนหน้าจอเล็ก
- ไม่มี DB migration

# v1.48.58 — Portal Black Gold RGB Banner

- เพิ่มช่องอัปโหลดภาพ Banner ส่วนหัว Portal ในหน้า Config แยกจาก Logo และ Hero Background
- แสดง Banner แนวนอนใน Hero พร้อมภาพ fallback ของแบรนด์ และ badge จำนวนสาขาที่ใช้ข้อความสั้น “N ร้าน”
- ปรับ Portal เป็นธีมดำ-ทอง ตัดขอบ RGB Neon และถอดเอฟเฟกต์เด้งออกจากตัวนับสาขา
- เพิ่มการรองรับจัดวาง Banner/Badge สำหรับหน้าจอมือถือ และรองรับ motion preference
- ไม่มี DB migration

# v1.48.14 — POS Sales Direct Drink Save

- เพิ่มปุ่ม “เสร็จสิ้น / บันทึกยอด” หลังคำนวณค่าดื่ม Sales ดื่มตรง
- บันทึกผลเป็นรายงานจริงในรอบค่าดื่ม โดยแยกหัวข้อเป็น `sales_direct_drink`
- ป้องกันการบันทึกซ้ำสำหรับ Report เดียวกันในหัวข้อ Sales ดื่มตรง
- เพิ่มประวัติรายงานที่บันทึกแล้วให้แสดงเมนู Sales, Sales ที่จับคู่, D/M, ตัวคูณ และยอดจ่าย
- ไม่มี DB migration

# v1.48.13 — POS Sales Direct Drink Mapping

- เพิ่มหน้าคำนวณ preview สำหรับ “ค่าดื่ม Sales ดื่มตรง” โดยดึงเฉพาะกลุ่ม Sales จาก Report ยอดขายตามเมนู
- แสดงตัวคูณจากค่าคอม Sales ที่บันทึกไว้ก่อนหน้าในรอบวันที่เดียวกัน
- เพิ่มตารางจับคู่ชื่อเมนูเซลกับชื่อ Sales จากระบบ แล้วคำนวณผลในหน้าเดิมโดยไม่เด้งไปหน้าอื่น
- ยังไม่บันทึกรอบจริง เพื่อให้รีวิวสูตรและหน้าตาก่อน
- ไม่มี DB migration

# v1.48.12 — POS Sales Drinks Split Landing

- เพิ่มหน้าคั่นเฉพาะ “ค่าดื่ม Sales” เพื่อแยก 2 ปุ่ม “ดื่มตรงของ Sales” และ “ดื่มจากน้อง PR ในทีม”
- กด “คำนวณค่าดื่มของ Sales” จากหน้าเลือกค่าดื่ม จะยังไม่เข้าฟอร์มทันที แต่ให้เลือกประเภทย่อยก่อน
- ตั้งชื่อหน้าคำนวณปลายทางตามประเภทย่อย เพื่อรอต่อสูตรแยกกันในรอบถัดไป
- ไม่มี DB migration

# v1.48.11 — POS Drinks PR Sales Landing

- เพิ่มหน้า Landing ก่อนเข้าคำนวณค่าดื่ม เพื่อแยกทางเข้า “คำนวณค่าดื่มของ PR” และ “คำนวณค่าดื่มของ Sales”
- ปรับเมนูขั้นตอนค่าดื่มเป็น “ค่าดื่ม PR / Sales” ให้ชัดว่าเป็นจุดเลือกประเภทก่อนต่อสูตร
- ยังไม่เปลี่ยนสูตรคำนวณจริงของค่าดื่ม เพื่อรอต่อสูตร PR/Sales แยกกันในรอบถัดไป
- ไม่มี DB migration

# v1.48.10 — POS Commission Sales Sentence Summary

- เปลี่ยนสรุปยอดขาย Sales รายบุคคลจากการ์ดตัวเลขโดด เป็นประโยค “ยอดขายรวม ของเซล ชื่อ ... ประจำรอบวันที่ ... ถึง ... เป็นจำนวนเงิน ... บาท”
- ยังคงเรียง Sales จากยอดขายรวมมากไปน้อย
- เอาเลขยอดขายแบบโดดใน summary card ออก เพื่อลดความสับสนกับตารางรายละเอียด
- ไม่มี DB migration

# v1.48.9 — POS Commission Batch-Scoped Summary

- แก้ยอดสรุป Sales รายบุคคลให้รวมเฉพาะรอบโต๊ะที่จับคู่กับ Report รายละเอียดบิล Batch ที่เลือกอยู่
- ป้องกัน session จาก Batch/รอบอื่นปนเข้ามาในยอดรวมรายคน
- ยอดบนการ์ดรายคน, ยอดรวมด้านบน และตารางรายละเอียดจะใช้ชุดข้อมูลเดียวกัน
- ไม่มี DB migration

# v1.48.8 — POS Commission Tier Multipliers

- ทำสรุปยอดเชียร์ Sales ให้เด่นขึ้น โดยรวมจำนวนบิล, จำนวนรอบโต๊ะ และยอดขายรวมแยกตาม Sales รายคน
- เปลี่ยนค่าคอม Sales เป็นเงื่อนไข Tier “ยอดขายไม่ถึง X ได้ตัวคูณ Y” ให้แอดมินกรอกเองได้
- ผลคำนวณแสดง Sales, จำนวนบิล, ยอดขายรวม, Tier ที่เข้า และตัวคูณที่จะใช้ต่อ
- บันทึกตัวคูณ Sales ลง storage เพื่อใช้ต่อในหน้าคิดค่าดื่ม Sales
- เพิ่มปุ่มหลังบันทึกค่าคอมเพื่อไปคิดค่าดื่ม PR/Sales ต่อ
- ไม่มี DB migration

# v1.48.7 — POS Commission Bill-Only Flow

- แยก Flow หน้าค่าคอม Sales ให้อ้างอิงเฉพาะ Report รายละเอียดบิล/ยอดเชียร์ ไม่ใช้ Report ยอดขายตามเมนู
- หน้า “ค่าดื่ม PR” ยังคงใช้ Report ยอดขายตามเมนูสำหรับคำนวณ D/M เท่านั้น
- เพิ่มการคำนวณค่าคอม Sales จากยอดเชียร์ที่จับคู่เลขบิลกับรอบโต๊ะ โดยระบุอัตราเป็นเปอร์เซ็นต์
- ปรับข้อความใน Step bar และ dropdown ให้แยก “ค่าดื่ม PR” กับ “ค่าคอม Sales” ชัดเจน
- ไม่มี DB migration

# v1.48.6 — POS Bill Report Visibility

- หน้า “ค่าคอม” แสดง Report รายละเอียดบิลที่ Process แล้วโดยตรง พร้อม Batch, ยอดรวม, จำนวนเลขบิล และยอดเชียร์ที่จับคู่กับรอบโต๊ะ
- เพิ่มตารางรอบโต๊ะ/เลขบิล/Sales ที่จับคู่สำเร็จ เพื่อให้ตรวจข้อมูลหลัง Import ได้ทันที
- ปรับข้อความช่องเลือก Report ให้ชัดว่า dropdown นั้นเป็นของ Report ยอดขายตามเมนู ไม่ใช่รายละเอียดบิล
- ไม่มี DB migration

# v1.48.5 — POS Per-File Process Actions

- เปลี่ยนหน้า Process ให้มีปุ่ม “ใช้ไฟล์นี้ Process ...” อยู่ในแต่ละรายการไฟล์
- เอาปุ่ม “ขั้นตอนต่อไป: Process ไฟล์ที่เลือก” ออกจากแถบล่างในขั้นตอนเลือกไฟล์
- Process รายละเอียดบิลแล้วส่งไปหน้า “ค่าคอม” เพื่อดูข้อมูลยอดเชียร์/จับคู่บิล
- Process ยอดขายตามเมนูแล้วยังไปหน้า Sort/ตรวจข้อมูลตามเดิม
- เพิ่มปุ่มทางลัดหลังไฟล์ Process สำเร็จ
- ไม่มี DB migration

# v1.48.4 — POS Bill Process Idempotent Guidance

- ถ้า Report รายละเอียดบิลเคย Process แล้ว ระบบจะผูกสถานะ Inbox เป็น completed แทนการฟ้องซ้ำว่า Process แล้ว
- ปรับข้อความหลัง Process รายละเอียดบิลให้บอกชัดว่าต้อง Process “ยอดขายตามเมนู” เพิ่มสำหรับคำนวณค่าดื่ม/ค่าคอม
- หน้าเลือก Report ในค่าดื่ม/ค่าคอมจะแสดงคำแนะนำเมื่อมีเฉพาะรายละเอียดบิล แต่ยังไม่มียอดขายตามเมนู
- Disable ปุ่มไปต่อเมื่อยังไม่มี Report ยอดขายตามเมนูที่ Process สำเร็จ
- ไม่มี DB migration

# v1.48.3 — POS Process Relaxed Bill Mapping

- ผ่อนเงื่อนไขขั้นตอน 2 สำหรับ Report รายละเอียดบิล ไม่บังคับต้อง auto-detect วันที่/เวลาให้เจอจากหัวคอลัมน์
- เพิ่มตัวเดาคอลัมน์รายละเอียดบิลแบบกว้างขึ้น รองรับ Bill No, Receipt, Invoice, Total, Paid Amount และชื่อหัวคอลัมน์ภาษาไทยหลายแบบ
- ถ้าไฟล์รายละเอียดบิลไม่มีวันที่ชัดเจน ระบบจะใช้วันที่สิ้นสุดของ Report เป็น fallback เพื่อให้ Process ต่อได้
- ยังต้องมีเลขบิล/ใบเสร็จและยอดขายเพื่อใช้จับคู่กับรอบเปิดโต๊ะ
- ไม่มี DB migration

# v1.48.2 — POS Upload Staging Relaxed Validation

- เอาเงื่อนไขตรวจหัวคอลัมน์ POS ออกจากหน้า Import เพื่อให้ IT อัปโหลดไฟล์ไว้รอได้ก่อน
- ยังคงตรวจชนิด Report, ช่วงวันที่ และไฟล์ซ้ำตามประเภทเดิม
- การตรวจคอลัมน์/Mapping ยังไปเกิดตอนทีมทำเงินเดือนเลือกไฟล์ไป Process ในขั้นตอน 2
- ไม่มี DB migration

# v1.48.1 — Split POS Import Inbox

- แยกหน้า Import POS เป็น 2 ช่องอิสระ: รายละเอียดบิล และยอดขายตามเมนู
- แต่ละช่องอัปโหลดตรงเข้า Server ได้ทันที ไม่ต้องแนบไฟล์อีกประเภทพร้อมกัน
- เพิ่มรายการไฟล์บน Server แยกตามประเภท เพื่อให้ IT เห็นว่าไฟล์ไหนรอทีมทำเงินเดือนไป Process ต่อ
- ปรับ duplicate check ให้แยกตามประเภท Report ไม่ให้ไฟล์คนละประเภทชนกัน
- ไม่มี DB migration

# v1.48.0 — Full System Report Center

- เพิ่มหน้า Report Center รวมข้อมูลทุกฟังก์ชันหลัก: Operations, Workforce, Customer CRM, POS, Sales และ System Audit
- เพิ่ม Tool Sort/Filter/Search/Limit แบบจริงจัง พร้อม Export CSV สำหรับสิทธิ์ที่ export ได้
- เพิ่มเมนู Report Center ใน Sidebar เพื่อเข้าหน้ารายงานรวมจากระบบ Admin
- เพิ่ม Hidden Clear Report Tool เฉพาะ Super Admin พร้อมยืนยันหลายชั้น, reason, checkbox และ Audit Log
- การเคลียร์เป็น Soft Clear เฉพาะ report artifacts/cache หรือ POS report ที่ยังไม่ถูก lock ด้วย payout round

# v1.46.3 — Unified POS Incentive Suite

- รวมเมนูค่าดื่ม ค่าคอม และ Report กลับไว้ใต้ POS Incentive & Commission รายการเดียว
- เชื่อมระบบเดิม Import / Process / Mapping / Sort กับสองแกนคำนวณใหม่ผ่านแถบขั้นตอนเดียว
- ปรับหน้าค่าดื่มและค่าคอมให้ใช้ Sidebar, Header, Card และสีชุดเดียวกับหน้าระบบเดิม
- ไม่เปลี่ยนข้อมูลหรือรอบบันทึกที่แยกอย่างปลอดภัยไว้แล้ว

# v1.46.2 — Control Center Navigation

- ย้ายเมนู Tools & Reports (QR / Shift / Reports) ไปอยู่ใต้ศูนย์ควบคุม
- คง URL สิทธิ์ และข้อมูล QR เดิมทั้งหมด

# v1.46.1 — POS Report Center

- เพิ่ม Report กลางสำหรับค่าดื่มและค่าคอม พร้อมตัวกรองช่วงวันที่ ประเภท สถานะ และคำค้น
- เพิ่ม Sort รอบจ่ายและสรุปตามพนักงาน รวมถึง Export CSV
- เพิ่มยกเลิกรอบจ่ายและเคลียร์ Report แบบ Soft Delete พร้อมเหตุผลและ Audit Log
- ป้องกันเคลียร์ Report ที่ยังมีรอบจ่ายใช้งานอยู่

# v1.46.0 — ค่าดื่ม / ค่าคอม

- แยกเมนู ขั้นตอนคำนวณ และรอบบันทึกค่าดื่มส่วนตัวออกจากค่าคอมทีม
- ตรวจลำดับขั้นและข้อมูลเปลี่ยนก่อนบันทึก ป้องกัน Report ซ้ำภายในแต่ละส่วน
- รักษาข้อมูลเดิม และแสดงสถานะยอดเชียร์ที่ยังรอจับคู่ใบเสร็จ POS

# v1.45.1 — Sales QR Customer Table Status

- Sales QR opening marks shared table occupancy for customer floor maps and Night Ops.
- Closing with receipts releases a table only when no other active check-in or floor service remains.
- Keeps blocked/inactive tables unchanged; prevents other table writes from freeing an open Sales round.
- Existing open Sales rounds are reflected on branch reads. Customer pages show changes on load/refresh.
- Regression tests exercise the actual public floor-map booking state and branch persistence.

# v1.45.0 — Sales Table QR

- Replaces printable customer QR destinations with branch-scoped authenticated Sales table recording.
- Records Sales owner separately from opening/closing staff; PR, Sales and staff may record on behalf of Sales.
- Requires receipt numbers to close; supports split bills and rejects duplicate receipts within a branch.
- Rejects duplicate open rounds, unauthorized branch access and stale updates; all writes use the database lock.
- Manager-only owner/receipt corrections require reasons and preserve before/after audit history.
- Keeps attribution rounds separate from Night Ops seating and from POS drink commission calculations.
- Receipt matching is pending: no sales amount is inferred from Sales drink-menu revenue.
- Existing printed customer QR codes must be replaced. No existing sales or check-in data is deleted.

# v1.44.2 — Team and Sales Revenue Context

- Shows the total net sales for each PR team detected from the selected POS report in Step 3.
- Shows each Sales employee's direct net sales alongside direct D/M drink units and income.
- Shows the combined net sales of every PR team assigned to each Sales employee.
- Keeps net sales as decision context only; commission continues to use the configured D/M rates.
- Expanded regression coverage for team totals, direct Sales totals, and multi-team Sales totals.
- Schema remains v28; no migration is required.

# v1.44.1 — Multi-Team Sales Assignment

- Allows the same Sales employee to be selected as the owner of multiple POS teams in Step 3.
- Normalizes `PR D <team>` and `PR M <team>` into one base team while preserving separate D/M unit totals.
- Combines all assigned teams into one Sales payout row instead of duplicating the employee.
- Displays every assigned team on the Sales row and sums direct drink income plus all team commission.
- Normalizes legacy D/M-prefixed team mappings automatically when calculating and reopening a saved rule.
- Expanded regression coverage for one Sales employee managing multiple teams.
- Schema remains v28; no migration is required.

# v1.44.0 — Sales Dual Income Calculator

- Added the missing Step 3 Sales calculation workspace.
- Calculates Sales income from two sources: direct D/M drinks and commission from PR drinks in assigned teams.
- Added separate D/M rates for direct Sales drinks and team PR drinks.
- Detects team codes from the current POS file and lets the operator assign each detected team to a Sales employee.
- Added a live Sales table showing both income sources and the combined payout per person.
- Step 4 remains locked until the Step 3 rate/team configuration is saved.
- Schema remains v28; no migration is required.

# v1.43.9 — Commission Function Selector

- Removed the “คำนวณยอดที่เลือก” button from Step 2.
- Added a function list with “คำนวณค่าดื่ม PR” and “คำนวณค่าคอม Sales”.
- Enables the bottom next-step action immediately after a calculation function is selected.
- Carries the selected calculation mode into Step 3 and displays the matching heading.
- No longer requires selecting the Sales product group before continuing.
- Schema remains v28; no migration is required.

# v1.43.8 — Clear Stuck POS Process

- Added a “เคลียร์ Process ไฟล์ที่เลือก” action to Step 1.
- Clears stale processing/error state for the selected inbox file without deleting its original upload.
- Voids an active duplicate Batch with the same file hash and report period, including its calculated rows.
- Returns the selected report to the ready-to-process state with an audit record.
- Requires reopening a finalized period before clearing its Batch.
- Schema remains v28; no migration is required.

# v1.43.7 — Separated POS Import Workspace

- Added a dedicated `pos-incentive-import.php` page for uploading POS reports only.
- The upload page now requires report name, start date, end date, and source file, with an optional detail field.
- Removed file selection and processing from the upload workspace.
- Changed Step 1 of the calculation wizard into a dedicated stored-report selection page.
- Removed the unrelated period picker and KPI cards from the file-selection step.
- Schema remains v28; no migration is required.

# v1.43.6 — Reliable POS Process Redirect

- Redirects to Step 2 immediately after a selected POS file finishes processing.
- Prevents browser refresh or double submission from processing the same file twice.
- Reusing an already-completed inbox item opens its existing calculation period instead of showing an error.
- Adds a clear success notice after the redirect.
- Schema remains v28; no migration is required.

# v1.43.5 — Select Then Process Flow

- Removed the per-file “Process” action button from the POS import list.
- Added a clear check-style selector and limited selection to one POS file per calculation round.
- Moved processing to the main “ขั้นตอนต่อไป” action at the bottom of Step 1.
- The next-step action now validates a selected file, processes it, and continues to Step 2.
- Schema remains v28; no migration is required.

# v1.43.4 — Clear POS Import Workflow

- Renamed the POS inbox area to “Import File จาก POS”.
- Separated server upload from choosing a stored file for processing with explicit numbered sections and actions.
- Rendered the PR / Sales payout table only on wizard Step 4, the final review page.
- Hid the Step 3 commission section entirely while users are on Steps 1–2.
- Schema remains v28; no migration is required.

# v1.43.3 — Simplified Commission Step

- Removed the entire team-code-to-Sales assignment panel from Step 3.
- Removed its save button and explanatory fields to reduce visual complexity.
- Step 3 no longer blocks progression on that removed configuration.
- Preserved imported data and calculation history for the next commission-flow redesign.
- Schema remains v28; no migration is required.

# v1.43.2 — Branch-scoped POS Team Codes

- Removed the visible D/M rate-entry cards from Step 3 for now.
- Labels detected values explicitly as category codes from the imported POS file.
- Keeps D and M in the detected key, so D PRIEST and M PRIEST are separate assignments.
- Filters detected keys against the current branch identity; Priest no longer displays MW or R4 keys.
- Preserves previously stored rates internally while the rate-entry UI is hidden.
- Schema remains v28; no migration is required.

# v1.43.1 — Dynamic Team Discovery

- Removed the requirement to predefine Team Code or Sales team-lead status in Employee Card.
- Team names are discovered from the imported POS category values for the selected period.
- Supports arbitrary Thai, English and shop-specific team names without fixed keywords.
- Step 3 displays every detected PR team with its D/M units and lets the operator assign the responsible Sales after import.
- Team-to-Sales assignments are stored per calculation period and no team commission is paid until an assignment is confirmed.
- Schema remains v28; no migration is required.

# v1.43.0 — PR & Sales Team Commission

- Rebuilt Step 3 around the actual PR/Sales income structure.
- PR commission is calculated from own D and M drink units using separate configurable rates.
- Sales income combines own D/M drink commission with commission from PR drink units in the same team.
- Only a Sales employee marked as team lead receives the PR team commission, preventing duplicate payouts.
- Team mapping prefers an explicit code in the POS category (TEAM-A, ทีม A or @A), then falls back to the Employee Card Team Code.
- Added a combined PR/Sales payout table, unmapped-menu warning and copy-for-payment output.
- Existing legacy Sales tiers and R4 data remain stored for backward compatibility but are no longer used by the new Step 3 payout table.
- Schema remains v28; no migration is required.

# v1.42.2 — Inline Personal Sales Calculation

- Moved personal Sales inputs into the payout result table where the zero values were previously displayed.
- Added a prominent warning when one or more Sales personal totals are missing.
- Added an inline Save personal totals and recalculate button directly below the payout table.
- Removed the duplicate personal-sales entry fields from the source summary.
- Schema remains v28; no migration is required.

# v1.42.1 — Sales Personal Total Input Fix

- Added personal sales inputs beside every Sales name in Step 3.
- Personal totals can now be saved by POS menu name even before the menu is mapped to an Employee Card.
- A mapped employee still falls back to the matching R4 personal-sales input when no manual value was saved here.
- Commission tiers recalculate immediately using store sales, entered personal sales and bill/table count.
- Schema remains v28; no migration is required.

# v1.42.0 — Multi-level Sales Commission Conditions

- Replaced the single Sales commission condition with reusable multi-level tiers.
- Every tier supports minimum store sales, minimum personal sales, minimum bills/tables and commission per bill.
- Store sales are read automatically from the selected POS period.
- Personal sales are read from the employee R4 input for the same period.
- The calculation checks tiers from highest to lowest and uses the first tier where all conditions pass.
- Removed the additional bonus fields; a Sales employee who matches no tier receives zero.
- Added Add/Remove tier controls, responsive styling, clearer payout results and regression tests.
- Schema remains v28; no migration is required.

# v1.41.0 — Strict POS Wizard Flow

- The calculation period is selected only in Step 1; later steps show a compact read-only period summary.
- Replaced clickable step shortcuts with a high-contrast progress indicator showing Current, Complete and Locked states.
- Added enforced Previous/Next navigation and server-side step clamping to prevent skipping incomplete work.
- Step 2 proceeds only after Group > Sales is selected and calculated.
- Step 3 proceeds only after a commission or R4 rule is saved.
- Removed duplicate Final controls; saving and Final are available only on Step 4.
- Added server-side Final guard so a request outside the final review step is rejected.
- Schema remains v28; no migration is required.

# v1.40.0 — R4 Commission & Employee Mapping

- Added configurable R4 rules for store targets, Sales personal-sales tiers and normal/reduced PR drink rates.
- Supports either THB per unit or percentage-of-normal interpretation for the 100/90/80/70/60/50 ladder.
- Added Hold calculation, branch-scoped Hold ledger, cross-month release targets and authorized release confirmation.
- Added online exception evidence with daily posting, online table count and manager approval requirements.
- Added POS menu Alias mapping directly to Employee Cards with immediate period Re-map and audit history.
- Added per-employee personal-sales input because the D/M commission report does not contain the 600k personal-sales metric.
- Added R4 regression tests. Schema remains v28; no migration is required.

# v1.39.0 — POS Upload Inbox

- Added a server-backed POS data inbox so the IT team can upload XLSX/CSV files with a title, notes and calculation period without processing them immediately.
- Added clear Uploaded, Processing, Completed and Failed states so the payment team can select and process work later.
- Preserves uploaded source files under `storage/pos-upload-inbox` and records uploader/processor audit events.
- Duplicate protection now blocks only the same completed file in the same exact date range instead of all historical imports.
- Added recent-processing protection plus safe retry for abandoned processing jobs after 15 minutes.
- Persists the inbox as branch-scoped data. Schema remains v28; no migration is required.

# v1.38.0 — Flexible POS Period Flow

- Replaced the subtle month bar with a prominent calculation-period selector.
- Added monthly, weekly, 22-to-22 and custom date-range modes, including calculate-through-today.
- Added cross-month import support for accounting cycles such as 22 July through 22 August.
- Removed the visible source/mapping confirmation block from the normal workflow; upload now auto-detects, stores and opens Sort.
- Connected the existing “Calculate selected total” button directly to Sales commission when Group > Sales is selected.
- Filters product and Sales commission data by the selected start/end dates.
- Stores Sales commission conditions separately for each exact date range.
- Schema remains v28; no migration is required.

# v1.37.0 — Sales Commission Payout

- Added a Sales-only commission action from the imported product Group sorter.
- Consolidates D/M product rows into one Sales name and uses total sold units as monthly bills/tables.
- Added monthly conditions for minimum units, per-unit commission, bonus target and target bonus.
- Added clear source, payout and overall summary tables for accounting.
- Added one-click tab-delimited copy for Excel, Google Sheets and payment workflows.
- Stores Sales commission conditions per month with an audit entry.
- Schema remains v28; no migration is required.

# v1.36.0 — POS Product Group & Category Sort

- Replaced the employee/role filter from v1.35.0 with product-level sorting based on the imported POS columns.
- Added a two-stage selector: choose Group or Category, then choose an actual value such as Sales.
- Added instant filtered totals for row count, quantity, net sales and average sales.
- Added searchable full-file preview before commit and a persistent imported-product explorer after commit.
- Preserved the monthly commission rules and employee result calculations as a separate next step.
- Schema remains v28; no migration is required.

# v1.35.1 — POS Incentive Deploy Structure Fix

- Reissued the POS Incentive update with preserved `assets/` and `config/` directory paths.
- Bumped the Wizard stylesheet URL to v1351 to bypass cached missing/old CSS responses.
- No data or calculation changes; Schema remains v28.

# v1.35.0 — POS Incentive Guided Flow

- Reorganized POS Incentive into a four-step workflow: Import, Group & Sort, Calculate Commission, and Review & Finalize.
- Added Excel-style grouping by Sales, PR and Team with optional employee filtering.
- Added on-demand calculation summary for selected groups using the existing monthly commission rules.
- Improved KPI, workflow, filter and calculation-card contrast for faster reading in operational use.
- Existing imported POS batches, aliases, rules and monthly closings are preserved; Schema remains v28.

# v1.34.0 — Portal Favicon Upload

- Added direct Favicon upload, preview, replacement and removal to Config Web Portal.
- Portal Favicon files are validated as JPG, PNG or WEBP and stored securely in server-side Portal media storage.
- The public Portal now emits the configured browser tab icon automatically.
- Schema remains v28; no migration is required.

# v1.33.2 — Portal Logo Inline Lock

- Locked the Portal Logo directly on the image element to a 64 × 64 px square, independent of stylesheet loading or cache.
- Prevented intrinsic image dimensions and global CSS from expanding the Portal Header.
- Schema remains v28; no migration is required.

# v1.33.1 — Portal Logo Size Hotfix

- Locked the Portal header Logo to a compact 76 × 58 px desktop box so global image styles cannot stretch the Header.
- Added proportional tablet/mobile limits while keeping the transparent, frameless presentation.
- Schema remains v28; no migration is required.

# v1.33.0 — Portal Brand Experience

- Added direct Portal Logo upload to server storage from Config Web Portal with secure image validation and public media delivery.
- Portal header now renders the uploaded transparent Logo at a larger size without a surrounding frame, with a graceful symbol fallback.
- Strengthened the Portal hero with a vivid RGB border, layered lighting, enhanced count badge and responsive light/dark presentation.
- Schema remains v28; no migration is required.

# v1.32.1 — Sales Photo Branch URL Fix

- Fixed broken Sales profile images in the customer reservation selector opened from canonical `/shop/{slug}/` pages.
- Sales photo URLs now use the installation-root endpoint and carry `public_branch`, so the media endpoint loads the Employee from the correct branch.
- Added regression checks for installation subdirectory and Branch context; Schema remains v28 with no migration required.

# v1.32.0 — Unified People & Access

- Established Employee Master as the single person record per branch; a login account is now an optional one-to-one extension.
- Separated operational Position from User Role: Position drives PR/Sales/customer/POS behavior, while Role and overrides control system access only.
- Added Schema v28 repair for legacy Sales accounts and missing PR operational profiles without duplicating people across branches.
- Added branch-aware account health counters for unlinked accounts, employees without login, incompatible roles and inactive logins.
- Limited account and permission-user directories to the active branch while retaining global authentication and platform Role templates.
- Blocked incompatible Role/Super Admin changes for linked PR/Sales employees and added per-branch account linkage metadata.
- Migration is automatic on first request; back up the production `storage/` folder before deployment.

# v1.31.0 — Customer CRM / Member Foundation

- Added branch-scoped Customer CRM storage and Schema v27 migration.
- Existing and new Reservations are linked to customer records by normalized phone number without changing confirmed table assignment behavior.
- Added the Customer CRM administration page with search, VIP, tier, birthday, tags, notes, marketing consent and preferred Sales/PR.
- Added visit summaries for Booking count, seated visits, last visit, latest Sales and latest PR.
- Added separate `customers.view` and `customers.manage` permissions plus audit events.
- Added team documentation and the next-step contract for LINE Login, OTP and POS reconciliation.
- Migration is automatic on first request; back up the production `storage/` folder before deployment.

# v1.30.13 — Customer Branch Logo

- Connected the customer shop Header and Footer to the Logo saved for the selected Branch.
- Resolved uploaded `branch-media.php` paths against the installation root so canonical `/shop/{slug}/` URLs do not request media from the wrong nested path.
- Added responsive contain-fit Logo presentation with a safe animated-star fallback when a configured image is missing or cannot load.
- Schema remains v26; no migration is required.

# v1.30.12 — Reproducible Full Source Baseline

- Corrected PowerShell argument handling in the Full Source ZIP builder so Git receives the archive prefix and output path atomically.
- Revalidated the complete Source, generated the reproducible archive and retained v1.30.11 as immutable history.
- Application functions and schema remain unchanged at v26; no migration is required.

# v1.30.11 — Full Source Team Collaboration Baseline

- Published a complete, safe Source baseline for team development; this is not a partial deployment pack.
- Added GitHub Actions validation for PHP syntax, JavaScript syntax and forbidden runtime/private files.
- Added team Branch assignments, project structure documentation and a repeatable release checklist.
- Added a PowerShell builder that creates a reproducible Full Source ZIP directly from the committed Git tree.
- Prepared CRM/Member, OTP authentication, POS reconciliation and Booking Operations work streams from `develop`.
- Application functions and schema remain unchanged at v26; no migration is required.

# v1.30.10 — Fixed Compact Logo Preview

- Corrected the v1.30.9 Logo preview regression where inherited aspect and minimum-height rules could still expand the preview into the text column.
- Set an explicit 76 × 62 px Logo preview with a dedicated 82 px grid track.
- Forced upload controls and URL fields to remain inside their own flexible column without overlap.
- Portal Cover preview remains unchanged; schema remains v26 with no migration.

# v1.30.9 — Compact Branch Logo Preview

- Reduced the Branch Manager Logo preview from about 160px to 118px wide on desktop.
- Returned the freed horizontal space to upload instructions, saved media URL and helper text.
- Kept the Portal Cover preview unchanged and added a compact mobile Logo preview.
- Schema remains v26 with no migration.

# v1.30.8 — Wider Featured Portal Image

- Reduced the desktop Featured Shop detail column by approximately 48 pixels at the current Portal width.
- Returned the saved width directly to the branch cover image for a larger visual presentation.
- Tightened detail-panel padding and responsively scaled long shop titles so the narrower column remains readable.
- Tablet and mobile stacked layouts are unchanged; schema remains v26 with no migration.

# v1.30.7 — Adaptive Multi-Mood Portal Themes

- Corrected Portal text colours that became unreadable when the shared Light theme recoloured headings over dark surfaces.
- Rebuilt Light mode with pearl-white glass panels, dark readable typography and vivid cyan, violet and pink ambience.
- Brightened Dark mode from near-black to luminous navy, violet and cyan while preserving the nightlife identity.
- Added rotating per-branch colour personalities so shop cards can represent different moods instead of sharing one dark palette.
- Increased card surface contrast, live badges, footer actions and closed-shop readability in both themes.
- Changes are isolated to the public Portal; schema remains v26 with no migration.

# v1.30.6 — Readable Multi-Branch Admin Themes

- Rebuilt Branch Manager and Config Web Portal surfaces for correct light and dark theme contrast.
- Light mode now uses bright cards and form fields with dark readable text instead of dark-on-dark content.
- Increased page titles, section headings, labels, helper text, branch details, action buttons and live-list typography.
- Enlarged form controls, switch options, save actions and direct image-upload controls for easier use at 100% browser scale.
- Added responsive spacing and single-column KPI behavior for tablet and mobile screens.
- Changes are isolated to the two multi-branch administration pages; schema remains v26 with no migration.

# v1.30.5 — Collision-Safe Sidebar Footer

- Rebuilt the desktop admin sidebar as a fixed-height flex layout with separate brand, scrollable navigation and footer zones.
- Reserved dedicated bottom space between the `SYSTEM READY` version card and the fixed `ACTIVE SHOP` switcher.
- Navigation now scrolls independently when the viewport is short or multiple menu groups are expanded.
- Added compact-height tuning and a collapsed-sidebar version of the branch switcher.
- The mobile drawer remains scrollable and temporarily clears the floating branch switcher while the drawer is open.
- Scope is limited to shared admin sidebar layout; schema remains v26 with no migration.

# v1.30.4 — Living RGB Portal Showcase

- Rebuilt Portal shop cards as large visual showcases with a full-width featured branch and spacious two-column standard cards.
- Added animated RGB borders, ambient glow, light sweeps, live-status pulses and gently floating brand artwork.
- Added scroll reveal and subtle pointer tilt interactions on supported desktop devices.
- Added responsive ambient particles and a pointer-reactive background spotlight to make the Portal feel alive.
- Brightened the Portal background, glass panels, typography and CTA contrast while retaining the nightlife identity.
- Added responsive layouts for desktop, tablet and mobile plus `prefers-reduced-motion` accessibility behavior.
- JavaScript failure remains safe: shop cards are visible by default before motion enhancement activates.
- Schema remains v26; no migration is required and production data remains excluded.

# v1.30.3 — Direct Branch Media Upload

- Added direct Logo and Portal Cover uploads to Branch Manager while retaining optional external URL fields.
- Added instant client-side image previews and clear file name/size feedback before saving.
- Added server-side validation for upload errors, real image content, MIME type, file size and image dimensions.
- Uploaded branch media is stored in `storage/branch-media` and served through a restricted public image endpoint.
- Portal asset paths are now base-path aware, supporting both the current `/it/` installation and the future root-domain deployment.
- Branch directory thumbnails now display the configured Logo or Cover image.
- Schema remains v26; no migration is required and production media is excluded from the update pack.

# v1.30.2 — Responsive Long Shop Header

- Rebalanced the customer header grid so navigation and action buttons always retain their own space.
- Long shop names now scale responsively and truncate with an ellipsis instead of overlapping the navigation.
- The full shop name remains available through the logo link tooltip and accessible label.
- Added dedicated desktop, tablet, mobile, and extra-small breakpoints for future branches with longer names.
- Scope is limited to the public customer header; schema remains v26 with no migration.

# v1.30.1 — Canonical Portal & Priest Data Recovery

- Changed the application root to redirect to the central `/Portal` gateway instead of the legacy `/custumers/` page.
- Direct customer-home access without a selected branch now returns to the central Portal.
- Added canonical public shop URLs such as `/shop/Priest/` while preserving editable slugs and case-insensitive aliases.
- Added automatic schema v26 repair for v1.30.0 installations where legacy Priest data was stored under another branch during the first multi-branch migration.
- Recovery copies missing tables, PR, employees, media, Hero media, floor plans, bookings, attendance, service history and POS incentive data into Priest without deleting the source branch.
- Branch Manager now shows table/PR/media counts and confirms when automatic recovery was applied.
- Added root-domain compatibility for the future `mrbarsupport.com` deployment; shared assets and trusted-device cookies no longer require the `/it/` subfolder.
- Production storage and uploads remain excluded from the update pack.

# v1.30.0 — Multi Branch Foundation

- Added a central customer Portal that lists published shops and shows live table/PR availability per branch.
- Added Super Admin Portal configuration and branch management pages.
- Added the lower-left shop switcher for the back office, with branch-aware access control for each team member.
- Added stable internal branch IDs and editable public slugs at `/shop/{slug}/`; old slugs are retained as aliases and redirect to the current URL.
- Separated operational settings and data by branch, including tables, PR, employees, bookings, attendance, floor plans, customer media, service sessions, and POS incentive data.
- Preserved global users, roles, branch directory, Portal configuration, and audit storage.
- Customer booking, Check-in, status, privacy and floor-plan routes now preserve the selected branch.
- Existing schema v24 data is migrated automatically to schema v25 on first request. A full `storage/` backup is mandatory before deployment.
- Production `storage/`, uploads, and data files are not included in the update pack.

# v1.29.18 — Stable Customer Hero

- Fixed the periodic dark flash during automatic customer Hero transitions.
- Incoming media starts before activation; outgoing media remains visible through the crossfade.
- Outgoing video and YouTube media stop/reset only after the 650 ms fade has completed.
- Pending cleanup is cancelled when a slide is selected again quickly, preventing active media from being reset.
- Scope is limited to the public customer Hero slider; schema remains v24 with no migration.

# v1.29.17 — Flicker-Free Quick Floor

- Replaced the primary Night Operations table update flow with an AJAX Quick Switch; the whole page no longer reloads after a quick status save.
- Manual refresh and 15-second background sync update only live table data, avoiding page flashes and scroll jumps.
- Added large touch controls for Available, Occupied, and Temporarily Blocked table states.
- Added quick PR assignment/change and automatic PR BUSY/release status handling.
- Added Sales owner attribution for each occupied table.
- Added additive `floor_service_sessions` history with table/check-in, PR snapshots, Sales employee snapshots, start/end timestamps, action user, and change/end reason for future POS and commission reconciliation.
- Changing PR or Sales closes the prior service interval before opening a new one, preserving an auditable timeline.
- Added `operations.quick_floor` permission to Admin, Staff, and Sales default roles; custom roles remain opt-in.
- Retained Reservation, Walk-in, Move Table, Complete, and Cancel controls under Advanced tools.
- Moving a table now closes the prior table interval and continues the same service on the destination table; closing/cancelling from Night Operations also closes its service interval.
- Stale open service intervals linked to Check-ins completed elsewhere are reconciled on the next Quick Floor update.
- Server-side permission, CSRF, active-table, duplicate Check-in, and PR double-assignment checks remain authoritative.
- Schema remains v24; no migration and no production data folders are included.

# v1.29.16 — Complete Live Floor Controls

- Added a complete operational control panel when a table is selected.
- Available/requested tables can create a Walk-in Check-in directly.
- Linked tables can be opened or temporarily blocked; customer availability reflects the saved table state.
- Active tables can move guests, complete the current job, or cancel it and safely release the table.
- Existing reservation requests can still be seated directly from the selected table.
- Added permission-aware controls for tables.manage, reservations.seat, operations.move_table, operations.complete, and operations.cancel.
- Added CSRF protection, active Check-in conflict detection, invalid reservation-state protection, inactive-table checks, confirmations, double-submit prevention, and audit events.
- No schema migration; existing data is preserved.
# v1.48.47 - Hosting Storage Diagnostics

- Expanded the Super Admin preflight screen to show the exact active database file, PHP-visible permissions, readability, and writability.
- Added a real temporary write/delete probe in `storage` so hosting owner/ACL problems can be distinguished from FileZilla permission displays.
- Diagnostics explicitly report whether `runtime-data.php` is overriding `data.php`.
- No database schema or business logic changes.

# v1.48.46 - Public Storefront Read-Only Fallback

- Fixed all `/shop/{branch}/` pages returning HTTP 500 when the optional public visit counter could not write to the migrated storage database.
- Visit counting now fails quietly while the storefront continues rendering; transactional actions still report storage permission problems normally.
- Production hosting must still grant PHP write access to the active database for bookings, check-ins, attendance, and Admin changes.
- No database schema migration required.

# v1.48.45 - Production Domain Readiness & Regression Audit

- Audited all 112 PHP files and aligned the automated regression suite with the current split POS workflow.
- Updated HTTPS fallback URLs so root-domain deployment on `mrbarsupport.com` no longer falls back to the test host or `/it` path.
- Restricted `preflight.php` and `migrate.php` to authenticated Super Admin users; schema mutation now requires a CSRF-protected POST.
- Synchronized release metadata to v1.48.45 / Schema 28.
- Added a production migration checklist covering code, runtime data, media, permissions, DNS/SSL, verification, and rollback.
- No database schema migration and no calculation formula changes.
# v1.48.48 - MR BAR TIME Secure Preview Fix

- แก้หน้า MR BAR TIME Preview ว่าง เมื่อ HTTP ถูก redirect ไปยัง HTTPS virtual host ที่ยังตั้งค่าไม่สมบูรณ์
- อนุญาตเฉพาะ Admin Preview แบบ read-only ให้แสดงผ่าน origin ปัจจุบัน โดยยังตรวจสิทธิ์และ preview token ตามเดิม
- คงการบังคับ HTTPS สำหรับหน้า Time Staff ที่ใช้งานจริง เพื่อให้ Camera และ GPS ทำงานใน secure context
- เพิ่มการตรวจ HTTPS หลัง reverse proxy และป้องกัน Host/URI ผิดรูปในการ redirect
- ไม่มี DB migration
# v1.48.49 - MR BAR TIME Navigation and Mobile Overlay Fix

- เอากล่อง `ACTIVE SHOP` ออกจากหน้า Time Staff และ PR Time ทั้งชุด เพื่อไม่ให้บัง Check-in / Check-out บนมือถือ
- ครอบคลุมหน้าเวลา, ปฏิทิน, รายได้ และงาน ทั้งฝั่ง Staff และ PR
- แก้ PR Calendar ที่อ้างตัวแปร Employee ก่อนกำหนด ซึ่งอาจทำให้บางบัญชีเกิด warning/error
- เพิ่ม regression test สำหรับ shell และเส้นทาง Preview ของ Time Staff
- ไม่มี DB migration
# v1.48.50 - MR BAR TIME HTTP Recovery

- แก้ Time Staff หน้าขาวบนมือถือจากการ redirect ไป HTTPS ที่ Document Root ยังไม่พบไฟล์ระบบ
- เปลี่ยนเป็น graceful HTTP fallback เพื่อให้ Time Staff และ Preview โหลดหน้าได้ระหว่างรอแก้ SSL/VHost
- รองรับการเปิดบังคับ HTTPS กลับด้วย environment `MRBAR_FORCE_STAFF_HTTPS=1` เมื่อโฮสต์พร้อม
- คงการตรวจ secure context ของ Camera/GPS ภายในหน้า เพื่อไม่รายงานว่าปลอดภัยผิดจากความจริง
- ไม่มี DB migration

# v1.48.51 - MR BAR TIME Mobile Recovery

- รวม cache recovery สำหรับ iPhone/PWA โดยไม่ redirect ไป HTTPS ที่ Production ยังตอบ 404
- ให้ PWA Manifest ใช้ origin ปัจจุบัน เพื่อไม่พา Home Screen ไป Document Root ที่ไม่มีแอป
- เปลี่ยน Service Worker cache เป็น `v14851` และ bump script query เพื่อบังคับมือถือโหลด logic ใหม่
- เพิ่มคำแนะนำ Secure Context สำหรับ Camera/GPS แทนข้อผิดพลาดที่ไม่ชัดเจน
- คง HTTP fallback จนกว่า Hosting จะตั้ง SSL และ HTTPS Document Root ถูกต้อง แล้วจึงเปิด `MRBAR_FORCE_STAFF_HTTPS=1`
- ไม่มี DB migration
# v1.48.52 - MR BAR TIME HTTPS Landing

- เปิด HTTPS เป็นค่าเริ่มต้นบน `mrbarsupport.com` และ `www.mrbarsupport.com` สำหรับ Time Staff, PR Time และ Staff Preview
- บังคับ HTTPS ให้หน้า Login, ตั้ง PIN และเปิดใช้งานบัญชีพนักงานด้วย
- อัปเกรดไอคอน PWA เก่าที่เปิดด้วย HTTP ไป HTTPS อัตโนมัติ
- ตั้ง PWA Manifest `start_url`, `scope` และ shortcut เป็น HTTPS บนโดเมน Production
- ลิงก์ Invite พนักงานสร้างเป็น HTTPS แม้ Admin เปิดระบบผ่าน HTTP
- เปลี่ยน Service Worker cache และ version ของ asset เพื่อให้มือถือโหลดชุดใหม่
- ไม่มี DB migration
# v1.48.53 - Multi Branch Integrity

- จำกัด Employee Center ให้เพิ่ม/แก้ไข/นำเข้าพนักงานได้เฉพาะ silo ของสาขาปัจจุบัน พร้อมตรวจฝั่ง server
- กำหนดสาขาสมาชิกให้บัญชีใหม่ตามสาขาพนักงาน และใส่ branch slug ใน Invite Link
- ตรวจสิทธิ์สาขาก่อนเชื่อมบัญชีกับพนักงาน และก่อนเปลี่ยน role/สถานะบัญชี
- ป้องกันการปิด Login แบบรวมเมื่อบัญชีพนักงานยังเชื่อมอยู่หลายสาขา
- ปฏิเสธ URL ร้านที่ไม่มีอยู่หรือยังไม่เผยแพร่; อนุญาต preview ร้านที่ซ่อนเฉพาะ Super Admin ที่มี token ถูกต้อง
- เพิ่ม regression test สำหรับบัญชีพนักงานข้ามสาขาและ Invite Link
- ไม่มี DB migration
# v1.48.54 - Customer Hourly Weather

- ปรับการ์ดอากาศหน้าลูกค้าให้แยกสภาพปัจจุบัน, รายชั่วโมง 8 ช่วงถัดไป, โอกาสฝน, ลม และคำแนะนำ
- เพิ่มวันที่ภาษาไทยและนาฬิกาเวลาประเทศไทยที่อัปเดตทุกนาที
- แยกสภาพอากาศปัจจุบันออกจากสรุปความเสี่ยงในช่วงเวลาเปิดร้าน
- เพิ่ม regression test สำหรับข้อมูลรายชั่วโมงและวันที่ไทย
- ไม่มี DB migration
# v1.48.55 - Portal Branch Discovery

- เพิ่มค้นหาสาขาตามชื่อ ทำเล รหัส และคำอธิบาย พร้อมตัวกรองสาขาที่เปิดให้เลือกและร้านแนะนำ
- แสดงจำนวนผลลัพธ์และสถานะเมื่อไม่พบสาขา
- ปรับป้ายสถานะและจุดเข้าดูร้าน/จองโต๊ะให้สแกนเจอได้เร็วขึ้น
- ไม่มี DB migration
# v1.48.56 - Portal Visual Refresh

- ปรับลำดับสายตาและขนาด Hero ให้สมดุลขึ้น พร้อมเพิ่มแถบภาพรวมข้อมูลสาขา
- แสดงจำนวนสาขาพร้อมต้อนรับ โต๊ะว่าง และ PR active พร้อมวันที่และเวลาไทย
- เพิ่มตัวกรองสาขาที่มีโต๊ะว่าง และปรับโทน Portal ให้หลากหลายขึ้นทั้ง Desktop และ Mobile
- ไม่มี DB migration
# v1.48.63 — Portal Customer Hero Gallery Sync

- เปลี่ยนภาพบรรยากาศบนการ์ด Portal ให้ดึงรายการ Active ชุดเดียวกับ Hero Gallery ในหน้าร้าน และเรียงตามลำดับที่กำหนดไว้
- รองรับภาพ, วิดีโออัปโหลด, YouTube และวิดีโอ URL ภายนอกในตัวดูสื่อแบบเต็มจอ
- หากสาขายังไม่มี Hero Gallery ที่เปิดใช้งาน จะใช้ Gallery เดิมของ Portal เป็น fallback
- ไม่มี DB migration
