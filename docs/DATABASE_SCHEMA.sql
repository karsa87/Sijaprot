-- =============================================================================
-- SiJaProt (Sistem Jadwal Protokol Kemendikdasmen RI)
-- PostgreSQL 17 DDL Database Schema
-- Generated: 2026-10-20
-- =============================================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. ENUM TYPES
CREATE TYPE user_role_enum AS ENUM ('super_admin', 'admin_protokol', 'operator');
CREATE TYPE protokol_status_enum AS ENUM ('active', 'inactive');
CREATE TYPE payment_type_enum AS ENUM ('paid', 'unpaid');
CREATE TYPE payment_day_type_enum AS ENUM ('workday', 'offday');
CREATE TYPE location_type_enum AS ENUM ('dalam_kota', 'luar_kota');
CREATE TYPE kegiatan_status_enum AS ENUM ('draft', 'scheduled', 'completed', 'cancelled');
CREATE TYPE libur_type_enum AS ENUM ('nasional', 'cuti_bersama', 'hari_besar');

-- 2. USERS (Authentication & RBAC)
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role user_role_enum NOT NULL DEFAULT 'operator',
    remember_token VARCHAR(100),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);

-- 3. PROTOKOLS (Master Pegawai Protokol)
CREATE TABLE protokols (
    id BIGSERIAL PRIMARY KEY,
    nip VARCHAR(30) UNIQUE,
    name VARCHAR(255) NOT NULL,
    phone_wa VARCHAR(25) NOT NULL,
    status protokol_status_enum NOT NULL DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_protokols_status ON protokols(status);
CREATE INDEX idx_protokols_name ON protokols(name);

-- 4. JENIS KEGIATAN (Template Reference)
CREATE TABLE jenis_kegiatan (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    payment_type payment_type_enum NOT NULL DEFAULT 'unpaid',
    payment_day_type payment_day_type_enum, -- Null jika payment_type = unpaid
    location_type location_type_enum NOT NULL DEFAULT 'dalam_kota',
    estimated_fee DECIMAL(12, 2) DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. HARI LIBUR NASIONAL (Manual Input Per Tahun)
CREATE TABLE hari_libur (
    id BIGSERIAL PRIMARY KEY,
    tanggal DATE NOT NULL UNIQUE,
    tahun INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type libur_type_enum NOT NULL DEFAULT 'nasional',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_hari_libur_tahun ON hari_libur(tahun);
CREATE INDEX idx_hari_libur_tanggal ON hari_libur(tanggal);

-- 6. KEGIATAN (Events)
CREATE TABLE kegiatan (
    id BIGSERIAL PRIMARY KEY,
    jenis_kegiatan_id BIGINT NOT NULL REFERENCES jenis_kegiatan(id) ON DELETE RESTRICT,
    title VARCHAR(255) NOT NULL,
    detail TEXT,
    start_datetime TIMESTAMP WITH TIME ZONE NOT NULL,
    end_datetime TIMESTAMP WITH TIME ZONE NOT NULL,
    jumlah_protokol INT NOT NULL DEFAULT 1 CHECK (jumlah_protokol > 0),
    is_holiday BOOLEAN NOT NULL DEFAULT FALSE,
    is_weekend BOOLEAN NOT NULL DEFAULT FALSE,
    location_type location_type_enum NOT NULL DEFAULT 'dalam_kota',
    status kegiatan_status_enum NOT NULL DEFAULT 'draft',
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_kegiatan_datetime CHECK (end_datetime >= start_datetime)
);

CREATE INDEX idx_kegiatan_start ON kegiatan(start_datetime);
CREATE INDEX idx_kegiatan_end ON kegiatan(end_datetime);
CREATE INDEX idx_kegiatan_status ON kegiatan(status);

-- 7. PENUGASAN (Assignment of Protocol to Activity)
CREATE TABLE penugasan (
    id BIGSERIAL PRIMARY KEY,
    kegiatan_id BIGINT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
    protokol_id BIGINT NOT NULL REFERENCES protokols(id) ON DELETE RESTRICT,
    assigned_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    assigned_by BIGINT REFERENCES users(id) ON DELETE SET NULL, -- NULL jika di-assign oleh sistem engine
    is_system_assigned BOOLEAN NOT NULL DEFAULT TRUE,
    is_override BOOLEAN NOT NULL DEFAULT FALSE,
    override_reason TEXT,
    score_snapshot NUMERIC(6, 2), -- Skor saat dipilih oleh engine
    selection_reason TEXT,       -- Log transparan alasan pemilihan
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_kegiatan_protokol UNIQUE (kegiatan_id, protokol_id)
);

CREATE INDEX idx_penugasan_protokol ON penugasan(protokol_id);
CREATE INDEX idx_penugasan_kegiatan ON penugasan(kegiatan_id);

-- 8. AUDIT LOG OVERRIDE (Audit Trail Khusus Pergantian Petugas)
CREATE TABLE audit_override_logs (
    id BIGSERIAL PRIMARY KEY,
    penugasan_id BIGINT REFERENCES penugasan(id) ON DELETE CASCADE,
    kegiatan_id BIGINT NOT NULL REFERENCES kegiatan(id) ON DELETE CASCADE,
    previous_protokol_id BIGINT REFERENCES protokols(id) ON DELETE SET NULL,
    new_protokol_id BIGINT REFERENCES protokols(id) ON DELETE SET NULL,
    overridden_by BIGINT NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    reason TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_kegiatan ON audit_override_logs(kegiatan_id);

-- 9. SEED DATA DEFAULT (Master Template)
INSERT INTO jenis_kegiatan (name, code, payment_type, payment_day_type, location_type, description) VALUES
('Penyambutan Tamu VIP Negara', 'VIP_DALAM', 'paid', 'workday', 'dalam_kota', 'Kunjungan delegasi asing/dubes di hari kerja'),
('Upacara & Peringatan Hari Besar', 'UPACARA_WEEKEND', 'paid', 'offday', 'dalam_kota', 'Upacara bendera / apel di akhir pekan atau hari libur'),
('Pendampingan Dinas Luar Kota Menteri', 'DINAS_LUARKOTA', 'paid', 'workday', 'luar_kota', 'Kunker pimpinan ke daerah, mengaktifkan aturan gap H+1'),
('Rapat Koordinasi Pimpinan Internal', 'RAPAT_INTERNAL', 'unpaid', NULL, 'dalam_kota', 'Pelayanan protokoler rapat dinas rutin internal');
