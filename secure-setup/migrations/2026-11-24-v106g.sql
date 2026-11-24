CREATE TABLE IF NOT EXISTS growth_twilio_inbound (
 event_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 account_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 channel ENUM('sms','calls') NOT NULL,
 day_utc DATE NOT NULL,
 INDEX idx_tw_inbound_day(account_hash,channel,day_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
