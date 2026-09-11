# MR BAR Team Workflow

## Branch หลัก

| Branch | หน้าที่ | ใคร Merge |
|---|---|---|
| `main` | Source ที่ผ่านการตรวจและพร้อม Deploy | เจ้าของระบบ |
| `develop` | จุดรวมงานเพื่อทดสอบร่วมกัน | Maintainer |
| `feature/*` | ฟังก์ชันใหม่หนึ่งเรื่อง | ผ่าน Pull Request |
| `fix/*` | แก้บั๊กหนึ่งเรื่อง | ผ่าน Pull Request |
| `hotfix/*` | ปัญหาเร่งด่วนจาก Production | เจ้าของระบบ |

## Branch งานที่เตรียมไว้

- `feature/crm-member` — CRM/Member และประวัติลูกค้า
- `feature/auth-otp` — Email/เบอร์โทรและ OTP
- `feature/pos-incentive-reconciliation` — เทียบ POS กับค่าดริ้ง/ค่าคอม
- `feature/booking-operations` — การจองโต๊ะและ Night Operations

ทุก Branch เริ่มจาก `develop` เวอร์ชันเดียวกัน และห้ามเก็บข้อมูลร้านจริงใน Git

## วิธีเริ่มงาน

```bash
git clone https://github.com/pee0525550/mrbar.git
cd mrbar
git switch develop
git pull origin develop
git switch feature/ชื่องาน
```

ถ้าจะสร้างงานใหม่:

```bash
git switch develop
git pull origin develop
git switch -c feature/ชื่องานใหม่
git push -u origin feature/ชื่องานใหม่
```

## ก่อนส่งงาน

1. ดึง `develop` ล่าสุดและแก้ Conflict ใน Branch ตัวเอง
2. รัน PHP lint และ JavaScript syntax check
3. ทดสอบสิทธิ์ผู้ใช้และการแยกข้อมูลหลายร้าน
4. ทดสอบ Desktop, Tablet, Mobile รวมทั้ง Light/Dark หากแก้ UI
5. เปิด Pull Request เข้า `develop`
6. ให้ผู้ร่วมงานอย่างน้อยหนึ่งคน Review
7. ห้าม Merge เข้า `main` โดยตรง

## ขอบเขตไฟล์เพื่อลด Conflict

- CRM: สร้างโมดูลใน `app/` และหน้าเฉพาะของ CRM
- OTP: แยก service/auth handler ไม่แก้ `app/bootstrap.php` โดยไม่จำเป็น
- POS: ทำงานหลักใน `app/pos-incentive.php`, `pos-incentive.php` และ asset ที่เกี่ยวข้อง
- Booking: ทำงานใน floor-plan, reserve, night-ops และ API ที่เกี่ยวข้อง
- ไฟล์ร่วม เช่น `app/db.php`, `app/bootstrap.php`, `assets/theme.css` ต้องแจ้งทีมก่อนแก้
