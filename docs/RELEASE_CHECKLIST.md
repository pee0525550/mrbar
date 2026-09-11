# Release Checklist

## Source

- [ ] Working tree สะอาด
- [ ] Pull Request ผ่าน Review
- [ ] GitHub Actions ผ่าน
- [ ] PHP lint ผ่านทุกไฟล์
- [ ] JavaScript syntax check ผ่าน
- [ ] ไม่มี Runtime data, Upload, POS, Backup หรือ Credential
- [ ] README, CHANGELOG และ release-info ตรงกับ Version

## Function

- [ ] Login/Logout และ Permission
- [ ] Switch Shop และการแยกข้อมูล
- [ ] Portal กลางและ `/shop/{slug}/`
- [ ] Booking/Floor Plan/Hotspot
- [ ] Night Operations และสถานะโต๊ะ/PR
- [ ] Employee/Attendance/Payroll
- [ ] POS Import Preview และ Incentive
- [ ] Light/Dark บน Desktop/Tablet/Mobile

## Deploy

- [ ] สำรอง `storage/` จาก Server
- [ ] ตรวจ PHP version และสิทธิ์เขียน storage
- [ ] Deploy จาก Tag บน `main`
- [ ] รัน Migration เฉพาะ Release ที่ระบุ
- [ ] เปิด `preflight.php`
- [ ] Smoke test Portal, Shop, Admin และ Night Operations
- [ ] บันทึก Tag, SHA256 และเวลาที่ Deploy
