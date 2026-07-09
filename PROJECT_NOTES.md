# AMK Schema Core
Project Context & Development Memory

> این فایل حافظه دائمی پروژه است.
> هدف آن این است که وضعیت فعلی پروژه، تصمیمات معماری و اطلاعات مهم را نگهداری کند تا نیازی به تحلیل مجدد کل پروژه در هر گفتگو نباشد.

---

# Project Information

Project Name:
AMK Schema Core

Project Type:
Commercial WordPress Plugin

Current Version:
1.0.2

Minimum PHP:
8.1

Minimum WordPress:
6.x

WooCommerce:
Supported

License:
Private

Maintainer:
Amir Mohammad Karimi

---

# Project Goal

هدف این پروژه توسعه یک افزونه حرفه‌ای و ماژولار برای تولید Schema Markup مطابق استانداردهای Schema.org و Google است.

تمرکز اصلی پروژه:

- کیفیت کد
- توسعه‌پذیری
- معماری تمیز
- Performance
- قابلیت نگهداری
- سازگاری با وردپرس
- سازگاری با WooCommerce
- قابلیت افزودن Schemaهای جدید بدون تغییر ساختار اصلی

---

# Current Status

Project State:
✅ Active Development

Current Phase:
Core Architecture Stabilization

Current Sprint Goals:

- بهبود ساختار Builderها
- افزایش Performance
- حذف Duplicate Logic
- تکمیل Schemaهای اصلی
- بهبود Validator

---

# Current Architecture

پروژه بر اساس معماری ماژولار طراحی شده است.

اصول اصلی:

- Single Responsibility
- Dependency Injection
- Low Coupling
- High Cohesion
- Extensible Architecture

هر ماژول فقط یک مسئولیت دارد.

Builderها فقط وظیفه تولید Schema دارند.

Managerها وظیفه مدیریت جریان اجرا را دارند.

Resolverها فقط داده آماده می‌کنند.

Output فقط مسئول Render است.

---

# Important Design Decisions

تصمیمات مهم معماری:

- هیچ Builder نباید مستقیماً داده دریافت کند.
- تمام داده‌ها باید از Resolverها عبور کنند.
- Output نباید منطق تجاری داشته باشد.
- هیچ کلاس نباید چند مسئولیت داشته باشد.
- از Helperهای عمومی فقط در صورت نیاز استفاده شود.
- از Singleton فقط در صورت ضرورت استفاده شود.
- از ایجاد Global State خودداری شود.

---

# Coding Standards

تمام توسعه‌ها باید مطابق موارد زیر باشند:

- WordPress Coding Standards
- PSR-12
- Clean Code
- SOLID
- DRY
- KISS

---

# Development Rules

قبل از هر تغییر:

1. بررسی کن آیا قابلیت مشابه وجود دارد.
2. بررسی کن آیا می‌توان همان کد را توسعه داد.
3. از ایجاد کلاس تکراری خودداری کن.
4. از ایجاد Utilityهای غیرضروری جلوگیری کن.
5. ابتدا ساختار فعلی را درک کن.

---

# Performance Rules

اولویت‌ها:

- کمترین مصرف حافظه
- کمترین Query
- Lazy Loading در صورت امکان
- جلوگیری از اجرای کدهای غیرضروری
- جلوگیری از Instantiate بی‌دلیل کلاس‌ها

---

# Security Rules

همیشه رعایت شود:

- Escape Output
- Sanitize Input
- Nonce Verification
- Capability Check
- Validation
- Type Safety

---

# Current Known Issues

هیچ مورد ثبت نشده.

یا

- Product Builder نیاز به Refactor دارد.
- Validator هنوز کامل نشده.
- بررسی سازگاری PHP 8.4

---

# Current TODO


# Implemented Features

- Organization Schema
- Breadcrumb Schema
- Website Schema
- WebPage Schema
- Article Schema
- FAQ Schema
- Product Schema
- WooCommerce Integration


---

# Pending Features

- VideoObject
- Event
- Recipe
- JobPosting
- Course
- SoftwareApplication
- Book
- Movie
- Podcast

---

# Breaking Changes


# Project Notes

هر تصمیم مهم معماری که گرفته می‌شود اینجا ثبت شود.

مثال:

2026-07-10

تصمیم گرفته شد Output فقط Render انجام دهد و هیچ داده‌ای تولید نکند.

---

# Claude Instructions

اگر این فایل را می‌خوانی:

- این فایل حافظه رسمی پروژه است.
- قبل از تحلیل کل پروژه ابتدا این فایل را بررسی کن.
- اگر اطلاعات لازم در این فایل وجود دارد، نیازی به تحلیل مجدد کل پروژه نیست.
- فقط در صورتی فایل‌های پروژه را بررسی کن که اطلاعات موردنیاز در این فایل موجود نباشد.
- اگر نسخه ZIP تغییر کرده باشد، فقط یک بار پروژه را مجدداً تحلیل کن و سپس این فایل را مبنای پاسخ‌های بعدی قرار بده.
- از اسکن کامل پروژه برای تغییرات کوچک خودداری کن.
- همیشه ابتدا بررسی کن آیا قابلیت موردنظر قبلاً وجود دارد یا خیر.
- از ایجاد Duplicate Logic جلوگیری کن.
- معماری فعلی پروژه را حفظ کن مگر اینکه دلیل فنی محکمی برای تغییر آن وجود داشته باشد.

---

# Update Log

## Version 1.0.2
Date:

Summary:

Added:

Changed:

Fixed:

Removed:

Notes: