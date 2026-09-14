# MR BAR Support

ระบบบริหารร้านแบบ Multi-Branch สำหรับ Portal ลูกค้า การจองโต๊ะ ผังร้าน พนักงาน/PR การลงเวลา Night Operations และ POS Incentive

Current baseline: **v1.31.0**
Schema: **v26**  
Production domain planned: `mrbarsupport.com`

## ส่วนสำคัญ

- Central customer Portal: `/Portal`
- Branch customer page: `/shop/{slug}/`
- Multi-Branch administration and shop switcher
- Table layout, uploaded floor plan and booking hotspots
- Live table/PR operations
- Employee, attendance and workforce management
- POS incentive import and commission preparation

## เริ่มพัฒนา

ต้องใช้ PHP รุ่นที่รองรับ syntax ของ PHP 8 และ Web Server ที่เขียนไฟล์ใน `storage/` ได้

1. Clone repository
2. สร้างโฟลเดอร์ `storage/` หากยังไม่มี
3. ตรวจสิทธิ์เขียนของ Web Server
4. เปิด `preflight.php` เพื่อตรวจระบบ
5. เปิด `migrate.php` เฉพาะเมื่อ Release ระบุว่าต้อง Migration

Runtime database, uploads, POS files และข้อมูลร้านจริงจะไม่ถูกเก็บใน Git

## Git workflow

- `main` — เวอร์ชันที่ผ่านตรวจและพร้อม Deploy
- `develop` — จุดรวมงานก่อนทดสอบ
- `feature/<name>` — ฟังก์ชันใหม่
- `fix/<name>` — แก้บั๊ก
- เปิด Pull Request เข้า `develop` และให้ผู้ร่วมงานอีกคนตรวจ
- รวม `develop` เข้า `main` เมื่อผ่าน Staging

อ่านรายละเอียดที่ [CONTRIBUTING.md](CONTRIBUTING.md), [Team Workflow](docs/TEAM_WORKFLOW.md), [Project Structure](docs/PROJECT_STRUCTURE.md) และ [Release Checklist](docs/RELEASE_CHECKLIST.md)

## ความปลอดภัย

ห้าม Commit:

- `storage/data.php` และ `storage/runtime-data.php`
- รูป/หลักฐาน/ไฟล์อัปโหลดจริง
- POS Excel/CSV
- Password, Token, API Key หรือไฟล์ `.env`
- Backup และ Deploy ZIP

หากพบข้อมูลลับใน Commit ให้หยุด Push และแจ้งเจ้าของ Repository ทันที
