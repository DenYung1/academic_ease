-- SQL script to add the missing last_message_time column to the conversations table
ALTER TABLE conversations ADD COLUMN last_message_time TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP;

-- Update existing rows to have a value for last_message_time
UPDATE conversations SET last_message_time = start_time WHERE last_message_time IS NULL;

-- Add an index to improve query performance
CREATE INDEX idx_conversations_last_message_time ON conversations(last_message_time);
