-- Notificações na app: sem repetidos (dedupe_key único por utilizador), preferências por tipo
-- e data da última verificação diária (funciona sem cron).
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS dedupe_key VARCHAR(80) NULL AFTER type;
ALTER TABLE notifications ADD UNIQUE INDEX IF NOT EXISTS uq_notif_dedupe (user_id, dedupe_key);

ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS notif_prefs JSON NULL;
ALTER TABLE user_settings ADD COLUMN IF NOT EXISTS notif_checked_on DATE NULL;
