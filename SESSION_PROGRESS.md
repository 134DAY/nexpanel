# NexPanel — Session Progress

**อัปเดตล่าสุด:** 2026-07-09 · commit `dc0ad56` (29 commits) · repo: github.com/134DAY/nexpanel

โน้ตสำหรับทำงานต่อ — สรุปว่าทำถึงไหน + วิธี resume

---

## 🎯 ภาพรวม
NexPanel = AI-powered Linux server panel (Laravel 11) ตอนนี้ทำได้เทียบ **aaPanel** เกือบครบ
- **โค้ดหลัก:** `Project Server Base/NexPanel_First/` (ตัวนี้เป็น primary)
- **GitHub:** `github.com/134DAY/nexpanel` (public) — one-liner install:
  ```bash
  curl -fsSL https://raw.githubusercontent.com/134DAY/nexpanel/main/install.sh | sudo REPO_URL=https://github.com/134DAY/nexpanel.git bash
  ```
- **ทดสอบบน:** Ubuntu 24.04 VM (VirtualBox) — เข้าผ่าน `http://127.0.0.1:8080` (NAT port-forward 8080→80, 2222→22)
- **Login:** `admin@nexpanel.local` / **`aa012859`** (เปลี่ยนจาก password เดิมแล้ว)

## 🔄 วิธี update โค้ดขึ้น VM (ทำหลัง push ทุกครั้ง)
```bash
cd /var/www/nexpanel && sudo bash update.sh
```
> **ห้าม** รัน install.sh ซ้ำเพื่ออัปเดต — มันลบ .env + database ทิ้ง!

---

## ✅ เสร็จแล้ว (Phase 1-4 + aaPanel features)

### Core
- Dashboard (metric สด), Service Control (systemctl จริง), Settings, Notifications (Discord/Telegram/webhook/email), Auth
- Cron, Web Terminal (cd persist), SSL (certbot), Website/Nginx

### AI Assistant (สั่งงานแทนได้ — ยืนยันก่อนรัน)
- Tools: create/delete/toggle_website, create/drop_database, **sql**, create_db_user, service, create_cron, **read_file/write_file**, shell (root ผ่าน nexpanel-run + SafetyGuard)
- **Agent loop** — ป้อนผล action กลับให้ AI ทำ step ต่อ/แก้ error เอง
- **sql self-heal** — ถ้า db ไม่มี สร้างให้แล้ว retry
- delete_website ลบไฟล์ด้วย

### Database (เทียบ aaPanel)
- **phpMyAdmin จริง** + **per-db auto-login (signon)** — คลิก db ไหน login เป็น user นั้นเลย
- DB+User pairing (สร้าง db ได้ user+password คู่, เก็บ encrypted, โชว์/copy)
- **Backup manager** (สร้าง gzip, list, recover/download/delete แต่ละอัน)
- Import .sql/.gz/.tar.gz/.zip, Permission (grant/revoke), Change password
- Delete = 2-step (พิมพ์ชื่อ db ยืนยัน)
- DB isolation ถูกต้อง (user เห็นแค่ db ตัวเอง)

### File Manager (เทียบ aaPanel)
- **Right-click context menu**: Edit/Download/Extract/chmod/Copy/Cut/Copy Path/Compress/Rename/Delete/Properties
- Copy/Cut/Paste (clipboard), chmod modal (ติ๊ก rwx), zip/unzip
- **ทุก op fallback เป็น root** เมื่อ www-data โดน denied → จัดการไฟล์ root-owned ได้ (ไม่มี Permission denied)

---

## ⚠️ เรื่องที่ต้องรู้ / ค้างอยู่

1. **โมเดล AI = Groq llama-3.3** — เร็ว/ฟรี แต่**ทำ multi-step วกวน + เขียนไทยมั่ว**
   → ระบบพร้อมแล้ว (agent loop + self-heal ช่วยเยอะ) แต่ถ้าอยากลื่นจริง **เปลี่ยนเป็น Claude** ที่ Settings → AI (model ids อัปเป็น claude-sonnet-4-6/opus-4-8 แล้ว)

2. **ทดสอบเว็บที่สร้าง** ต้องเพิ่ม hosts + ใช้ port :8080 (เพราะ VM + โดเมนปลอม เช่น `portfolio`, `test.example.com`)
   บน VPS จริง + โดเมนจริง = ชี้ DNS มาก็เข้าได้เลย

3. **ยังไม่ได้ทำ (Phase 4 เดิม + idea):**
   - Auto-backup ตามเวลา (cron schedule)
   - One-line installer ทดสอบบน VPS จริง (ยังเทสแค่ VM)

## 🔑 credentials (VM)
- Panel: admin@nexpanel.local / aa012859
- MySQL admin (phpMyAdmin login): `nexpanel` / ดูใน `.env` DB_ADMIN_PASSWORD (`84a59ad21615a6f8bcaaa58d`)
- SSH key ของ Claude ฝากไว้ที่ VM `/root/.ssh/authorized_keys` (ลบด้วย `sed -i '/claude-nexpanel/d'` ถ้าไม่ใช้)

## 🚀 Resume ยังไง
1. เปิด VM + start services (nginx/mysql/php8.3-fpm ควรรันอยู่แล้ว)
2. เข้า http://127.0.0.1:8080 login
3. งานต่อ: เปลี่ยนเป็น Claude แล้วเทส AI / auto-backup / deploy VPS จริง
