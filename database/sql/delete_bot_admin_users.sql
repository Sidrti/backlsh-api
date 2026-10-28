-- ==============================================================================
-- Cleanup Script: Delete Bot Admin Accounts from Production Database
-- ==============================================================================
-- Criteria:
--   1. Admin users with 0 sub-users added under them
--   2. Admin users whose email is not verified (is_verified = 0 or NULL)
--   3. Admin users who have 0 recorded activities in user_activities
-- ==============================================================================

START TRANSACTION;

-- Step 1: Create a temporary table of target bot admin IDs
DROP TEMPORARY TABLE IF EXISTS temp_bot_admin_ids;
CREATE TEMPORARY TABLE temp_bot_admin_ids AS
SELECT u.id
FROM users u
WHERE (u.role = 'ADMIN' OR u.parent_user_id = 0)
  AND (u.is_verified = 0 OR u.is_verified IS NULL)
  AND NOT EXISTS (
      SELECT 1 FROM users sub WHERE sub.parent_user_id = u.id
  )
  AND NOT EXISTS (
      SELECT 1 FROM user_activities ua WHERE ua.user_id = u.id
  );

-- Show count of bot users identified
SELECT COUNT(*) AS total_bot_users_to_delete FROM temp_bot_admin_ids;

-- Step 2: Delete auth tokens generated during bot registration
DELETE pat FROM personal_access_tokens pat
INNER JOIN temp_bot_admin_ids t 
   ON pat.tokenable_id = t.id 
  AND pat.tokenable_type = 'App\\Models\\User';

-- Step 3: Delete notifications if any were sent to or by these bot users
DELETE n FROM notifications n
INNER JOIN temp_bot_admin_ids t 
   ON n.user_id = t.id 
   OR n.triggered_by = t.id;

-- Step 4: Delete the bot admin users
DELETE u FROM users u
INNER JOIN temp_bot_admin_ids t 
   ON u.id = t.id;

-- Step 5: Clean up temporary table
DROP TEMPORARY TABLE IF EXISTS temp_bot_admin_ids;

-- Step 6: Verify remaining legitimate users count
SELECT COUNT(*) AS remaining_legitimate_users FROM users;

COMMIT;
