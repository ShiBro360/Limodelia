# Telegram Attendance Bot

## Purpose

Telegram attendance bot lets the teacher mark lesson attendance directly from a Telegram group while Limodelia CRM runs locally on XAMPP.

The bot works through long polling. It does not require a public webhook URL, so it is suitable for local development.

## Configuration

Telegram settings are configured in `Ustawienia` under the Telegram section:

- Enabled
- Bot token
- Chat ID

The bot accepts commands and button clicks only from the configured Chat ID.

If a bot token was ever shared in screenshots or messages, regenerate it in BotFather before real use.

## Running The Bot

Start Apache and MySQL in XAMPP first. Then run the bot from a terminal:

```powershell
C:\Users\shibr\OneDrive\Documents\XAMPP\php\php.exe C:\Users\shibr\OneDrive\Documents\XAMPP\htdocs\Limodelia\scripts\telegram_attendance_bot.php
```

Keep the terminal open. When the terminal is closed, polling stops.

For a one-time test run:

```powershell
C:\Users\shibr\OneDrive\Documents\XAMPP\php\php.exe C:\Users\shibr\OneDrive\Documents\XAMPP\htdocs\Limodelia\scripts\telegram_attendance_bot.php --once
```

To send the attendance form for today:

```powershell
C:\Users\shibr\OneDrive\Documents\XAMPP\php\php.exe C:\Users\shibr\OneDrive\Documents\XAMPP\htdocs\Limodelia\scripts\telegram_attendance_bot.php --send-today
```

To send the attendance form for a specific lesson date:

```powershell
C:\Users\shibr\OneDrive\Documents\XAMPP\php\php.exe C:\Users\shibr\OneDrive\Documents\XAMPP\htdocs\Limodelia\scripts\telegram_attendance_bot.php --date=2026-08-26
```

## Telegram Commands

The teacher can write these commands in the configured Telegram group:

- `/obecnosc` sends the attendance form for today.
- `/obecnosc YYYY-MM-DD` sends the attendance form for the selected date.
- `/dzisiaj` sends the attendance form for today.
- `/help` shows available commands.

## Attendance Buttons

The bot sends one consolidated attendance message for the lesson. It contains the current status of every active student and compact student-selection buttons.

After selecting a student, the same Telegram message changes to the student's attendance controls:

- `Obecna`
- `Uprzedziła`
- `Bez uprzedzenia`
- `Odwołane`

Clicking a status saves the result to the CRM attendance table using the same AttendanceService rules as the web interface. The message then returns to the consolidated list with the refreshed status. The bot edits this single message instead of sending a separate message for every student.

The same controls can be used for past dates. Send `/obecnosc YYYY-MM-DD`, select a student, and choose the corrected status. Existing past attendance is updated with an audit entry; a missing record for that date is created.

Ordinary group messages are ignored. The command list is displayed only after `/start` or `/help`, so normal group conversation does not trigger bot replies.

## Subscription Logic

Attendance affects lesson usage through the existing CRM rules:

- Present: uses one lesson.
- Absent with notice: the first two notices in the current paid subscription do not use a lesson.
- Third and later absence with notice: uses one lesson.
- Absent without notice: uses one lesson.
- School cancelled: does not use a lesson.

When a new subscription payment is created, notice counting starts again for that new paid subscription.

## Stored State

The bot stores the last processed Telegram update ID in settings:

- `telegram.last_update_id`

This prevents the same Telegram message or button click from being processed repeatedly.

## Local XAMPP Limitation

Polling is not a background server by itself. For local use, the terminal must remain open.

For production or always-on local use, configure one of these later:

- Windows Task Scheduler
- NSSM Windows service wrapper
- A small production worker process
- Webhook on a public HTTPS server

## Security

- The token is loaded from CRM settings.
- The token is not hardcoded in code.
- The configured Chat ID is required for all commands and callbacks.
- The bot should not be added to unrelated groups.
- BotFather token should be regenerated if exposed.
