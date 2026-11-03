SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS rekapmobile CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rekapmobile;
ALTER DATABASE rekapmobile CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rekap_harian (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tgl_permohonan DATE NOT NULL,
  no_permohonan VARCHAR(50) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  nama_key VARCHAR(150) NOT NULL,
  jk ENUM('L','P') NOT NULL,
  tp TINYINT(1) NOT NULL DEFAULT 0,
  tl TINYINT(1) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (tgl_permohonan), KEY (no_permohonan), KEY (nama_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rekap_absensi (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tgl_absen DATE NOT NULL,
  nama VARCHAR(150) NOT NULL,
  nama_key VARCHAR(150) NOT NULL,
  jk ENUM('L','P') NOT NULL,
  tp TINYINT(1) NOT NULL DEFAULT 0,
  tl TINYINT(1) NOT NULL DEFAULT 0,
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY (tgl_absen), KEY (nama_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sudah_dicetak (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tgl_permohonan DATE NOT NULL,
  no_permohonan VARCHAR(50) NOT NULL,
  nama_pemohon VARCHAR(150) NOT NULL,
  UNIQUE KEY (no_permohonan), KEY (tgl_permohonan)
) ENGINE=InnoDB;

-- Samakan collation tabel lama (jika database sudah pernah dibuat)
ALTER TABLE rekap_harian CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rekap_absensi CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE sudah_dicetak CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Status cetak & absen dihitung otomatis secara realtime lewat view (selalu sinkron instan)
-- Catatan: Keterangan ditolak, pending, biometric, tidak datang/ada, patah, cancel/batal, reschedule diberi status '-' bukan Belum Dicetak
CREATE OR REPLACE VIEW v_harian AS
SELECT 
  h.id, h.tgl_permohonan, h.no_permohonan, h.nama, h.nama_key, h.jk, h.tp, h.tl, h.keterangan, h.created_at,
  IF(EXISTS(
    SELECT 1 FROM sudah_dicetak s 
    WHERE TRIM(s.no_permohonan) = TRIM(h.no_permohonan)
       OR REPLACE(REPLACE(TRIM(s.no_permohonan),' ',''),'-','') = REPLACE(REPLACE(TRIM(h.no_permohonan),' ',''),'-','')
  ),'Sudah Dicetak',
    IF(LOWER(COALESCE(h.keterangan,'')) REGEXP 'ditolak|duplikasi|duplikat|pending|dipending|biometric|biometrik|tidak[ _-]*datang|tidak[ _-]*ada|data[ _-]*tidak[ _-]*ada|patah|re-?schedule|cancel|batal', '-', 'Belum Dicetak')
  ) AS status_cetak,
  IF(EXISTS(
    SELECT 1 FROM rekap_absensi a 
    WHERE a.nama_key=h.nama_key
  ),'Ada','Tidak Ada') AS absen
FROM rekap_harian h;

CREATE OR REPLACE VIEW v_harian_latest AS
SELECT h.* FROM rekap_harian h JOIN (SELECT MAX(id) mid FROM rekap_harian GROUP BY nama_key) m ON m.mid=h.id;

CREATE OR REPLACE VIEW v_absensi_latest AS
SELECT a.* FROM rekap_absensi a JOIN (SELECT MAX(id) mid FROM rekap_absensi GROUP BY nama_key) m ON m.mid=a.id;

-- Keterangan & status cetak absensi mengikuti rekap harian & data sudah dicetak
CREATE OR REPLACE VIEW v_absensi AS
SELECT a.id,a.tgl_absen,a.nama,a.nama_key,a.jk,a.tp,a.tl,a.keterangan AS ket_asli,
  COALESCE(h.keterangan,a.keterangan) AS keterangan,
  IF(h.id IS NULL,
     IF(LOWER(COALESCE(a.keterangan,'')) REGEXP 'ditolak|duplikasi|duplikat|pending|dipending|biometric|biometrik|tidak[ _-]*datang|tidak[ _-]*ada|data[ _-]*tidak[ _-]*ada|patah|re-?schedule|cancel|batal', '-', 'Belum Dicetak'),
     IF(EXISTS(
       SELECT 1 FROM sudah_dicetak s 
       WHERE TRIM(s.no_permohonan) = TRIM(h.no_permohonan)
          OR REPLACE(REPLACE(TRIM(s.no_permohonan),' ',''),'-','') = REPLACE(REPLACE(TRIM(h.no_permohonan),' ',''),'-','')
     ),'Sudah Dicetak',
       IF(LOWER(COALESCE(h.keterangan,a.keterangan,'')) REGEXP 'ditolak|duplikasi|duplikat|pending|dipending|biometric|biometrik|tidak[ _-]*datang|tidak[ _-]*ada|data[ _-]*tidak[ _-]*ada|patah|re-?schedule|cancel|batal', '-', 'Belum Dicetak')
     )
  ) AS status_cetak,
  IF(h.id IS NULL,'Tidak Ada','Ada') AS absen
FROM rekap_absensi a
LEFT JOIN v_harian_latest h ON h.nama_key=a.nama_key;
