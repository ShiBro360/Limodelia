CREATE TABLE IF NOT EXISTS room_booking_reminders (
    lesson_date DATE NOT NULL PRIMARY KEY,
    sent_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
