# MR BAR Project Structure

MR BAR เป็น PHP Web Application แบบ Multi-Branch ที่ใช้ Source Code ชุดเดียวกันทุกสาขา โดยแยกข้อมูลตาม Branch ID และ Public Slug

## โครงสร้างสำคัญ

| Path | หน้าที่ |
|---|---|
| `app/` | Business logic, storage, permissions, branch context และ service |
| `assets/` | CSS/JavaScript ของหลังบ้านและหน้าพนักงาน |
| `custumers/` | Customer Portal, หน้าร้าน, จองโต๊ะ, PR และผังร้าน |
| `config/` | การตั้งค่า Source ที่แชร์ได้ ห้ามใส่ Credential |
| `storage/` | Runtime database และ Upload จริง ไม่เก็บใน Git |
| `.github/` | Pull Request, Issue template, CODEOWNERS และ CI |
| `docs/` | เอกสารโครงสร้าง การทำงาน และ Release |

## Routing หลัก

- Portal กลาง: `/Portal`
- หน้าร้าน: `/shop/{slug}/`
- หลังบ้าน: `/admin.php`
- Branch Manager: `/branch-manager.php`
- Portal Config: `/portal-config.php`
- Night Operations: `/night-ops.php`
- POS Incentive: `/pos-incentive.php`

## Multi-Branch

Git Branch ไม่ใช่สาขาร้านค้า ระบบร้านค้าใช้ Branch ID เป็นตัวตนถาวร และใช้ Slug เป็น URL ที่แก้ได้ ข้อมูลระดับร้านต้องอ่าน/เขียนผ่าน Branch context เสมอ ส่วนบัญชีระดับระบบ สิทธิ์ และ Portal กลางเป็นข้อมูล Global

## Runtime ที่ต้องสำรองแยก

- `storage/data.php`
- `storage/runtime-data.php`
- `storage/customer-web-media/`
- `storage/branch-media/`
- หลักฐานลงเวลาและ Upload อื่นทั้งหมด

Full Source Package มีเฉพาะ Source ที่ปลอดภัยสำหรับแบ่งทีม และไม่ใช่ Backup ข้อมูล Production
