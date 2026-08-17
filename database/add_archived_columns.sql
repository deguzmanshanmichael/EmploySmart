-- Migration to add archived columns for soft delete functionality
-- Run this after updating the schema

USE employsmart;

-- Add archived column to users table
ALTER TABLE users ADD COLUMN archived BOOLEAN DEFAULT FALSE;

-- Add archived column to employers table
ALTER TABLE employers ADD COLUMN archived BOOLEAN DEFAULT FALSE;

-- Add archived column to jobs table
ALTER TABLE jobs ADD COLUMN archived BOOLEAN DEFAULT FALSE;

-- Add archived column to training_programs table
ALTER TABLE training_programs ADD COLUMN archived BOOLEAN DEFAULT FALSE;

-- Add archived column to skills table
ALTER TABLE skills ADD COLUMN archived BOOLEAN DEFAULT FALSE;