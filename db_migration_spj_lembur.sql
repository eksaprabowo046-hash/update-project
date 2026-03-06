-- Migration: Add idtask column to ttanggungjawab_lembur
-- Run in phpMyAdmin on database: logklikdsi

ALTER TABLE ttanggungjawab_lembur 
ADD COLUMN IF NOT EXISTS idtask INT NULL DEFAULT NULL AFTER idlembur;
