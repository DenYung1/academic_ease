-- Academic Ease - Supabase Migration SQL Script
-- This script sets up the database schema and security policies for your new Supabase account

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create tables
-- Users table
CREATE TABLE IF NOT EXISTS users (
  user_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  email TEXT UNIQUE NOT NULL,
  full_name TEXT NOT NULL,
  password_hash TEXT NOT NULL,
  phone_number TEXT,
  school TEXT,
  grade TEXT,
  subscription_status TEXT DEFAULT 'trial',
  subscription_end_date TIMESTAMP WITH TIME ZONE DEFAULT (NOW() + INTERVAL '7 days'),
  created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  last_login TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Conversations table
CREATE TABLE IF NOT EXISTS conversations (
  conversation_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  start_time TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  end_time TIMESTAMP WITH TIME ZONE,
  platform TEXT,
  subject TEXT,
  topic TEXT
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
  message_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  conversation_id UUID REFERENCES conversations(conversation_id),
  sender TEXT NOT NULL,
  content TEXT,
  content_type TEXT DEFAULT 'text',
  media_url TEXT,
  timestamp TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
  subscription_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
  user_id UUID REFERENCES users(user_id),
  plan_type TEXT NOT NULL,
  start_date TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
  end_date TIMESTAMP WITH TIME ZONE,
  payment_status TEXT,
  payment_reference TEXT,
  amount DECIMAL(10,2)
);

-- Set up Row Level Security (RLS)
-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE conversations ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;

-- Create policies for users table
CREATE POLICY "Users can view their own data" 
ON users FOR SELECT 
USING (auth.uid() = user_id);

CREATE POLICY "Users can update their own data" 
ON users FOR UPDATE 
USING (auth.uid() = user_id);

-- Create policies for conversations table
CREATE POLICY "Users can view their own conversations" 
ON conversations FOR SELECT 
USING (auth.uid() = user_id);

CREATE POLICY "Users can insert their own conversations" 
ON conversations FOR INSERT 
WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their own conversations" 
ON conversations FOR UPDATE 
USING (auth.uid() = user_id);

-- Create policies for messages table
CREATE POLICY "Users can view messages from their conversations" 
ON messages FOR SELECT 
USING (
  conversation_id IN (
    SELECT conversation_id FROM conversations WHERE user_id = auth.uid()
  )
);

CREATE POLICY "Users can insert messages to their conversations" 
ON messages FOR INSERT 
WITH CHECK (
  conversation_id IN (
    SELECT conversation_id FROM conversations WHERE user_id = auth.uid()
  )
);

-- Create policies for subscriptions table
CREATE POLICY "Users can view their own subscriptions" 
ON subscriptions FOR SELECT 
USING (auth.uid() = user_id);

-- Storage bucket setup and policies
-- First, create the bucket if it doesn't exist (this needs to be done via the Supabase dashboard)
-- Then, set up RLS policies for the storage

-- Policy for user-specific uploads
CREATE POLICY "Users can upload their own files"
ON storage.objects FOR INSERT
TO authenticated
WITH CHECK (
  bucket_id = 'academic-ease' AND
  (storage.foldername(name))[1] = 'user-' || auth.uid()::text
);

CREATE POLICY "Users can view their own files"
ON storage.objects FOR SELECT
TO authenticated
USING (
  bucket_id = 'academic-ease' AND
  (storage.foldername(name))[1] = 'user-' || auth.uid()::text
);

CREATE POLICY "Users can update their own files"
ON storage.objects FOR UPDATE
TO authenticated
USING (
  bucket_id = 'academic-ease' AND
  (storage.foldername(name))[1] = 'user-' || auth.uid()::text
);

CREATE POLICY "Users can delete their own files"
ON storage.objects FOR DELETE
TO authenticated
USING (
  bucket_id = 'academic-ease' AND
  (storage.foldername(name))[1] = 'user-' || auth.uid()::text
);

-- Policy for public files
CREATE POLICY "Anyone can view public files"
ON storage.objects FOR SELECT
TO anon, authenticated
USING (
  bucket_id = 'academic-ease' AND
  (storage.foldername(name))[1] = 'public'
);

-- Create an admin role for bypassing RLS
CREATE ROLE academic_ease_admin;
GRANT ALL ON ALL TABLES IN SCHEMA public TO academic_ease_admin;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO academic_ease_admin;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO academic_ease_admin;

-- Note: After running this script, you'll need to:
-- 1. Create the storage bucket via the Supabase dashboard
-- 2. Set up any additional authentication providers you need
-- 3. Import your existing data from your old Supabase project
