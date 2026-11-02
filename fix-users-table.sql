-- Fix Users Table Schema for Academic Ease
-- This script adds missing columns to the users table after migration

-- Add missing columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_level TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_name TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS user_type TEXT DEFAULT 'student';
ALTER TABLE users ADD COLUMN IF NOT EXISTS parent_id UUID;
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_plan TEXT DEFAULT 'trial';
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_start_date TIMESTAMP WITH TIME ZONE DEFAULT NOW();
ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_auto_renew BOOLEAN DEFAULT TRUE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS grade TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active TIMESTAMP WITH TIME ZONE DEFAULT NOW();
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture_url TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verification_token TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires TIMESTAMP WITH TIME ZONE;

-- Create parent_child table if it doesn't exist
CREATE TABLE IF NOT EXISTS parent_child (
  id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  parent_id UUID REFERENCES users(user_id),
  child_id UUID REFERENCES users(user_id),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create study_sessions table
CREATE TABLE IF NOT EXISTS study_sessions (
  session_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  start_time TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  end_time TIMESTAMP WITH TIME ZONE,
  duration_minutes INTEGER DEFAULT 0,
  subject TEXT,
  topic TEXT,
  notes TEXT
);

-- Create quizzes table if it doesn't exist
CREATE TABLE IF NOT EXISTS quizzes (
  quiz_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  title TEXT NOT NULL,
  description TEXT,
  subject TEXT,
  level TEXT,
  category TEXT,
  question_count INTEGER DEFAULT 0,
  time_limit INTEGER DEFAULT 30,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create quiz_questions table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_questions (
  question_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  quiz_id UUID REFERENCES quizzes(quiz_id),
  question_text TEXT NOT NULL,
  options JSONB,
  correct_answer TEXT NOT NULL,
  explanation TEXT,
  question_order INTEGER DEFAULT 0,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create quiz_results table if it doesn't exist
CREATE TABLE IF NOT EXISTS quiz_results (
  result_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  quiz_id UUID REFERENCES quizzes(quiz_id),
  score INTEGER DEFAULT 0,
  max_score INTEGER DEFAULT 0,
  percentage INTEGER DEFAULT 0,
  completion_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  answers JSONB
);

-- Create study_plans table if it doesn't exist
CREATE TABLE IF NOT EXISTS study_plans (
  plan_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  plan_name TEXT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  goal TEXT,
  subjects JSONB,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create notes table if it doesn't exist
CREATE TABLE IF NOT EXISTS notes (
  note_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  title TEXT NOT NULL,
  content TEXT,
  subject TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Enable RLS on tables
ALTER TABLE parent_child ENABLE ROW LEVEL SECURITY;
ALTER TABLE study_sessions ENABLE ROW LEVEL SECURITY;
ALTER TABLE quizzes ENABLE ROW LEVEL SECURITY;
ALTER TABLE quiz_questions ENABLE ROW LEVEL SECURITY;
ALTER TABLE quiz_results ENABLE ROW LEVEL SECURITY;
ALTER TABLE study_plans ENABLE ROW LEVEL SECURITY;
ALTER TABLE notes ENABLE ROW LEVEL SECURITY;

-- Create policies for parent_child table
CREATE POLICY "Parents can view their children"
ON parent_child FOR SELECT
USING (auth.uid() = parent_id);

CREATE POLICY "Parents can insert their children"
ON parent_child FOR INSERT
WITH CHECK (auth.uid() = parent_id);

-- Create policies for study_sessions table
CREATE POLICY "Users can view their own study sessions"
ON study_sessions FOR SELECT
USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own study sessions"
ON study_sessions FOR INSERT
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own study sessions"
ON study_sessions FOR UPDATE
USING (auth.uid() = user_id);

-- Create policies for quizzes table
CREATE POLICY "Quizzes are viewable by all authenticated users"
ON quizzes FOR SELECT
USING (auth.role() = 'authenticated');

-- Create policies for quiz_questions table
CREATE POLICY "Quiz questions are viewable by all authenticated users"
ON quiz_questions FOR SELECT
USING (auth.role() = 'authenticated');

-- Create policies for quiz_results table
CREATE POLICY "Users can view their own quiz results"
ON quiz_results FOR SELECT
USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own quiz results"
ON quiz_results FOR INSERT
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own quiz results"
ON quiz_results FOR UPDATE
USING (auth.uid() = user_id);

-- Create policies for study_plans table
CREATE POLICY "Users can view their own study plans"
ON study_plans FOR SELECT
USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own study plans"
ON study_plans FOR INSERT
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own study plans"
ON study_plans FOR UPDATE
USING (auth.uid() = user_id);

CREATE POLICY "Users can delete their own study plans"
ON study_plans FOR DELETE
USING (auth.uid() = user_id);

-- Create policies for notes table
CREATE POLICY "Users can view their own notes"
ON notes FOR SELECT
USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own notes"
ON notes FOR INSERT
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own notes"
ON notes FOR UPDATE
USING (auth.uid() = user_id);

CREATE POLICY "Users can delete their own notes"
ON notes FOR DELETE
USING (auth.uid() = user_id);

-- Add index for faster lookups
CREATE INDEX IF NOT EXISTS idx_parent_child_parent_id ON parent_child(parent_id);
CREATE INDEX IF NOT EXISTS idx_parent_child_child_id ON parent_child(child_id);
CREATE INDEX IF NOT EXISTS idx_study_sessions_user_id ON study_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_quizzes_subject_level ON quizzes(subject, level);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz_id ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_quiz_results_user_id ON quiz_results(user_id);
CREATE INDEX IF NOT EXISTS idx_quiz_results_quiz_id ON quiz_results(quiz_id);
CREATE INDEX IF NOT EXISTS idx_study_plans_user_id ON study_plans(user_id);
CREATE INDEX IF NOT EXISTS idx_notes_user_id ON notes(user_id);
CREATE INDEX IF NOT EXISTS idx_notes_subject ON notes(subject);
