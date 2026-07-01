# ZoeFeeds — Automatic Draw Finalization (Cron Job)

This folder contains the script that **automatically finalizes draws the
instant they expire** — generating the 15-digit winning code, ranking the
Top 3 participants, and notifying winners — with zero admin interaction.

## What it does

Every time it runs, `finalize-expired-draws.php`:

1. Finds every draw with status `active` or `paused` whose `end_date` has passed.
2. For each one:
   - Generates a random 15-digit winning code
   - Scores every participant's best code against it (digit-by-digit match)
   - Ranks the **Top 3** (tiebreakers: most matches → most entries → earliest registration)
   - Saves the winner + runners-up to the `draw_rankings` table
   - Marks the draw `completed`
   - Marks all entered codes as `used` and deducts user balances
   - Sends notifications to the winner and the 2nd/3rd place finishers
3. Logs the run to the `cron_logs` database table and to stdout (captured by your cron's log redirect).

It's **safe to run every minute** — already-completed draws are automatically skipped.

## Setup — choose ONE method

### Option A: cPanel (most shared hosting)
1. Log in to cPanel → **Cron Jobs**
2. Common Settings → **Once Per Minute** (`* * * * *`)
3. Command:
   ```
   /usr/bin/php /home/YOURUSER/public_html/zoefeeds/cron/finalize-expired-draws.php >> /home/YOURUSER/public_html/zoefeeds/logs/cron.log 2>&1
   ```
   (Adjust the path to match where you uploaded the `zoefeeds` folder.)

### Option B: VPS / Linux server
```bash
crontab -e
```
Add this line:
```
* * * * * php /var/www/zoefeeds/cron/finalize-expired-draws.php >> /var/www/zoefeeds/logs/cron.log 2>&1
```

### Option C: Windows Task Scheduler
- Program: `C:\php\php.exe`
- Arguments: `C:\path\to\zoefeeds\cron\finalize-expired-draws.php`
- Trigger: repeat every 1 minute

### Option D: No server cron access? Use an external HTTP cron service
Services like cron-job.org or EasyCron can hit a URL every minute instead of
running a CLI command. First, **change the secret** at the top of
`finalize-expired-draws.php`:
```php
define('CRON_HTTP_SECRET', 'change-this-to-a-long-random-string');
```
Then schedule the external service to GET:
```
https://yourdomain.com/zoefeeds/cron/finalize-expired-draws.php?key=YOUR_SECRET
```

## Testing it manually
You can run it by hand any time to verify it works:
```bash
php cron/finalize-expired-draws.php
```
Or from the admin panel: **Auto-Finalize Status** page has a "Run Now" button.

## Monitoring
Visit **Admin → Auto-Finalize Status** to see:
- Draws currently awaiting finalization
- Full run history (timestamps, results, errors)
- Setup instructions reprinted with your actual server path

## Database requirement
This feature requires the `draw_rankings` and `cron_logs` tables. Run
`database/migration-draw-rankings.sql` once against your database if you
haven't already (also included in the main `database/schema.sql` for fresh installs).
