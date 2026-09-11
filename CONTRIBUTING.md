# การทำงานร่วมกัน

## การเริ่มงาน

1. ดึง `develop` ล่าสุด
2. สร้าง Branch ของงานตัวเอง
3. Commit เป็นส่วนเล็กที่ตรวจสอบได้
4. Push Branch และเปิด Pull Request เข้า `develop`

ตัวอย่าง:

```bash
git switch develop
git pull
git switch -c feature/crm-member
```

## รูปแบบชื่อ Branch

- `feature/crm-member`
- `feature/pos-incentive`
- `feature/booking-flow`
- `fix/portal-theme`
- `fix/night-ops-status`

## ก่อนเปิด Pull Request

- PHP ทุกไฟล์ที่แก้ต้องผ่าน `php -l`
- ทดสอบ Light/Dark และ Desktop/Mobile ถ้ามีการแก้ UI
- ทดสอบการแยกข้อมูลระหว่าง Branch
- ห้ามมีข้อมูลจริง ไฟล์ Upload, POS, Password หรือ Token
- ระบุว่าต้อง Migration หรือไม่
- แนบภาพ Before/After เมื่อแก้หน้าตา
- ระบุขั้นตอนทดสอบให้ Reviewer ทำซ้ำได้

## กติกาการ Merge

- ห้าม Push ตรงเข้า `main`
- งานใหม่เข้า `develop` ผ่าน Pull Request
- อย่างน้อย 1 คนตรวจงานก่อน Merge
- ใช้ Squash merge สำหรับงานฟังก์ชัน/แก้บั๊กหนึ่งเรื่อง
- Release จาก `main` ต้องมี Version, Changelog และ Tag
