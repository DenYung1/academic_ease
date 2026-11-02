-- SQL script to add the missing title column to the conversations table
ALTER TABLE conversations ADD COLUMN title TEXT DEFAULT 'New Conversation';

-- Update existing rows to have a default title
UPDATE conversations SET title = 'Conversation ' || conversation_id WHERE title IS NULL;
