-- SQL for Academic Ease Admin Panel Tables

-- Create past_questions table
CREATE TABLE IF NOT EXISTS past_questions (
    question_id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    subject VARCHAR(50) NOT NULL,
    level VARCHAR(50) NOT NULL,
    year VARCHAR(4) NOT NULL,
    exam_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    created_by INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE
);

-- Create formula_sheets table
CREATE TABLE IF NOT EXISTS formula_sheets (
    formula_id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    subject VARCHAR(50) NOT NULL,
    level VARCHAR(50) NOT NULL,
    topic VARCHAR(100),
    content_type VARCHAR(20) NOT NULL, -- 'file' or 'text'
    file_path VARCHAR(255),
    file_name VARCHAR(255),
    content_text TEXT,
    created_by INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE
);

-- Create admin_users table (if not already using the main users table for admins)
CREATE TABLE IF NOT EXISTS admin_users (
    admin_id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role VARCHAR(50) DEFAULT 'admin',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP WITH TIME ZONE
);

-- Create admin_activity_log table for tracking admin actions
CREATE TABLE IF NOT EXISTS admin_activity_log (
    log_id SERIAL PRIMARY KEY,
    admin_id INTEGER REFERENCES admin_users(admin_id),
    action_type VARCHAR(50) NOT NULL, -- e.g., 'create', 'update', 'delete'
    action_description TEXT NOT NULL,
    entity_type VARCHAR(50) NOT NULL, -- e.g., 'quiz', 'user', 'past_question'
    entity_id INTEGER,
    ip_address VARCHAR(45),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_past_questions_subject ON past_questions(subject);
CREATE INDEX IF NOT EXISTS idx_past_questions_level ON past_questions(level);
CREATE INDEX IF NOT EXISTS idx_past_questions_year ON past_questions(year);

CREATE INDEX IF NOT EXISTS idx_formula_sheets_subject ON formula_sheets(subject);
CREATE INDEX IF NOT EXISTS idx_formula_sheets_level ON formula_sheets(level);

-- Admin users should be created through the application's secure admin registration process
-- or directly in the database by authorized personnel only

-- Enable Row Level Security (RLS) on tables
ALTER TABLE past_questions ENABLE ROW LEVEL SECURITY;
ALTER TABLE formula_sheets ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_users ENABLE ROW LEVEL SECURITY;
ALTER TABLE admin_activity_log ENABLE ROW LEVEL SECURITY;

-- Create policies for past_questions table
CREATE POLICY "Public users can view past questions" ON past_questions
    FOR SELECT USING (true);

CREATE POLICY "Admins can insert past questions" ON past_questions
    FOR INSERT WITH CHECK (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "Admins can update past questions" ON past_questions
    FOR UPDATE USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "Admins can delete past questions" ON past_questions
    FOR DELETE USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

-- Create policies for formula_sheets table
CREATE POLICY "Public users can view formula sheets" ON formula_sheets
    FOR SELECT USING (true);

CREATE POLICY "Admins can insert formula sheets" ON formula_sheets
    FOR INSERT WITH CHECK (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "Admins can update formula sheets" ON formula_sheets
    FOR UPDATE USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "Admins can delete formula sheets" ON formula_sheets
    FOR DELETE USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

-- Create policies for admin_users table
CREATE POLICY "Admins can view admin users" ON admin_users
    FOR SELECT USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "Super admins can manage admin users" ON admin_users
    FOR ALL USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users WHERE role = 'super_admin'
    ));

-- Create policies for admin_activity_log table
CREATE POLICY "Admins can view activity logs" ON admin_activity_log
    FOR SELECT USING (auth.role() = 'authenticated' AND auth.email() IN (
        SELECT email FROM admin_users
    ));

CREATE POLICY "System can insert activity logs" ON admin_activity_log
    FOR INSERT WITH CHECK (true);

