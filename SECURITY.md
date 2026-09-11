# Security Policy

Repository นี้ต้องเก็บเฉพาะ Source Code

ห้าม Commit ฐานข้อมูล Runtime, รายชื่อลูกค้า/พนักงาน, Password Hash, Trusted-device token, หลักฐานลงเวลา, รูป Upload, POS report, API key หรือ Credential ทุกชนิด

หากข้อมูลลับถูก Commit:

1. หยุด Push/Merge
2. แจ้งเจ้าของ Repository
3. เปลี่ยน Credential ที่เกี่ยวข้อง
4. ลบข้อมูลออกจาก Git history ไม่ใช่เพียง Commit ลบไฟล์
5. ตรวจ Branch และ Fork ที่อาจมีสำเนา

รายงานช่องโหว่กับเจ้าของ Repository แบบส่วนตัว ไม่เปิดรายละเอียดข้อมูลจริงใน Public Issue
