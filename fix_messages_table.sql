-- Add missing has_image column to messages table
ALTER TABLE messages ADD COLUMN IF NOT EXISTS has_image BOOLEAN DEFAULT FALSE;

-- Add missing image_url column to messages table
ALTER TABLE messages ADD COLUMN IF NOT EXISTS image_url TEXT DEFAULT NULL;

-- First, create a default conversation for messages with NULL conversation_id
INSERT INTO conversations (conversation_id, user_id, title, start_time, last_message_time)
VALUES (
    gen_random_uuid(), -- Generate a UUID for the conversation
    (SELECT user_id FROM users LIMIT 1), -- Use the first user as the owner
    'Legacy Messages', -- Give it a descriptive title
    NOW(), -- Current timestamp for start_time
    NOW() -- Current timestamp for last_message_time
)
ON CONFLICT DO NOTHING; -- In case this has already been run

-- Update messages with NULL conversation_id to use the default conversation
UPDATE messages 
SET conversation_id = (SELECT conversation_id FROM conversations WHERE title = 'Legacy Messages' LIMIT 1)
WHERE conversation_id IS NULL;

-- Now we can safely set the column to NOT NULL
ALTER TABLE messages ALTER COLUMN conversation_id SET NOT NULL;

-- Add comment explaining the purpose of this script
COMMENT ON TABLE messages IS 'Stores all messages exchanged between users and AI assistant';
