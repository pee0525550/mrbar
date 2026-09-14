# Customer CRM / Member

สถานะ: Foundation implementation on `feature/crm-member`.

## สิ่งที่ทำแล้ว

- เก็บ `customers` แยกตาม Branch ID
- สร้างและเชื่อมลูกค้าจาก Reservation ด้วยเบอร์โทรที่ Normalize แล้ว
- Reservation เก็บ `customer_id` โดยไม่เปลี่ยน Flow ยืนยันโต๊ะเดิม
- หน้า `customers.php` สำหรับค้นหา เพิ่ม แก้ไข VIP, Tier, วันเกิด, Tags, Notes และ Sales/PR ประจำ
- สรุปจำนวน Booking, การเข้าใช้บริการ, วันที่ล่าสุด และผู้ดูแลล่าสุด
- Permission แยก `customers.view` และ `customers.manage`
- Audit log เมื่อสร้าง แก้ไข หรือ Sync CRM

## Data contract

Customer เป็นข้อมูลระดับร้านและอยู่ใน `branch_data[{branch_id}].customers`.

ฟิลด์หลัก:

- `id`: ตัวตนถาวรภายในสาขา
- `phone_key`: ตัวเลขเบอร์โทรที่ Normalize เพื่อ Matching
- `tier`: `standard | silver | gold | platinum`
- `preferred_sales_employee_id`: Sales ประจำ
- `preferred_pr_id`: PR ที่ชอบ/ดูแลประจำ
- `marketing_consent`: สถานะยินยอมรับข่าวสาร
- `active`: ใช้งานหรือเก็บเป็นประวัติ

ห้ามใช้ชื่อหรือ URL Slug ของร้านเป็น Foreign Key.

## งานถัดไป

1. LINE Login และการผูก `line_user_id`
2. Phone OTP / Email magic link
3. Member self-service profile และ Reservation history
4. POS matching ด้วยเบอร์โทร, Bill/Transaction reference และเวลาบริการ
5. Duplicate review/merge สำหรับเบอร์ซ้ำหรือข้อมูลเก่า
6. Consent history แบบ append-only ก่อนส่ง Marketing จริง
