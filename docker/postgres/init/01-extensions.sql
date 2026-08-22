-- إضافات PostgreSQL التي تعتمد عليها المنصة.
CREATE EXTENSION IF NOT EXISTS "pgcrypto";     -- توليد UUID وتشفير
CREATE EXTENSION IF NOT EXISTS "citext";       -- بريد إلكتروني غير حساس لحالة الأحرف
CREATE EXTENSION IF NOT EXISTS "pg_trgm";      -- بحث تقريبي في أسماء الطلاب
CREATE EXTENSION IF NOT EXISTS "btree_gist";   -- قيود EXCLUDE لمنع تعارض المواعيد

-- قاعدة بيانات الاختبارات
SELECT 'CREATE DATABASE eschool_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'eschool_testing')
\gexec
